<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class FacilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first LGA and Ward for seeding (assuming they exist)
        $lga = Lga::first();
        $ward = Ward::first();

        if (!$lga || !$ward) {
            $this->command->warn('No LGA or Ward found. Please seed LGAs and Wards first.');
            return;
        }

        $facilities = [
            [
                'hcp_code' => 'HCP001',
                'name' => 'Federal Medical Centre Abuja',
                'ownership' => 'Public',
                'level_of_care' => 'Tertiary',
                'address' => 'Jabi, Abuja',
                'phone' => '09012345678',
                'email' => 'info@fmcabuja.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 500,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP002',
                'name' => 'University of Abuja Teaching Hospital',
                'ownership' => 'Public',
                'level_of_care' => 'Tertiary',
                'address' => 'Gwagwalada, Abuja',
                'phone' => '09012345679',
                'email' => 'info@uath.edu.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 400,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP003',
                'name' => 'National Hospital Abuja',
                'ownership' => 'Public',
                'level_of_care' => 'Tertiary',
                'address' => 'Central Business District, Abuja',
                'phone' => '09012345680',
                'email' => 'info@nationalhospital.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 600,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP004',
                'name' => 'Garki General Hospital',
                'ownership' => 'Public',
                'level_of_care' => 'Secondary',
                'address' => 'Garki, Abuja',
                'phone' => '09012345681',
                'email' => 'info@garkihospital.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 200,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP005',
                'name' => 'Wuse General Hospital',
                'ownership' => 'Public',
                'level_of_care' => 'Secondary',
                'address' => 'Wuse, Abuja',
                'phone' => '09012345682',
                'email' => 'info@wusehospital.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 150,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP006',
                'name' => 'Kubwa Primary Health Centre',
                'ownership' => 'Public',
                'level_of_care' => 'Primary',
                'address' => 'Kubwa, Abuja',
                'phone' => '09012345683',
                'email' => 'info@kubwaphc.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 50,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP007',
                'name' => 'Nyanya Primary Health Centre',
                'ownership' => 'Public',
                'level_of_care' => 'Primary',
                'address' => 'Nyanya, Abuja',
                'phone' => '09012345684',
                'email' => 'info@nyanyphc.gov.ng',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 40,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP008',
                'name' => 'Cedarcrest Hospital',
                'ownership' => 'Private',
                'level_of_care' => 'Secondary',
                'address' => 'Gudu, Abuja',
                'phone' => '09012345685',
                'email' => 'info@cedarcrest.com',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 100,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP009',
                'name' => 'Nisa Premier Hospital',
                'ownership' => 'Private',
                'level_of_care' => 'Secondary',
                'address' => 'Jahi, Abuja',
                'phone' => '09012345686',
                'email' => 'info@nisapremier.com',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 80,
                'status' => 1,
            ],
            [
                'hcp_code' => 'HCP010',
                'name' => 'Zankli Medical Centre',
                'ownership' => 'Private',
                'level_of_care' => 'Tertiary',
                'address' => 'Utako, Abuja',
                'phone' => '09012345687',
                'email' => 'info@zankli.com',
                'lga_id' => $lga->id,
                'ward_id' => $ward->id,
                'capacity' => 120,
                'status' => 1,
            ],
        ];

        foreach ($facilities as $facilityData) {
            Facility::updateOrCreate(
                ['hcp_code' => $facilityData['hcp_code']],
                $facilityData
            );
        }

        $this->command->info('Facilities seeded successfully!');
    }
}
