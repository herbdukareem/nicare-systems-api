<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ServiceGroup;
use App\Models\Service;

class ServiceGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serviceGroups = [
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

        foreach ($serviceGroups as $group) {
            ServiceGroup::create($group);
        }

        // Update existing services to link with service groups
        $this->linkExistingServices();
    }

    private function linkExistingServices()
    {
        $services = Service::all();

        foreach ($services as $service) {
            $groupName = $service->group;
            $serviceGroup = ServiceGroup::where('name', $groupName)->first();

            if ($serviceGroup) {
                $service->update(['service_group_id' => $serviceGroup->id]);
            }
        }
    }
}
