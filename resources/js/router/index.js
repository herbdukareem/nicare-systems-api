import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';
import { firstAccessiblePath } from '../navigation';

const LoginPage = () => import('../components/auth/LoginPage.vue');
const Dashboard = () => import('../components/dashboard/Dashboard.vue');
const EnrolleesPage = () => import('../components/enrollees/EnrolleesPage.vue');
const EnrolleeProfilePage = () => import('../components/enrollees/EnrolleeProfilePage.vue');
const FacilitiesPage = () => import('../components/facilities/FacilitiesPage.vue');
const UsersPage = () => import('../components/settings/UsersPage.vue');
const BenefactorsPage = () => import('../components/settings/BenefactorsPage.vue');
const RolesPermissionsPage = () => import('../components/settings/RolesPermissionsPage.vue');
const PendingEnrolleesPage = () => import('../components/enrollees/PendingEnrolleesPage.vue');
const EnrollmentApprovalPage = () => import('../components/enrollees/EnrollmentApprovalPage.vue');
const BulkEnrollmentSlipPage = () => import('../components/enrollees/BulkEnrollmentSlipPage.vue');
const SetupWorkspace = () => import('../components/setup/SetupWorkspace.vue');

// Helper: returns the best landing page for a user given their permissions
const getDefaultDashboard = (authStore) => {
  const path = firstAccessiblePath(authStore);
  if (path) return path;
  if (authStore.hasPermission('dashboard.view'))              return '/dashboard';
  if (authStore.hasPermission('dashboard.desk_officer.view')) return '/do-dashboard';
  if (authStore.hasPermission('dashboard.facility.view'))    return '/facility-dashboard';
  if (authStore.hasPermission('dashboard.pas.view'))         return '/pas';
  if (authStore.hasPermission('claims.dashboard.view'))      return '/claims';
  return '/dashboard';
};

