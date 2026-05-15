<?php

namespace App\Services\Legacy;

use App\Enums\RelationshipToPrincipal;
use App\Models\Benefactor;
use App\Models\BenefitPackage;
use App\Models\EnrolleeCategory;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LegacyEnrolleeMapper
{
    public function map(object $legacy, string $sourceTable): array
    {
        $classification = str_contains($sourceTable, 'formal')
            ? $this->classifyFormal($legacy)
            : $this->classifyInformal($legacy);

        $programme = $this->programme($classification['programme_code'], $classification['programme_name']);
        $category = $this->category($programme, $classification['category_name']);
        $fundingType = $this->fundingType($classification['funding_type']);
        $vulnerableGroup = $this->vulnerableGroup($legacy, $classification, $sourceTable);
        $benefitPackage = $this->benefitPackage($classification['benefit_package']);
        $benefactor = $this->benefactor($legacy, $classification);
        $facility = $this->facility($legacy);
        $lga = $this->lga($legacy);
        $ward = $this->ward($legacy, $lga);
        $plan = $this->plan($classification, $programme, $category, $benefitPackage, $legacy);
        $pin = $this->premiumPin($legacy);
        if ($pin?->plan) {
            $plan = $pin->plan;
        }

        $coverageStart = $this->date($legacy->cap_date_month ?? null)
            ?? $this->date($legacy->approved_date ?? null)
            ?? $this->date($legacy->enrol_date ?? null)
            ?? now();
        $coverageEnd = $this->date($legacy->date_expired ?? null) ?? $coverageStart->copy()->addYear()->subDay();
        if ($coverageEnd->lt($coverageStart)) {
            $coverageEnd = $coverageStart->copy()->addYear()->subDay();
        }

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
                'premium_pin_id' => $pin?->id,
                'benefit_package_id' => $benefitPackage->id,
                'vulnerable_group_id' => $vulnerableGroup?->id,
                'relationship_to_principal' => $this->relationship($legacy->enrolee_category ?? null),
                'facility_id' => $facility->id,
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'funding_type_id' => $fundingType->id,
                'benefactor_id' => $benefactor?->id,
                'capitation_start_date' => $this->date($legacy->cap_date_month ?? null)?->toDateString(),
                'coverage_start_date' => $coverageStart->toDateString(),
                'coverage_end_date' => $coverageEnd->toDateString(),
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
                'premium_pin_id' => $pin?->id,
                'benefit_package_id' => $benefitPackage->id,
                'facility_id' => $facility->id,
                'benefactor_id' => $benefactor?->id,
                'funding_type_id' => $fundingType->id,
                'coverage_start_date' => $coverageStart->toDateString(),
                'coverage_end_date' => $coverageEnd->toDateString(),
                'activation_source' => $classification['activation_source'],
                'status' => $coverageStatus,
                'metadata' => [
                    'source_table' => $sourceTable,
                    'legacy_id' => (int) $legacy->id,
                    'legacy_pin' => $this->string($legacy->pin ?? null),
                    'legacy_mode_of_enrolment' => $this->string($legacy->mode_of_enrolment ?? null),
                    'legacy_funding' => $this->string($legacy->funding ?? null),
                    'legacy_benefactor' => $this->string($legacy->benefactor ?? null),
                    'legacy_vulnerability_status' => $this->string($legacy->vulnerability_status ?? null),
                    'funding_source' => $fundingType->name,
                ],
            ],
            'purchase' => $classification['create_purchase'] ? [
                'premium_plan_id' => $plan->id,
                'benefactor_id' => $benefactor?->id,
                'funding_type_id' => $fundingType->id,
                'payer_type' => $classification['payer_type'],
                'payer_name' => $benefactor?->name ?? trim(($legacy->first_name ?? '') . ' ' . ($legacy->surname ?? '')) ?: 'Legacy Payer',
                'payment_method' => $classification['payment_method'],
                'payment_status' => 'confirmed',
                'payment_reference' => $this->paymentReference($sourceTable, $legacy),
                'quantity' => 1,
                'amount' => $classification['amount'],
                'paid_at' => $coverageStart->toDateTimeString(),
            ] : null,
            'flags' => [
                'missing_facility' => $facility->hcp_code === 'LEGACY-UNKNOWN',
                'missing_lga' => $lga->code === 'LEGACY',
                'missing_ward' => $ward->name === 'Unknown Legacy Ward',
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
        $programme = InsuranceProgramme::where('code', $code)->orWhere('name', $name)->first();

        if (!$programme) {
            throw new \RuntimeException("Insurance programme [{$name}] is missing. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $programme;
    }

    private function category(InsuranceProgramme $programme, string $name): EnrolleeCategory
    {
        $code = Str::of($name)->lower()->replace(['/', '-'], ' ')->slug('_')->toString();
        $category = EnrolleeCategory::where('insurance_programme_id', $programme->id)
            ->where(function ($query) use ($name, $code) {
                $query->where('code', $code)->orWhere('name', $name);
            })
            ->first();

        if (!$category) {
            throw new \RuntimeException("Enrollee category [{$name}] is missing for {$programme->name}. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $category;
    }

    private function fundingType(string $name): FundingType
    {
        $fundingType = LegacyReferenceData::fundingTypeByLegacyValue($name);
        $canonicalName = $fundingType['name'] ?? $name;
        $description = $fundingType
            ? sprintf(
                'Legacy funding code: %s; legacy short code: %s; legacy id: %s',
                $fundingType['code'],
                $fundingType['code2'],
                $fundingType['legacy_id']
            )
            : "Legacy migration funding classification: {$name}";

        return FundingType::firstOrCreate(
            ['name' => $canonicalName],
            ['description' => $description, 'status' => $fundingType['status'] ?? 1]
        );
    }

    private function vulnerableGroup(object $legacy, array $classification, string $sourceTable): ?VulnerableGroup
    {
        if (str_contains($sourceTable, 'formal') || $classification['funding_type'] === 'formal') {
            return null;
        }

        $legacyName = $this->string($legacy->vulnerability_status ?? null);
        $allowedName = $this->allowedVulnerableGroupName($legacyName);
        if (!$allowedName) {
            return null;
        }

        $code = Str::of($allowedName)->lower()->slug('_')->toString();

        return VulnerableGroup::where('name', $allowedName)->orWhere('code', $code)->first();
    }

    private function benefitPackage(string $name): BenefitPackage
    {
        return BenefitPackage::firstOrCreate(['name' => $name], ['code' => Str::of($name)->upper()->replace([' ', '(', ')'], ['_', '', ''])->toString(), 'status' => 1]);
    }

    private function plan(array $classification, InsuranceProgramme $programme, EnrolleeCategory $category, BenefitPackage $package, object $legacy): PremiumPlan
    {
        $planType = $this->isLegacyDependant($legacy) ? 'household' : 'individual';
        $code = $planType . '_' . $programme->code;

        $plan = PremiumPlan::where('code', $code)
            ->where('insurance_programme_id', $programme->id)
            ->first()
            ?: PremiumPlan::where('insurance_programme_id', $programme->id)->where('status', 'active')->orderBy('id')->first();

        if (!$plan) {
            throw new \RuntimeException("Premium plan [{$code}] is missing. Run InsuranceProgrammeSeeder before legacy migration.");
        }

        return $plan;
    }

    private function premiumPin(object $legacy): ?PremiumPin
    {
        $raw = $this->string($legacy->pin_raw ?? null)
            ?: $this->string($legacy->pin ?? null)
            ?: $this->string($legacy->premium_pin ?? null);

        if (!$raw) {
            return null;
        }

        return PremiumPin::where('pin', $raw)
            ->orWhere('serial_number', $raw)
            ->first();
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

    private function benefactor(object $legacy, array $classification): ?Benefactor
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

        return Benefactor::firstOrCreate(
            ['name' => $name],
            [
                'type' => $benefactorRef['type'] ?? ($classification['payer_type'] === 'government' ? 'government' : ($classification['payer_type'] === 'employer' ? 'employer' : 'donor')),
                'status' => 1,
            ]
        );
    }

    private function facility(object $legacy): Facility
    {
        $providerId = $legacy->provider_id ?? $legacy->p_provider_id ?? null;
        if ($providerId) {
            $provider = DB::connection('legacy_mysql')->table('tbl_providers')->where('id', $providerId)->first();
            if ($provider) {
                $facility = Facility::where('hcp_code', $provider->hcpcode)->first();
                if ($facility) {
                    return $facility;
                }
            }
        }

        $lga = $this->unknownLga();
        $ward = $this->unknownWard($lga);

        return Facility::firstOrCreate(
            ['hcp_code' => 'LEGACY-UNKNOWN'],
            ['name' => 'Unknown Legacy Facility', 'ownership' => 'Public', 'type' => 'Primary', 'lga_id' => $lga->id, 'ward_id' => $ward->id, 'status' => 1]
        );
    }

    private function lga(object $legacy): Lga
    {
        $name = $this->string($legacy->lga ?? null) ?: $this->string($legacy->lga_id ?? null);
        if ($name) {
            if (is_numeric($name)) {
                $lga = Lga::find((int) $name);
                if ($lga) {
                    return $lga;
                }
            }

            $lga = Lga::where('name', $name)->orWhere('code', $name)->first();
            if ($lga) {
                return $lga;
            }
        }

        return $this->unknownLga();
    }

    private function ward(object $legacy, Lga $lga): Ward
    {
        $name = $this->string($legacy->ward ?? null);
        if ($name) {
            if (is_numeric($name)) {
                $ward = Ward::whereKey((int) $name)->where('lga_id', $lga->id)->first()
                    ?: Ward::find((int) $name);
                if ($ward) {
                    return $ward;
                }
            }

            $ward = Ward::where('lga_id', $lga->id)->where('name', $name)->first() ?: Ward::where('name', $name)->first();
            if ($ward) {
                return $ward;
            }
        }

        return $this->unknownWard($lga);
    }

    private function unknownLga(): Lga
    {
        return Lga::firstOrCreate(['code' => 'LEGACY'], ['name' => 'Unknown Legacy LGA', 'zone' => 0, 'status' => 1]);
    }

    private function unknownWard(Lga $lga): Ward
    {
        return Ward::firstOrCreate(['name' => 'Unknown Legacy Ward', 'lga_id' => $lga->id], ['settlement_type' => 1, 'status' => 1]);
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
            return 'Retirees / Pensioners';
        }
        if (Str::contains($text, ['local government', 'lga'])) {
            return 'Local Government Employees';
        }
        if (Str::contains($text, ['company', 'private', 'ngo', 'sme'])) {
            return 'Registered Company Employees';
        }

        return 'State Civil Servants';
    }

    private function coverageStatus(object $legacy, int $waitingDays, Carbon $coverageEnd): string
    {
        $status = Str::lower((string) ($legacy->status ?? ''));
        $approval = (string) ($legacy->enrolment_approval_status ?? '');

        if (Str::contains($status, ['0', 'suspend', 'deceased', 'dead', 'inactive', 'deleted']) || $approval === '0') {
            return 'suspended';
        }
        if ($coverageEnd->isPast()) {
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

    private function string(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
