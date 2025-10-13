<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Referral;
use App\Models\Facility;
use App\Models\Service;
use Carbon\Carbon;

class ReferralSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some facilities for referrals
        $facilities = Facility::limit(5)->get();
        $services = Service::limit(10)->get();

        if ($facilities->isEmpty()) {
            $this->command->warn('No facilities found. Please run FacilitySeeder first.');
            return;
        }

        $referrals = [
            [
                'referring_facility_name' => 'Primary Health Centre Kano',
                'referring_nicare_code' => 'NGSCHA/PHC/KN/001',
                'referring_address' => 'No. 15 Hospital Road, Kano',
                'referring_phone' => '08012345678',
                'referring_email' => 'phc.kano@ngscha.gov.ng',

                'contact_full_name' => 'Dr. Amina Hassan',
                'contact_phone' => '08012345678',
                'contact_email' => 'amina.hassan@ngscha.gov.ng',

                'receiving_facility_name' => $facilities->first()->name,
                'receiving_nicare_code' => $facilities->first()->hcp_code,
                'receiving_facility_id' => $facilities->first()->id,
                'receiving_address' => $facilities->first()->address ?? 'Hospital Address',
                'receiving_phone' => $facilities->first()->phone ?? '08098765432',
                'receiving_email' => $facilities->first()->email ?? 'hospital@example.com',

                'nicare_number' => 'NGSCHA/2024/001234',
                'enrollee_full_name' => 'Musa Ibrahim',
                'gender' => 'Male',
                'age' => 45,
                'enrollee_phone_main' => '08087654321',
                'referral_date' => Carbon::now()->subDays(2),

                'presenting_complaints' => 'Chest pain, shortness of breath, and fatigue for the past 3 days',
                'reasons_for_referral' => 'Suspected cardiac condition requiring specialist evaluation and management',
                'preliminary_diagnosis' => 'Acute coronary syndrome (suspected)',

                'severity_level' => 'urgent',
                'status' => 'pending',

                'personnel_full_name' => 'Dr. Fatima Abdullahi',
                'personnel_phone' => '08012345678',
                'personnel_email' => 'fatima.abdullahi@ngscha.gov.ng',

                'service_id' => $services->first()?->id,
                'service_description' => 'Cardiology Consultation',
            ],
            [
                'referring_facility_name' => 'General Hospital Kaduna',
                'referring_nicare_code' => 'NGSCHA/GH/KD/002',
                'referring_address' => 'No. 25 Independence Way, Kaduna',
                'referring_phone' => '08023456789',
                'referring_email' => 'gh.kaduna@ngscha.gov.ng',

                'contact_full_name' => 'Dr. John Adamu',
                'contact_phone' => '08023456789',
                'contact_email' => 'john.adamu@ngscha.gov.ng',

                'receiving_facility_name' => $facilities->skip(1)->first()->name ?? $facilities->first()->name,
                'receiving_nicare_code' => $facilities->skip(1)->first()->hcp_code ?? $facilities->first()->hcp_code,
                'receiving_facility_id' => $facilities->skip(1)->first()->id ?? $facilities->first()->id,
                'receiving_address' => $facilities->skip(1)->first()->address ?? 'Hospital Address',
                'receiving_phone' => $facilities->skip(1)->first()->phone ?? '08098765432',
                'receiving_email' => $facilities->skip(1)->first()->email ?? 'hospital@example.com',

                'nicare_number' => 'NGSCHA/2024/001235',
                'enrollee_full_name' => 'Aisha Mohammed',
                'gender' => 'Female',
                'age' => 32,
                'enrollee_phone_main' => '08076543210',
                'referral_date' => Carbon::now()->subDays(1),

                'presenting_complaints' => 'Severe abdominal pain, nausea, and vomiting',
                'reasons_for_referral' => 'Suspected appendicitis requiring surgical evaluation',
                'preliminary_diagnosis' => 'Acute appendicitis (suspected)',

                'severity_level' => 'emergency',
                'status' => 'pending',

                'personnel_full_name' => 'Dr. Sarah Yakubu',
                'personnel_phone' => '08023456789',
                'personnel_email' => 'sarah.yakubu@ngscha.gov.ng',

                'service_id' => $services->skip(1)->first()?->id ?? $services->first()?->id,
                'service_description' => 'General Surgery Consultation',
            ],
            [
                'referring_facility_name' => 'Specialist Hospital Abuja',
                'referring_nicare_code' => 'NGSCHA/SH/AB/003',
                'referring_address' => 'No. 10 Central Area, Abuja',
                'referring_phone' => '08034567890',
                'referring_email' => 'sh.abuja@ngscha.gov.ng',

                'contact_full_name' => 'Dr. Michael Okafor',
                'contact_phone' => '08034567890',
                'contact_email' => 'michael.okafor@ngscha.gov.ng',

                'receiving_facility_name' => $facilities->skip(2)->first()->name ?? $facilities->first()->name,
                'receiving_nicare_code' => $facilities->skip(2)->first()->hcp_code ?? $facilities->first()->hcp_code,
                'receiving_facility_id' => $facilities->skip(2)->first()->id ?? $facilities->first()->id,
                'receiving_address' => $facilities->skip(2)->first()->address ?? 'Hospital Address',
                'receiving_phone' => $facilities->skip(2)->first()->phone ?? '08098765432',
                'receiving_email' => $facilities->skip(2)->first()->email ?? 'hospital@example.com',

                'nicare_number' => 'NGSCHA/2024/001236',
                'enrollee_full_name' => 'Ibrahim Suleiman',
                'gender' => 'Male',
                'age' => 28,
                'enrollee_phone_main' => '08065432109',
                'referral_date' => Carbon::now(),

                'presenting_complaints' => 'Persistent headaches, blurred vision, and dizziness',
                'reasons_for_referral' => 'Neurological symptoms requiring specialist evaluation',
                'preliminary_diagnosis' => 'Possible intracranial pathology',

                'severity_level' => 'routine',
                'status' => 'pending',

                'personnel_full_name' => 'Dr. Grace Eze',
                'personnel_phone' => '08034567890',
                'personnel_email' => 'grace.eze@ngscha.gov.ng',

                'service_id' => $services->skip(2)->first()?->id ?? $services->first()?->id,
                'service_description' => 'Neurology Consultation',
            ]
        ];

        foreach ($referrals as $referralData) {
            $referral = Referral::create($referralData);

            // Generate referral code
            $referralCode = $referral->generateReferralCode();
            $referral->update(['referral_code' => $referralCode]);
        }

        $this->command->info('Created ' . count($referrals) . ' sample referrals');
    }
}
