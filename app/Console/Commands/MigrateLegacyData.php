<?php

namespace App\Console\Commands;

use App\Models\AccountDetail;
use App\Models\Bank;
use App\Models\Benefactor;
use App\Models\BenefitPackage;
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
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyData extends Command
{
    private const LEGACY_CONNECTION = 'legacy_mysql';

    protected $signature = 'legacy:migrate
        {--only=all : all, reference, pins, invoices, or enrollees}
        {--source=all : all, informal, or formal}
        {--dry-run : Inspect and print without writing}
        {--chunk=500 : Number of legacy enrollee rows per chunk}
        {--from-id= : Start enrollee migration from a legacy id}
        {--limit= : Maximum enrollee rows to process per source table}
        {--skip-users : Do not migrate legacy admin users}';

    protected $description = 'Migrate legacy reference data and enrollees into the current coverage structure';

    /** @var array<int, int> */
    private array $lgaIds = [];

    /** @var array<int, int> */
    private array $wardIds = [];

    public function handle(LegacyEnrolleeMigrationService $enrolleeService): int
    {
        $only = strtolower((string) $this->option('only'));
        if (!in_array($only, ['all', 'reference', 'pins', 'invoices', 'enrollees'], true)) {
            $this->error('--only must be one of: all, reference, pins, invoices, enrollees');
            return self::FAILURE;
        }

        $source = strtolower((string) $this->option('source'));
        if (!in_array($source, ['all', 'informal', 'formal'], true)) {
            $this->error('--source must be one of: all, informal, formal');
            return self::FAILURE;
        }

        $runReference = in_array($only, ['all', 'reference'], true);
        $runPins = in_array($only, ['all', 'pins', 'enrollees'], true);
        $runInvoices = in_array($only, ['all', 'invoices', 'pins', 'enrollees'], true);
        $runEnrollees = in_array($only, ['all', 'enrollees'], true);
        $enrolleeTables = $runEnrollees ? $this->selectedEnrolleeTables($source) : [];

        if (!$this->schemaReady($runReference, $runPins || $runInvoices, $enrolleeTables)) {
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

        if ($runInvoices) {
            $dryRun ? $this->previewLegacyInvoices() : $this->migrateLegacyInvoices();
        }

        if ($runPins) {
            $dryRun ? $this->previewLegacyPins() : $this->migrateLegacyPins();
        }

        $enrolleeStats = ['failed' => 0];
        if ($runEnrollees) {
            $enrolleeStats = $this->migrateEnrollees(
                $enrolleeService,
                $enrolleeTables,
                max((int) $this->option('chunk'), 1),
                $this->option('from-id') !== null ? (int) $this->option('from-id') : null,
                $this->option('limit') !== null ? (int) $this->option('limit') : null,
                $dryRun
            );
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
            FundingType::updateOrCreate(
                ['name' => $fundingType['name']],
                [
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
                'status' => $benefactor['status'],
            ];

            if ($hasType) {
                $data['type'] = $benefactor['type'];
            }

            Benefactor::updateOrCreate(['name' => $benefactor['name']], $data);
            $count++;
        }

        $this->info("Benefactors seeded from legacy reference array: {$count}");
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

                $facility = Facility::updateOrCreate(['hcp_code' => $hcpCode], $data);
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
        $count = 0;

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

        $requestLookup = $this->legacyRequestsByPaymentId();

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

                    PremiumPin::updateOrCreate(
                        ['pin' => $rawPin],
                        [
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
                        ]
                    );

                    $count++;
                }
            });

        $this->info("Legacy premium pins migrated from tbl_pin_inven: {$count}");
        if ($skipped > 0) {
            $this->warn("Legacy premium pins skipped because pin_raw was empty: {$skipped}");
        }
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
            $remaining = $limit;
            $lastId = $fromId ? $fromId - 1 : 0;

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

                        $this->line(sprintf('[%s:%s] %s', $table, $row->id, $result['message']));
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
            ['Facilities', $this->legacyTableExists('tbl_providers') ? DB::connection(self::LEGACY_CONNECTION)->table('tbl_providers')->count() : 0],
            ['Admin users', $this->legacyTableExists('users') ? DB::connection(self::LEGACY_CONNECTION)->table('users')->where('user_role_id', 1)->count() : 0],
        ];

        $this->info('Dry-run reference data counts. Array-backed reference records and legacy table records were not written.');
        $this->table(['Dataset', 'Rows'], $rows);
    }

    /**
     * @param array<int, string> $enrolleeTables
     */
    private function schemaReady(bool $runReference, bool $runPremiumPinsOrInvoices, array $enrolleeTables): bool
    {
        $requiredNewTables = $runReference
            ? ['lgas', 'wards', 'funding_types', 'benefactors', 'vulnerable_groups', 'facilities', 'banks', 'account_details', 'users', 'staff', 'roles', 'role_user']
            : [];

        if ($enrolleeTables) {
            $requiredNewTables = array_merge($requiredNewTables, [
                'enrollees',
                'funding_types',
                'benefactors',
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

        foreach (array_unique($requiredNewTables) as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Required new-system table [{$table}] is missing. Run php artisan migrate before legacy:migrate.");
                return false;
            }
        }

        foreach ($runReference ? ['lga', 'ward', 'tbl_providers'] : [] as $table) {
            if (!$this->legacyTableExists($table)) {
                $this->error("Required legacy table [{$table}] is missing on the " . self::LEGACY_CONNECTION . ' connection.');
                return false;
            }
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

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function selectedEnrolleeTables(string $source): array
    {
        $formalTable = $this->firstExistingLegacyTable(['tbl_enrolee_formal2', 'tbl_enrolee_formal']) ?? 'tbl_enrolee_formal';

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
