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
            // Claim Management
            'claims.create',
            'claims.view',
            'claims.edit',
            'claims.delete',
            'claims.submit',
            'claims.withdraw',

            // Doctor
            'claims.doctor.review',
            'claims.doctor.approve',
            'claims.doctor.reject',
            'claims.diagnoses.validate',
            'claims.treatments.validate',

            // Pharmacist
            'claims.pharmacist.review',
            'claims.pharmacist.approve',
            'claims.pharmacist.reject',
            'claims.medications.validate',

            // Claim Reviewer
            'claims.reviewer.review',
            'claims.reviewer.approve',
            'claims.reviewer.reject',
            'claims.tariff.validate',

            // Claim Confirmer
            'claims.confirmer.review',
            'claims.confirmer.confirm',
            'claims.confirmer.reject',

            // Claim Approver
            'claims.approver.review',
            'claims.approver.approve',
            'claims.approver.reject',
            'claims.payment.authorize',

            // Reporting & Analytics
            'claims.reports.view',
            'claims.analytics.view',
            'claims.audit.view',

            // Administrative
            'claims.admin.all',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
            ], [
                'label' => ucfirst(str_replace('.', ' ', $name)),
                'description' => 'Permission for ' . str_replace('.', ' ', $name),
            ]);
        }

        // ---- Roles & their permissions ----
        $roles = [
            'desk_officer'   => [
                'claims.create',
                'claims.view',
                'claims.edit',
                'claims.submit',
                'claims.withdraw',
            ],
            'doctor'         => [
                'claims.view',
                'claims.doctor.review',
                'claims.doctor.approve',
                'claims.doctor.reject',
                'claims.diagnoses.validate',
                'claims.treatments.validate',
            ],
            'pharmacist'     => [
                'claims.view',
                'claims.pharmacist.review',
                'claims.pharmacist.approve',
                'claims.pharmacist.reject',
                'claims.medications.validate',
            ],
            'claim_reviewer' => [
                'claims.view',
                'claims.reviewer.review',
                'claims.reviewer.approve',
                'claims.reviewer.reject',
                'claims.tariff.validate',
                'claims.reports.view',
            ],
            'claim_confirmer'=> [
                'claims.view',
                'claims.confirmer.review',
                'claims.confirmer.confirm',
                'claims.confirmer.reject',
                'claims.reports.view',
                'claims.analytics.view',
            ],
            'claim_approver' => [
                'claims.view',
                'claims.approver.review',
                'claims.approver.approve',
                'claims.approver.reject',
                'claims.payment.authorize',
                'claims.reports.view',
                'claims.analytics.view',
                'claims.audit.view',
            ],
            // admin gets everything
            'claims_admin'   => '*',
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
            ], [
                'label' => ucfirst(str_replace('_', ' ', $roleName)),
                'description' => 'Role for ' . str_replace('_', ' ', $roleName),
            ]);

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
