<?php

namespace Database\Seeders;

use App\Models\BenefitPackage;
use App\Models\EnrolleeCategory;
use App\Models\InsuranceProgramme;
use App\Models\PremiumPlan;
use App\Models\VulnerableGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InsuranceProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedVulnerableGroups();

        $standardPackage = $this->standardPackage();

        foreach ($this->programmes() as $programmeCode => $programmeData) {
            $programme = InsuranceProgramme::updateOrCreate(
                ['code' => $programmeCode],
                [
                    'name' => $programmeData['name'],
                    'status' => 'active',
                ]
            );

            foreach ($programmeData['categories'] as $categoryCode => $categoryName) {
                EnrolleeCategory::updateOrCreate(
                    [
                        'insurance_programme_id' => $programme->id,
                        'code' => $categoryCode,
                    ],
                    [
                        'name' => $categoryName,
                        'status' => 'active',
                    ]
                );
            }

            $this->seedBasePremiumPlans($programme, $standardPackage);
        }
    }

    private function seedVulnerableGroups(): void
    {
        foreach ($this->vulnerableGroups() as $name) {
            $code = Str::of($name)->lower()->slug('_')->toString();

            VulnerableGroup::updateOrCreate(
                ['code' => $code],
                $this->onlyExistingColumns('vulnerable_groups', [
                    'name' => $name,
                    'status' => 1,
                ])
            );
        }
    }

    private function standardPackage(): BenefitPackage
    {
        $package = BenefitPackage::where('name', 'Standard Package')
            ->orWhereIn('code', ['standard', 'standard_package'])
            ->orderByRaw("CASE WHEN code = 'standard' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (!$package) {
            return BenefitPackage::create($this->onlyExistingColumns('benefit_packages', [
                'name' => 'Standard Package',
                'code' => 'standard',
                'description' => 'Default benefit package for standard NiCARE enrollment.',
                'status' => 1,
            ]));
        }

        $data = [
            'name' => 'Standard Package',
            'description' => 'Default benefit package for standard NiCARE enrollment.',
            'status' => 1,
        ];

        if (!$this->benefitPackageCodeExistsForAnother('standard', $package->id)) {
            $data['code'] = 'standard';
        }

        $package->forceFill($this->onlyExistingColumns('benefit_packages', $data))->save();
        $this->mergeDuplicateStandardPackages($package);

        return $package->fresh();
    }

    private function benefitPackageCodeExistsForAnother(string $code, int $packageId): bool
    {
        return BenefitPackage::where('code', $code)
            ->whereKeyNot($packageId)
            ->exists();
    }

    private function mergeDuplicateStandardPackages(BenefitPackage $canonical): void
    {
        $duplicates = BenefitPackage::where('name', 'Standard Package')
            ->whereKeyNot($canonical->id)
            ->pluck('id');

        if ($duplicates->isEmpty()) {
            return;
        }

        foreach (['premium_plans', 'premium_pins', 'premiums', 'enrollees'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'benefit_package_id')) {
                DB::table($table)
                    ->whereIn('benefit_package_id', $duplicates)
                    ->update(['benefit_package_id' => $canonical->id]);
            }
        }

        BenefitPackage::whereIn('id', $duplicates)->delete();
    }

    private function seedBasePremiumPlans(InsuranceProgramme $programme, BenefitPackage $standardPackage): void
    {
        $plans = [
            [
                'name' => $programme->name . ' Individual',
                'code' => 'individual_' . $programme->code,
                'is_family_plan' => false,
                'maximum_dependants' => 0,
            ],
            [
                'name' => $programme->name . ' Household',
                'code' => 'household_' . $programme->code,
                'is_family_plan' => true,
                'maximum_dependants' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            PremiumPlan::updateOrCreate(
                ['code' => $plan['code']],
                $this->onlyExistingColumns('premium_plans', [
                    'insurance_programme_id' => $programme->id,
                    'benefit_package_id' => $standardPackage->id,
                    'name' => $plan['name'],
                    'amount' => 0,
                    'consultant_fee' => 0,
                    'payment_required' => false,
                    'payment_gateway' => null,
                    'duration_days' => 365,
                    'has_no_expiry' => false,
                    'waiting_period_days' => 0,
                    'is_family_plan' => $plan['is_family_plan'],
                    'maximum_dependants' => $plan['maximum_dependants'],
                    'status' => 'active',
                ])
            );
        }
    }

    /**
     * @return array<string, array{name: string, categories: array<int, string>}>
     */
    private function programmes(): array
    {
        return [
            'formal_sector' => [
                'name' => 'Formal Sector',
                'categories' => [
                    'public_corporate' => 'Federal/State Civil Servants',
                    'private_corporate' => 'Private Sector Employees',
                    'armed_forces' => 'Military and Paramilitary',
                    'students' => 'Tertiary Institution Students (TISHIP)',
                ],
            ],
            'informal_sector' => [
                'name' => 'Informal Sector',
                'categories' => [
                    'individual' => 'Individual / Voluntary Contributors',
                    'family' => 'Family Plan',
                    'tiship' => 'Community-Based Health Insurance (CBHI)',
                    'organized_groups' => 'Trade Associations / Cooperatives',
                ],
            ],
            'vulnerable_groups' => [
                'name' => 'Vulnerable Groups',
                'categories' => collect($this->vulnerableGroups())
                    ->mapWithKeys(fn (string $name) => [Str::of($name)->lower()->slug('_')->toString() => $name])
                    ->all(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function vulnerableGroups(): array
    {
        return [
            'Others',
            'Female Reproductive (15-45 years)',
            'Elderly (85 and above)',
            'Children under 5yrs',
            'Normal',
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function onlyExistingColumns(string $table, array $data): array
    {
        if (!Schema::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->filter(fn ($value, string $column) => Schema::hasColumn($table, $column))
            ->all();
    }
}
