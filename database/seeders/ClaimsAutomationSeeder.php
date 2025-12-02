<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bundle;
use App\Models\CaseCategory;

/**
 * Simplified Claims Automation Seeder
 *
 * Creates bundles for the simplified claims workflow.
 * Note: Admissions now require approved referrals with validated UTNs,
 * so test admissions should be created through the proper workflow.
 */
class ClaimsAutomationSeeder extends Seeder
{
    public function run(): void
    {
        $this->createBundles();
    }

    private function createBundles(): void
    {
        // Simplified bundle structure - only essential fields
        $bundles = [
            // Obstetric Bundles
            [
                'bundle_code' => 'OBS-NVD-001',
                'bundle_name' => 'Normal Vaginal Delivery Bundle',
                'description' => 'Bundle for uncomplicated vaginal delivery',
                'icd10_code' => 'O80',
                'bundle_tariff' => 85000.00,
                'level_of_care' => 'Secondary',
            ],
            [
                'bundle_code' => 'OBS-CS-001',
                'bundle_name' => 'Cesarean Section Bundle',
                'description' => 'Bundle for cesarean section delivery',
                'icd10_code' => 'O82',
                'bundle_tariff' => 250000.00,
                'level_of_care' => 'Secondary',
            ],
            // Medical Bundles
            [
                'bundle_code' => 'MED-MAL-001',
                'bundle_name' => 'Severe Malaria Bundle',
                'description' => 'Bundle for treatment of severe malaria',
                'icd10_code' => 'B50',
                'bundle_tariff' => 45000.00,
                'level_of_care' => 'Secondary',
            ],
            [
                'bundle_code' => 'MED-TYP-001',
                'bundle_name' => 'Typhoid Fever Bundle',
                'description' => 'Bundle for treatment of typhoid fever',
                'icd10_code' => 'A01',
                'bundle_tariff' => 35000.00,
                'level_of_care' => 'Secondary',
            ],
            // Surgical Bundles
            [
                'bundle_code' => 'SUR-APP-001',
                'bundle_name' => 'Appendectomy Bundle',
                'description' => 'Bundle for appendectomy surgery',
                'icd10_code' => 'K35',
                'bundle_tariff' => 180000.00,
                'level_of_care' => 'Secondary',
            ],
            [
                'bundle_code' => 'SUR-HYS-001',
                'bundle_name' => 'Emergency Hysterectomy Bundle',
                'description' => 'Bundle for emergency hysterectomy',
                'icd10_code' => 'N80',
                'bundle_tariff' => 450000.00,
                'level_of_care' => 'Tertiary',
            ],
        ];

        $category = CaseCategory::first();

        foreach ($bundles as $bundleData) {
            $bundleData['case_category_id'] = $category?->id;
            $bundleData['status'] = true;
            $bundleData['effective_from'] = now()->subYear();
            $bundleData['effective_to'] = now()->addYears(2);

            Bundle::updateOrCreate(
                ['bundle_code' => $bundleData['bundle_code']],
                $bundleData
            );
        }

        $this->command->info('Created ' . count($bundles) . ' bundles for claims automation');
    }
}

