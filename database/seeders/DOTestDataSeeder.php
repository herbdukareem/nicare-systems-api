<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Facility;
use App\Models\DOFacility;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\Enrollee;
use App\Models\DeskOfficer;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DOTestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create test facilities if they don't exist
        $primaryFacility = Facility::firstOrCreate([
            'name' => 'Primary Health Center Abuja',
            'hcp_code' => 'PHC001',
        ], [
            'level_of_care' => 'Primary',
            'address' => 'Abuja, FCT',
            'phone' => '08012345678',
            'email' => 'phc001@ngscha.gov.ng',
            'lga_id' => 1, // Default LGA
            'status' => 1,
        ]);

        $secondaryFacility = Facility::firstOrCreate([
            'name' => 'General Hospital Kano',
            'hcp_code' => 'GH002',
        ], [
            'level_of_care' => 'Secondary',
            'address' => 'Kano, Kano State',
            'phone' => '08012345679',
            'email' => 'gh002@ngscha.gov.ng',
            'lga_id' => 1, // Default LGA
            'status' => 1,
        ]);

        $tertiaryFacility = Facility::firstOrCreate([
            'name' => 'University Teaching Hospital Lagos',
            'hcp_code' => 'UTH003',
        ], [
            'level_of_care' => 'Tertiary',
            'address' => 'Lagos, Lagos State',
            'phone' => '08012345680',
            'email' => 'uth003@ngscha.gov.ng',
            'lga_id' => 1, // Default LGA
            'status' => 1,
        ]);

        // Get or create desk officer role
        $deskOfficerRole = Role::firstOrCreate(['name' => 'desk_officer']);

        // Create test desk officers
        $primaryDO = $this->createDeskOfficer(
            'Primary DO',
            'primary_do',
            'primary.do@ngscha.gov.ng',
            $deskOfficerRole
        );

        $secondaryDO = $this->createDeskOfficer(
            'Secondary DO',
            'secondary_do',
            'secondary.do@ngscha.gov.ng',
            $deskOfficerRole
        );

        $tertiaryDO = $this->createDeskOfficer(
            'Tertiary DO',
            'tertiary_do',
            'tertiary.do@ngscha.gov.ng',
            $deskOfficerRole
        );

        // Assign facilities to desk officers
        DOFacility::firstOrCreate([
            'user_id' => $primaryDO->id,
            'facility_id' => $primaryFacility->id,
        ], [
            'assigned_at' => now(),
        ]);

        DOFacility::firstOrCreate([
            'user_id' => $secondaryDO->id,
            'facility_id' => $secondaryFacility->id,
        ], [
            'assigned_at' => now(),
        ]);

        DOFacility::firstOrCreate([
            'user_id' => $tertiaryDO->id,
            'facility_id' => $tertiaryFacility->id,
        ], [
            'assigned_at' => now(),
        ]);

        // Create test enrollees
        $enrollee1 = Enrollee::firstOrCreate([
            'enrollee_id' => 'EID001',
        ], [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '08012345681',
            'email' => 'john.doe@example.com',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'status' => 'Active',
        ]);

        $enrollee2 = Enrollee::firstOrCreate([
            'enrollee_id' => 'EID002',
        ], [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '08012345682',
            'email' => 'jane.smith@example.com',
            'date_of_birth' => '1985-05-15',
            'gender' => 'Female',
            'status' => 'Active',
        ]);

        // Create test referrals
        $this->createTestReferrals($primaryFacility, $secondaryFacility, $tertiaryFacility, $enrollee1, $enrollee2);

        // Create test PA codes
        $this->createTestPACodes($primaryFacility, $secondaryFacility, $tertiaryFacility, $enrollee1, $enrollee2);

        $this->command->info('DO test data seeded successfully!');
        $this->command->info('Test Desk Officers created:');
        $this->command->info('- Primary DO: username=primary_do, password=password');
        $this->command->info('- Secondary DO: username=secondary_do, password=password');
        $this->command->info('- Tertiary DO: username=tertiary_do, password=password');
    }

    private function createDeskOfficer($name, $username, $email, $role)
    {
        // Check if user already exists
        $user = User::where('username', $username)->first();
        if ($user) {
            return $user;
        }

        // Create DeskOfficer record
        $deskOfficer = DeskOfficer::create([
            'employee_id' => 'DO' . rand(1000, 9999),
            'department_id' => 1, // Assuming department exists
            'designation_id' => 1, // Assuming designation exists
            'hire_date' => now(),
            'status' => 'Active',
        ]);

        // Create User record
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make('password'),
            'userable_type' => DeskOfficer::class,
            'userable_id' => $deskOfficer->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);

        // Assign role
        $user->assignRole($role);

        return $user;
    }

    private function createTestReferrals($primaryFacility, $secondaryFacility, $tertiaryFacility, $enrollee1, $enrollee2)
    {
        // Referral from primary to secondary (with UTN for validation)
        Referral::firstOrCreate([
            'referral_code' => 'REF001',
        ], [
            'enrollee_id' => $enrollee1->id,
            'referring_facility_id' => $primaryFacility->id,
            'receiving_facility_id' => $secondaryFacility->id,
            'reason' => 'Specialist consultation required',
            'diagnosis' => 'Hypertension',
            'status' => 'Pending',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'utn_validated' => false,
            'created_at' => now()->subDays(2),
        ]);

        // Referral from secondary to tertiary (with validated UTN)
        Referral::firstOrCreate([
            'referral_code' => 'REF002',
        ], [
            'enrollee_id' => $enrollee2->id,
            'referring_facility_id' => $secondaryFacility->id,
            'receiving_facility_id' => $tertiaryFacility->id,
            'reason' => 'Complex surgery required',
            'diagnosis' => 'Cardiac condition',
            'status' => 'Approved',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'utn_validated' => true,
            'utn_validated_at' => now()->subDays(1),
            'created_at' => now()->subDays(3),
        ]);

        // Referral from primary (outgoing for primary DO)
        Referral::firstOrCreate([
            'referral_code' => 'REF003',
        ], [
            'enrollee_id' => $enrollee1->id,
            'referring_facility_id' => $primaryFacility->id,
            'receiving_facility_id' => $tertiaryFacility->id,
            'reason' => 'Emergency case',
            'diagnosis' => 'Trauma',
            'status' => 'Completed',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'utn_validated' => true,
            'utn_validated_at' => now()->subHours(12),
            'created_at' => now()->subDays(1),
        ]);
    }

    private function createTestPACodes($primaryFacility, $secondaryFacility, $tertiaryFacility, $enrollee1, $enrollee2)
    {
        // PA codes for different facilities
        PACode::firstOrCreate([
            'pa_code' => 'PA001',
        ], [
            'enrollee_id' => $enrollee1->id,
            'facility_id' => $primaryFacility->id,
            'service_type' => 'Consultation',
            'diagnosis' => 'Routine checkup',
            'amount' => 5000.00,
            'status' => 'Approved',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'created_at' => now()->subDays(1),
        ]);

        PACode::firstOrCreate([
            'pa_code' => 'PA002',
        ], [
            'enrollee_id' => $enrollee2->id,
            'facility_id' => $secondaryFacility->id,
            'service_type' => 'Surgery',
            'diagnosis' => 'Appendectomy',
            'amount' => 150000.00,
            'status' => 'Pending',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'created_at' => now()->subHours(6),
        ]);

        PACode::firstOrCreate([
            'pa_code' => 'PA003',
        ], [
            'enrollee_id' => $enrollee1->id,
            'facility_id' => $tertiaryFacility->id,
            'service_type' => 'Specialist Consultation',
            'diagnosis' => 'Cardiology consultation',
            'amount' => 25000.00,
            'status' => 'Approved',
            'utn' => 'UTN' . date('ymdHis') . rand(1000, 9999),
            'created_at' => now()->subHours(3),
        ]);
    }
}
