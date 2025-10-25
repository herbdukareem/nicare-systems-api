<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Referral;
use App\Models\Facility;
use App\Models\Service;
use App\Models\Enrollee;
use Carbon\Carbon;

class DOTestReferralsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the test DO's assigned facility
        $assignedFacility = Facility::where('name', 'BHC EKOWUNA')->first();
        
        if (!$assignedFacility) {
            $this->command->error('BHC EKOWUNA facility not found. Please run FacilitySeeder first.');
            return;
        }

        // Get some other facilities for referring
        $referringFacilities = Facility::where('id', '!=', $assignedFacility->id)->limit(3)->get();
        
        if ($referringFacilities->isEmpty()) {
            $this->command->error('No referring facilities found. Please run FacilitySeeder first.');
            return;
        }

        // Get some services
        $services = Service::limit(5)->get();
        if ($services->isEmpty()) {
            $this->command->error('No services found. Please run ServiceSeeder first.');
            return;
        }

        // Get some enrollees
        $enrollees = Enrollee::limit(10)->get();
        if ($enrollees->isEmpty()) {
            $this->command->error('No enrollees found. Please run EnrolleeSeeder first.');
            return;
        }

        $this->command->info('Creating test referrals for DO Dashboard...');

        // Create 5 approved referrals TO the assigned facility (BHC EKOWUNA)
        for ($i = 1; $i <= 5; $i++) {
            $referringFacility = $referringFacilities->random();
            $service = $services->random();
            $enrollee = $enrollees->random();

            Referral::create([
                'referring_facility_id' => $referringFacility->id,
                'receiving_facility_id' => $assignedFacility->id,
                'service_id' => $service->id,
                'enrollee_id' => $enrollee->id,
                'referral_code' => 'NGSCHA-' . str_pad($referringFacility->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($i + 1000, 6, '0', STR_PAD_LEFT),
                'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
                'utn_validated' => true,
                'utn_validated_at' => now(),
                'referring_facility_name' => $referringFacility->name,
                'referring_nicare_code' => $referringFacility->hcp_code,
                'receiving_facility_name' => $assignedFacility->name,
                'receiving_nicare_code' => $assignedFacility->hcp_code,
                'nicare_number' => $enrollee->nicare_number,
                'enrollee_full_name' => $enrollee->first_name . ' ' . $enrollee->last_name,
                'gender' => $enrollee->gender,
                'age' => Carbon::parse($enrollee->date_of_birth)->age,
                'enrollee_phone_main' => $enrollee->phone,
                'enrollee_email' => $enrollee->email,
                'referral_date' => now()->subDays(rand(1, 30)),
                'presenting_complaints' => 'Test presenting complaints for referral ' . $i,
                'reasons_for_referral' => 'Test reasons for referral ' . $i,
                'treatments_given' => 'Test treatments given for referral ' . $i,
                'examination_findings' => 'Test examination findings for referral ' . $i,
                'preliminary_diagnosis' => 'Test preliminary diagnosis for referral ' . $i,
                'severity_level' => ['routine', 'urgent', 'emergency'][rand(0, 2)],
                'personnel_full_name' => 'Dr. Test Physician ' . $i,
                'personnel_specialization' => 'General Practice',
                'personnel_cadre' => 'Doctor',
                'personnel_phone' => '080' . rand(10000000, 99999999),
                'personnel_email' => 'doctor' . $i . '@test.com',
                'status' => 'approved',
                'approved_at' => now()->subDays(rand(1, 10)),
                'approved_by' => 1, // Assuming admin user ID 1
                'comments' => 'Approved for test DO dashboard',
                'service_description' => $service->name,
            ]);
        }

        // Create 3 pending referrals (these should NOT show up for desk officers)
        for ($i = 6; $i <= 8; $i++) {
            $referringFacility = $referringFacilities->random();
            $service = $services->random();
            $enrollee = $enrollees->random();

            Referral::create([
                'referring_facility_id' => $referringFacility->id,
                'receiving_facility_id' => $assignedFacility->id,
                'service_id' => $service->id,
                'enrollee_id' => $enrollee->id,
                'referral_code' => 'NGSCHA-' . str_pad($referringFacility->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($i + 1000, 6, '0', STR_PAD_LEFT),
                'referring_facility_name' => $referringFacility->name,
                'referring_nicare_code' => $referringFacility->hcp_code,
                'receiving_facility_name' => $assignedFacility->name,
                'receiving_nicare_code' => $assignedFacility->hcp_code,
                'nicare_number' => $enrollee->nicare_number,
                'enrollee_full_name' => $enrollee->first_name . ' ' . $enrollee->last_name,
                'gender' => $enrollee->gender,
                'age' => Carbon::parse($enrollee->date_of_birth)->age,
                'enrollee_phone_main' => $enrollee->phone,
                'enrollee_email' => $enrollee->email,
                'referral_date' => now()->subDays(rand(1, 5)),
                'presenting_complaints' => 'Test pending presenting complaints for referral ' . $i,
                'reasons_for_referral' => 'Test pending reasons for referral ' . $i,
                'severity_level' => ['routine', 'urgent', 'emergency'][rand(0, 2)],
                'personnel_full_name' => 'Dr. Test Physician ' . $i,
                'personnel_specialization' => 'General Practice',
                'personnel_cadre' => 'Doctor',
                'personnel_phone' => '080' . rand(10000000, 99999999),
                'personnel_email' => 'doctor' . $i . '@test.com',
                'status' => 'pending', // These should NOT show up for desk officers
                'service_description' => $service->name,
            ]);
        }

        $this->command->info('✅ Created 5 approved referrals and 3 pending referrals for DO testing');
        $this->command->info('✅ Approved referrals should show up in DO dashboard');
        $this->command->info('✅ Pending referrals should NOT show up in DO dashboard');
    }
}
