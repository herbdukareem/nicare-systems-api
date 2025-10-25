<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Facility;
use App\Models\DOFacility;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\Enrollee;

class DODashboardDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get the test desk officer
        $testDO = User::where('username', 'test_do')->first();
        if (!$testDO) {
            $this->command->error('Test desk officer not found. Run TestDeskOfficerSeeder first.');
            return;
        }

        // Get the assigned facility
        $doFacility = DOFacility::where('user_id', $testDO->id)->first();
        if (!$doFacility) {
            $this->command->error('No facility assigned to test desk officer.');
            return;
        }

        $facility = $doFacility->facility;
        $this->command->info("Creating test data for facility: {$facility->name}");

        // Get existing enrollees
        $enrollees = Enrollee::take(5)->get();
        if ($enrollees->count() == 0) {
            $this->command->error('No enrollees found. Please seed enrollees first.');
            return;
        }

        // Get other facilities for referrals
        $otherFacilities = Facility::where('id', '!=', $facility->id)->take(3)->get();

        // Create test referrals based on facility level
        $this->createReferrals($facility, $otherFacilities, $enrollees, $testDO);

        // Create test PA codes
        $this->createPACodes($facility, $enrollees);

        $this->command->info('DO dashboard test data created successfully!');
    }

    private function createReferrals($facility, $otherFacilities, $enrollees, $testDO)
    {
        $levelOfCare = $facility->level_of_care;

        if ($levelOfCare === 'Primary') {
            // Primary facility: create outgoing referrals
            foreach ($otherFacilities as $index => $targetFacility) {
                $enrollee = $enrollees[$index % count($enrollees)];
                
                Referral::firstOrCreate([
                    'referral_code' => "REF-OUT-{$facility->id}-{$index}",
                ], [
                    'enrollee_id' => $enrollee->id,
                    'referring_facility_id' => $facility->id,
                    'receiving_facility_id' => $targetFacility->id,
                    'reason' => 'Specialist consultation required',
                    'diagnosis' => 'Hypertension - needs specialist care',
                    'status' => $index % 2 == 0 ? 'Pending' : 'Approved',
                    'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
                    'utn_validated' => $index % 2 == 1,
                    'utn_validated_at' => $index % 2 == 1 ? now()->subHours(rand(1, 24)) : null,
                    'utn_validated_by' => $index % 2 == 1 ? $testDO->id : null,
                    'created_at' => now()->subDays(rand(1, 7)),
                ]);
            }
        } else {
            // Secondary/Tertiary facility: create incoming referrals (some validated, some not)
            foreach ($otherFacilities as $index => $sourceFacility) {
                $enrollee = $enrollees[$index % count($enrollees)];
                $isValidated = $index % 3 != 0; // 2/3 are validated, 1/3 pending
                
                Referral::firstOrCreate([
                    'referral_code' => "REF-IN-{$facility->id}-{$index}",
                ], [
                    'enrollee_id' => $enrollee->id,
                    'referring_facility_id' => $sourceFacility->id,
                    'receiving_facility_id' => $facility->id,
                    'reason' => 'Complex case requiring higher level care',
                    'diagnosis' => 'Cardiac condition requiring specialist intervention',
                    'status' => $isValidated ? 'Approved' : 'Pending',
                    'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
                    'utn_validated' => $isValidated,
                    'utn_validated_at' => $isValidated ? now()->subHours(rand(1, 48)) : null,
                    'utn_validated_by' => $isValidated ? $testDO->id : null,
                    'created_at' => now()->subDays(rand(1, 10)),
                ]);
            }
        }
    }

    private function createPACodes($facility, $enrollees)
    {
        $services = [
            ['type' => 'Consultation', 'diagnosis' => 'General consultation', 'amount' => 5000],
            ['type' => 'Laboratory', 'diagnosis' => 'Blood test', 'amount' => 8000],
            ['type' => 'Pharmacy', 'diagnosis' => 'Medication dispensing', 'amount' => 12000],
            ['type' => 'Surgery', 'diagnosis' => 'Minor surgery', 'amount' => 50000],
            ['type' => 'Emergency', 'diagnosis' => 'Emergency treatment', 'amount' => 25000],
        ];

        foreach ($services as $index => $service) {
            $enrollee = $enrollees[$index % count($enrollees)];
            
            PACode::firstOrCreate([
                'pa_code' => "PA-{$facility->id}-{$index}",
            ], [
                'enrollee_id' => $enrollee->id,
                'facility_id' => $facility->id,
                'service_type' => $service['type'],
                'diagnosis' => $service['diagnosis'],
                'amount' => $service['amount'],
                'status' => $index % 3 == 0 ? 'Pending' : 'Approved',
                'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
                'created_at' => now()->subDays(rand(1, 5)),
            ]);
        }
    }
}
