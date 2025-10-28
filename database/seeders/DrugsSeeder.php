<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Drug;

class DrugsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sample drugs data
        $drugs = [
            [
                'nicare_code' => 'NGSCHA/DRUG/001',
                'drug_name' => 'Paracetamol',
                'drug_dosage_form' => 'Tablet',
                'drug_strength' => '500mg',
                'drug_presentation' => 'Tab',
                'drug_unit_price' => 50.00,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHA/DRUG/002',
                'drug_name' => 'Amoxicillin',
                'drug_dosage_form' => 'Capsule',
                'drug_strength' => '250mg',
                'drug_presentation' => 'Cap',
                'drug_unit_price' => 75.00,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHA/DRUG/003',
                'drug_name' => 'Ibuprofen',
                'drug_dosage_form' => 'Tablet',
                'drug_strength' => '400mg',
                'drug_presentation' => 'Tab',
                'drug_unit_price' => 60.00,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHA/DRUG/004',
                'drug_name' => 'Metformin',
                'drug_dosage_form' => 'Tablet',
                'drug_strength' => '500mg',
                'drug_presentation' => 'Tab',
                'drug_unit_price' => 120.00,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHA/DRUG/005',
                'drug_name' => 'Omeprazole',
                'drug_dosage_form' => 'Capsule',
                'drug_strength' => '20mg',
                'drug_presentation' => 'Cap',
                'drug_unit_price' => 150.00,
                'status' => true,
            ]
        ];

        foreach ($drugs as $drug) {
            Drug::create($drug);
        }

        // Sample services data
        $services = [
            [
                'nicare_code' => 'NGSCHS/GCons/P/0001',
                'service_description' => 'General Consultation',
                'level_of_care' => 'Primary',
                'price' => 1000.00,
                'group' => 'GENERAL CONSULTATION',
                'pa_required' => false,
                'referable' => true,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHS/Paed/S/0001',
                'service_description' => 'Paediatric Consultation',
                'level_of_care' => 'Secondary',
                'price' => 2000.00,
                'group' => 'PAEDIATRICS',
                'pa_required' => true,
                'referable' => true,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHS/IM/T/0001',
                'service_description' => 'Internal Medicine Consultation',
                'level_of_care' => 'Tertiary',
                'price' => 3000.00,
                'group' => 'INTERNAL MEDICINE (PRV)',
                'pa_required' => true,
                'referable' => true,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHS/HE/P/0001',
                'service_description' => 'Health Education Session',
                'level_of_care' => 'Primary',
                'price' => 500.00,
                'group' => 'HEALTH EDUCATION',
                'pa_required' => false,
                'referable' => false,
                'status' => true,
            ],
            [
                'nicare_code' => 'NGSCHS/Lab/S/0001',
                'service_description' => 'Complete Blood Count',
                'level_of_care' => 'Secondary',
                'price' => 1500.00,
                'group' => 'LABORATORY',
                'pa_required' => false,
                'referable' => true,
                'status' => true,
            ]
        ];

        foreach ($services as $service) {
            \App\Models\CaseRecord::create($service);
        }
    }
}
