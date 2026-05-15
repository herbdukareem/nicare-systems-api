<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::updateOrCreate(
            ['name' => 'Super Admin'],
            [
                'label' => 'Super Admin',
                'description' => 'Full system access with all permissions.',
            ]
        );

        $superAdminRole->permissions()->sync(Permission::pluck('id'));

        $department = Department::firstOrCreate(
            ['name' => 'Administration'],
            ['description' => 'System administration department', 'status' => 1]
        );

        $designation = Designation::firstOrCreate(
            ['title' => 'System Administrator', 'department_id' => $department->id],
            ['description' => 'System administrator with full access', 'status' => 1]
        );

        $staff = Staff::updateOrCreate(
            ['email' => 'admin@nicare.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'middle_name' => null,
                'date_of_birth' => null,
                'gender' => 'Male',
                'phone' => '08000000000',
                'designation_id' => $designation->id,
                'department_id' => $department->id,
                'address' => 'NiCare System Administration',
                'status' => 1,
            ]
        );

        $user = User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@nicare.test',
                'phone' => '08000000000',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 1,
                'userable_type' => Staff::class,
                'userable_id' => $staff->id,
                'current_role_id' => $superAdminRole->id,
            ]
        );

        $user->roles()->syncWithoutDetaching([$superAdminRole->id]);

        $this->command?->info('Super Admin test user ready: username=superadmin password=password');
    }
}
