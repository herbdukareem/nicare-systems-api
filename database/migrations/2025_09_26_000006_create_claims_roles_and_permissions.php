<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Claims Management Permissions
        $permissions = [
            // Claim Management
            'claims.create',
            'claims.view',
            'claims.edit',
            'claims.delete',
            'claims.submit',
            'claims.withdraw',
            
            // Doctor Permissions
            'claims.doctor.review',
            'claims.doctor.approve',
            'claims.doctor.reject',
            'claims.diagnoses.validate',
            'claims.treatments.validate',
            
            // Pharmacist Permissions
            'claims.pharmacist.review',
            'claims.pharmacist.approve',
            'claims.pharmacist.reject',
            'claims.medications.validate',
            
            // Claim Reviewer Permissions
            'claims.reviewer.review',
            'claims.reviewer.approve',
            'claims.reviewer.reject',
            'claims.tariff.validate',
            
            // Claim Confirmer Permissions
            'claims.confirmer.review',
            'claims.confirmer.confirm',
            'claims.confirmer.reject',
            
            // Claim Approver Permissions
            'claims.approver.review',
            'claims.approver.approve',
            'claims.approver.reject',
            'claims.payment.authorize',
            
            // Reporting and Analytics
            'claims.reports.view',
            'claims.analytics.view',
            'claims.audit.view',
            
            // Administrative
            'claims.admin.all',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create Roles and Assign Permissions
        
        // 1. Desk Officer (HIM)
        $deskOfficer = Role::create(['name' => 'desk_officer', 'guard_name' => 'web']);
        $deskOfficer->givePermissionTo([
            'claims.create',
            'claims.view',
            'claims.edit',
            'claims.submit',
            'claims.withdraw',
        ]);

        // 2. Doctor
        $doctor = Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        $doctor->givePermissionTo([
            'claims.view',
            'claims.doctor.review',
            'claims.doctor.approve',
            'claims.doctor.reject',
            'claims.diagnoses.validate',
            'claims.treatments.validate',
        ]);

        // 3. Pharmacist
        $pharmacist = Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist->givePermissionTo([
            'claims.view',
            'claims.pharmacist.review',
            'claims.pharmacist.approve',
            'claims.pharmacist.reject',
            'claims.medications.validate',
        ]);

        // 4. Claim Reviewer
        $claimReviewer = Role::create(['name' => 'claim_reviewer', 'guard_name' => 'web']);
        $claimReviewer->givePermissionTo([
            'claims.view',
            'claims.reviewer.review',
            'claims.reviewer.approve',
            'claims.reviewer.reject',
            'claims.tariff.validate',
            'claims.reports.view',
        ]);

        // 5. Claim Confirmer
        $claimConfirmer = Role::create(['name' => 'claim_confirmer', 'guard_name' => 'web']);
        $claimConfirmer->givePermissionTo([
            'claims.view',
            'claims.confirmer.review',
            'claims.confirmer.confirm',
            'claims.confirmer.reject',
            'claims.reports.view',
            'claims.analytics.view',
        ]);

        // 6. Claim Approver
        $claimApprover = Role::create(['name' => 'claim_approver', 'guard_name' => 'web']);
        $claimApprover->givePermissionTo([
            'claims.view',
            'claims.approver.review',
            'claims.approver.approve',
            'claims.approver.reject',
            'claims.payment.authorize',
            'claims.reports.view',
            'claims.analytics.view',
            'claims.audit.view',
        ]);

        // 7. Claims Administrator (Super User)
        $claimsAdmin = Role::create(['name' => 'claims_admin', 'guard_name' => 'web']);
        $claimsAdmin->givePermissionTo(Permission::all());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Delete roles
        $roles = ['desk_officer', 'doctor', 'pharmacist', 'claim_reviewer', 'claim_confirmer', 'claim_approver', 'claims_admin'];
        foreach ($roles as $role) {
            Role::where('name', $role)->delete();
        }

        // Delete permissions
        Permission::where('name', 'like', 'claims.%')->delete();
    }
};
