<?php

namespace Database\Seeders;

use App\Models\CaseCategory;
use Illuminate\Database\Seeder;

class CaseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Surgical',
                'description' => 'Surgical procedures and operations',
                'status' => true
            ],
            [
                'name' => 'Medical',
                'description' => 'Medical treatments and consultations',
                'status' => true
            ],
            [
                'name' => 'Dental',
                'description' => 'Dental procedures and treatments',
                'status' => true
            ],
            [
                'name' => 'Obstetrics & Gynecology',
                'description' => 'Obstetric and gynecological procedures',
                'status' => true
            ],
            [
                'name' => 'Pediatrics',
                'description' => 'Pediatric care and treatments',
                'status' => true
            ],
            [
                'name' => 'Emergency',
                'description' => 'Emergency medical services',
                'status' => true
            ],
            [
                'name' => 'Diagnostic',
                'description' => 'Diagnostic tests and procedures',
                'status' => true
            ]
        ];

        foreach ($categories as $category) {
            CaseCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