const routes = [
  // ── Auth ───────────────────────────────────────────────────────────────────
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: { requiresAuth: false },
  },

  // ── Dashboards ─────────────────────────────────────────────────────────────
  {
    path: '/dashboard',
    name: 'dashboard',
    component: Dashboard,
    meta: {
      requiresAuth: true,
      permissions: ['dashboard.view'],
      title: 'Dashboard',
      breadcrumb: 'Dashboard',
    },
  },
  {
    path: '/do-dashboard',
    name: 'do-dashboard',
    component: () => import('../components/do/DODashboard.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['dashboard.desk_officer.view'],
      title: 'DO Dashboard',
      breadcrumb: 'DO Dashboard',
    },
  },
  {
    path: '/facility-dashboard',
    name: 'facility-dashboard',
    component: () => import('../components/dashboard/FacilityDashboard.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['dashboard.facility.view'],
      title: 'Facility Dashboard',
      breadcrumb: 'Facility Dashboard',
    },
  },

  // ── Enrollment ─────────────────────────────────────────────────────────────
  {
    path: '/enrollees',
    name: 'enrollees',
    component: EnrolleesPage,
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view'],
      title: 'Enrollees',
      breadcrumb: 'Enrollees',
    },
  },
  {
    path: '/enrollees/pending',
    name: 'pending-enrollees',
    component: PendingEnrolleesPage,
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view'],
      title: 'Pending Enrollees',
      breadcrumb: 'Pending Enrollees',
    },
  },
  {
    path: '/enrollees/approval',
    name: 'enrollee-approval',
    component: EnrollmentApprovalPage,
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.update', 'enrollee.approve'],
      title: 'Pending Approval',
      breadcrumb: 'Pending Approval',
    },
  },
  {
    path: '/enrollees/bulk-enrollment-slip',
    name: 'bulk-enrollment-slip',
    component: BulkEnrollmentSlipPage,
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view', 'enrollee.print-bulk-slip'],
      title: 'Bulk Enrollment Slip',
      breadcrumb: 'Bulk Enrollment Slip',
    },
  },
  {
    path: '/enrollees/bulk-id-card',
    name: 'bulk-id-card',
    component: () => import('../components/enrollees/BulkIdCardPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view'],
      title: 'Bulk ID Cards',
      breadcrumb: 'Bulk ID Cards',
    },
  },
  {
    path: '/enrollees/demo-enrollment',
    name: 'demo-enrollee-enrollment',
    component: () => import('../components/enrollees/DemoEnrollmentPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.create'],
      title: 'Demo Enrollee Enrollment',
      breadcrumb: 'Demo Enrollment',
    },
  },
  {
    path: '/enrollees/:id',
    name: 'enrollee-profile',
    component: EnrolleeProfilePage,
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view'],
      title: 'Enrollee Profile',
      breadcrumb: 'Enrollee Profile',
    },
  },
  {
    path: '/enrollment/mobile-sync',
    name: 'enrollment-mobile-sync',
    component: () => import('../components/common/ComingSoonPage.vue'),
    props: { title: 'Mobile Sync', subtitle: 'Sync mobile enrollment data', icon: 'mdi-cellphone-sync' },
    meta: {
      requiresAuth: true,
      permissions: ['mobile-sync.push', 'mobile-sync.status'],
      title: 'Mobile Sync',
      breadcrumb: 'Mobile Sync',
    },
  },
  {
    path: '/enrollment/change-facility',
    name: 'enrollment-change-facility',
    component: () => import('../components/common/ComingSoonPage.vue'),
    props: { title: 'Change of Facility', subtitle: 'Manage facility changes for enrollees', icon: 'mdi-hospital-marker' },
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.update'],
      title: 'Change of Facility',
      breadcrumb: 'Change of Facility',
    },
  },
  {
    path: '/enrollment/id-cards',
    name: 'enrollment-id-cards',
    component: () => import('../components/common/ComingSoonPage.vue'),
    props: { title: 'ID Card Printing', subtitle: 'Print and manage ID cards', icon: 'mdi-card-account-details' },
    meta: {
      requiresAuth: true,
      permissions: ['enrollees.view'],
      title: 'ID Card Printing',
      breadcrumb: 'ID Cards',
    },
  },

  // ── Facilities ─────────────────────────────────────────────────────────────
  {
    path: '/facilities',
    name: 'facilities',
    component: FacilitiesPage,
    meta: {
      requiresAuth: true,
      permissions: ['facilities.view'],
      title: 'Facilities',
      breadcrumb: 'Facilities',
    },
  },

  {
    path: '/setup',
    name: 'setup-workspace',
    component: SetupWorkspace,
    meta: {
      requiresAuth: true,
      permissions: ['setup.lga.view', 'setup.ward.view', 'setup.facility.view', 'setup.benefit-package.view', 'setup.funding-type.view', 'setup.benefactor.view', 'facilities.view', 'benefactor.view'],
      title: 'Setup',
      breadcrumb: 'Setup',
    },
  },
  {
    path: '/setup/:section',
    name: 'setup-section',
    component: SetupWorkspace,
    meta: {
      requiresAuth: true,
      permissions: ['setup.lga.view', 'setup.ward.view', 'setup.facility.view', 'setup.benefit-package.view', 'setup.funding-type.view', 'setup.benefactor.view', 'facilities.view', 'benefactor.view'],
      title: 'Setup',
      breadcrumb: 'Setup',
    },
  },
  {
    path: '/do-facilities',
    name: 'do-facility-assignments',
    component: () => import('../components/pas/DOFacilityAssignmentPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['facilities.assign'],
      title: 'DO Facility Assignments',
      breadcrumb: 'DO Facility Assignments',
    },
  },
  {
    path: '/do/assigned-referrals',
    name: 'do-assigned-referrals',
    component: () => import('../components/do/DOAssignedReferralsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['referrals.view'],
      title: 'Assigned Facilities Referrals',
      breadcrumb: 'Assigned Referrals',
    },
  },

  // ── PAS ────────────────────────────────────────────────────────────────────
  {
    path: '/pas',
    name: 'pas-dashboard',
    component: () => import('../components/pas/PASDashboard.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['dashboard.pas.view', 'referrals.view', 'pa_codes.view'],
      title: 'Pre-Authorization System',
      breadcrumb: 'PAS Dashboard',
    },
  },
  {
    path: '/pas/validate-utn',
    name: 'validate-utn',
    component: () => import('../components/pas/UTNValidationPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['utn.validate'],
      title: 'Validate UTN',
      breadcrumb: 'UTN Validation',
    },
  },
  {
    path: '/claims/referral-request',
    name: 'claims-referral-request',
    component: () => import('../components/claims/ReferralSubmissionPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['referrals.create'],
      title: 'Submit Referral to PAS',
      breadcrumb: 'Submit Referral',
    },
  },
  {
    path: '/pas/referral-management',
    name: 'referral-management',
    component: () => import('../components/pas/ReferralManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['referrals.view'],
      title: 'Referral Management',
      breadcrumb: 'Referral Management',
    },
  },
  {
    path: '/facility/admissions',
    name: 'facility-admissions',
    component: () => import('../components/facility/FacilityAdmissionManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['admissions.view', 'admissions.create'],
      title: 'Admission Management',
      breadcrumb: 'Admission Management',
    },
  },
  {
    path: '/pas/facility-pa-codes',
    name: 'facility-pa-codes',
    component: () => import('../components/pas/FacilityPACodeManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['pa_codes.view', 'pa_codes.request'],
      title: 'FU-PA Code Management',
      breadcrumb: 'FU-PA Code Management',
    },
  },
  {
    path: '/pas/fu-pa-request',
    name: 'fu-pa-code-request',
    component: () => import('../components/pas/FUPACodeRequestPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['pa_codes.request'],
      title: 'Request FU-PA Code',
      breadcrumb: 'FU-PA Code Request',
    },
  },
  {
    path: '/pas/fu-pa-approval',
    name: 'fu-pa-code-approval',
    component: () => import('../components/pas/FUPACodeApprovalPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['pa_codes.approve', 'pa_codes.reject'],
      title: 'FU-PA Code Approval',
      breadcrumb: 'FU-PA Code Approval',
    },
  },
  {
    path: '/document-requirements',
    name: 'document-requirements-management',
    component: () => import('../components/pas/DocumentRequirementsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['documents.manage', 'documents.requirements.manage'],
      title: 'Document Requirements',
      breadcrumb: 'Document Requirements',
    },
  },

  // ── Claims ─────────────────────────────────────────────────────────────────
  {
    path: '/claims',
    name: 'claims-dashboard',
    component: () => import('../components/claims/ClaimsDashboard.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.dashboard.view', 'claims.view'],
      title: 'Claims Dashboard',
      breadcrumb: 'Claims Dashboard',
    },
  },
  {
    path: '/facility/claims/submit',
    name: 'facility-claim-submission',
    component: () => import('../components/facility/FacilityClaimSubmissionPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.create', 'claims.submit'],
      title: 'Submit Claim',
      breadcrumb: 'Submit Claim',
    },
  },
  {
    path: '/claims/review',
    name: 'claims-review',
    component: () => import('../components/claims/ClaimsReviewPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.review', 'claims.confirm', 'claims.approve'],
      title: 'Review Claims',
      breadcrumb: 'Review Claims',
    },
  },
  {
    path: '/claims/approval',
    name: 'claims-approval',
    component: () => import('../components/claims/ClaimsApprovalPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.approve', 'claims.approver.approve'],
      title: 'Approve Claims',
      breadcrumb: 'Approve Claims',
    },
  },
  {
    path: '/claims/payment-batches',
    name: 'payment-batch-management',
    component: () => import('../components/claims/PaymentBatchManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['payment_batches.view', 'payment_batches.manage'],
      title: 'Payment Batch Management',
      breadcrumb: 'Payment Batches',
    },
  },
  {
    path: '/claims/history',
    name: 'claims-history',
    component: () => import('../components/claims/ClaimsHistoryPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.view'],
      title: 'Claims History',
      breadcrumb: 'Claims History',
    },
  },

  // ── Claims Automation ──────────────────────────────────────────────────────
  {
    path: '/claims/automation/admissions',
    name: 'claims-automation-admissions',
    component: () => import('../components/claims/automation/AdmissionManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['admissions.manage'],
      title: 'Admission Processing',
      breadcrumb: 'Admission Processing',
    },
  },
  {
    path: '/claims/automation/admissions/:id',
    name: 'claims-automation-admission-detail',
    component: () => import('../components/claims/automation/AdmissionDetailPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['admissions.manage', 'admissions.view'],
      title: 'Admission Details',
      breadcrumb: 'Admission Details',
    },
  },
  {
    path: '/claims/automation/process',
    name: 'claims-automation-process',
    component: () => import('../components/claims/automation/ClaimsProcessingPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.process', 'claims.automate'],
      title: 'Claims Processing',
      breadcrumb: 'Claims Processing',
    },
  },
  {
    path: '/claims/automation/process/:id',
    name: 'claims-automation-process-claim',
    component: () => import('../components/claims/automation/ClaimsProcessingPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['claims.process', 'claims.automate'],
      title: 'Process Claim',
      breadcrumb: 'Process Claim',
    },
  },
  {
    path: '/claims/automation/bundles',
    name: 'claims-automation-bundles',
    component: () => import('../components/claims/automation/BundleManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['bundles.manage'],
      title: 'Bundle Management',
      breadcrumb: 'Bundle Management',
    },
  },

  // ── Premium & Coverage ─────────────────────────────────────────────────────
  {
    path: '/premium',
    name: 'premium-dashboard',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'dashboard' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.plan.view', 'premium.purchase.view', 'coverage.view'],
      title: 'Premium Dashboard',
      breadcrumb: 'Premium Dashboard',
    },
  },
  {
    path: '/premium/plans',
    name: 'premium-plans',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'plans' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.plan.view'],
      title: 'Premium Plans',
      breadcrumb: 'Premium Plans',
    },
  },
  {
    path: '/premium/generate-pins',
    name: 'premium-generate-pins',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'generate-pins' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.pin.generate'],
      title: 'Generate Premium PINs',
      breadcrumb: 'Generate PINs',
    },
  },
  {
    path: '/premium/pins',
    name: 'premium-pin-inventory',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'inventory' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.pin.view'],
      title: 'PIN Inventory',
      breadcrumb: 'PIN Inventory',
    },
  },
  {
    path: '/premium/sell-pin',
    name: 'premium-sell-pin',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'sell-pin' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.pin.sell'],
      title: 'Sell Premium PIN',
      breadcrumb: 'Sell PIN',
    },
  },
  {
    path: '/premium/validate-pin',
    name: 'premium-validate-pin',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'validate-pin' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.pin.view'],
      title: 'Validate PIN',
      breadcrumb: 'Validate PIN',
    },
  },
  {
    path: '/premium/purchases',
    name: 'premium-purchases',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'purchases' },
    meta: {
      requiresAuth: true,
      permissions: ['premium.purchase.view'],
      title: 'Premium Purchases',
      breadcrumb: 'Premium Purchases',
    },
  },
  {
    path: '/premium/benefactors',
    name: 'premium-benefactors',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'benefactors' },
    meta: {
      requiresAuth: true,
      permissions: ['benefactor.view'],
      title: 'Benefactor Management',
      breadcrumb: 'Benefactors',
    },
  },
  {
    path: '/premium/payroll',
    name: 'premium-payroll',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'payroll' },
    meta: {
      requiresAuth: true,
      permissions: ['payroll-upload.view'],
      title: 'Payroll Upload',
      breadcrumb: 'Payroll Upload',
    },
  },
  {
    path: '/premium/eligibility',
    name: 'premium-eligibility',
    component: () => import('../components/premium/PremiumWorkspace.vue'),
    props: { mode: 'eligibility' },
    meta: {
      requiresAuth: true,
      permissions: ['eligibility.lookup'],
      title: 'Coverage Eligibility Lookup',
      breadcrumb: 'Eligibility Lookup',
    },
  },

  // ── Capitation ─────────────────────────────────────────────────────────────
  {
    path: '/capitation/generate',
    name: 'capitation-generate',
    component: () => import('../components/capitation/CapitationWorkspace.vue'),
    props: { mode: 'generate' },
    meta: {
      requiresAuth: true,
      permissions: ['capitation.create', 'capitation.compute'],
      title: 'Generate Capitation',
      breadcrumb: 'Generate Capitation',
    },
  },
  {
    path: '/capitation/review',
    name: 'capitation-review',
    component: () => import('../components/capitation/CapitationWorkspace.vue'),
    props: { mode: 'review' },
    meta: {
      requiresAuth: true,
      permissions: ['capitation.review'],
      title: 'Review Capitation',
      breadcrumb: 'Review Capitation',
    },
  },
  {
    path: '/capitation/approval',
    name: 'capitation-approval',
    component: () => import('../components/capitation/CapitationWorkspace.vue'),
    props: { mode: 'approval' },
    meta: {
      requiresAuth: true,
      permissions: ['capitation.approve', 'capitation.finalise'],
      title: 'Capitation Approval',
      breadcrumb: 'Capitation Approval',
    },
  },
  {
    path: '/capitation/payments',
    name: 'capitation-payments',
    component: () => import('../components/capitation/CapitationWorkspace.vue'),
    props: { mode: 'payments' },
    meta: {
      requiresAuth: true,
      permissions: ['capitation.pay'],
      title: 'Capitation Payments',
      breadcrumb: 'Capitation Payments',
    },
  },

  // ── Payments ───────────────────────────────────────────────────────────────
  {
    path: '/payments',
    name: 'payments-overview',
    component: () => import('../components/payments/PaymentsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['payments.view', 'payment_batches.view', 'payment_batches.manage'],
      title: 'Payments Overview',
      breadcrumb: 'Payments',
    },
  },
  {
    path: '/payments/process',
    name: 'payments-process',
    redirect: '/claims/payment-batches',
  },

  // ── Management ─────────────────────────────────────────────────────────────
  {
    path: '/management',
    name: 'management-dashboard',
    component: () => import('../components/management/ManagementDashboard.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['dashboard.management.view', 'cases.view', 'bundles.view', 'tariffs.view'],
      title: 'Management Module',
      breadcrumb: 'Management Dashboard',
    },
  },
  {
    path: '/management/cases',
    name: 'management-cases',
    component: () => import('../components/management/CaseManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['cases.view', 'cases.manage'],
      title: 'Case Management',
      breadcrumb: 'Case Management',
    },
  },
  {
    path: '/management/bundle-services',
    name: 'management-bundle-services',
    component: () => import('../components/management/BundleServicesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['bundle_services.view', 'bundle_services.manage'],
      title: 'Bundle Services',
      breadcrumb: 'Bundle Services',
    },
  },
  {
    path: '/management/bundle-components',
    name: 'management-bundle-components',
    component: () => import('../components/management/BundleComponentsManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['bundle_components.view', 'bundle_components.manage'],
      title: 'Bundle Components',
      breadcrumb: 'Bundle Components',
    },
  },
  {
    path: '/management/drugs',
    name: 'management-drugs',
    component: () => import('../components/management/DrugsManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['tariffs.view'],
      title: 'Tariff Items',
      breadcrumb: 'Tariff Items',
    },
  },
  {
    path: '/management/laboratories',
    name: 'management-laboratories',
    component: () => import('../components/management/LaboratoriesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['tariffs.view'],
      title: 'Laboratories Management',
      breadcrumb: 'Laboratories',
    },
  },
  {
    path: '/management/professional-services',
    name: 'management-professional-services',
    component: () => import('../components/management/ProfessionalServicesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['tariffs.view'],
      title: 'Professional Services',
      breadcrumb: 'Professional Services',
    },
  },

  // ── Reports & Analytics ────────────────────────────────────────────────────
  {
    path: '/reports',
    name: 'reports',
    component: () => import('../components/reports/ReportsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['reports.view', 'reports.generate', 'dashboard.view'],
      title: 'Reports',
      breadcrumb: 'Reports',
    },
  },
  {
    path: '/reports/financial',
    name: 'reports-financial',
    component: () => import('../components/reports/FinancialReportsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['reports.financial', 'reports.executive', 'reports.view'],
      title: 'Financial Reports',
      breadcrumb: 'Financial Reports',
    },
  },
  {
    path: '/analytics',
    name: 'analytics',
    component: () => import('../components/reports/AnalyticsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['analytics.view', 'dashboard.view'],
      title: 'Analytics',
      breadcrumb: 'Analytics',
    },
  },
  {
    path: '/audit-logs',
    name: 'audit-logs',
    component: () => import('../components/security/AuditLogsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['audit-logs.view', 'audit.view'],
      title: 'Audit Logs',
      breadcrumb: 'Audit Logs',
    },
  },

  // ── Feedback ───────────────────────────────────────────────────────────────
  {
    path: '/feedback',
    name: 'feedback-management',
    component: () => import('../components/feedback/FeedbackManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['feedback.view'],
      title: 'Feedback Management',
      breadcrumb: 'Feedback',
    },
  },
  {
    path: '/feedback/create',
    name: 'feedback-create',
    component: () => import('../components/feedback/FeedbackCreationPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['feedback.create'],
      title: 'Create Feedback',
      breadcrumb: 'Create Feedback',
    },
  },

  // ── Task Management ────────────────────────────────────────────────────────
  {
    path: '/task-management',
    name: 'task-management',
    component: () => import('../components/task-management/TaskManagementPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['tasks.view'],
      title: 'Task Management',
      breadcrumb: 'Task Management',
    },
  },

  // ── Administration ─────────────────────────────────────────────────────────
  {
    path: '/settings/users',
    name: 'settings-users',
    component: UsersPage,
    meta: {
      requiresAuth: true,
      permissions: ['users.view'],
      title: 'User Management',
      breadcrumb: 'Users',
    },
  },
  {
    path: '/settings/benefactors',
    name: 'settings-benefactors',
    component: BenefactorsPage,
    meta: {
      requiresAuth: true,
      permissions: ['benefactor.view'],
      title: 'Benefactors',
      breadcrumb: 'Benefactors',
    },
  },
  {
    path: '/settings/roles',
    name: 'settings-roles',
    component: RolesPermissionsPage,
    meta: {
      requiresAuth: true,
      permissions: ['roles.view', 'permissions.view'],
      title: 'Roles & Permissions',
      breadcrumb: 'Roles & Permissions',
    },
  },
  {
    path: '/settings/departments',
    name: 'settings-departments',
    component: () => import('../components/settings/DepartmentsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['users.view', 'departments.view', 'departments.manage'],
      title: 'Departments',
      breadcrumb: 'Departments',
    },
  },
  {
    path: '/settings/designations',
    name: 'settings-designations',
    component: () => import('../components/settings/DesignationsPage.vue'),
    meta: {
      requiresAuth: true,
      permissions: ['users.view', 'designations.view', 'designations.manage'],
      title: 'Designations',
      breadcrumb: 'Designations',
    },
  },

  // ── Legacy / device routes ─────────────────────────────────────────────────
  {
    path: '/devices/manage',
    name: 'devices-manage',
    component: () => import('../components/common/ComingSoonPage.vue'),
    props: { title: 'Manage Devices', subtitle: 'Device management and configuration', icon: 'mdi-tablet' },
    meta: { requiresAuth: true, permissions: ['users.view'], title: 'Manage Devices', breadcrumb: 'Devices' },
  },
  {
    path: '/devices/config',
    name: 'devices-config',
    component: () => import('../components/common/ComingSoonPage.vue'),
    props: { title: 'Enrollment Configuration', subtitle: 'Configure enrollment settings', icon: 'mdi-cog' },
    meta: { requiresAuth: true, permissions: ['users.view'], title: 'Enrollment Config', breadcrumb: 'Config' },
  },

  // ── Default ────────────────────────────────────────────────────────────────
  { path: '/', redirect: '/dashboard' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// ─── Navigation guard ──────────────────────────────────────────────────────────
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();
  const uiStore = useUiStore();
  uiStore.setRouteLoading(true);

  try {
  // Public routes
  if (!to.meta.requiresAuth) {
    if (to.name === 'login' && authStore.isAuthenticated) {
      next({ path: getDefaultDashboard(authStore), replace: true });
      return;
    }
    next();
    return;
  }

  // Ensure auth is initialized
  if (!authStore.isAuthenticated && !authStore._initializing) {
    await authStore.initializeAuth();
  }

  if (!authStore.isAuthenticated) {
    next({ path: '/login', replace: true });
    return;
  }

  // ── Permission check ────────────────────────────────────────────────────────
  const requiredPermissions = to.meta.permissions;

  if (requiredPermissions && requiredPermissions.length > 0) {
    const hasAccess = requiredPermissions.some((p) => authStore.hasPermission(p));

    if (!hasAccess) {
      const target = getDefaultDashboard(authStore);
      // Prevent redirect loops: if we're already going to the target, let it through
      if (to.path !== target) {
        next({ path: target, replace: true });
        return;
      }
    }
  }

  next();
  } catch (error) {
    uiStore.setRouteLoading(false);
    next(error);
  }
});

// ─── Page titles ───────────────────────────────────────────────────────────────
router.afterEach((to) => {
  const uiStore = useUiStore();
  uiStore.setRouteLoading(false);
  document.title = to.meta?.title ? `${to.meta.title} — NGSCHA` : 'NGSCHA';
});

router.onError(() => {
  const uiStore = useUiStore();
  uiStore.setRouteLoading(false);
});

export default router;
