<?php

namespace Database\Seeders;

use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serviceTypes = [
            [
                'name' => 'Professional Fees',
                'description' => 'Fees for professional medical services',
                'status' => true,
            ],
            [
                'name' => 'Hospital Stay',
                'description' => 'Accommodation and hospital stay charges',
                'status' => true,
            ],
            [
                'name' => 'Laboratory Investigations',
                'description' => 'Laboratory tests and investigations',
                'status' => true,
            ],
            [
                'name' => 'Other Investigations',
                'description' => 'Other diagnostic investigations (imaging, etc.)',
                'status' => true,
            ],
            [
                'name' => 'Other Fees',
                'description' => 'Miscellaneous fees and charges',
                'status' => true,
            ],
        ];

        foreach ($serviceTypes as $serviceType) {
            ServiceType::updateOrCreate(
                ['name' => $serviceType['name']],
                $serviceType
            );
        }
    }
}

