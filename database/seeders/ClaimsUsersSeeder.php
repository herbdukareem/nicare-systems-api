<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class ClaimsUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users for each claims role
        
        // 1. Desk Officer (HIM)
        $deskOfficerStaff = Staff::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'desk.officer@ngscha.gov.ng',
            'phone' => '08012345678',
            'status' => 1,
        ]);

        $deskOfficerUser = User::create([
            'name' => 'John Doe',
            'username' => 'desk_officer',
            'email' => 'desk.officer@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $deskOfficerStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $deskOfficerUser->assignRole('desk_officer');

        // 2. Doctor
        $doctorStaff = Staff::create([
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'email' => 'doctor@ngscha.gov.ng',
            'phone' => '08012345679',
            'status' => 1,
        ]);

        $doctorUser = User::create([
            'name' => 'Dr. Sarah Johnson',
            'username' => 'doctor',
            'email' => 'doctor@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $doctorStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $doctorUser->assignRole('doctor');

        // 3. Pharmacist
        $pharmacistStaff = Staff::create([
            'first_name' => 'Michael',
            'last_name' => 'Brown',
            'email' => 'pharmacist@ngscha.gov.ng',
            'phone' => '08012345680',
            'status' => 1,
        ]);

        $pharmacistUser = User::create([
            'name' => 'Michael Brown',
            'username' => 'pharmacist',
            'email' => 'pharmacist@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $pharmacistStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $pharmacistUser->assignRole('pharmacist');

        // 4. Claim Reviewer
        $reviewerStaff = Staff::create([
            'first_name' => 'Emily',
            'last_name' => 'Davis',
            'email' => 'claim.reviewer@ngscha.gov.ng',
            'phone' => '08012345681',
            'status' => 1,
        ]);

        $reviewerUser = User::create([
            'name' => 'Emily Davis',
            'username' => 'claim_reviewer',
            'email' => 'claim.reviewer@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $reviewerStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $reviewerUser->assignRole('claim_reviewer');

        // 5. Claim Confirmer
        $confirmerStaff = Staff::create([
            'first_name' => 'David',
            'last_name' => 'Wilson',
            'email' => 'claim.confirmer@ngscha.gov.ng',
            'phone' => '08012345682',
            'status' => 1,
        ]);

        $confirmerUser = User::create([
            'name' => 'David Wilson',
            'username' => 'claim_confirmer',
            'email' => 'claim.confirmer@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $confirmerStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $confirmerUser->assignRole('claim_confirmer');

        // 6. Claim Approver
        $approverStaff = Staff::create([
            'first_name' => 'Lisa',
            'last_name' => 'Anderson',
            'email' => 'claim.approver@ngscha.gov.ng',
            'phone' => '08012345683',
            'status' => 1,
        ]);

        $approverUser = User::create([
            'name' => 'Lisa Anderson',
            'username' => 'claim_approver',
            'email' => 'claim.approver@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $approverStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $approverUser->assignRole('claim_approver');

        // 7. Claims Administrator
        $adminStaff = Staff::create([
            'first_name' => 'Robert',
            'last_name' => 'Taylor',
            'email' => 'claims.admin@ngscha.gov.ng',
            'phone' => '08012345684',
            'status' => 1,
        ]);

        $adminUser = User::create([
            'name' => 'Robert Taylor',
            'username' => 'claims_admin',
            'email' => 'claims.admin@ngscha.gov.ng',
            'password' => Hash::make('password123'),
            'userable_type' => Staff::class,
            'userable_id' => $adminStaff->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('claims_admin');

        $this->command->info('Claims users created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('Desk Officer: desk_officer / password123');
        $this->command->info('Doctor: doctor / password123');
        $this->command->info('Pharmacist: pharmacist / password123');
        $this->command->info('Claim Reviewer: claim_reviewer / password123');
        $this->command->info('Claim Confirmer: claim_confirmer / password123');
        $this->command->info('Claim Approver: claim_approver / password123');
        $this->command->info('Claims Admin: claims_admin / password123');
    }
}
