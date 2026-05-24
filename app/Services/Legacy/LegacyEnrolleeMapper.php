<?php

namespace App\Services\Legacy;

use App\Enums\RelationshipToPrincipal;
use App\Models\Benefactor;
use App\Models\BenefitPackage;
use App\Models\EnrolleeCategory;
use App\Models\EnrollmentPhase;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use App\Models\Lga;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\VulnerableGroup;
use App\Models\Ward;
use App\Support\LegacyReferenceData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyEnrolleeMapper
{
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

    /** @var array<int, int|null> */
    private array $enrollmentPhaseIds = [];

    /** @var array<string, InsuranceProgramme> */
    private array $programmes = [];

    /** @var array<string, EnrolleeCategory> */
    private array $categories = [];

    /** @var array<string, BenefitPackage> */
    private array $benefitPackages = [];

    /** @var array<string, PremiumPlan> */
    private array $premiumPlans = [];

    /** @var array<string, int|null> */
    private array $vulnerableGroupIds = [];

    /** @var array<string, int|null> */
    private array $premiumPinIds = [];

    /** @var array<string, int> */
    private array $fallbackIds = [];

    /** @var array<int, int|null> */
    private array $facilityIds = [];

    /** @var array<int, bool> */
    private array $ensuredFundingTypeIds = [];

    /** @var array<int, bool> */
    private array $ensuredBenefactorIds = [];

    public function map(object $legacy, string $sourceTable): array
    {
        $classification = str_contains($sourceTable, 'formal')
            ? $this->classifyFormal($legacy)
            : $this->classifyInformal($legacy);

        $programme = $this->programme($classification['programme_code'], $classification['programme_name']);
        $category = $this->category($programme, $this->categoryName($legacy, $classification, $sourceTable));
        $fundingTypeId = $this->fundingTypeId($classification['funding_type']);
        $fundingTypeName = $this->fundingTypeName($classification['funding_type']);
        $vulnerableGroupId = $this->vulnerableGroupId($legacy, $classification, $sourceTable);
        $benefitPackage = $this->benefitPackage($classification['benefit_package']);
        $benefactorId = $this->benefactorId($legacy, $classification);
        $benefactorName = $this->benefactorName($legacy, $classification);
        $enrollmentPhaseId = $this->enrollmentPhaseId($legacy);
        $lgaId = $this->lgaId($legacy);
        $wardId = $this->wardId($legacy, $lgaId);
        $facilityId = $this->facilityId($legacy, $lgaId, $wardId);
        $premiumPinId = $this->premiumPinId($legacy);
        $plan = $this->plan($classification, $programme, $category, $benefitPackage, $legacy);

        $coverageStart = $this->date($legacy->cap_date_month ?? null)
            ?? $this->date($legacy->approved_date ?? null)
            ?? $this->date($legacy->enrol_date ?? null)
            ?? now();
        $coverageEnd = $this->date($legacy->date_expired ?? null);

        $coverageStatus = $this->coverageStatus($legacy, $classification['waiting_period_days'], $coverageEnd);
        $enrolleeStatus = match ($coverageStatus) {
            'active', 'pending_waiting_period' => 1,
            'expired' => 4,
            default => 3,
        };
        $legacyEnrolmentNumber = $this->string($legacy->enrolment_number ?? null) ?: $this->fallbackShin($sourceTable, $legacy);

        return [
            'source_table' => $sourceTable,
            'legacy_id' => (int) $legacy->id,
            'legacy_enrolment_number' => $legacyEnrolmentNumber,
            'dedupe' => [
                'shin' => $legacyEnrolmentNumber,
                'nin' => $this->string($legacy->nin ?? null) ?: $this->string($legacy->national_identification_number ?? null),
                'phone' => $this->string($legacy->phone_number ?? null),
                'first_name' => $this->string($legacy->first_name ?? null),
                'last_name' => $this->string($legacy->surname ?? null),
                'date_of_birth' => $this->date($legacy->date_of_birth ?? null)?->toDateString(),
            ],
            'enrollee' => [
                'enrollee_id' => $legacyEnrolmentNumber,
                'legacy_id' => (int) $legacy->id,
                'legacy_enrollee_id' => $legacyEnrolmentNumber,
                'nin' => $this->string($legacy->nin ?? null) ?: $this->string($legacy->national_identification_number ?? null),
                'first_name' => $this->string($legacy->first_name ?? null) ?: 'Unknown',
                'last_name' => $this->string($legacy->surname ?? null) ?: 'Legacy',
                'middle_name' => $this->string($legacy->other_name ?? null),
                'sex' => $this->sex($legacy->sex ?? null),
                'marital_status' => $this->maritalStatus($legacy->marital_status ?? null),
                'date_of_birth' => $this->date($legacy->date_of_birth ?? null)?->toDateString(),
                'image_url' => $this->string($legacy->enrolee_image_link ?? null),
                'email' => $this->string($legacy->email_address ?? null),
                'phone' => $this->string($legacy->phone_number ?? null),
                'address' => $this->string($legacy->address ?? null) ?: $this->string($legacy->community ?? null),
                'village' => $this->string($legacy->village ?? null) ?: $this->string($legacy->community ?? null),
                'disability' => $this->string($legacy->disability ?? null),
                'pregnant' => (int) ($legacy->pregnant ?? 0),
                'insurance_programme_id' => $programme->id,
                'enrollee_category_id' => $category->id,
                'premium_plan_id' => $plan->id,
                'premium_pin_id' => $premiumPinId,
                'benefit_package_id' => $benefitPackage->id,
                'vulnerable_group_id' => $vulnerableGroupId,
                'relationship_to_principal' => $this->relationship($legacy->enrolee_category ?? null),
                'facility_id' => $facilityId,
                'lga_id' => $lgaId,
                'ward_id' => $wardId,
                'funding_type_id' => $fundingTypeId,
                'benefactor_id' => $benefactorId,
                'enrollment_phase_id' => $enrollmentPhaseId,
                'capitation_start_date' => $this->date($legacy->cap_date_month ?? null)?->toDateString(),
                'coverage_start_date' => $coverageStart->toDateString(),
                'coverage_end_date' => $coverageEnd?->toDateString(),
                'approval_date' => $this->date($legacy->approved_date ?? null)?->toDateTimeString(),
                'enrollment_date' => $this->date($legacy->enrol_date ?? null)?->toDateTimeString(),
                'nok_name' => $this->string($legacy->nok_name ?? null),
                'nok_phone_number' => $this->string($legacy->nok_phone_number ?? null),
                'nok_address' => $this->string($legacy->nok_address ?? null),
                'nok_relationship' => $this->string($legacy->nok_relationship ?? null),
                'occupation' => $this->string($legacy->occupation ?? null),
                'cno' => $this->string($legacy->cno ?? null),
                'dfa' => $this->date($legacy->date_of_first_appointment ?? null)?->toDateString(),
                'dpa' => $this->date($legacy->date_of_retirement ?? null)?->toDateString(),
                'basic_salary' => is_numeric($legacy->basic_salary ?? null) ? $legacy->basic_salary : null,
                'station' => $this->string($legacy->station ?? null),
                'salary_scheme' => $this->string($legacy->salary_scheme ?? null),
                'status' => $enrolleeStatus,
            ],
            'coverage' => [
                'insurance_programme_id' => $programme->id,
                'enrollee_category_id' => $category->id,
                'premium_plan_id' => $plan->id,
                'premium_pin_id' => $premiumPinId,
                'benefit_package_id' => $benefitPackage->id,
                'facility_id' => $facilityId,
                'benefactor_id' => $benefactorId,
                'funding_type_id' => $fundingTypeId,
                'coverage_start_date' => $coverageStart->toDateString(),
                'coverage_end_date' => $coverageEnd?->toDateString(),
                'activation_source' => $classification['activation_source'],
                'status' => $coverageStatus,
                'metadata' => [
                    'source_table' => $sourceTable,
                    'legacy_id' => (int) $legacy->id,
                    'legacy_pin' => $this->string($legacy->pin ?? null),
                    'legacy_mode_of_enrolment' => $this->string($legacy->mode_of_enrolment ?? null),
                    'legacy_funding' => $this->string($legacy->funding ?? null),
                    'legacy_benefactor' => $this->string($legacy->benefactor ?? null),
                    'legacy_enrollment_phase_id' => $this->string($legacy->tracking ?? null),
                    'enrollment_phase_id' => $enrollmentPhaseId,
                    'legacy_vulnerability_status' => $this->string($legacy->vulnerability_status ?? null),
                    'funding_source' => $fundingTypeName,
                ],
            ],
            'purchase' => $classification['create_purchase'] ? [
                'premium_plan_id' => $plan->id,
                'benefactor_id' => $benefactorId,
                'funding_type_id' => $fundingTypeId,
                'payer_type' => $classification['payer_type'],
                'payer_name' => $benefactorName ?? trim(($legacy->first_name ?? '') . ' ' . ($legacy->surname ?? '')) ?: 'Legacy Payer',
                'payment_method' => $classification['payment_method'],
                'payment_status' => 'confirmed',
                'payment_reference' => $this->paymentReference($sourceTable, $legacy),
                'quantity' => 1,
                'amount' => $classification['amount'],
                'paid_at' => $coverageStart->toDateTimeString(),
            ] : null,
            'flags' => [
                'missing_facility' => !$this->directLegacyId($legacy, 'provider_id', 'p_provider_id', 'facility_id'),
                'missing_lga' => !$this->directLegacyId($legacy, 'lga_id', 'lga'),
                'missing_ward' => !$this->directLegacyId($legacy, 'ward_id', 'ward'),
                'missing_funding_type' => $classification['missing_funding_type'],
            ],
        ];
    }

    private function classifyFormal(object $legacy): array
    {
        return [
            'programme_code' => 'formal_sector',
            'programme_name' => 'Formal Sector',
            'category_name' => 'Formal',
            'funding_type' => 'formal',
            'benefit_package' => 'Standard Package',
            'activation_source' => 'payroll',
            'waiting_period_days' => 0,
            'create_purchase' => true,
            'payer_type' => 'employer',
            'payment_method' => 'payroll_deduction',
            'amount' => is_numeric($legacy->basic_salary ?? null) ? max(((float) $legacy->basic_salary) * 0.05, 0) : 0,
            'missing_funding_type' => false,
        ];
    }

    private function classifyInformal(object $legacy): array
    {
        $mode = Str::lower((string) ($legacy->mode_of_enrolment ?? ''));
        $legacyCategory = Str::lower((string) ($this->legacyCategoryValue($legacy) ?? ''));
        $funding = $this->string($legacy->funding ?? null);
        $benefactorValue = $this->string($legacy->benefactor ?? null);
        $benefactorRef = LegacyReferenceData::benefactorByLegacyValue($benefactorValue);
        $fundingRef = $funding !== null ? LegacyReferenceData::fundingTypeByLegacyValue($funding) : null;
        $fundingRef ??= !empty($benefactorRef['funding'])
            ? LegacyReferenceData::fundingTypeByLegacyValue($benefactorRef['funding'])
            : null;
        $fundingRef ??= isset($benefactorRef['funding_type_legacy_id'])
            ? LegacyReferenceData::fundingTypeByLegacyValue($benefactorRef['funding_type_legacy_id'])
            : null;
        $fundingRef ??= LegacyReferenceData::fundingTypeByLegacyValue($funding);

        $fundingCode = $fundingRef['code2'] ?? null;
        $benefactorName = Str::lower((string) ($benefactorRef['name'] ?? $benefactorValue ?? ''));

        if ($fundingCode === 'formal' || ($benefactorRef['legacy_id'] ?? null) === 100) {
            return $this->classification('formal_sector', 'Formal Sector', 'Formal', 'formal', 'Standard Package', 'payroll', true, 'employer', 'payroll_deduction', 0, false);
        }

        if (Str::contains($legacyCategory, 'formal') && Str::contains($mode, 'premium')) {
            return $this->classification('formal_sector', 'Formal Sector', 'Formal', 'formal', 'Standard Package', 'payroll', true, 'employer', 'payroll_deduction', 0, false);
        }

        if (Str::contains($legacyCategory, 'informal') && Str::contains($mode, 'huwe')) {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', 'bhcpf', 'Standard Package', 'subsidy', false, 'government', 'government_subsidy', 0, $fundingRef === null && $funding !== null);
        }

        if (Str::contains($legacyCategory, 'informal') && Str::contains($mode, 'premium')) {
            return $this->classification('informal_sector', 'Informal Sector', 'Informal', 'premium', 'Standard Package', 'payment', true, 'individual', 'cash', 0, false);
        }

        if ($fundingCode === 'bhcpf' || Str::contains($mode . ' ' . ($legacy->BHCPF_number ?? ''), ['bhcpf', 'huwe', 'bmp'])) {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', 'bhcpf', 'Standard Package', 'subsidy', false, 'government', 'government_subsidy', 0, $fundingRef === null && $funding !== null);
        }

        if ($fundingCode === 'cf') {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', 'cf', 'Standard Package', 'subsidy', false, 'government', 'government_subsidy', 0, false);
        }

        if ($fundingCode === 'gac') {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', 'gac', 'Standard Package', 'subsidy', false, 'government', 'government_subsidy', 0, false);
        }

        if ($fundingCode === 'unicef' || Str::contains($benefactorName, ['unicef'])) {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', 'unicef', 'Standard Package', 'donor', false, 'donor', 'donor_sponsorship', 0, false);
        }

        if (!empty($legacy->pin) || $fundingCode === 'premium' || Str::contains($mode, ['premium', 'pin', 'self'])) {
            return $this->classification('informal_sector', 'Informal Sector', 'Informal', 'premium', 'Standard Package', 'payment', true, 'individual', 'cash', 0, false);
        }

        if ($benefactorRef && !in_array(Str::lower($benefactorRef['name']), ['self'], true)) {
            return $this->classification('vulnerable_groups', 'Vulnerable Groups', 'Vulnerable', $fundingRef['code2'] ?? 'premium', 'Standard Package', 'donor', true, 'donor', 'donor_sponsorship', 0, $fundingRef === null);
        }

        return $this->classification('informal_sector', 'Informal Sector', 'Informal', $fundingRef['code2'] ?? 'premium', 'Standard Package', 'payment', false, 'individual', 'cash', 0, $fundingRef === null && $funding !== null);
    }

    private function classification(string $programmeCode, string $programmeName, string $category, string $funding, string $package, string $activation, bool $purchase, string $payerType, string $paymentMethod, float $amount = 0, bool $missingFundingType = false): array
    {
        return [
            'programme_code' => $programmeCode,
            'programme_name' => $programmeName,
            'category_name' => $category,
            'funding_type' => $funding,
            'benefit_package' => $package,
            'activation_source' => $activation,
            'waiting_period_days' => 0,
            'create_purchase' => $purchase,
            'payer_type' => $payerType,
            'payment_method' => $paymentMethod,
            'amount' => $amount,
            'missing_funding_type' => $missingFundingType,
        ];
    }

    private function programme(string $code, string $name): InsuranceProgramme
    {
        if (isset($this->programmes[$code])) {
            return $this->programmes[$code];
        }

        $programme = InsuranceProgramme::where('code', $code)->orWhere('name', $name)->first();

        if (!$programme) {
            throw new \RuntimeException("Insurance programme [{$name}] is missing. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $this->programmes[$code] = $programme;
    }

    private function category(InsuranceProgramme $programme, string $name): EnrolleeCategory
    {
        $cacheKey = $programme->id . ':' . $name;
        if (isset($this->categories[$cacheKey])) {
            return $this->categories[$cacheKey];
        }

        $code = Str::of($name)->lower()->replace(['/', '-'], ' ')->slug('_')->toString();
        $category = EnrolleeCategory::where('insurance_programme_id', $programme->id)
            ->where(function ($query) use ($name, $code) {
                $query->where('code', $code)->orWhere('name', $name);
            })
            ->first();

        if (!$category) {
            throw new \RuntimeException("Enrollee category [{$name}] is missing for {$programme->name}. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $this->categories[$cacheKey] = $category;
    }

    private function categoryName(object $legacy, array $classification, string $sourceTable): string
    {
        if ($classification['programme_code'] === 'vulnerable_groups') {
            return $this->allowedVulnerableGroupName($this->string($legacy->vulnerability_status ?? null)) ?? 'Others';
        }

        if ($classification['programme_code'] === 'formal_sector') {
            return $this->formalCategory($legacy);
        }

        if ($classification['programme_code'] === 'informal_sector') {
            $legacyCategory = Str::lower((string) ($this->legacyCategoryValue($legacy) ?? ''));
            $mode = Str::lower((string) ($legacy->mode_of_enrolment ?? ''));

            if (Str::contains($legacyCategory . ' ' . $mode, ['family', 'household'])) {
                return 'Family Plan';
            }

            if (Str::contains($legacyCategory . ' ' . $mode, ['community', 'cbhi'])) {
                return 'Community-Based Health Insurance (CBHI)';
            }

            if (Str::contains($legacyCategory . ' ' . $mode, ['cooperative', 'association', 'organized', 'organised', 'group'])) {
                return 'Trade Associations / Cooperatives';
            }

            return 'Individual / Voluntary Contributors';
        }

        return $classification['category_name'];
    }

    private function fundingTypeId(string $name): int
    {
        $fundingType = LegacyReferenceData::fundingTypeByLegacyValue($name);
        $code = $fundingType['code2'] ?? Str::lower($name);

        if (isset(self::FUNDING_TYPE_ID_MAP[$code])) {
            $id = self::FUNDING_TYPE_ID_MAP[$code];
            $this->ensureFundingTypeId($id, $fundingType, $name);

            return $id;
        }

        return FundingType::firstOrCreate(
            ['name' => $fundingType['name'] ?? $name],
            ['description' => "Legacy migration funding classification: {$name}", 'status' => $fundingType['status'] ?? 1]
        )->id;
    }

    private function fundingTypeName(string $name): string
    {
        return LegacyReferenceData::fundingTypeByLegacyValue($name)['name'] ?? $name;
    }

    private function vulnerableGroupId(object $legacy, array $classification, string $sourceTable): ?int
    {
        if (str_contains($sourceTable, 'formal') || $classification['funding_type'] === 'formal') {
            return null;
        }

        $legacyName = $this->string($legacy->vulnerability_status ?? null);
        $allowedName = $this->allowedVulnerableGroupName($legacyName);
        if (!$allowedName) {
            return null;
        }

        if (array_key_exists($allowedName, $this->vulnerableGroupIds)) {
            return $this->vulnerableGroupIds[$allowedName];
        }

        $code = Str::of($allowedName)->lower()->slug('_')->toString();

        return $this->vulnerableGroupIds[$allowedName] = VulnerableGroup::where('name', $allowedName)
            ->orWhere('code', $code)
            ->value('id');
    }

    private function benefitPackage(string $name): BenefitPackage
    {
        if (isset($this->benefitPackages[$name])) {
            return $this->benefitPackages[$name];
        }

        return $this->benefitPackages[$name] = BenefitPackage::firstOrCreate(
            ['name' => $name],
            ['code' => Str::of($name)->upper()->replace([' ', '(', ')'], ['_', '', ''])->toString(), 'status' => 1]
        );
    }

    private function plan(array $classification, InsuranceProgramme $programme, EnrolleeCategory $category, BenefitPackage $package, object $legacy): PremiumPlan
    {
        $planType = $this->isLegacyDependant($legacy) ? 'household' : 'individual';
        $code = $planType . '_' . $programme->code;
        $cacheKey = $programme->id . ':' . $code;

        if (isset($this->premiumPlans[$cacheKey])) {
            return $this->premiumPlans[$cacheKey];
        }

        $plan = PremiumPlan::where('code', $code)
            ->where('insurance_programme_id', $programme->id)
            ->first()
            ?: PremiumPlan::where('insurance_programme_id', $programme->id)->where('status', 'active')->orderBy('id')->first();

        if (!$plan) {
            throw new \RuntimeException("Premium plan [{$code}] is missing. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $this->premiumPlans[$cacheKey] = $plan;
    }

    private function legacyCategoryValue(object $legacy): ?string
    {
        return $this->string($legacy->enrollee_category ?? null)
            ?: $this->string($legacy->enrolee_category ?? null)
            ?: $this->string($legacy->enrollee_type ?? null);
    }

    private function allowedVulnerableGroupName(?string $legacyName): ?string
    {
        if (!$legacyName) {
            return null;
        }

        $allowed = [
            'others' => 'Others',
            'female reproductive (15-45 years)' => 'Female Reproductive (15-45 years)',
            'elderly (85 and above)' => 'Elderly (85 and above)',
            'children under 5yrs' => 'Children under 5yrs',
            'normal' => 'Normal',
        ];

        return $allowed[Str::lower(trim($legacyName))] ?? null;
    }

    private function isLegacyDependant(object $legacy): bool
    {
        $category = Str::lower((string) ($legacy->enrolee_category ?? $legacy->relationship_to_principal ?? ''));

        return in_array($category, ['spouse', 'child', 'other'], true);
    }

    private function benefactorId(object $legacy, array $classification): ?int
    {
        $benefactorRef = LegacyReferenceData::benefactorByLegacyValue($legacy->benefactor ?? null)
            ?: LegacyReferenceData::benefactorByFundingValue($legacy->funding ?? null);

        $legacyId = $benefactorRef['legacy_id'] ?? $this->positiveInteger($legacy->benefactor ?? null);
        if ($legacyId && isset(self::BENEFACTOR_ID_MAP[$legacyId])) {
            $id = self::BENEFACTOR_ID_MAP[$legacyId];
            $this->ensureBenefactorId($id, $benefactorRef, $this->benefactorName($legacy, $classification));

            return $id;
        }

        if ($classification['activation_source'] === 'payroll') {
            $id = self::BENEFACTOR_ID_MAP[100];
            $this->ensureBenefactorId($id, LegacyReferenceData::benefactorByLegacyValue(100), 'Formal Sector');

            return $id;
        }

        return null;
    }

    private function benefactorName(object $legacy, array $classification): ?string
    {
        $benefactorRef = LegacyReferenceData::benefactorByLegacyValue($legacy->benefactor ?? null)
            ?: LegacyReferenceData::benefactorByFundingValue($legacy->funding ?? null);

        $name = $benefactorRef['name'] ?? (
            $this->string($legacy->benefactor ?? null)
            ?: $this->string($legacy->employer ?? null)
            ?: $this->string($legacy->ministry ?? null)
        );

        if (!$name && in_array($classification['payer_type'], ['government'], true)) {
            $name = 'Niger State Government';
        }
        if (!$name && $classification['activation_source'] === 'payroll') {
            $name = 'Legacy Formal Sector Employer';
        }

        if (!$name) {
            return null;
        }

        return $name;
    }

    private function enrollmentPhaseId(object $legacy): ?int
    {
        $tracking = $this->positiveInteger($legacy->tracking ?? null);
        if (!$tracking) {
            return null;
        }

        if (array_key_exists($tracking, $this->enrollmentPhaseIds)) {
            return $this->enrollmentPhaseIds[$tracking];
        }

        $phaseId = Schema::hasColumn('enrollment_phases', 'legacy_id')
            ? EnrollmentPhase::where(function ($query) use ($tracking): void {
                $query->where('legacy_id', $tracking)->orWhere('id', $tracking);
            })->value('id')
            : EnrollmentPhase::whereKey($tracking)->value('id');

        $this->enrollmentPhaseIds[$tracking] = $phaseId;

        return $phaseId;
    }

    private function facilityId(object $legacy, int $lgaId, int $wardId): int
    {
        $providerId = $this->directLegacyId($legacy, 'provider_id', 'p_provider_id', 'facility_id');
        if ($providerId && $this->facilityExists($providerId)) {
            return $providerId;
        }

        return $this->fallbackIds['facility'] ??= Facility::firstOrCreate(
            ['hcp_code' => 'LEGACY-UNKNOWN'],
            ['name' => 'Unknown Legacy Facility', 'ownership' => 'Public', 'type' => 'Primary', 'lga_id' => $lgaId, 'ward_id' => $wardId, 'status' => 1]
        )->id;
    }

    private function facilityExists(int $providerId): bool
    {
        if (array_key_exists($providerId, $this->facilityIds)) {
            return $this->facilityIds[$providerId] !== null;
        }

        $this->facilityIds[$providerId] = Facility::whereKey($providerId)->value('id');

        return $this->facilityIds[$providerId] !== null;
    }

    private function lgaId(object $legacy): int
    {
        $lgaId = $this->directLegacyId($legacy, 'lga_id', 'lga');
        if ($lgaId) {
            return $lgaId;
        }

        return $this->unknownLgaId();
    }

    private function wardId(object $legacy, int $lgaId): int
    {
        $wardId = $this->directLegacyId($legacy, 'ward_id', 'ward');
        if ($wardId) {
            return $wardId;
        }

        return $this->unknownWardId($lgaId);
    }

    private function premiumPinId(object $legacy): ?int
    {
        $directId = $this->directLegacyId($legacy, 'premium_pin_id', 'pin_id', 'pin_inven_id', 'pin_inventory_id');
        if ($directId) {
            return $directId;
        }

        $raw = $this->string($legacy->pin_raw ?? null)
            ?: $this->string($legacy->pin ?? null)
            ?: $this->string($legacy->premium_pin ?? null);

        if (!$raw) {
            return null;
        }

        if (array_key_exists($raw, $this->premiumPinIds)) {
            return $this->premiumPinIds[$raw];
        }

        return $this->premiumPinIds[$raw] = PremiumPin::where('pin', $raw)
            ->orWhere('serial_number', $raw)
            ->value('id');
    }

    /**
     * The main reference migration seeds these IDs first. This fallback keeps
     * focused tests and partial reruns from failing on FK checks.
     *
     * @param array<string, mixed>|null $reference
     */
    private function ensureFundingTypeId(int $id, ?array $reference, string $fallbackName): void
    {
        if (isset($this->ensuredFundingTypeIds[$id])) {
            return;
        }

        if (!FundingType::whereKey($id)->exists()) {
            FundingType::unguarded(function () use ($id, $reference, $fallbackName): void {
                $fundingType = new FundingType(['id' => $id]);
                $fundingType->forceFill([
                    'name' => $reference['name'] ?? $fallbackName,
                    'description' => isset($reference['code'], $reference['code2'], $reference['legacy_id'])
                        ? sprintf(
                            'Legacy funding code: %s; legacy short code: %s; legacy id: %s',
                            $reference['code'],
                            $reference['code2'],
                            $reference['legacy_id']
                        )
                        : "Legacy migration funding classification: {$fallbackName}",
                    'status' => $reference['status'] ?? 1,
                ])->save();
            });
        }

        $this->ensuredFundingTypeIds[$id] = true;
    }

    /**
     * @param array<string, mixed>|null $reference
     */
    private function ensureBenefactorId(int $id, ?array $reference, ?string $fallbackName): void
    {
        if (isset($this->ensuredBenefactorIds[$id])) {
            return;
        }

        if (!Benefactor::whereKey($id)->exists()) {
            Benefactor::unguarded(function () use ($id, $reference, $fallbackName): void {
                $benefactor = new Benefactor(['id' => $id]);
                $benefactor->forceFill([
                    'name' => $reference['name'] ?? $fallbackName ?? 'Legacy Benefactor ' . $id,
                    'type' => $reference['type'] ?? 'donor',
                    'status' => $reference['status'] ?? 1,
                ])->save();
            });
        }

        $this->ensuredBenefactorIds[$id] = true;
    }

    private function unknownLgaId(): int
    {
        return $this->fallbackIds['lga'] ??= Lga::firstOrCreate(
            ['code' => 'LEGACY'],
            ['name' => 'Unknown Legacy LGA', 'zone' => 0, 'status' => 1]
        )->id;
    }

    private function unknownWardId(int $lgaId): int
    {
        return $this->fallbackIds['ward:' . $lgaId] ??= Ward::firstOrCreate(
            ['name' => 'Unknown Legacy Ward', 'lga_id' => $lgaId],
            ['settlement_type' => 1, 'status' => 1]
        )->id;
    }

    private function formalCategory(object $legacy): string
    {
        $text = Str::lower(implode(' ', array_filter([
            $legacy->enrolee_category ?? null,
            $legacy->employer ?? null,
            $legacy->ministry ?? null,
            $legacy->salary_scheme ?? null,
        ])));

        if (Str::contains($text, ['retire', 'pension'])) {
            return 'Federal/State Civil Servants';
        }
        if (Str::contains($text, ['local government', 'lga'])) {
            return 'Federal/State Civil Servants';
        }
        if (Str::contains($text, ['company', 'private', 'ngo', 'sme'])) {
            return 'Private Sector Employees';
        }

        if (Str::contains($text, ['military', 'paramilitary', 'army', 'police', 'civil defence', 'customs', 'immigration'])) {
            return 'Military and Paramilitary';
        }

        if (Str::contains($text, ['student', 'tiship', 'tertiary'])) {
            return 'Tertiary Institution Students (TISHIP)';
        }

        return 'Federal/State Civil Servants';
    }

    private function coverageStatus(object $legacy, int $waitingDays, ?Carbon $coverageEnd): string
    {
        $status = Str::lower((string) ($legacy->status ?? ''));
        $approval = (string) ($legacy->enrolment_approval_status ?? '');

        if (Str::contains($status, ['0', 'suspend', 'deceased', 'dead', 'inactive', 'deleted']) || $approval === '0') {
            return 'suspended';
        }
        if ($coverageEnd !== null && $coverageEnd->isPast()) {
            return 'expired';
        }
        if ($waitingDays > 0) {
            return 'pending_waiting_period';
        }

        return 'active';
    }

    private function paymentReference(string $sourceTable, object $legacy): string
    {
        return 'LEGACY-' . strtoupper(str_replace('tbl_enrolee_', '', $sourceTable)) . '-' . $legacy->id . '-' . ($legacy->pin ?: 'NO-PIN');
    }

    private function fallbackShin(string $sourceTable, object $legacy): string
    {
        return 'LEG-' . (str_contains($sourceTable, 'formal') ? 'F' : 'I') . '-' . $legacy->id;
    }

    private function sex(?string $sex): ?int
    {
        return match (Str::lower((string) $sex)) {
            'male', 'm', '1' => 1,
            'female', 'f', '2' => 2,
            default => null,
        };
    }

    private function maritalStatus(?string $status): ?int
    {
        return match (Str::lower((string) $status)) {
            'single', '1' => 1,
            'married', '2' => 2,
            'divorced', '3' => 3,
            'widowed', 'widow', '4' => 4,
            default => null,
        };
    }

    private function relationship(?string $category): int
    {
        return match (Str::lower((string) $category)) {
            'spouse' => RelationshipToPrincipal::SPOUSE->value,
            'child' => RelationshipToPrincipal::CHILD->value,
            'other' => RelationshipToPrincipal::OTHER->value,
            default => RelationshipToPrincipal::PRINCIPAL->value,
        };
    }

    private function date(mixed $value): ?Carbon
    {
        if (!$value || in_array((string) $value, ['0000-00-00', '0000-00-00 00:00:00'], true)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        $value = $this->string($value);
        if (!$value || !ctype_digit($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function directLegacyId(object $legacy, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            if (!property_exists($legacy, $key)) {
                continue;
            }

            $id = $this->positiveInteger($legacy->{$key});
            if ($id) {
                return $id;
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
