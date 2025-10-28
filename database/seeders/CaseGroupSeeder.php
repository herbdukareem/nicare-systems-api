<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CaseGroup;

class CaseGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $caseGroups = [
            [
                'name' => 'GENERAL CONSULTATION',
                'description' => 'General medical consultation services',
                'status' => true
            ],
            [
                'name' => 'PAEDIATRICS',
                'description' => 'Pediatric medical services',
                'status' => true
            ],
            [
                'name' => 'INTERNAL MEDICINE (PRV)',
                'description' => 'Internal medicine private services',
                'status' => true
            ],
            [
                'name' => 'HEALTH EDUCATION',
                'description' => 'Health education and promotion services',
                'status' => true
            ],
            [
                'name' => 'LABORATORY',
                'description' => 'Laboratory diagnostic services',
                'status' => true
            ],
            [
                'name' => 'RADIOLOGY',
                'description' => 'Radiology and imaging services',
                'status' => true
            ],
            [
                'name' => 'SURGERY',
                'description' => 'Surgical procedures and operations',
                'status' => true
            ],
            [
                'name' => 'OBSTETRICS & GYNAECOLOGY',
                'description' => 'Obstetrics and gynecology services',
                'status' => true
            ],
            [
                'name' => 'EMERGENCY SERVICES',
                'description' => 'Emergency medical services',
                'status' => true
            ],
            [
                'name' => 'PHARMACY',
                'description' => 'Pharmaceutical services',
                'status' => true
            ]
        ];

        foreach ($caseGroups as $group) {
            CaseGroup::create($group);
        }

        // Update existing cases to link with case groups
        $this->linkExistingCases();
    }

    private function linkExistingCases()
    {
        $cases = \App\Models\Case::all();

        foreach ($cases as $case) {
            $groupName = $case->group;
            $caseGroup = CaseGroup::where('name', $groupName)->first();

            if ($caseGroup) {
                $case->update(['case_group_id' => $caseGroup->id]);
            }
        }
    }
}

