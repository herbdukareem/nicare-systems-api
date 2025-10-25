<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Professional Fees',
                'description' => 'Professional consultation and service fees',
                'status' => true
            ],
            [
                'name' => 'Hospital Stay',
                'description' => 'Accommodation and hospital stay charges',
                'status' => true
            ],
            [
                'name' => 'Diagnostic Services',
                'description' => 'Laboratory tests, imaging, and diagnostic procedures',
                'status' => true
            ],
            [
                'name' => 'Therapeutic Services',
                'description' => 'Treatment and therapeutic procedures',
                'status' => true
            ],
            [
                'name' => 'Surgical Procedures',
                'description' => 'Surgical operations and related services',
                'status' => true
            ],
            [
                'name' => 'Pharmacy Services',
                'description' => 'Medication and pharmaceutical services',
                'status' => true
            ],
            [
                'name' => 'Emergency Services',
                'description' => 'Emergency medical services and procedures',
                'status' => true
            ]
        ];

        foreach ($categories as $category) {
            ServiceCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
