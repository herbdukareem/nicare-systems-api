<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {

        // ---- Permissions (same list you had in the migration) ----
        $permissions = [
            // Dashboard Access
            'dashboard.view',
            'dashboard.facility.view',
            'dashboard.desk_officer.view',
            'dashboard.claims.view',
            'dashboard.pas.view',
            'dashboard.management.view',

            // Enrollee Management
            'enrollees.view',
            'enrollees.create',
            'enrollees.edit',
            'enrollees.delete',
            'enrollees.export',

            // Facility Management
            'facilities.view',
            'facilities.create',
            'facilities.edit',
            'facilities.delete',
            'facilities.assign_desk_officer',

            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.assign_roles',

            // Role & Permission Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign_permissions',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Referral Management
            'referrals.create',
            'referrals.view',
            'referrals.edit',
            'referrals.submit',
            'referrals.approve',
            'referrals.reject',
            'referrals.print',
            'referrals.manage',

            // PA Code Management
            'pa_codes.create',
            'pa_codes.view',
            'pa_codes.request',
            'pa_codes.approve',
            'pa_codes.reject',
            'pa_codes.manage',

            // Admission Management
            'admissions.create',
            'admissions.view',
            'admissions.edit',
            'admissions.discharge',
            'admissions.manage',

            // UTN Management
            'utn.validate',
            'utn.view',
            'utn.generate',

            // Claim Management
            'claims.create',
            'claims.view',
            'claims.edit',
            'claims.delete',
            'claims.submit',
            'claims.withdraw',
            'claims.process',

            // Claim Review Workflow
            'claims.reviewer.review',
            'claims.reviewer.approve',
            'claims.reviewer.reject',
            'claims.confirmer.review',
            'claims.confirmer.confirm',
            'claims.confirmer.reject',
            'claims.approver.review',
            'claims.approver.approve',
            'claims.approver.reject',
            'claims.payment.authorize',

            // Tariff & Bundle Management
            'tariffs.view',
            'tariffs.create',
            'tariffs.edit',
            'tariffs.delete',
            'bundles.view',
            'bundles.create',
            'bundles.edit',
            'bundles.delete',

            // Document Management
            'documents.view',
            'documents.upload',
            'documents.download',
            'documents.delete',
            'documents.requirements.manage',

            // Reporting & Analytics
            'reports.view',
            'reports.generate',
            'reports.export',
            'analytics.view',
            'audit.view',

            // Feedback Management
            'feedback.view',
            'feedback.create',
            'feedback.respond',
            'feedback.manage',

            // Task Management
            'tasks.view',
            'tasks.create',
            'tasks.edit',
            'tasks.delete',
            'tasks.assign',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
            ], [
                'label' => ucfirst(str_replace('.', ' ', $name)),
                'description' => 'Permission for ' . str_replace('.', ' ', $name),
            ]);
        }

        // ---- Roles with their permissions and modules ----
        $roles = [
            'desk_officer'   => [
                'modules' => ['general', 'pas', 'claims'],
                'permissions' => [
                'dashboard.desk_officer.view',
                'facilities.view',
                'referrals.create',
                'referrals.view',
                'referrals.submit',
                'referrals.print',
                'pa_codes.view',
                'pa_codes.request',
                'admissions.view',
                'admissions.create',
                'utn.validate',
                'utn.view',
                'claims.create',
                'claims.view',
                'claims.submit',
                'documents.view',
                'documents.upload',
                'documents.download',
                'reports.view',
                ],
            ],
            'facility_admin' => [
                'modules' => ['general', 'pas', 'claims'],
                'permissions' => [
                'dashboard.facility.view',
                'facilities.view',
                'referrals.create',
                'referrals.view',
                'referrals.submit',
                'referrals.print',
                'pa_codes.create',
                'pa_codes.view',
                'pa_codes.request',
                'admissions.create',
                'admissions.view',
                'admissions.edit',
                'admissions.discharge',
                'utn.validate',
                'utn.view',
                'claims.create',
                'claims.view',
                'claims.submit',
                'documents.view',
                'documents.upload',
                'documents.download',
                'reports.view',
                'feedback.view',
                'feedback.create',
            ],
            'facility_user'  => [
                'dashboard.facility.view',
                'referrals.create',
                'referrals.view',
                'referrals.submit',
                'pa_codes.create',
                'pa_codes.view',
                'pa_codes.request',
                'admissions.create',
                'admissions.view',
                'utn.validate',
                'utn.view',
                'claims.create',
                'claims.view',
                'claims.submit',
                'documents.view',
                'documents.upload',
                'documents.download',
                'feedback.view',
                'feedback.create',
                ],
            ],
            'facility_user'  => [
                'modules' => ['general', 'pas', 'claims'],
                'permissions' => [
                'dashboard.facility.view',
                'referrals.create',
                'referrals.view',
                'referrals.submit',
                'pa_codes.create',
                'pa_codes.view',
                'pa_codes.request',
                'admissions.create',
                'admissions.view',
                'utn.validate',
                'utn.view',
                'claims.create',
                'claims.view',
                'claims.submit',
                'documents.view',
                'documents.upload',
                'documents.download',
                'feedback.view',
                'feedback.create',
                ],
            ],
            'claim_reviewer' => [
                'modules' => ['general', 'claims'],
                'permissions' => [
                'dashboard.claims.view',
                'claims.view',
                'claims.reviewer.review',
                'claims.reviewer.approve',
                'claims.reviewer.reject',
                'claims.process',
                'reports.view',
                'analytics.view',
                ],
            ],
            'claim_confirmer'=> [
                'modules' => ['general', 'claims'],
                'permissions' => [
                'dashboard.claims.view',
                'claims.view',
                'claims.confirmer.review',
                'claims.confirmer.confirm',
                'claims.confirmer.reject',
                'reports.view',
                'analytics.view',
                ],
            ],
            'claim_approver' => [
                'modules' => ['general', 'claims'],
                'permissions' => [
                'dashboard.claims.view',
                'claims.view',
                'claims.approver.review',
                'claims.approver.approve',
                'claims.approver.reject',
                'claims.payment.authorize',
                'reports.view',
                'analytics.view',
                'audit.view',
                ],
            ],
            'claims_officer' => [
                'modules' => ['general', 'pas', 'claims'],
                'permissions' => [
                'dashboard.claims.view',
                'dashboard.pas.view',
                'referrals.view',
                'referrals.approve',
                'referrals.reject',
                'referrals.print',
                'referrals.manage',
                'pa_codes.view',
                'pa_codes.approve',
                'pa_codes.reject',
                'pa_codes.manage',
                'admissions.view',
                'admissions.manage',
                'claims.view',
                'claims.process',
                'documents.view',
                'documents.requirements.manage',
                'reports.view',
                'reports.generate',
                'analytics.view',
                'feedback.view',
                'feedback.respond',
                'feedback.manage',
                ],
            ],
            'tariff_manager' => [
                'modules' => ['general', 'management'],
                'permissions' => [
                'dashboard.management.view',
                'tariffs.view',
                'tariffs.create',
                'tariffs.edit',
                'tariffs.delete',
                'bundles.view',
                'bundles.create',
                'bundles.edit',
                'bundles.delete',
                'reports.view',
                ],
            ],
            // admin gets everything
            'admin'          => [
                'modules' => ['general', 'pas', 'claims', 'automation', 'management'],
                'permissions' => '*',
            ],
            'Super Admin'    => [
                'modules' => ['general', 'pas', 'claims', 'automation', 'management'],
                'permissions' => '*',
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            // Extract modules and permissions
            $modules = $roleData['modules'] ?? [];
            $perms = $roleData['permissions'] ?? $roleData;

            $role = Role::firstOrCreate([
                'name' => $roleName,
            ], [
                'label' => ucfirst(str_replace('_', ' ', $roleName)),
                'description' => 'Role for ' . str_replace('_', ' ', $roleName),
            ]);

            // Update modules
            $role->modules = $modules;
            $role->save();

            if ($perms === '*' || (is_array($perms) && in_array('*', $perms, true))) {
                // all permissions
                $allPermissions = Permission::all();
                $role->permissions()->sync($allPermissions->pluck('id'));
            } else {
                // Get permission IDs by names
                $permissionIds = Permission::whereIn('name', $perms)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }
    }
}
