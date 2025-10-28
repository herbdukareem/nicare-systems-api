<?php

namespace Database\Seeders;

use App\Models\CaseType;
use Illuminate\Database\Seeder;

class CaseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $caseTypes = [
            [
                'name' => 'Surgical',
                'description' => 'Surgical procedures and operations',
                'status' => true,
            ],
            [
                'name' => 'Non-surgical',
                'description' => 'Non-surgical medical procedures and treatments',
                'status' => true,
            ],
        ];

        foreach ($caseTypes as $caseType) {
            CaseType::updateOrCreate(
                ['name' => $caseType['name']],
                $caseType
            );
        }
    }
}

