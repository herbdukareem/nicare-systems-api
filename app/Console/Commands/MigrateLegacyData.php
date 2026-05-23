<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLegacyEnrolleesJob;
use App\Models\AccountDetail;
use App\Models\Bank;
use App\Models\Benefactor;
use App\Models\BenefitPackage;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\EnrollmentPhase;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\Invoice;
use App\Models\Lga;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\Ward;
use App\Services\Legacy\LegacyEnrolleeMigrationService;
use App\Support\LegacyReferenceData;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyData extends Command
{
    private const LEGACY_CONNECTION = 'legacy_mysql';

    private const BENEFACTOR_ID_MAP = [
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
        6 => 6,
        7 => 7,
        8 => 8,
        9 => 9,
        10 => 10,
        11 => 11,
        12 => 12,
        13 => 13,
        14 => 14,
        100 => 15,
    ];

    private const FUNDING_TYPE_ID_MAP = [
        'bhcpf' => 1,
        'cf' => 2,
        'premium' => 3,
        'gac' => 4,
        'unicef' => 5,
        'formal' => 6,
    ];

    protected $signature = 'legacy:migrate
        {--only=all : all, reference, phases, pins, invoices, enrollees, or capitations}
        {--source=all : all, informal, or formal}
        {--dry-run : Inspect and print without writing}
        {--chunk=500 : Number of legacy enrollee rows per chunk}
        {--from-id= : Start enrollee migration from a legacy id}
        {--limit= : Maximum enrollee rows to process per source table}
        {--skip-users : Do not migrate legacy admin users}
        {--dispatch : Dispatch enrollees as queued jobs instead of processing inline}
        {--workers=4 : Number of parallel job chunks when using --dispatch}';

    protected $description = 'Migrate legacy reference data and enrollees into the current coverage structure';

    /** @var array<int, int> */
    private array $lgaIds = [];

    /** @var array<int, int> */
    private array $wardIds = [];

    public function handle(LegacyEnrolleeMigrationService $enrolleeService): int
    {
        $only = strtolower((string) $this->option('only'));
        if (!in_array($only, ['all', 'reference', 'phases', 'pins', 'invoices', 'enrollees', 'capitations'], true)) {
            $this->error('--only must be one of: all, reference, phases, pins, invoices, enrollees, capitations');
            return self::FAILURE;
        }

        $source = strtolower((string) $this->option('source'));
        if (!in_array($source, ['all', 'informal', 'formal'], true)) {
            $this->error('--source must be one of: all, informal, formal');
            return self::FAILURE;
        }

        $runReference = in_array($only, ['all', 'reference'], true);
        $runPhases = $only === 'phases';
        $runPins = in_array($only, ['all', 'pins'], true);
        $runInvoices = in_array($only, ['all', 'invoices', 'pins'], true);
        $runEnrollees = in_array($only, ['all', 'enrollees'], true);
        $runCapitations = in_array($only, ['all', 'capitations'], true);
        $enrolleeTables = $runEnrollees ? $this->selectedEnrolleeTables($source) : [];

        if (!$this->schemaReady($runReference, $runPhases, $runPins || $runInvoices, $runCapitations, $enrolleeTables)) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($runReference) {
            if ($dryRun) {
                $this->previewReferenceData();
            } else {
                $this->migrateReferenceData();
            }
        }

        if ($runPhases) {
            if ($dryRun) {
                $this->previewEnrollmentPhases();
            } else {
                $this->migrateEnrollmentPhases();
            }
        }

        if ($runInvoices) {
            $dryRun ? $this->previewLegacyInvoices() : $this->migrateLegacyInvoices();
        }

        if ($runPins) {
            $dryRun ? $this->previewLegacyPins() : $this->migrateLegacyPins();
        }

        $enrolleeStats = ['failed' => 0];
        if ($runEnrollees) {
            $chunk   = max((int) $this->option('chunk'), 1);
            $fromId  = $this->option('from-id') !== null ? (int) $this->option('from-id') : null;
            $limit   = $this->option('limit') !== null ? (int) $this->option('limit') : null;

            if ((bool) $this->option('dispatch')) {
                $this->dispatchEnrollees($enrolleeTables, $chunk, $fromId, $limit, $dryRun);
            } else {
                $enrolleeStats = $this->migrateEnrollees(
                    $enrolleeService,
                    $enrolleeTables,
                    $chunk,
                    $fromId,
                    $limit,
                    $dryRun
                );
            }
        }

        if ($runCapitations) {
            $dryRun ? $this->previewLegacyCapitations() : $this->migrateLegacyCapitations();
        }

        $this->info('Legacy migration completed.');

        return $enrolleeStats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migrateReferenceData(): void
    {
        $this->info('Migrating legacy reference data...');

        $this->migrateLgas();
        $this->migrateWards();
        $this->migrateFundingTypes();
        $this->migrateBenefactors();
        $this->migrateEnrollmentPhases();
        $this->migrateFacilities();

        if (!(bool) $this->option('skip-users')) {
            $this->migrateAdminUsers();
        }

        $this->info('Reference data migration completed.');
    }

    private function migrateLgas(): void
    {
        $count = 0;

        DB::connection(self::LEGACY_CONNECTION)
            ->table('lga')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$count): void {
                $legacyId = (int) $row->id;
                $lga = Lga::updateOrCreate(
                    ['id' => $legacyId],
                    [
                        'name' => $this->string($this->value($row, 'lga', 'name')) ?? 'Legacy LGA ' . $legacyId,
                        'code' => $this->uniqueLgaCode($row),
                        'zone' => is_numeric($row->zone ?? null) ? (int) $row->zone : null,
                        'status' => 1,
                    ]
                );

                $this->lgaIds[$legacyId] = $lga->id;
                $count++;
            });

        $this->info("LGAs migrated: {$count}");
    }

    private function migrateWards(): void
    {
        $count = 0;
        $unknownLga = $this->unknownLga();

        DB::connection(self::LEGACY_CONNECTION)
            ->table('ward')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$count, $unknownLga): void {
                $legacyId = (int) $row->id;
                $legacyLgaId = (int) ($row->lga_id ?? 0);
                $lgaId = $this->lgaIds[$legacyLgaId] ?? ($legacyLgaId ?: $unknownLga->id);

                $ward = Ward::updateOrCreate(
                    ['id' => $legacyId],
                    [
                        'name' => $this->string($this->value($row, 'ward', 'name')) ?? 'Legacy Ward ' . $legacyId,
                        'lga_id' => Lga::whereKey($lgaId)->exists() ? $lgaId : $unknownLga->id,
                        'settlement_type' => $this->settlementType($row->settlement ?? null),
                        'status' => 1,
                    ]
                );

                $this->wardIds[$legacyId] = $ward->id;
                $count++;
            });

        $this->info("Wards migrated: {$count}");
    }

    private function migrateFundingTypes(): void
    {
        $count = 0;
        foreach (LegacyReferenceData::fundingTypes() as $fundingType) {
            $newId = self::FUNDING_TYPE_ID_MAP[$fundingType['code2']] ?? (int) $fundingType['legacy_id'];
            FundingType::updateOrCreate(
                ['id' => $newId],
                [
                    'name' => $fundingType['name'],
                    'description' => sprintf(
                        'Legacy funding code: %s; legacy short code: %s; legacy id: %s',
                        $fundingType['code'],
                        $fundingType['code2'],
                        $fundingType['legacy_id']
                    ),
                    'status' => $fundingType['status'],
                ]
            );
            $count++;
        }

        $this->info("Funding types seeded from legacy reference array: {$count}");
    }

    private function migrateBenefactors(): void
    {
        $count = 0;
        $hasType = Schema::hasColumn('benefactors', 'type');

        foreach (LegacyReferenceData::benefactors() as $benefactor) {
            $data = [
                'name' => $benefactor['name'],
                'status' => $benefactor['status'],
            ];

            if ($hasType) {
                $data['type'] = $benefactor['type'];
            }

            Benefactor::updateOrCreate(
                ['id' => self::BENEFACTOR_ID_MAP[$benefactor['legacy_id']] ?? (int) $benefactor['legacy_id']],
                $data
            );
            $count++;
        }

        $this->info("Benefactors seeded from legacy reference array: {$count}");
    }

    private function migrateEnrollmentPhases(): void
    {
        if (!$this->legacyTableExists('enrolment_phases')) {
            $this->warn('Skipping enrollment phases; legacy enrolment_phases table was not found.');
            return;
        }

        $count = 0;
        $hasLegacyId = Schema::hasColumn('enrollment_phases', 'legacy_id');
        $hasPhase = Schema::hasColumn('enrollment_phases', 'phase');
        $hasSponsor = Schema::hasColumn('enrollment_phases', 'sponsor');
        $hasFunding = Schema::hasColumn('enrollment_phases', 'funding');
        $hasIsCurrent = Schema::hasColumn('enrollment_phases', 'is_current');

        DB::connection(self::LEGACY_CONNECTION)
            ->table('enrolment_phases')
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use (&$count, $hasLegacyId, $hasPhase, $hasSponsor, $hasFunding, $hasIsCurrent): void {
                $legacyId = (int) $row->id;
                $benefactor = $this->benefactorForLegacyPhase($row);
                $payload = [
                    'name' => $this->string($row->name ?? null)
                        ?? $this->string($row->phase ?? null)
                        ?? 'Legacy Phase ' . $legacyId,
                    'benefactor_id' => $benefactor->id,
                    'status' => 1,
                ];

                if ($hasLegacyId) {
                    $payload['legacy_id'] = $legacyId;
                }
                if ($hasPhase) {
                    $payload['phase'] = $this->string($row->phase ?? null);
                }
                if ($hasSponsor) {
                    $payload['sponsor'] = $this->string($row->sponsor ?? null);
                }
                if ($hasFunding) {
                    $payload['funding'] = $this->string($row->funding ?? null);
                }
                if ($hasIsCurrent) {
                    $payload['is_current'] = (bool) ($row->is_current ?? false);
                }

                EnrollmentPhase::updateOrCreate(['id' => $legacyId], $payload);
                $count++;
            });

        $this->info("Enrollment phases migrated from enrolment_phases: {$count}");
    }

    private function benefactorForLegacyPhase(object $row): Benefactor
    {
        $benefactorRef = LegacyReferenceData::benefactorByLegacyValue($row->benefactor_id ?? null)
            ?: LegacyReferenceData::benefactorByLegacyValue($row->sponsor ?? null)
            ?: LegacyReferenceData::benefactorByFundingValue($row->funding ?? null);

        $name = $benefactorRef['name'] ?? (
            $this->string($row->sponsor ?? null)
            ?: 'Legacy Phase Benefactor ' . ($row->benefactor_id ?? 'unknown')
        );

        return Benefactor::firstOrCreate(
            ['name' => $name],
            [
                'type' => $benefactorRef['type'] ?? $this->benefactorType($name),
                'status' => $benefactorRef['status'] ?? 1,
            ]
        );
    }

    private function migrateFacilities(): void
    {
        $count = 0;
        $unknownLga = $this->unknownLga();
        $unknownWard = $this->unknownWard($unknownLga);
        $hasAccreditation = Schema::hasColumn('facilities', 'accreditation_status');

        DB::connection(self::LEGACY_CONNECTION)
            ->table('tbl_providers')
            ->orderBy('id')
            ->get()
            ->each(function (object $provider) use (&$count, $unknownLga, $unknownWard, $hasAccreditation): void {
                $legacyId = (int) $provider->id;
                $hcpCode = $this->string($provider->hcpcode ?? null) ?? 'LEGACY-HCP-' . $legacyId;
                $legacyLgaId = (int) ($provider->hcplga ?? 0);
                $legacyWardId = (int) ($provider->hcpward ?? 0);
                $lgaId = $this->lgaIds[$legacyLgaId] ?? ($legacyLgaId ?: $unknownLga->id);
                $wardId = $this->wardIds[$legacyWardId] ?? ($legacyWardId ?: $unknownWard->id);

                if (!Lga::whereKey($lgaId)->exists()) {
                    $lgaId = $unknownLga->id;
                }
                if (!Ward::whereKey($wardId)->exists()) {
                    $wardId = $unknownWard->id;
                }

                $data = [
                    'hcp_code' => $hcpCode,
                    'name' => $this->string($provider->hcpname ?? null) ?? 'Legacy Facility ' . $legacyId,
                    'ownership' => $this->facilityOwnership($provider->hcpcategory ?? null),
                    'type' => $this->facilityType($provider->hcptype ?? null),
                    'address' => $this->string($provider->hcpaddress ?? null),
                    'lga_id' => $lgaId,
                    'ward_id' => $wardId,
                    'capacity' => is_numeric($provider->hcpcap ?? null) ? (int) $provider->hcpcap : 0,
                    'phone' => $this->string($provider->hcpcontactphone ?? null),
                    'email' => $this->string($provider->hcpemailaddress ?? null),
                    'status' => 1,
                ];

                if ($hasAccreditation) {
                    $data['accreditation_status'] = 'active';
                }

                $facility = Facility::whereKey($legacyId)->first()
                    ?: Facility::where('hcp_code', $hcpCode)->first()
                    ?: new Facility(['id' => $legacyId]);
                $facility->forceFill($data)->save();
                $this->syncFacilityAccount($facility, $provider);
                $count++;
            });

        $this->info("Facilities migrated: {$count}");
    }

    private function syncFacilityAccount(Facility $facility, object $provider): void
    {
        $bankName = $this->string($provider->hcpBankName ?? null);
        $accountNumber = $this->string($provider->hcpBankAccountNumber ?? null);

        if (!$bankName || !$accountNumber) {
            return;
        }

        $bank = Bank::firstOrCreate(
            ['name' => $bankName],
            [
                'code' => $this->string($provider->sortCode ?? null),
                'sort_code' => $this->string($provider->sortCode ?? null),
                'status' => 1,
            ]
        );

        $account = AccountDetail::updateOrCreate(
            [
                'accountable_type' => Facility::class,
                'accountable_id' => $facility->id,
                'account_number' => $accountNumber,
            ],
            [
                'account_name' => $this->string($provider->hcpBankAccountName ?? null) ?? $facility->name,
                'bank_id' => $bank->id,
                'account_type' => 'savings',
                'status' => 'active',
            ]
        );

        $facility->forceFill(['account_detail_id' => $account->id])->save();
    }

    private function migrateAdminUsers(): void
    {
        if (!$this->legacyTableExists('users')) {
            $this->warn('Skipping users; legacy users table was not found.');
            return;
        }

        $role = Role::firstOrCreate(
            ['name' => 'System Admin'],
            ['label' => 'System Admin', 'description' => 'System Administrator']
        );

        $this->ensureSystemAdmin($role);

        $count = 0;
        DB::connection(self::LEGACY_CONNECTION)
            ->table('users')
            ->where('user_role_id', 1)
            ->orderBy('id')
            ->get()
            ->each(function (object $legacyUser) use (&$count, $role): void {
                $email = $this->string($legacyUser->email_address ?? null);
                if ($email === 'admin@nicare.com') {
                    return;
                }

                $fullName = $this->string($legacyUser->fullname ?? null) ?? 'Legacy Admin ' . $legacyUser->id;
                $nameHead = strtok($fullName, ' ') ?: null;
                $firstName = $this->string($legacyUser->first_name ?? null) ?? $nameHead ?? 'Legacy';
                $derivedLastName = trim(str_replace($firstName, '', $fullName));
                $lastName = $this->string($legacyUser->surname ?? null) ?? ($derivedLastName !== '' ? $derivedLastName : 'Admin');
                $phone = $this->string($legacyUser->phone_number ?? null);
                $username = $this->string($legacyUser->nicare_code ?? null)
                    ?? $this->string($legacyUser->username ?? null)
                    ?? 'legacy-admin-' . $legacyUser->id;

                $staff = Staff::updateOrCreate(
                    ['email' => $email ?? 'legacy-admin-' . $legacyUser->id . '@nicare.local'],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'middle_name' => $this->string($legacyUser->other_name ?? null),
                        'gender' => null,
                        'email' => $email ?? 'legacy-admin-' . $legacyUser->id . '@nicare.local',
                        'phone' => $phone,
                        'designation_id' => null,
                        'department_id' => null,
                        'address' => null,
                        'status' => 1,
                    ]
                );

                $user = User::updateOrCreate(
                    ['username' => $username],
                    [
                        'name' => $fullName,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => Hash::make('password'),
                        'status' => 1,
                        'userable_id' => $staff->id,
                        'userable_type' => Staff::class,
                    ]
                );

                $user->roles()->syncWithoutDetaching([$role->id]);
                $count++;
            });

        $this->info("Admin users migrated: {$count}");
    }

    private function ensureSystemAdmin(Role $role): User
    {
        $staff = Staff::updateOrCreate(
            ['email' => 'admin@nicare.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'middle_name' => null,
                'gender' => 'Male',
                'email' => 'admin@nicare.com',
                'phone' => '08130051228',
                'designation_id' => null,
                'department_id' => null,
                'address' => null,
                'status' => 1,
            ]
        );

        $user = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'System Admin',
                'email' => 'admin@nicare.com',
                'phone' => '08130051228',
                'password' => Hash::make('password'),
                'status' => 1,
                'userable_id' => $staff->id,
                'userable_type' => Staff::class,
            ]
        );

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function previewLegacyInvoices(): void
    {
        $count = $this->legacyTableExists('tbl_request')
            ? DB::connection(self::LEGACY_CONNECTION)->table('tbl_request')->count()
            : 0;

        $this->info("Legacy invoices available in tbl_request: {$count}");
    }

    private function migrateLegacyInvoices(): void
    {
        if (!$this->legacyTableExists('tbl_request')) {
            $this->warn('Skipping invoices; legacy tbl_request table was not found.');
            return;
        }

        $plan = $this->defaultPremiumPlan();
        $userId = $this->systemUserId();
        $keyColumn = $this->legacyKeyColumn('tbl_request', ['id', 'sn', 'request_id', 'invoice_id']);
        $paymentReferenceCounts = $this->legacyPaymentReferenceCounts('tbl_request');
        $total = DB::connection(self::LEGACY_CONNECTION)->table('tbl_request')->count();
        $count = 0;

        $this->info("Migrating legacy invoices from tbl_request: {$total} row(s) found.");

        DB::connection(self::LEGACY_CONNECTION)
            ->table('tbl_request')
            ->orderBy($keyColumn)
            ->chunk(500, function ($rows) use ($plan, $userId, $keyColumn, $paymentReferenceCounts, &$count): void {
                foreach ($rows as $row) {
                    $legacyId = (int) $this->value($row, $keyColumn, 'id', 'sn');
                    $paymentReference = $this->string($this->value($row, 'payment_id'));
                    $legacyReference = 'LEGACY-REQUEST-' . $legacyId;
                    $reference = $this->legacyInvoiceReference($paymentReference, $legacyId, $paymentReferenceCounts);
                    $amount = $this->numeric($this->value($row, 'amount', 'total_amount', 'price', 'payment_amount')) ?? (float) $plan->amount;
                    $paidAt = $this->legacyDateTime($this->value($row, 'payment_date', 'paid_at', 'created_at'));

                    $invoice = Invoice::where('reference', $reference)
                        ->orWhere('reference', $legacyReference)
                        ->first();

                    $payload = [
                        'reference' => $reference,
                        'invoice_number' => $paymentReference ?: $legacyReference,
                        'description' => 'Legacy premium invoice ' . ($paymentReference ?: $legacyReference),
                        'amount' => $amount,
                        'invoice_type' => 'cr',
                        'payment_date' => $paidAt,
                        'payable_type' => PremiumPlan::class,
                        'payable_id' => $plan->id,
                        'merchant_id' => 0,
                        'merchant_service_type_id' => 0,
                        'userable_type' => User::class,
                        'userable_id' => $userId,
                        'metadata' => [
                            'source_table' => 'tbl_request',
                            'legacy_sn' => $legacyId,
                            'payment_reference' => $paymentReference,
                            'invoice_number' => $paymentReference,
                            'legacy_payment_id' => $paymentReference,
                            'legacy_payload' => json_decode(json_encode($row), true),
                        ],
                        'status' => $this->legacyInvoiceStatus($row),
                        'payment_catgory_id' => 0,
                        'gateway_status' => $this->string($this->value($row, 'gateway_status', 'payment_status', 'status')),
                    ];

                    if ($invoice) {
                        $invoice->fill($payload)->save();
                    } else {
                        Invoice::create($payload);
                    }

                    $count++;
                }

                $this->line("Legacy invoices progress: {$count} migrated.");
            });

        $this->info("Legacy invoices migrated from tbl_request: {$count}");
    }

    private function previewLegacyPins(): void
    {
        $count = $this->legacyTableExists('tbl_pin_inven')
            ? DB::connection(self::LEGACY_CONNECTION)->table('tbl_pin_inven')->count()
            : 0;

        $this->info("Legacy premium pins available in tbl_pin_inven: {$count}");
    }

    private function migrateLegacyPins(): void
    {
        if (!$this->legacyTableExists('tbl_pin_inven')) {
            $this->warn('Skipping premium PINs; legacy tbl_pin_inven table was not found.');
            return;
        }

        $standardPackage = BenefitPackage::where('name', 'Standard Package')
            ->orWhereIn('code', ['standard', 'standard_package'])
            ->first();

        if (!$standardPackage) {
            $this->error('Standard Package benefit package is missing. Run InsuranceProgrammeSeeder before legacy:migrate.');
            return;
        }

        $count = 0;
        $skipped = 0;
        $total = DB::connection(self::LEGACY_CONNECTION)->table('tbl_pin_inven')->count();

        $requestLookup = $this->legacyRequestsByPaymentId();

        $this->info("Migrating legacy premium pins from tbl_pin_inven: {$total} row(s) found.");

        DB::connection(self::LEGACY_CONNECTION)
            ->table('tbl_pin_inven')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($standardPackage, $requestLookup, &$count, &$skipped): void {
                foreach ($rows as $row) {
                    $rawPin = $this->string($this->value($row, 'pin_raw', 'pin', 'pin_number', 'pin_no'));
                    if (!$rawPin) {
                        $skipped++;
                        continue;
                    }

                    $plan = $this->premiumPlanForLegacyPin($row);
                    $legacyId = (int) $row->id;
                    $legacyPaymentId = $this->string($this->value($row, 'payment_id'));
                    $legacyRequest = $this->legacyRequestForPin($row, $requestLookup);
                    $legacyRequestId = $this->numeric($this->value($row, 'request_id', 'req_id', 'invoice_id', 'tbl_request_id'))
                        ?? ($legacyRequest ? (int) $legacyRequest->sn : null);
                    $status = $this->legacyPinStatus($row, $legacyRequest);
                    $soldAt = $this->legacyDateTime($legacyRequest?->payment_date ?? null)
                        ?? $this->legacyDateTime($row->date_generate ?? null)
                        ?? $this->legacyDateTime($row->date_request ?? null);
                    $usedAt = $status === PremiumPin::STATUS_USED ? $this->legacyDateTime($row->date_used ?? null) : null;
                    $serialNumber = $this->legacyPinSerialNumber($row, $rawPin, $legacyId);
                    $legacyLgaId = $this->legacyIntegerId($this->value($row, 'lga', 'lga_id'));
                    $legacyWardId = $this->legacyIntegerId($this->value($row, 'ward', 'ward_id'));

                    $pin = PremiumPin::where('pin', $rawPin)->first()
                        ?: PremiumPin::whereKey($legacyId)->first()
                        ?: new PremiumPin(['id' => $legacyId]);

                    $pin->forceFill([
                        'legacy_id' => $legacyId,
                        'legacy_request_id' => $legacyRequestId ? (int) $legacyRequestId : null,
                        'premium_plan_id' => $plan->id,
                        'insurance_programme_id' => $plan->insurance_programme_id,
                        'benefit_package_id' => $standardPackage->id,
                        'lga_id' => $legacyLgaId && Lga::whereKey($legacyLgaId)->exists() ? $legacyLgaId : null,
                        'ward_id' => $legacyWardId && Ward::whereKey($legacyWardId)->exists() ? $legacyWardId : null,
                        'premium_purchase_id' => null,
                        'batch_code' => $this->string($this->value($row, 'batch_code', 'batch', 'request_no'))
                            ?? 'LEGACY-PIN-' . ($legacyPaymentId ?: $legacyRequestId ?: $legacyId),
                        'pin' => $rawPin,
                        'serial_number' => $serialNumber,
                        'amount' => $this->numeric($this->value($row, 'nicare_premium', 'amount', 'price', 'pin_amount'))
                            ?? $this->requestUnitAmount($legacyRequest)
                            ?? (float) $plan->amount,
                        'status' => $status,
                        'legacy_status' => $this->string($this->value($row, 'status')),
                        'metadata' => [
                            'source_table' => 'tbl_pin_inven',
                            'legacy_id' => $legacyId,
                            'payment_reference' => $legacyPaymentId,
                            'invoice_number' => $legacyPaymentId,
                            'legacy_payment_id' => $legacyPaymentId,
                            'legacy_request_sn' => $legacyRequestId,
                            'legacy_lga_id' => $legacyLgaId,
                            'legacy_ward_id' => $legacyWardId,
                            'legacy_pin_type' => $this->string($row->pin_type ?? null),
                            'legacy_category' => $this->string($row->category ?? null),
                            'legacy_benefit_type' => $this->string($row->benefit_type ?? null),
                            'legacy_payload' => json_decode(json_encode($row), true),
                        ],
                        'expires_at' => $this->legacyDateTime($row->date_expired ?? null),
                        'sold_at' => in_array($status, [PremiumPin::STATUS_SOLD, PremiumPin::STATUS_USED], true) ? $soldAt : null,
                        'used_at' => $usedAt,
                    ])->save();

                    $count++;
                }

                $this->line("Legacy premium pins progress: {$count} migrated, {$skipped} skipped.");
            });

        $this->info("Legacy premium pins migrated from tbl_pin_inven: {$count}");
        if ($skipped > 0) {
            $this->warn("Legacy premium pins skipped because pin_raw was empty: {$skipped}");
        }
    }

    private function previewLegacyCapitations(): void
    {
        $groups = $this->legacyTableExists('capitation_grouping')
            ? DB::connection(self::LEGACY_CONNECTION)->table('capitation_grouping')->count()
            : 0;
        $details = $this->legacyTableExists('capitations')
            ? DB::connection(self::LEGACY_CONNECTION)->table('capitations')->count()
            : 0;
        $programmeTypes = $this->legacyTableExists('capitations')
            ? DB::connection(self::LEGACY_CONNECTION)
                ->table('capitations')
                ->select('programme_type', DB::raw('COUNT(*) as total'))
                ->groupBy('programme_type')
                ->orderBy('programme_type')
                ->pluck('total', 'programme_type')
                ->all()
            : [];

        $this->info("Legacy capitation groups available in capitation_grouping: {$groups}");
        $this->info("Legacy capitation details available in capitations: {$details}");

        if ($programmeTypes !== []) {
            $this->table(
                ['programme_type', 'funding type code', 'rows'],
                collect($programmeTypes)->map(fn ($total, $programmeType): array => [
                    $programmeType ?: '(blank)',
                    $this->legacyCapitationFundingCode($programmeType) ?? '(unmapped)',
                    $total,
                ])->values()->all()
            );
        }
    }

    private function migrateLegacyCapitations(): void
    {
        if (!$this->legacyTableExists('capitation_grouping')) {
            $this->warn('Skipping capitations; legacy capitation_grouping table was not found.');
            return;
        }

        if (!$this->legacyTableExists('capitations')) {
            $this->warn('Skipping capitations; legacy capitations table was not found.');
            return;
        }

        $systemUserId = $this->systemUserId();
        $ratesByGroup = DB::connection(self::LEGACY_CONNECTION)
            ->table('capitations')
            ->select('group_id', DB::raw('MAX(cap_rate) as cap_rate'))
            ->groupBy('group_id')
            ->pluck('cap_rate', 'group_id')
            ->all();
        $groupLookup = [];
        $groupsMigrated = 0;
        $groupsTotal = DB::connection(self::LEGACY_CONNECTION)->table('capitation_grouping')->count();
        $detailsTotal = DB::connection(self::LEGACY_CONNECTION)->table('capitations')->count();

        $this->info("Migrating legacy capitation groups from capitation_grouping: {$groupsTotal} row(s) found.");

        DB::connection(self::LEGACY_CONNECTION)
            ->table('capitation_grouping')
            ->orderBy('id')
            ->chunk(200, function ($rows) use ($systemUserId, $ratesByGroup, &$groupLookup, &$groupsMigrated): void {
                foreach ($rows as $row) {
                    $legacyId = (int) $row->id;
                    $cutoff = $this->legacyCarbon($row->enroled_on_before_date ?? null);
                    $capitatedMonth = $cutoff?->month ?: (int) ($row->month ?? 1);
                    $periodYear = (int) ($row->cap_year ?? $row->year ?? $cutoff?->year ?? now()->year);
                    $createdAt = $this->legacyDateTime($row->date_created ?? null) ?? now()->toDateTimeString();
                    $updatedAt = $this->legacyDateTime($row->last_modified ?? null) ?? $createdAt;
                    $finalisedAt = $this->legacyDateTime($row->approval_date_bhcpf ?? null)
                        ?? $this->legacyDateTime($row->approval_date_nicare ?? null);

                    $payload = [
                        'name' => $this->string($row->name ?? null) ?? 'Legacy Capitation ' . $legacyId,
                        'period_start' => $cutoff?->copy()->startOfMonth()->toDateString(),
                        'period_end' => $cutoff?->copy()->endOfMonth()->toDateString(),
                        'capitation_rate' => $this->numeric($ratesByGroup[$legacyId] ?? null) ?? 0,
                        'capitated_month' => max(1, min(12, $capitatedMonth)),
                        'capitation_month' => max(1, min(12, (int) ($row->month ?? $capitatedMonth))),
                        'year' => $periodYear,
                        'funding_type_id' => null,
                        'user_id' => $systemUserId,
                        'created_by' => $this->legacyUserIdOrSystem($row->created_by ?? null, $systemUserId),
                        'status' => (string) ($row->status ?? '1') === '1',
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                        'computed_at' => $createdAt,
                        'computed_by' => $this->legacyUserIdOrSystem($row->created_by ?? null, $systemUserId),
                        'finalised_at' => $finalisedAt,
                        'finalised_by' => null,
                        'metadata' => [
                            'source_table' => 'capitation_grouping',
                            'legacy_id' => $legacyId,
                            'legacy_name' => $this->string($row->name ?? null),
                            'legacy_year' => $row->year ?? null,
                            'legacy_cap_year' => $row->cap_year ?? null,
                            'legacy_month' => $row->month ?? null,
                            'legacy_month_full' => $this->string($row->month_full ?? null),
                            'legacy_enroled_on_before_date' => $this->legacyDateTime($row->enroled_on_before_date ?? null),
                            'legacy_providers_string' => $this->string($row->providers_string ?? null),
                            'legacy_cap_for' => $this->string($row->cap_for ?? null),
                            'legacy_open_status' => $row->open_status ?? null,
                            'legacy_status' => $row->status ?? null,
                            'legacy_created_by' => $row->created_by ?? null,
                            'legacy_review_message_bhcpf' => $this->string($row->review_message_bhcpf ?? null),
                            'legacy_review_message_nicare' => $this->string($row->review_message_nicare ?? null),
                            'legacy_review_date_bhcpf' => $this->legacyDateTime($row->review_date_bhcpf ?? null),
                            'legacy_review_date_nicare' => $this->legacyDateTime($row->review_date_nicare ?? null),
                            'legacy_approval_message_bhcpf' => $this->string($row->approval_message_bhcpf ?? null),
                            'legacy_approval_message_nicare' => $this->string($row->approval_message_nicare ?? null),
                            'legacy_approval_date_bhcpf' => $this->legacyDateTime($row->approval_date_bhcpf ?? null),
                            'legacy_approval_date_nicare' => $this->legacyDateTime($row->approval_date_nicare ?? null),
                            'legacy_payment_date_bhcpf' => $this->legacyDateTime($row->payment_date_bhcpf ?? null),
                            'legacy_payment_date_nicare' => $this->legacyDateTime($row->payment_date_nicare ?? null),
                        ],
                    ];

                    $capitation = $this->capitationForLegacyGroup($legacyId);
                    if ($capitation) {
                        $capitation->fill($payload)->save();
                    } else {
                        $capitation = Capitation::create($payload);
                    }

                    $groupLookup[$legacyId] = $capitation->id;
                    $groupsMigrated++;
                }

                $this->line("Legacy capitation groups progress: {$groupsMigrated} migrated.");
            });

        $detailsMigrated = 0;
        $detailsSkipped = 0;

        $this->info("Migrating legacy capitation details from capitations: {$detailsTotal} row(s) found.");

        DB::connection(self::LEGACY_CONNECTION)
            ->table('capitations')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$groupLookup, &$detailsMigrated, &$detailsSkipped): void {
                foreach ($rows as $row) {
                    $legacyId = (int) $row->id;
                    $legacyGroupId = (int) ($row->group_id ?? 0);
                    $capitationId = $groupLookup[$legacyGroupId] ?? null;

                    if (!$capitationId) {
                        $capitation = $this->capitationForLegacyGroup($legacyGroupId);
                        $capitationId = $capitation?->id;
                        if ($capitationId) {
                            $groupLookup[$legacyGroupId] = $capitationId;
                        }
                    }

                    if (!$capitationId) {
                        $detailsSkipped++;
                        continue;
                    }

                    $capitation = Capitation::find($capitationId);
                    $fundingType = $this->fundingTypeForLegacyCapitation($row->programme_type ?? null);
                    $facility = $this->facilityForLegacyCapitationProvider($row->provider_id ?? null);
                    $generatedAt = $this->legacyDateTime($row->generated_at ?? null) ?? now()->toDateTimeString();
                    $reviewedAt = $this->legacyDateTime($row->reviewed_at ?? null);
                    $approvedAt = $this->legacyDateTime($row->approved_at ?? null);
                    $paidAt = $this->legacyDateTime($row->paid_at ?? null)
                        ?? ((string) ($row->payment_status ?? '') === '1' ? ($approvedAt ?? $generatedAt) : null);
                    $totalEnrollees = (int) ($this->numeric($row->total_enrolee ?? null) ?? 0);
                    $capitationRate = $this->numeric($row->cap_rate ?? null) ?? 0;
                    $totalAmount = $this->numeric($row->total_cap ?? null) ?? 0;

                    $payload = [
                        'capitation_id' => $capitationId,
                        'facility_id' => $facility->id,
                        'capitated_month' => $capitation?->capitated_month,
                        'funding_type_id' => $fundingType->id,
                        'total_enrollees' => $totalEnrollees,
                        'capitation_rate' => $capitationRate,
                        'total_amount' => $totalAmount,
                        'total_enrolled' => $totalEnrollees,
                        'rate' => $capitationRate,
                        'amount' => $totalAmount,
                        'reviewed_by' => $this->legacyUserIdOrNull($row->reviewed_by ?? null),
                        'approved_by' => $this->legacyUserIdOrNull($row->approved_by ?? null),
                        'paid_by' => $this->legacyUserIdOrNull($row->paid_by ?? null),
                        'reviewed_at' => $reviewedAt,
                        'approved_at' => $approvedAt,
                        'paid_at' => $paidAt,
                        'status' => $this->legacyCapitationDetailStatus($row),
                        'created_at' => $generatedAt,
                        'updated_at' => $paidAt ?? $approvedAt ?? $reviewedAt ?? $generatedAt,
                        'metadata' => [
                            'source_table' => 'capitations',
                            'legacy_id' => $legacyId,
                            'legacy_group_id' => $legacyGroupId,
                            'legacy_programme_type' => $this->string($row->programme_type ?? null),
                            'mapped_funding_type_code' => $this->legacyCapitationFundingCode($row->programme_type ?? null),
                            'legacy_provider_id' => $row->provider_id ?? null,
                            'legacy_total_enrolee' => $row->total_enrolee ?? null,
                            'legacy_total_cap' => $row->total_cap ?? null,
                            'legacy_generated_by' => $row->generated_by ?? null,
                            'legacy_generated_at' => $this->legacyDateTime($row->generated_at ?? null),
                            'legacy_review_status' => $row->review_status ?? null,
                            'legacy_reviewed_by' => $row->reviewed_by ?? null,
                            'legacy_reviewed_at' => $this->legacyDateTime($row->reviewed_at ?? null),
                            'legacy_approval_status' => $row->approval_status ?? null,
                            'legacy_audit_status' => $row->audit_status ?? null,
                            'legacy_audited_by' => $row->audited_by ?? null,
                            'legacy_audited_at' => $this->legacyDateTime($row->audited_at ?? null),
                            'legacy_approved_by' => $row->approved_by ?? null,
                            'legacy_approved_at' => $this->legacyDateTime($row->approved_at ?? null),
                            'legacy_cap_rate' => $row->cap_rate ?? null,
                            'legacy_payment_status' => $row->payment_status ?? null,
                            'legacy_payment_code' => $this->string($row->payment_code ?? null),
                            'legacy_paid_by' => $row->paid_by ?? null,
                            'legacy_paid_at' => $this->legacyDateTime($row->paid_at ?? null),
                            'legacy_status' => $row->status ?? null,
                            'legacy_revoke_gen_date' => $this->legacyDateTime($row->revoke_gen_date ?? null),
                        ],
                    ];

                    $detail = $this->capitationDetailForLegacyRow($legacyId);
                    if ($detail) {
                        $detail->fill($payload)->save();
                    } else {
                        CapitationDetail::create($payload);
                    }

                    $detailsMigrated++;
                }

                $this->line("Legacy capitation details progress: {$detailsMigrated} migrated, {$detailsSkipped} skipped.");
            });

        $this->info("Legacy capitation groups migrated from capitation_grouping: {$groupsMigrated}");
        $this->info("Legacy capitation details migrated from capitations: {$detailsMigrated}");

        if ($detailsSkipped > 0) {
            $this->warn("Legacy capitation details skipped because group_id was missing in capitation_grouping: {$detailsSkipped}");
        }
    }

    private function capitationForLegacyGroup(int $legacyId): ?Capitation
    {
        return Capitation::where('metadata->source_table', 'capitation_grouping')
            ->where('metadata->legacy_id', $legacyId)
            ->first();
    }

    private function capitationDetailForLegacyRow(int $legacyId): ?CapitationDetail
    {
        return CapitationDetail::where('metadata->source_table', 'capitations')
            ->where('metadata->legacy_id', $legacyId)
            ->first();
    }

    private function legacyCapitationFundingCode(mixed $programmeType): ?string
    {
        $programmeType = strtolower((string) $this->string($programmeType));

        return match ($programmeType) {
            'bhcpf' => 'bhcpf',
            'unicef' => 'unicef',
            'nicare-formal', 'gac' => 'formal',
            'bhcpf-cf' => 'cf',
            'nicare' => 'premium',
            default => null,
        };
    }

    private function fundingTypeForLegacyCapitation(mixed $programmeType): FundingType
    {
        $code = $this->legacyCapitationFundingCode($programmeType) ?? 'premium';
        $reference = LegacyReferenceData::fundingTypeByLegacyValue($code);
        $name = $reference['name'] ?? ucfirst($code);

        return FundingType::firstOrCreate(
            ['name' => $name],
            [
                'description' => 'Legacy capitation programme_type mapped to funding code: ' . $code,
                'status' => $reference['status'] ?? 1,
            ]
        );
    }

    private function facilityForLegacyCapitationProvider(mixed $providerId): Facility
    {
        $legacyProviderId = $this->legacyIntegerId($providerId);

        if ($legacyProviderId && $this->legacyTableExists('tbl_providers')) {
            $provider = DB::connection(self::LEGACY_CONNECTION)
                ->table('tbl_providers')
                ->where('id', $legacyProviderId)
                ->first();
            $hcpCode = $this->string($provider->hcpcode ?? null);
            if ($hcpCode) {
                $facility = Facility::where('hcp_code', $hcpCode)->first();
                if ($facility) {
                    return $facility;
                }
            }
        }

        if ($legacyProviderId) {
            $facility = Facility::find($legacyProviderId);
            if ($facility) {
                return $facility;
            }
        }

        $unknownLga = $this->unknownLga();
        $unknownWard = $this->unknownWard($unknownLga);
        $hcpCode = 'LEGACY-CAP-PROVIDER-' . ($legacyProviderId ?: 'UNKNOWN');
        $payload = [
            'name' => 'Legacy Capitation Provider ' . ($legacyProviderId ?: 'Unknown'),
            'ownership' => 'Public',
            'type' => 'Primary',
            'address' => null,
            'phone' => null,
            'email' => null,
            'lga_id' => $unknownLga->id,
            'ward_id' => $unknownWard->id,
            'capacity' => 0,
            'status' => 1,
        ];

        if (Schema::hasColumn('facilities', 'accreditation_status')) {
            $payload['accreditation_status'] = 'active';
        }

        return Facility::firstOrCreate(['hcp_code' => $hcpCode], $payload);
    }

    private function legacyUserIdOrSystem(mixed $legacyUserId, int $systemUserId): int
    {
        return $this->legacyUserIdOrNull($legacyUserId) ?? $systemUserId;
    }

    private function legacyUserIdOrNull(mixed $legacyUserId): ?int
    {
        $userId = $this->legacyIntegerId($legacyUserId);

        return $userId && User::whereKey($userId)->exists() ? $userId : null;
    }

    private function legacyCapitationDetailStatus(object $row): int
    {
        if ((string) ($row->payment_status ?? '') === '1' || $this->legacyDateTime($row->paid_at ?? null)) {
            return 4;
        }

        if ((string) ($row->approval_status ?? '') === '1' || $this->legacyDateTime($row->approved_at ?? null)) {
            return 3;
        }

        if ((string) ($row->review_status ?? '') === '1' || $this->legacyDateTime($row->reviewed_at ?? null)) {
            return 2;
        }

        return (string) ($row->status ?? '1') === '1' ? 1 : 0;
    }

    /**
     * @param array<int, string> $tables
     */
    private function dispatchEnrollees(
        array $tables,
        int $chunk,
        ?int $fromId,
        ?int $limit,
        bool $dryRun
    ): void {
        $jobs = [];

        foreach ($tables as $table) {
            $query = DB::connection(self::LEGACY_CONNECTION)
                ->table($table)
                ->orderBy('id')
                ->when($fromId !== null, fn ($q) => $q->where('id', '>=', $fromId));

            $total   = $query->count();
            $target  = $limit === null ? $total : min($total, $limit);
            $fetched = 0;

            $this->info("Queuing jobs for {$table}: {$target} row(s) across " . (int) ceil($target / $chunk) . ' chunk(s).');

            $query->select('id')->limit($target)->chunk($chunk, function ($rows) use ($table, $dryRun, &$jobs, &$fetched): void {
                $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
                $jobs[] = new ProcessLegacyEnrolleesJob($table, $ids, $dryRun);
                $fetched += count($ids);
            });
        }

        if (empty($jobs)) {
            $this->warn('No enrollee jobs to dispatch.');
            return;
        }

        $batch = Bus::batch($jobs)
            ->name('legacy-enrollee-migration')
            ->allowFailures()
            ->onQueue('legacy')
            ->then(fn (Batch $b) => \Log::info("Legacy enrollee migration batch {$b->id} completed."))
            ->catch(fn (Batch $b, \Throwable $e) => \Log::error("Legacy enrollee migration batch {$b->id} failed: {$e->getMessage()}"))
            ->dispatch();

        $this->info("Dispatched {$batch->totalJobs} job(s) as batch ID: {$batch->id}");
        $this->line('Monitor progress: php artisan queue:work --queue=legacy');
        $this->line("Or check: DB::table('job_batches')->find('{$batch->id}')");
    }

    /**
     * @param array<int, string> $tables
     * @return array<string, int>
     */
    private function migrateEnrollees(
        LegacyEnrolleeMigrationService $service,
        array $tables,
        int $chunk,
        ?int $fromId,
        ?int $limit,
        bool $dryRun
    ): array {
        $stats = [
            'processed' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'duplicate_matched' => 0,
            'missing_facility' => 0,
            'missing_lga_ward' => 0,
            'missing_funding_type' => 0,
        ];

        $this->info(($dryRun ? 'Dry-running' : 'Migrating') . ' legacy enrollees from: ' . implode(', ', $tables));

        foreach ($tables as $table) {
            $available = DB::connection(self::LEGACY_CONNECTION)
                ->table($table)
                ->when($fromId !== null, fn ($query) => $query->where('id', '>=', $fromId))
                ->count();
            $target = $limit === null ? $available : min($available, $limit);
            $remaining = $limit;
            $lastId = $fromId ? $fromId - 1 : 0;

            $this->info("Migrating legacy enrollees from {$table}: {$target} row(s) targeted.");

            while ($remaining === null || $remaining > 0) {
                $take = $remaining === null ? $chunk : min($chunk, $remaining);
                $rows = DB::connection(self::LEGACY_CONNECTION)
                    ->table($table)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($take)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                foreach ($rows as $row) {
                    $lastId = (int) $row->id;
                    $stats['processed']++;

                    try {
                        $result = $service->migrate($row, $table, $dryRun);
                        $mapped = $result['mapped'] ?? [];
                        $flags = $mapped['flags'] ?? [];

                        $stats[$dryRun ? 'skipped' : 'migrated']++;
                        if ($result['duplicate_matched'] ?? false) {
                            $stats['duplicate_matched']++;
                        }
                        if ($flags['missing_facility'] ?? false) {
                            $stats['missing_facility']++;
                        }
                        if (($flags['missing_lga'] ?? false) || ($flags['missing_ward'] ?? false)) {
                            $stats['missing_lga_ward']++;
                        }
                        if ($flags['missing_funding_type'] ?? false) {
                            $stats['missing_funding_type']++;
                        }

                        if ($this->output->isVerbose()) {
                            $this->line(sprintf('[%s:%s] %s', $table, $row->id, $result['message']));
                        }
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        if (!$dryRun) {
                            $service->logFailure($row, $table, $e);
                        }
                        $this->error("[{$table}:{$row->id}] failed: {$e->getMessage()}");
                    }
                }

                if ($remaining !== null) {
                    $remaining -= $rows->count();
                }

                $this->line("Legacy enrollees progress for {$table}: last id {$lastId}; {$stats['processed']} total processed.");
            }
        }

        $this->newLine();
        $this->info('Legacy enrollee migration summary');
        $this->table(
            ['Metric', 'Count'],
            [
                ['total processed', $stats['processed']],
                ['migrated', $stats['migrated']],
                ['skipped', $stats['skipped']],
                ['failed', $stats['failed']],
                ['duplicate matched', $stats['duplicate_matched']],
                ['missing facility', $stats['missing_facility']],
                ['missing LGA/Ward', $stats['missing_lga_ward']],
                ['missing funding type', $stats['missing_funding_type']],
            ]
        );

        return $stats;
    }

    private function previewReferenceData(): void
    {
        $rows = [
            ['LGAs', $this->legacyTableExists('lga') ? DB::connection(self::LEGACY_CONNECTION)->table('lga')->count() : 0],
            ['Wards', $this->legacyTableExists('ward') ? DB::connection(self::LEGACY_CONNECTION)->table('ward')->count() : 0],
            ['Funding types', count(LegacyReferenceData::fundingTypes())],
            ['Benefactors', count(LegacyReferenceData::benefactors())],
            ['Enrollment phases', $this->legacyTableExists('enrolment_phases') ? DB::connection(self::LEGACY_CONNECTION)->table('enrolment_phases')->count() : 0],
            ['Facilities', $this->legacyTableExists('tbl_providers') ? DB::connection(self::LEGACY_CONNECTION)->table('tbl_providers')->count() : 0],
            ['Admin users', $this->legacyTableExists('users') ? DB::connection(self::LEGACY_CONNECTION)->table('users')->where('user_role_id', 1)->count() : 0],
        ];

        $this->info('Dry-run reference data counts. Array-backed reference records and legacy table records were not written.');
        $this->table(['Dataset', 'Rows'], $rows);
    }

    private function previewEnrollmentPhases(): void
    {
        $count = $this->legacyTableExists('enrolment_phases')
            ? DB::connection(self::LEGACY_CONNECTION)->table('enrolment_phases')->count()
            : 0;

        $this->info("Legacy enrollment phases available in enrolment_phases: {$count}");
    }

    /**
     * @param array<int, string> $enrolleeTables
     */
    private function schemaReady(
        bool $runReference,
        bool $runPhases,
        bool $runPremiumPinsOrInvoices,
        bool $runCapitations,
        array $enrolleeTables
    ): bool
    {
        $requiredNewTables = $runReference
            ? ['lgas', 'wards', 'funding_types', 'benefactors', 'enrollment_phases', 'vulnerable_groups', 'facilities', 'banks', 'account_details', 'users', 'staff', 'roles', 'role_user']
            : [];

        if ($runPhases) {
            $requiredNewTables = array_merge($requiredNewTables, ['benefactors', 'enrollment_phases']);
        }

        if ($enrolleeTables) {
            $requiredNewTables = array_merge($requiredNewTables, [
                'enrollees',
                'funding_types',
                'benefactors',
                'enrollment_phases',
                'vulnerable_groups',
                'benefit_packages',
                'insurance_programmes',
                'enrollee_categories',
                'premium_plans',
                'premium_pins',
                'premium_purchases',
                'legacy_migration_logs',
            ]);
        }

        if ($runPremiumPinsOrInvoices) {
            $requiredNewTables = array_merge($requiredNewTables, [
                'premium_plans',
                'premium_pins',
                'benefit_packages',
                'invoices',
                'users',
            ]);
        }

        if ($runCapitations) {
            $requiredNewTables = array_merge($requiredNewTables, [
                'capitations',
                'capitation_details',
                'facilities',
                'funding_types',
                'lgas',
                'wards',
                'users',
            ]);
        }

        foreach (array_unique($requiredNewTables) as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Required new-system table [{$table}] is missing. Run php artisan migrate before legacy:migrate.");
                return false;
            }
        }

        if ($runCapitations) {
            foreach (['capitations', 'capitation_details'] as $table) {
                if (!Schema::hasColumn($table, 'metadata')) {
                    $this->error("Required new-system column [{$table}.metadata] is missing. Run php artisan migrate before legacy:migrate --only=capitations.");
                    return false;
                }
            }
        }

        foreach ($runReference ? ['lga', 'ward', 'tbl_providers'] : [] as $table) {
            if (!$this->legacyTableExists($table)) {
                $this->error("Required legacy table [{$table}] is missing on the " . self::LEGACY_CONNECTION . ' connection.');
                return false;
            }
        }

        if ($runPhases && !$this->legacyTableExists('enrolment_phases')) {
            $this->error('Required legacy table [enrolment_phases] is missing on the ' . self::LEGACY_CONNECTION . ' connection.');
            return false;
        }

        foreach ($enrolleeTables as $table) {
            if (!$this->legacyTableExists($table)) {
                $this->error("Required legacy enrollee table [{$table}] is missing on the " . self::LEGACY_CONNECTION . ' connection.');
                return false;
            }
        }

        foreach ($runPremiumPinsOrInvoices ? ['tbl_pin_inven', 'tbl_request'] : [] as $table) {
            if (!$this->legacyTableExists($table)) {
                $this->warn("Legacy table [{$table}] is missing on the " . self::LEGACY_CONNECTION . ' connection; that dataset will be skipped.');
            }
        }

        foreach ($runCapitations ? ['capitation_grouping', 'capitations'] : [] as $table) {
            if (!$this->legacyTableExists($table)) {
                $this->error("Required legacy capitation table [{$table}] is missing on the " . self::LEGACY_CONNECTION . ' connection.');
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function selectedEnrolleeTables(string $source): array
    {
        $formalTable = 'tbl_enrolee_formal';

        return match ($source) {
            'informal' => ['tbl_enrolee'],
            'formal' => [$formalTable],
            default => ['tbl_enrolee', $formalTable],
        };
    }

    /**
     * @param array<int, string> $tables
     */
    private function firstExistingLegacyTable(array $tables): ?string
    {
        foreach ($tables as $table) {
            if ($this->legacyTableExists($table)) {
                return $table;
            }
        }

        return null;
    }

    private function legacyTableExists(string $table): bool
    {
        return Schema::connection(self::LEGACY_CONNECTION)->hasTable($table);
    }

    /**
     * @param array<int, string> $candidates
     */
    private function legacyKeyColumn(string $table, array $candidates = ['id']): string
    {
        foreach ($candidates as $column) {
            if (Schema::connection(self::LEGACY_CONNECTION)->hasColumn($table, $column)) {
                return $column;
            }
        }

        $columns = Schema::connection(self::LEGACY_CONNECTION)->getColumnListing($table);
        if ($columns === []) {
            throw new \RuntimeException("Legacy table [{$table}] has no readable columns.");
        }

        return $columns[0];
    }

    private function unknownLga(): Lga
    {
        return Lga::firstOrCreate(
            ['code' => 'LEGACY'],
            ['name' => 'Unknown Legacy LGA', 'zone' => 0, 'status' => 1]
        );
    }

    private function unknownWard(Lga $lga): Ward
    {
        return Ward::firstOrCreate(
            ['name' => 'Unknown Legacy Ward', 'lga_id' => $lga->id],
            ['settlement_type' => 1, 'status' => 1]
        );
    }

    private function uniqueLgaCode(object $row): string
    {
        $legacyId = (int) $row->id;
        $code = $this->string($row->code ?? null) ?? 'LEGACY-LGA-' . $legacyId;
        $existing = Lga::where('code', $code)->where('id', '<>', $legacyId)->exists();

        return $existing ? $code . '-' . $legacyId : $code;
    }

    private function settlementType(mixed $value): int
    {
        $value = strtolower((string) ($value ?? ''));

        return in_array($value, ['2', 'u', 'urban'], true) ? 2 : 1;
    }

    private function facilityOwnership(mixed $value): string
    {
        $value = strtolower((string) ($value ?? ''));

        return match (true) {
            str_contains($value, 'faith') => 'Faith-Based',
            str_contains($value, 'private') => 'Private',
            default => 'Public',
        };
    }

    private function facilityType(mixed $value): string
    {
        $value = strtolower((string) ($value ?? ''));

        return match (true) {
            str_contains($value, 'tertiary') => 'Tertiary',
            str_contains($value, 'secondary') => 'Secondary',
            default => 'Primary',
        };
    }

    private function benefactorType(string $name): string
    {
        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'government'), str_contains($name, 'gac'), str_contains($name, 'ngscha') => 'government',
            str_contains($name, 'employer'), str_contains($name, 'ministry') => 'employer',
            default => 'donor',
        };
    }

    private function defaultPremiumPlan(): PremiumPlan
    {
        $plan = PremiumPlan::where('code', 'individual_informal_sector')->first()
            ?: PremiumPlan::where('status', 'active')->orderBy('id')->first();

        if (!$plan) {
            throw new \RuntimeException('No premium plan found. Run InsuranceProgrammeSeeder before legacy:migrate.');
        }

        return $plan;
    }

    private function premiumPlanForLegacyPin(object $row): PremiumPlan
    {
        $mode = strtolower((string) $this->value($row, 'mode_of_enrolment', 'mode'));
        $category = strtolower((string) $this->value($row, 'enrollee_category', 'enrolee_category', 'category'));

        $code = match (true) {
            str_contains($category, 'formal') && str_contains($mode, 'premium') => 'individual_formal_sector',
            str_contains($category, 'informal') && str_contains($mode, 'huwe') => 'individual_vulnerable_groups',
            default => 'individual_informal_sector',
        };

        return PremiumPlan::where('code', $code)->first() ?: $this->defaultPremiumPlan();
    }

    /**
     * @return array<string, int>
     */
    private function legacyPaymentReferenceCounts(string $table): array
    {
        if (!$this->legacyTableExists($table) || !Schema::connection(self::LEGACY_CONNECTION)->hasColumn($table, 'payment_id')) {
            return [];
        }

        return DB::connection(self::LEGACY_CONNECTION)
            ->table($table)
            ->whereNotNull('payment_id')
            ->pluck('payment_id')
            ->map(fn ($paymentId): string => trim((string) $paymentId))
            ->filter()
            ->countBy()
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Legacy payment_id is the payment reference/invoice number. If the legacy
     * table repeats it, suffix the internal reference while keeping invoice_number
     * as the raw payment_id.
     *
     * @param array<string, int> $paymentReferenceCounts
     */
    private function legacyInvoiceReference(?string $paymentReference, int $legacyId, array $paymentReferenceCounts): string
    {
        $paymentReference = trim((string) $paymentReference);

        if ($paymentReference === '') {
            return 'LEGACY-REQUEST-' . $legacyId;
        }

        if (($paymentReferenceCounts[$paymentReference] ?? 0) > 1) {
            return substr($paymentReference . '-SN' . $legacyId, 0, 50);
        }

        return substr($paymentReference, 0, 50);
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function legacyRequestsByPaymentId(): array
    {
        if (!$this->legacyTableExists('tbl_request')) {
            return [];
        }

        return DB::connection(self::LEGACY_CONNECTION)
            ->table('tbl_request')
            ->whereNotNull('payment_id')
            ->orderBy('sn')
            ->get()
            ->groupBy(fn (object $row) => (string) $row->payment_id)
            ->map(fn ($rows) => $rows->values()->all())
            ->all();
    }

    /**
     * @param array<string, array<int, object>> $requestLookup
     */
    private function legacyRequestForPin(object $pin, array $requestLookup): ?object
    {
        $paymentId = $this->string($pin->payment_id ?? null);
        if (!$paymentId || empty($requestLookup[$paymentId])) {
            return null;
        }

        $requests = collect($requestLookup[$paymentId]);
        $pinType = strtolower((string) ($pin->pin_type ?? ''));
        $benefitType = strtolower((string) ($pin->benefit_type ?? ''));

        return $requests
            ->first(function (object $request) use ($pinType, $benefitType): bool {
                return strtolower((string) ($request->payment_status ?? '')) === 'paid'
                    && (!$pinType || strtolower((string) ($request->pin_type ?? '')) === $pinType)
                    && (!$benefitType || strtolower((string) ($request->benefit_type ?? '')) === $benefitType);
            })
            ?? $requests->first(fn (object $request) => strtolower((string) ($request->payment_status ?? '')) === 'paid')
            ?? $requests->first();
    }

    private function requestUnitAmount(?object $request): ?float
    {
        if (!$request) {
            return null;
        }

        $quantity = $this->numeric($request->quantity ?? null);
        $amount = $this->numeric($request->amount ?? null);

        if (!$amount || !$quantity || $quantity <= 0) {
            return $amount;
        }

        return round($amount / $quantity, 2);
    }

    private function legacyPinStatus(object $pin, ?object $request = null): string
    {
        $status = trim(strtolower((string) ($pin->status ?? '')));

        if (in_array($status, ['used', '1', 'redeemed'], true)) {
            return PremiumPin::STATUS_USED;
        }

        $paymentStatus = trim(strtolower((string) ($request->payment_status ?? '')));
        $gatewayStatus = trim(strtolower((string) ($pin->gateway_status ?? $request?->gateway_status ?? '')));

        if (in_array($status, ['not used', 'unused'], true)
            && ($paymentStatus === 'paid' || $gatewayStatus === 'success')) {
            return PremiumPin::STATUS_SOLD;
        }

        return PremiumPin::STATUS_GENERATED;
    }

    private function legacyPinSerialNumber(object $pin, string $rawPin, int $legacyId): string
    {
        $serial = $this->string($this->value($pin, 'serial_number', 'serial_no', 'sn')) ?? 'LEGACY-SN-' . $legacyId;

        $ownedByAnotherPin = PremiumPin::where('serial_number', $serial)
            ->where('pin', '<>', $rawPin)
            ->exists();

        return $ownedByAnotherPin ? $serial . '-' . $legacyId : $serial;
    }

    private function legacyIntegerId(mixed $value): ?int
    {
        $value = $this->string($value);

        if (!$value || !ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function legacyInvoiceStatus(object $row): int
    {
        $status = strtolower((string) $this->value($row, 'payment_status', 'gateway_status', 'status'));

        return str_contains($status, 'paid')
            || str_contains($status, 'success')
            || str_contains($status, 'confirm')
            || in_array($status, ['1', 'completed'], true)
            ? 1
            : 0;
    }

    private function systemUserId(): int
    {
        $user = User::query()->first();
        if ($user) {
            return $user->id;
        }

        return User::create([
            'name' => 'Legacy Migration',
            'username' => 'legacy-migration',
            'email' => 'legacy-migration@nicare.local',
            'password' => Hash::make(str()->random(32)),
            'userable_type' => User::class,
            'userable_id' => 0,
            'status' => 1,
        ])->id;
    }

    private function numeric(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function legacyDateTime(mixed $value): ?string
    {
        $value = $this->string($value);
        if (!$value || in_array($value, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function legacyCarbon(mixed $value): ?Carbon
    {
        $value = $this->string($value);
        if (!$value || in_array($value, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function value(object $row, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (property_exists($row, $key) && $row->{$key} !== null && $row->{$key} !== '') {
                return $row->{$key};
            }
        }

        return null;
    }

    private function string(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
