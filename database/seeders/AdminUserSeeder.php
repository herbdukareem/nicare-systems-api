<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Staff;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin role if it doesn't exist
        $superAdminRole = Role::firstOrCreate([
            'name' => 'Super Admin'
        ], [
            'label' => 'Super Administrator',
            'description' => 'Full system access with all permissions'
        ]);

        // Create all permissions if they don't exist
        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view', 'permissions.create', 'permissions.edit', 'permissions.delete',
            'enrollees.view', 'enrollees.create', 'enrollees.edit', 'enrollees.delete',
            'facilities.view', 'facilities.create', 'facilities.edit', 'facilities.delete',
            'benefactors.view', 'benefactors.create', 'benefactors.edit', 'benefactors.delete',
            'pas.view', 'pas.create', 'pas.edit', 'pas.delete', 'pas.approve',
            'reports.view', 'reports.export',
            'settings.view', 'settings.edit',
            'dashboard.view'
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName
            ], [
                'description' => ucfirst(str_replace('.', ' ', $permissionName))
            ]);
        }

        // Assign all permissions to Super Admin role
        $allPermissions = Permission::all();
        $superAdminRole->permissions()->sync($allPermissions->pluck('id'));

        // Create Admin Department if it doesn't exist
        $adminDepartment = Department::firstOrCreate([
            'name' => 'Administration'
        ], [
            'description' => 'System Administration Department',
            'status' => 1
        ]);

        // Create Admin Designation if it doesn't exist
        $adminDesignation = Designation::firstOrCreate([
            'title' => 'System Administrator',
            'department_id' => $adminDepartment->id
        ], [
            'description' => 'System Administrator with full access',
            'status' => 1
        ]);

        // Create Staff record for Super Admin
        $adminStaff = Staff::firstOrCreate([
            'email' => 'superadmin@ngscha.gov.ng'
        ], [
            'first_name' => 'Super',
            'last_name' => 'Administrator',
            'middle_name' => null,
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'phone' => '+234-800-000-0000',
            'designation_id' => $adminDesignation->id,
            'department_id' => $adminDepartment->id,
            'address' => 'NGSCHA Headquarters, Abuja',
            'status' => 1
        ]);

        // Create Super Admin user
        $superAdmin = User::firstOrCreate([
            'username' => 'superadmin'
        ], [
            'name' => 'Super Administrator',
            'email' => 'superadmin@ngscha.gov.ng',
            'password' => Hash::make('12345678'),
            'email_verified_at' => now(),
            'status' => 1,
            'userable_type' => Staff::class,
            'userable_id' => $adminStaff->id
        ]);

        // Assign Super Admin role to the user
        $superAdmin->roles()->sync([$superAdminRole->id]);

        $this->command->info('Super Admin user created successfully!');
        $this->command->info('Username: superadmin');
        $this->command->info('Password: 12345678');
    }
}
