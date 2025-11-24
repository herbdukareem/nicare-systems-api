<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Facility;
use App\Models\DOFacility;
use App\Models\DeskOfficer;
use App\Models\Role;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Support\Facades\Hash;
class TestDeskOfficerSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create desk officer role
        $deskOfficerRole = Role::firstOrCreate(['name' => 'desk_officer']);
        // Get or create department
        $department = Department::firstOrCreate(
            ['name' => 'Operations'],
            ['description' => 'Operations Department', 'status' => 'active']
        );
        // Get or create designation
        $designation = Designation::firstOrCreate(
            ['title' => 'Desk Officer'],
            ['department_id' => $department->id, 'description' => 'Desk Officer Role', 'status' => 'active']
        );

        // Create a test desk officer
        $deskOfficer = DeskOfficer::firstOrCreate([
            'email' => 'test.do@ngscha.gov.ng',
        ], [
            'first_name' => 'Test',
            'last_name' => 'DeskOfficer',
            'phone' => '08012345678',
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'status' => true,
        ]);

        // Create user for the desk officer
        $user = User::firstOrCreate([
            'username' => 'test_do',
        ], [
            'name' => 'Test Desk Officer',
            'email' => 'test.do@ngscha.gov.ng',
            'password' => Hash::make('password'),
            'userable_type' => DeskOfficer::class,
            'userable_id' => $deskOfficer->id,
            'status' => 1,
            'email_verified_at' => now(),
        ]);

        // Assign role using pivot table
        if (!$user->roles()->where('name', 'desk_officer')->exists()) {
            $user->roles()->attach($deskOfficerRole->id);
        }

        // Get a test facility to assign
        $facility = Facility::first();
        if ($facility) {
            DOFacility::firstOrCreate([
                'user_id' => $user->id,
                'facility_id' => $facility->id,
            ], [
                'assigned_at' => now(),
            ]);
        }

        $this->command->info('Test Desk Officer created successfully!');
        $this->command->info('Username: test_do');
        $this->command->info('Password: password');
        $this->command->info('Assigned to facility: ' . ($facility ? $facility->name : 'None'));
    }
}
