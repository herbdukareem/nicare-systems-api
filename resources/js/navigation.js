export const navigationItems = [
  {
    name: 'Dashboards',
    icon: 'mdi-view-dashboard-outline',
    children: [
      { name: 'Main', path: '/dashboard', icon: 'mdi-home-outline', permissions: ['dashboard.view'] },
      { name: 'Desk Officer', path: '/do-dashboard', icon: 'mdi-desk', permissions: ['dashboard.desk_officer.view'] },
      { name: 'Facility', path: '/facility-dashboard', icon: 'mdi-hospital-building', permissions: ['dashboard.facility.view'] },
      { name: 'PAS', path: '/pas', icon: 'mdi-shield-check-outline', permissions: ['dashboard.pas.view', 'referrals.view', 'pa_codes.view'] },
      { name: 'Claims', path: '/claims', icon: 'mdi-file-chart-outline', permissions: ['claims.dashboard.view', 'claims.view'] },
      { name: 'Management', path: '/management', icon: 'mdi-tune-variant', permissions: ['dashboard.management.view', 'cases.view', 'bundles.view', 'tariffs.view'] },
    ],
  },
  {
    name: 'Enrollment',
    icon: 'mdi-account-multiple-plus-outline',
    children: [
      { name: 'All Enrollees', path: '/enrollees', icon: 'mdi-account-group-outline', permissions: ['enrollees.view'] },
      { name: 'Demo Enrollment', path: '/enrollees/demo-enrollment', icon: 'mdi-account-plus-outline', permissions: ['enrollees.create'] },
      { name: 'Pending Approval', path: '/enrollees/approval', icon: 'mdi-account-check-outline', permissions: ['enrollees.update', 'enrollee.approve'] },
      { name: 'Bulk Enrollment Slip', path: '/enrollees/bulk-enrollment-slip', icon: 'mdi-file-document-multiple-outline', permissions: ['enrollees.view', 'enrollee.print-bulk-slip'] },
      { name: 'Bulk ID Cards', path: '/enrollees/bulk-id-card', icon: 'mdi-card-account-details-star-outline', permissions: ['enrollees.view'] },
      { name: 'Mobile Sync', path: '/enrollment/mobile-sync', icon: 'mdi-cellphone-sync', permissions: ['mobile-sync.push', 'mobile-sync.status'] },
      { name: 'Change Facility', path: '/enrollment/change-facility', icon: 'mdi-hospital-marker', permissions: ['enrollees.update'] },
    ],
  },
  {
    name: 'Setup',
    icon: 'mdi-cog-outline',
    children: [
      { name: 'Locations', path: '/setup/locations', icon: 'mdi-map-marker-multiple-outline', permissions: ['setup.lga.view', 'setup.ward.view'] },
      { name: 'Facilities', path: '/setup/facilities', icon: 'mdi-hospital-box-outline', permissions: ['setup.facility.view', 'facilities.view'] },
      { name: 'Benefit Packages', path: '/setup/benefit-packages', icon: 'mdi-package-variant-closed-check', permissions: ['setup.benefit-package.view'] },
      { name: 'Funding Types', path: '/setup/funding-types', icon: 'mdi-cash-multiple', permissions: ['setup.funding-type.view'] },
      { name: 'Benefactors', path: '/setup/benefactors', icon: 'mdi-account-heart-outline', permissions: ['setup.benefactor.view', 'benefactor.view'] },
    ],
  },
  {
    name: 'Facilities',
    icon: 'mdi-hospital-building',
    children: [
      { name: 'Facilities', path: '/facilities', icon: 'mdi-hospital-box-outline', permissions: ['facilities.view'] },
      { name: 'DO Assignments', path: '/do-facilities', icon: 'mdi-account-hard-hat-outline', permissions: ['facilities.assign'] },
      { name: 'Assigned Referrals', path: '/do/assigned-referrals', icon: 'mdi-clipboard-list-outline', permissions: ['facilities.view-own', 'referrals.view'] },
    ],
  },
  {
    name: 'Premium & Enrollment',
    icon: 'mdi-shield-star-outline',
    children: [
      { name: 'Overview', path: '/premium', icon: 'mdi-view-dashboard-outline', permissions: ['premium.plan.view', 'premium.purchase.view', 'coverage.view'] },
      { name: 'Plans', path: '/premium/plans', icon: 'mdi-format-list-bulleted-square', permissions: ['premium.plan.view'] },
      { name: 'Generate PINs', path: '/premium/generate-pins', icon: 'mdi-key-plus', permissions: ['premium.pin.generate'] },
      { name: 'PIN Inventory', path: '/premium/pins', icon: 'mdi-key-chain', permissions: ['premium.pin.view'] },
      { name: 'Sell PIN', path: '/premium/sell-pin', icon: 'mdi-point-of-sale', permissions: ['premium.pin.sell'] },
      { name: 'Validate PIN', path: '/premium/validate-pin', icon: 'mdi-key-check', permissions: ['premium.pin.view'] },
      { name: 'Purchases', path: '/premium/purchases', icon: 'mdi-receipt-text-outline', permissions: ['premium.purchase.view'] },
      { name: 'Benefactors', path: '/premium/benefactors', icon: 'mdi-account-heart-outline', permissions: ['benefactor.view', 'benefactors.view'] },
      { name: 'Payroll', path: '/premium/payroll', icon: 'mdi-upload-outline', permissions: ['payroll-upload.view'] },
      { name: 'Eligibility', path: '/premium/eligibility', icon: 'mdi-magnify-scan', permissions: ['eligibility.lookup'] },
    ],
  },
  {
    name: 'PAS',
    icon: 'mdi-shield-check-outline',
    children: [
      { name: 'Validate UTN', path: '/pas/validate-utn', icon: 'mdi-barcode-scan', permissions: ['utn.validate'] },
      { name: 'Submit Referral', path: '/claims/referral-request', icon: 'mdi-account-arrow-right-outline', permissions: ['referrals.create'] },
      { name: 'Referrals', path: '/pas/referral-management', icon: 'mdi-file-document-check-outline', permissions: ['referrals.view'] },
      { name: 'Admissions', path: '/facility/admissions', icon: 'mdi-bed-outline', permissions: ['admissions.view', 'admissions.create'] },
      { name: 'Facility PA Codes', path: '/pas/facility-pa-codes', icon: 'mdi-shield-edit-outline', permissions: ['pa_codes.view', 'pa_codes.request'] },
      { name: 'Request FU-PA', path: '/pas/fu-pa-request', icon: 'mdi-shield-plus-outline', permissions: ['pa_codes.request'] },
      { name: 'Approve FU-PA', path: '/pas/fu-pa-approval', icon: 'mdi-check-decagram-outline', permissions: ['pa_codes.approve', 'pa_codes.reject'] },
      { name: 'Documents', path: '/document-requirements', icon: 'mdi-file-document-multiple-outline', permissions: ['documents.manage', 'documents.requirements.manage'] },
    ],
  },
  {
    name: 'Claims',
    icon: 'mdi-file-document-multiple-outline',
    children: [
      { name: 'Submit Claim', path: '/facility/claims/submit', icon: 'mdi-file-plus-outline', permissions: ['claims.create', 'claims.submit'] },
      { name: 'Review', path: '/claims/review', icon: 'mdi-file-search-outline', permissions: ['claims.review', 'claims.confirm', 'claims.approve'] },
      { name: 'Approval', path: '/claims/approval', icon: 'mdi-file-sign', permissions: ['claims.approve', 'claims.approver.approve'] },
      { name: 'Payment Batches', path: '/claims/payment-batches', icon: 'mdi-cash-multiple', permissions: ['payment_batches.view', 'payment_batches.manage'] },
      { name: 'History', path: '/claims/history', icon: 'mdi-history', permissions: ['claims.view'] },
      { name: 'Admissions Processing', path: '/claims/automation/admissions', icon: 'mdi-bed-clock', permissions: ['admissions.manage'] },
      { name: 'Claims Processing', path: '/claims/automation/process', icon: 'mdi-cog-transfer-outline', permissions: ['claims.process', 'claims.automate'] },
      { name: 'Automation Bundles', path: '/claims/automation/bundles', icon: 'mdi-package-variant-closed', permissions: ['bundles.manage'] },
    ],
  },
  {
    name: 'Capitation',
    icon: 'mdi-calculator-variant-outline',
    children: [
      { name: 'Generate', path: '/capitation/generate', icon: 'mdi-plus-circle-outline', permissions: ['capitation.create', 'capitation.compute'] },
      { name: 'Review', path: '/capitation/review', icon: 'mdi-eye-outline', permissions: ['capitation.review'] },
      { name: 'Approval', path: '/capitation/approval', icon: 'mdi-check-circle-outline', permissions: ['capitation.approve', 'capitation.finalise'] },
      { name: 'Payments', path: '/capitation/payments', icon: 'mdi-receipt-text-outline', permissions: ['capitation.pay'] },
    ],
  },
  {
    name: 'Payments',
    icon: 'mdi-cash-fast',
    children: [
      { name: 'Overview', path: '/payments', icon: 'mdi-cash-multiple', permissions: ['payments.view', 'payment_batches.view', 'payment_batches.manage'] },
      { name: 'Process', path: '/payments/process', icon: 'mdi-cog-outline', permissions: ['payments.process', 'payment_batches.manage'] },
    ],
  },
  {
    name: 'Management',
    icon: 'mdi-tune-variant',
    children: [
      { name: 'Cases', path: '/management/cases', icon: 'mdi-file-document-multiple-outline', permissions: ['cases.view', 'cases.manage'] },
      { name: 'Bundle Services', path: '/management/bundle-services', icon: 'mdi-package-variant', permissions: ['bundle_services.view', 'bundle_services.manage'] },
      { name: 'Bundle Components', path: '/management/bundle-components', icon: 'mdi-package-variant-closed', permissions: ['bundle_components.view', 'bundle_components.manage'] },
      { name: 'Tariff Items', path: '/management/drugs', icon: 'mdi-format-list-numbered', permissions: ['tariffs.view'] },
      { name: 'Laboratories', path: '/management/laboratories', icon: 'mdi-flask-outline', permissions: ['tariffs.view'] },
      { name: 'Professional Services', path: '/management/professional-services', icon: 'mdi-stethoscope', permissions: ['tariffs.view'] },
    ],
  },
  {
    name: 'Reports',
    icon: 'mdi-chart-box-outline',
    children: [
      { name: 'Reports', path: '/reports', icon: 'mdi-file-chart-outline', permissions: ['reports.view', 'reports.generate', 'dashboard.view'] },
      { name: 'Financial', path: '/reports/financial', icon: 'mdi-finance', permissions: ['reports.financial', 'reports.executive', 'reports.view'] },
      { name: 'Analytics', path: '/analytics', icon: 'mdi-chart-line', permissions: ['analytics.view', 'dashboard.view'] },
      { name: 'Audit Logs', path: '/audit-logs', icon: 'mdi-clipboard-text-clock-outline', permissions: ['audit-logs.view', 'audit.view'] },
    ],
  },
  {
    name: 'Workdesk',
    icon: 'mdi-briefcase-outline',
    children: [
      { name: 'Feedback', path: '/feedback', icon: 'mdi-comment-text-multiple-outline', permissions: ['feedback.view'] },
      { name: 'Create Feedback', path: '/feedback/create', icon: 'mdi-message-plus-outline', permissions: ['feedback.create'] },
      { name: 'Tasks', path: '/task-management', icon: 'mdi-checkbox-marked-circle-outline', permissions: ['tasks.view'] },
    ],
  },
  {
    name: 'Administration',
    icon: 'mdi-shield-account-outline',
    children: [
      { name: 'Users', path: '/settings/users', icon: 'mdi-account-multiple-outline', permissions: ['users.view'] },
      { name: 'Roles & Permissions', path: '/settings/roles', icon: 'mdi-shield-lock-outline', permissions: ['roles.view', 'permissions.view'] },
      { name: 'Benefactors', path: '/settings/benefactors', icon: 'mdi-account-heart-outline', permissions: ['benefactor.view', 'benefactors.view'] },
      { name: 'Departments', path: '/settings/departments', icon: 'mdi-office-building-outline', permissions: ['departments.view', 'departments.manage', 'users.view'] },
      { name: 'Designations', path: '/settings/designations', icon: 'mdi-badge-account-outline', permissions: ['designations.view', 'designations.manage', 'users.view'] },
      { name: 'NIN Provider', path: '/settings/nin-provider', icon: 'mdi-card-account-details-outline', permissions: ['settings.nin.manage', 'settings.edit'] },
      { name: 'Devices', path: '/devices/manage', icon: 'mdi-tablet-dashboard', permissions: ['users.view'] },
      { name: 'Enrollment Config', path: '/devices/config', icon: 'mdi-cog-outline', permissions: ['users.view'] },
    ],
  },
];

export const flattenNavigation = (items = navigationItems) =>
  items.flatMap((item) => (item.children ? item.children : [item]));

export const canAccessNavigationItem = (authStore, item) => {
  if (!item.permissions || item.permissions.length === 0) return true;
  return item.permissions.some((permission) => authStore.hasPermission(permission));
};

export const firstAccessiblePath = (authStore) => {
  const item = flattenNavigation().find((entry) => canAccessNavigationItem(authStore, entry));
  return item?.path || '/dashboard';
};
