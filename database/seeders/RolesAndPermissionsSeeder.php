<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $hasCategoryColumn = Schema::hasColumn('permissions', 'category');

        $permissionsByCategory = [
            'Dashboard' => [
                'dashboard.view',
                'dashboard.facility.view',
                'dashboard.desk_officer.view',
                'dashboard.claims.view',
                'dashboard.pas.view',
                'dashboard.management.view',
                'claims.dashboard.view',
            ],
            'Enrollment' => [
                'enrollee.view',
                'enrollee.create',
                'enrollee.update',
                'enrollee.delete',
                'enrollee.approve',
                'enrollee.nin.verify',
                'enrollee.print-id-card',
                'enrollee.print-bulk-slip',
                'enrollees.view',
                'enrollees.create',
                'enrollees.edit',
                'enrollees.update',
                'enrollees.delete',
                'enrollees.export',
                'enrollees.import',
                'mobile-sync.push',
                'mobile-sync.status',
            ],
            'Setup' => [
                'setup.lga.view',
                'setup.lga.create',
                'setup.lga.update',
                'setup.lga.delete',
                'setup.ward.view',
                'setup.ward.create',
                'setup.ward.update',
                'setup.ward.delete',
                'setup.facility.view',
                'setup.facility.create',
                'setup.facility.update',
                'setup.facility.delete',
                'setup.benefit-package.view',
                'setup.benefit-package.create',
                'setup.benefit-package.update',
                'setup.benefit-package.delete',
                'setup.funding-type.view',
                'setup.funding-type.create',
                'setup.funding-type.update',
                'setup.funding-type.delete',
                'setup.benefactor.view',
                'setup.benefactor.create',
                'setup.benefactor.update',
                'setup.benefactor.delete',
            ],
            'Facilities' => [
                'facilities.view',
                'facilities.create',
                'facilities.edit',
                'facilities.update',
                'facilities.delete',
                'facilities.assign',
                'facilities.view-own',
                'facility.view-own',
            ],
            'Premium & Coverage' => [
                'premium.plan.view',
                'premium.plan.create',
                'premium.plan.update',
                'premium.plan.delete',
                'premium.pin.generate',
                'premium.pin.view',
                'premium.pin.sell',
                'premium.pin.use',
                'premium.pin.cancel',
                'premium.purchase.view',
                'premium.purchase.create',
                'premium.purchase.confirm',
                'premium.purchase.cancel',
                'coverage.view',
                'coverage.activate',
                'coverage.suspend',
                'coverage.renew',
                'benefactor.view',
                'benefactor.create',
                'benefactor.update',
                'benefactor.delete',
                'benefactors.view',
                'benefactors.create',
                'benefactors.edit',
                'benefactors.delete',
                'group-enrollment.view',
                'group-enrollment.create',
                'group-enrollment.update',
                'payroll-upload.view',
                'payroll-upload.create',
                'payroll-upload.approve',
                'subsidy-batch.view',
                'subsidy-batch.create',
                'subsidy-batch.approve',
                'eligibility.lookup',
            ],
            'PAS' => [
                'pas.view',
                'pas.create',
                'pas.edit',
                'pas.delete',
                'pas.approve',
                'referrals.create',
                'referrals.view',
                'referrals.edit',
                'referrals.submit',
                'referrals.approve',
                'referrals.reject',
                'referrals.deny',
                'referrals.print',
                'referrals.manage',
                'pa_codes.create',
                'pa_codes.view',
                'pa_codes.request',
                'pa_codes.approve',
                'pa_codes.reject',
                'pa_codes.manage',
                'admissions.create',
                'admissions.view',
                'admissions.edit',
                'admissions.update',
                'admissions.discharge',
                'admissions.manage',
                'utn.validate',
                'utn.view',
                'documents.view',
                'documents.upload',
                'documents.download',
                'documents.delete',
                'documents.manage',
                'documents.requirements.manage',
            ],
            'Claims' => [
                'claims.create',
                'claims.view',
                'claims.edit',
                'claims.delete',
                'claims.submit',
                'claims.withdraw',
                'claims.process',
                'claims.automate',
                'claims.review',
                'claims.confirm',
                'claims.approve',
                'claims.reject',
                'claims.export',
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
                'payment_batches.view',
                'payment_batches.manage',
                'payment_batches.create',
                'payment_batches.approve',
            ],
            'Capitation & Payments' => [
                'capitation.view',
                'capitation.create',
                'capitation.compute',
                'capitation.review',
                'capitation.approve',
                'capitation.pay',
                'capitation.finalise',
                'capitation.export',
                'payments.view',
                'payments.create',
                'payments.process',
                'payments.finalise',
                'payments.export',
                'payments.view-own',
            ],
            'Management' => [
                'tariffs.view',
                'tariffs.create',
                'tariffs.edit',
                'tariffs.delete',
                'bundles.view',
                'bundles.create',
                'bundles.edit',
                'bundles.delete',
                'bundles.manage',
                'bundle_services.view',
                'bundle_services.manage',
                'bundle_components.view',
                'bundle_components.manage',
                'cases.view',
                'cases.manage',
            ],
            'Reports & Audit' => [
                'reports.view',
                'reports.generate',
                'reports.export',
                'reports.financial',
                'reports.executive',
                'analytics.view',
                'audit.view',
                'audit-logs.view',
            ],
            'Administration' => [
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'roles.view',
                'roles.create',
                'roles.edit',
                'roles.delete',
                'permissions.view',
                'permissions.create',
                'permissions.edit',
                'permissions.delete',
                'departments.view',
                'departments.manage',
                'designations.view',
                'designations.manage',
                'settings.view',
                'settings.edit',
                'settings.nin.manage',
                'impersonate_users',
            ],
            'Feedback & Tasks' => [
                'feedback.view',
                'feedback.create',
                'feedback.respond',
                'feedback.manage',
                'tasks.view',
                'tasks.create',
                'tasks.edit',
                'tasks.delete',
                'tasks.assign',
            ],
        ];

        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $name) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    array_filter([
                        'label' => $this->label($name),
                        'description' => 'Allows ' . strtolower(str_replace(['.', '_', '-'], ' ', $name)) . '.',
                        'category' => $hasCategoryColumn ? $category : null,
                    ], static fn ($value) => $value !== null)
                );
            }
        }

        $all = Permission::pluck('name')->all();

        $roles = [
            'Super Admin' => [
                'label' => 'Super Admin',
                'description' => 'Full system access.',
                'permissions' => $all,
            ],
            'admin' => [
                'label' => 'Administrator',
                'description' => 'Full operational administrator.',
                'permissions' => $all,
            ],
            'scheme-admin' => [
                'label' => 'Scheme Admin',
                'description' => 'Manages scheme operations, users, enrolment, coverage, claims, and reporting.',
                'permissions' => array_values(array_unique(array_merge($this->pick($all, [
                    'dashboard.', 'enrollee.', 'enrollees.', 'setup.', 'facilities.', 'premium.', 'coverage.', 'benefactor.', 'benefactors.',
                    'group-enrollment.', 'payroll-upload.', 'subsidy-batch.', 'eligibility.', 'referrals.',
                    'pa_codes.', 'admissions.', 'utn.', 'claims.', 'payment_batches.', 'capitation.', 'payments.',
                    'reports.', 'analytics.', 'feedback.', 'users.view', 'roles.view', 'permissions.view',
                ]), ['settings.nin.manage'])),
            ],
            'enrollment-officer' => [
                'label' => 'Enrollment Officer',
                'description' => 'Registers and maintains enrollees.',
                'permissions' => [
                    'dashboard.view', 'enrollees.view', 'enrollees.create', 'enrollees.update',
                    'enrollee.view', 'enrollee.create', 'enrollee.update', 'enrollee.nin.verify', 'enrollee.print-id-card', 'enrollee.print-bulk-slip',
                    'enrollees.import', 'enrollees.export', 'facilities.view', 'coverage.view',
                    'eligibility.lookup', 'reports.view',
                ],
            ],
            'mobile-enrollment-officer' => [
                'label' => 'Mobile Enrollment Officer',
                'description' => 'Captures enrollee data from mobile devices.',
                'permissions' => ['enrollees.create', 'mobile-sync.push', 'mobile-sync.status'],
            ],
            'desk-officer' => [
                'label' => 'Desk Officer',
                'description' => 'Facility desk officer for enrollee and PAS workflows.',
                'permissions' => [
                    'dashboard.desk_officer.view', 'facilities.view', 'facilities.view-own',
                    'enrollees.view', 'enrollees.create', 'referrals.view', 'referrals.create',
                    'referrals.submit', 'referrals.print', 'pa_codes.view', 'pa_codes.request',
                    'admissions.view', 'admissions.create', 'utn.validate', 'utn.view',
                    'claims.create', 'claims.view', 'claims.submit', 'documents.view',
                    'documents.upload', 'documents.download', 'reports.view',
                ],
            ],
            'desk_officer' => [
                'label' => 'Desk Officer',
                'description' => 'Legacy alias for Desk Officer.',
                'permissions' => [
                    'dashboard.desk_officer.view', 'facilities.view', 'facilities.view-own',
                    'enrollees.view', 'enrollees.create', 'referrals.view', 'referrals.create',
                    'referrals.submit', 'pa_codes.view', 'pa_codes.request', 'admissions.view',
                    'admissions.create', 'utn.validate', 'claims.create', 'claims.view', 'claims.submit',
                ],
            ],
            'facility-admin' => [
                'label' => 'Facility Admin',
                'description' => 'Manages facility PAS, admission, and claim submission workflows.',
                'permissions' => [
                    'dashboard.facility.view', 'facilities.view', 'facility.view-own', 'referrals.create',
                    'referrals.view', 'referrals.submit', 'referrals.print', 'pa_codes.view',
                    'pa_codes.request', 'admissions.create', 'admissions.view', 'admissions.update',
                    'admissions.discharge', 'utn.validate', 'claims.create', 'claims.view',
                    'claims.submit', 'documents.view', 'documents.upload', 'documents.download',
                    'payments.view-own', 'feedback.view', 'feedback.create', 'reports.view',
                ],
            ],
            'facility_admin' => [
                'label' => 'Facility Admin',
                'description' => 'Legacy alias for Facility Admin.',
                'permissions' => [
                    'dashboard.facility.view', 'facilities.view', 'facility.view-own', 'referrals.create',
                    'referrals.view', 'referrals.submit', 'pa_codes.view', 'pa_codes.request',
                    'admissions.create', 'admissions.view', 'claims.create', 'claims.view',
                    'claims.submit', 'documents.view', 'documents.upload',
                ],
            ],
            'facility_user' => [
                'label' => 'Facility User',
                'description' => 'Facility user for routine submissions.',
                'permissions' => [
                    'dashboard.facility.view', 'facility.view-own', 'referrals.create', 'referrals.view',
                    'referrals.submit', 'pa_codes.view', 'pa_codes.request', 'admissions.create',
                    'admissions.view', 'utn.validate', 'claims.create', 'claims.view',
                    'claims.submit', 'documents.view', 'documents.upload', 'feedback.view',
                    'feedback.create',
                ],
            ],
            'pa-officer' => [
                'label' => 'PA Officer',
                'description' => 'Reviews referrals, PA codes, and admissions.',
                'permissions' => [
                    'dashboard.pas.view', 'referrals.create', 'referrals.view', 'referrals.submit',
                    'referrals.approve', 'referrals.reject', 'referrals.deny', 'referrals.print',
                    'referrals.manage', 'pa_codes.view', 'pa_codes.request', 'pa_codes.approve',
                    'pa_codes.reject', 'pa_codes.manage', 'admissions.view', 'admissions.manage',
                    'utn.validate', 'utn.view', 'claims.view', 'documents.view', 'documents.manage',
                    'reports.view', 'feedback.view', 'feedback.respond',
                ],
            ],
            'PA Officer' => [
                'label' => 'PA Officer',
                'description' => 'Legacy alias for PA Officer.',
                'permissions' => [
                    'dashboard.pas.view', 'referrals.view', 'referrals.approve', 'referrals.reject',
                    'pa_codes.view', 'pa_codes.approve', 'pa_codes.reject', 'admissions.view',
                    'admissions.manage', 'utn.validate', 'documents.view',
                ],
            ],
            'claims-officer' => [
                'label' => 'Claims Officer',
                'description' => 'Processes and reviews claims.',
                'permissions' => [
                    'dashboard.claims.view', 'claims.dashboard.view', 'claims.view', 'claims.review',
                    'claims.process', 'claims.automate', 'claims.approve', 'claims.reject',
                    'admissions.view', 'referrals.view', 'enrollees.view', 'payment_batches.view',
                    'reports.view', 'analytics.view',
                ],
            ],
            'claims_officer' => [
                'label' => 'Claims Officer',
                'description' => 'Legacy alias for Claims Officer.',
                'permissions' => [
                    'dashboard.claims.view', 'claims.dashboard.view', 'claims.view', 'claims.process',
                    'claims.review', 'referrals.view', 'pa_codes.view', 'admissions.view',
                    'documents.view', 'reports.view',
                ],
            ],
            'claim_reviewer' => [
                'label' => 'Claim Reviewer',
                'description' => 'Claim review workflow role.',
                'permissions' => [
                    'dashboard.claims.view', 'claims.dashboard.view', 'claims.view',
                    'claims.reviewer.review', 'claims.reviewer.approve', 'claims.reviewer.reject',
                    'claims.process', 'reports.view', 'analytics.view',
                ],
            ],
            'claim_confirmer' => [
                'label' => 'Claim Confirmer',
                'description' => 'Claim confirmation workflow role.',
                'permissions' => [
                    'dashboard.claims.view', 'claims.dashboard.view', 'claims.view',
                    'claims.confirmer.review', 'claims.confirmer.confirm', 'claims.confirmer.reject',
                    'reports.view', 'analytics.view',
                ],
            ],
            'claim_approver' => [
                'label' => 'Claim Approver',
                'description' => 'Claim approval and payment authorization role.',
                'permissions' => [
                    'dashboard.claims.view', 'claims.dashboard.view', 'claims.view',
                    'claims.approver.review', 'claims.approver.approve', 'claims.approver.reject',
                    'claims.payment.authorize', 'payment_batches.approve', 'reports.view',
                    'analytics.view', 'audit.view',
                ],
            ],
            'finance-officer' => [
                'label' => 'Finance Officer',
                'description' => 'Manages payments, purchases, and financial reporting.',
                'permissions' => [
                    'payments.view', 'payments.create', 'payments.process', 'payments.finalise',
                    'payments.export', 'payment_batches.view', 'payment_batches.manage',
                    'premium.purchase.view', 'premium.purchase.create', 'premium.purchase.confirm',
                    'premium.purchase.cancel', 'premium.pin.view', 'premium.pin.sell',
                    'coverage.view', 'coverage.renew', 'capitation.view', 'capitation.compute',
                    'capitation.review', 'capitation.approve', 'capitation.pay',
                    'capitation.finalise', 'capitation.export', 'reports.financial', 'claims.view',
                ],
            ],
            'tariff_manager' => [
                'label' => 'Tariff Manager',
                'description' => 'Manages cases, tariffs, and bundle definitions.',
                'permissions' => [
                    'dashboard.management.view', 'tariffs.view', 'tariffs.create', 'tariffs.edit',
                    'tariffs.delete', 'bundles.view', 'bundles.create', 'bundles.edit',
                    'bundles.delete', 'bundles.manage', 'bundle_services.view',
                    'bundle_services.manage', 'bundle_components.view', 'bundle_components.manage',
                    'cases.view', 'cases.manage', 'reports.view',
                ],
            ],
            'auditor' => [
                'label' => 'Auditor',
                'description' => 'Read-only audit and reporting access.',
                'permissions' => [
                    'audit-logs.view', 'audit.view', 'reports.view', 'reports.export',
                    'enrollees.view', 'claims.view', 'payments.view', 'referrals.view',
                ],
            ],
        ];

        foreach ($roles as $name => $data) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'label' => $data['label'],
                    'description' => $data['description'],
                ]
            );

            $ids = Permission::whereIn('name', $data['permissions'])->pluck('id');
            $role->permissions()->sync($ids);
        }
    }

    /**
     * @param array<int, string> $permissions
     * @param array<int, string> $prefixes
     * @return array<int, string>
     */
    private function pick(array $permissions, array $prefixes): array
    {
        return array_values(array_filter($permissions, function (string $permission) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($permission, $prefix) || $permission === $prefix) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function label(string $permission): string
    {
        return str($permission)
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }
}
