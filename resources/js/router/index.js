import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Components
import LoginPage from '../components/auth/LoginPage.vue';
import Dashboard from '../components/dashboard/Dashboard.vue';
import EnrolleesPage from '../components/enrollees/EnrolleesPage.vue';
import EnrolleeProfilePage from '../components/enrollees/EnrolleeProfilePage.vue';
import FacilitiesPage from '../components/facilities/FacilitiesPage.vue';
import UsersPage from '../components/settings/UsersPage.vue';
import BenefactorsPage from '../components/settings/BenefactorsPage.vue';
import RolesPermissionsPage from '../components/settings/RolesPermissionsPage.vue';
import ComingSoonPage from '../components/common/ComingSoonPage.vue';
import PendingEnrolleesPage from '../components/enrollees/PendingEnrolleesPage.vue';

const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: { requiresAuth: false },
  },

  // Dashboard Routes
  {
    path: '/dashboard',
    name: 'dashboard',
    component: Dashboard,
    meta: {
      requiresAuth: true,
      title: 'Dashboard',
      description: 'Overview of enrollees and system statistics',
      breadcrumb: 'Dashboard'
    },
  },
  {
    path: '/do-dashboard',
    name: 'do-dashboard',
    component: () => import('../components/do/DODashboard.vue'),
    meta: {
      requiresAuth: true,
      roles: ['desk_officer', 'Super Admin'],
      title: 'DO Dashboard',
      description: 'Desk Officer dashboard for managing assigned facilities',
      breadcrumb: 'DO Dashboard'
    },
  },
  {
    path: '/facility-dashboard',
    name: 'facility-dashboard',
    component: () => import('../components/dashboard/FacilityDashboard.vue'),
    meta: {
      requiresAuth: true,
      roles: ['facility_admin', 'facility_user', 'Super Admin', 'desk_officer'],
      title: 'Facility Dashboard',
      description: 'Facility dashboard for managing operations and requests',
      breadcrumb: 'Facility Dashboard'
    },
  },
  {
    path: '/do/assigned-referrals',
    name: 'do-assigned-referrals',
    component: () => import('../components/do/DOAssignedReferralsPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['desk_officer', 'facility_admin', 'facility_user', 'Super Admin'],
      title: 'Assigned Facilities Referrals',
      description: 'View referrals for your assigned facilities',
      breadcrumb: 'Assigned Referrals'
    },
  },
  // Enrollment Routes
  {
    path: '/enrollees',
    name: 'enrollees',
    component: EnrolleesPage,
    meta: {
      requiresAuth: true,
      title: 'Enrollees',
      description: 'Manage and view all enrollee information',
      breadcrumb: 'Enrollees List'
    },
  },
    {
    path: '/enrollees/pending',
    name: 'enrollee-profile',
    component: PendingEnrolleesPage,
    meta: {
      requiresAuth: true,
      title: 'Enrollee Profile',
      description: 'View and manage enrollee details',
      breadcrumb: 'Enrollee Profile'
    },
  },
  {
    path: '/enrollees/:id',
    name: 'enrollee-profile',
    component: EnrolleeProfilePage,
    meta: {
      requiresAuth: true,
      title: 'Enrollee Profile',
      description: 'View and manage enrollee details',
      breadcrumb: 'Enrollee Profile'
    },
  },
  {
    path: '/enrollment/change-facility',
    name: 'enrollment-change-facility',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Change of Facility', subtitle: 'Manage facility changes for enrollees', icon: 'mdi-hospital-marker' }
  },
  {
    path: '/enrollment/id-cards',
    name: 'enrollment-id-cards',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'ID Card Printing', subtitle: 'Print and manage ID cards', icon: 'mdi-card-account-details' }
  },
  {
    path: '/enrollment/phases',
    name: 'enrollment-phases',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Enrollment Phases', subtitle: 'Manage enrollment phases', icon: 'mdi-timeline' }
  },

  // Device Management Routes
  {
    path: '/devices/manage',
    name: 'devices-manage',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Device', subtitle: 'Device management and configuration', icon: 'mdi-tablet' }
  },
  {
    path: '/devices/config',
    name: 'devices-config',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Enrollment Configuration', subtitle: 'Configure enrollment settings', icon: 'mdi-cog' }
  },

  // Capitation Routes
  {
    path: '/capitation/generate',
    name: 'capitation-generate',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Generate Capitation', subtitle: 'Generate capitation payments', icon: 'mdi-plus-circle' }
  },
  {
    path: '/capitation/review',
    name: 'capitation-review',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Review Capitation', subtitle: 'Review capitation calculations', icon: 'mdi-eye' }
  },
  {
    path: '/capitation/approval',
    name: 'capitation-approval',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Capitation Approval', subtitle: 'Approve capitation payments', icon: 'mdi-check-circle' }
  },
  {
    path: '/capitation/payments',
    name: 'capitation-payments',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Capitation Payment/Invoices', subtitle: 'Manage payments and invoices', icon: 'mdi-receipt' }
  },
  {
    path: '/feedback',
    name: 'feedback-management',
    component: () => import('../components/feedback/FeedbackManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Feedback Management',
      description: 'Manage referral and PA code feedback for claims vetting',
      breadcrumb: 'Feedback Management'
    }
  },
  {
    path: '/feedback/create',
    name: 'feedback-create',
    component: () => import('../components/feedback/FeedbackCreationPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Create Feedback',
      description: 'Create feedback for approved referrals',
      breadcrumb: 'Create Feedback'
    }
  },
  {
    path: '/task-management',
    name: 'task-management',
    component: () => import('../components/task-management/TaskManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Task Management',
      description: 'Manage projects, tasks, and team collaboration',
      breadcrumb: 'Task Management'
    }
  },
 

  // Pre-Authorization (PAS) Module Dashboard
  {
    path: '/pas',
    name: 'pas-dashboard',
    component: () => import('../components/pas/PASDashboard.vue'),
    meta: {
      requiresAuth: true,
      title: 'Pre-Authorization System',
      description: 'Manage pre-authorizations, referrals, and PA codes',
      breadcrumb: 'PAS Dashboard'
    }
  },
  {
    path: '/document-requirements',
    name: 'document-requirements-management',
    component: () => import('../components/pas/DocumentRequirementsPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'claims_officer'],
      title: 'Document Requirements',
      description: 'Manage document requirements for referrals and PA codes',
      breadcrumb: 'Document Requirements'
    }
  },
  {
    path: '/do-facilities',
    name: 'do-facility-assignments',
    component: () => import('../components/pas/DOFacilityAssignmentPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin'],
      title: 'DO Facility Assignments',
      description: 'Assign facilities to desk officers',
      breadcrumb: 'DO Facility Assignments'
    }
  },
  {
    path: '/pas/validate-utn',
    name: 'validate-utn',
    component: () => import('../components/pas/UTNValidationPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['facility_admin', 'facility_user', 'desk_officer', 'Super Admin'],
      title: 'Validate UTN',
      description: 'Validate Unique Transaction Numbers for approved referrals',
      breadcrumb: 'UTN Validation'
    }
  },
  {
    path: '/pas/fu-pa-request',
    name: 'fu-pa-code-request',
    component: () => import('../components/pas/FUPACodeRequestPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['facility_admin', 'facility_user', 'Super Admin'],
      title: 'Request FU-PA Code',
      description: 'Request Follow-Up PA Code for FFS services',
      breadcrumb: 'FU-PA Code Request'
    }
  },
  {
    path: '/pas/fu-pa-approval',
    name: 'fu-pa-code-approval',
    component: () => import('../components/pas/FUPACodeApprovalPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'claims_officer'],
      title: 'FU-PA Code Approval',
      description: 'Approve or reject FU-PA Code requests',
      breadcrumb: 'FU-PA Code Approval'
    }
  },
  {
    path: '/pas/facility-pa-codes',
    name: 'facility-pa-codes',
    component: () => import('../components/pas/FacilityPACodeManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['desk_officer', 'facility_admin', 'facility_user'],
      title: 'FU-PA Code Management',
      description: 'View and manage your FU-PA Code requests',
      breadcrumb: 'FU-PA Code Management'
    }
  },
  {
    path: '/pas/referral-management',
    name: 'referral-management',
    component: () => import('../components/pas/ReferralManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'claims_officer', 'desk_officer', 'facility_admin', 'facility_user'],
      title: 'Referral Management',
      description: 'View, approve, reject, and print referrals',
      breadcrumb: 'Referral Management'
    }
  },

  // Claims Module Dashboard
  {
    path: '/claims',
    name: 'claims-dashboard',
    component: () => import('../components/claims/ClaimsDashboard.vue'),
    meta: {
      requiresAuth: true,
      title: 'Claims Module',
      description: 'Manage referrals, claims submission, and review',
      breadcrumb: 'Claims Dashboard'
    }
  },

  // Claims Routes
  {
    path: '/pas/referrals',
    name: 'pas-referrals',
    component: () => import('../components/claims/ReferralSubmissionPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'claims_officer'],
      title: 'Submit Referral to Pre-Authorization System (PAS)',
      description: 'Submit referrals on behalf of primary facilities to PAS',
      breadcrumb: 'Submit Referral to PAS'
    }
  },
  {
    path: '/claims/referral-request',
    name: 'claims-referral-request',
    component: () => import('../components/claims/ReferralSubmissionPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['primary_facility', 'facility_admin', 'admin'],
      title: 'Referral Request to Pre-Authorization System (PAS)',
      description: 'Submit referral requests for your facility to PAS',
      breadcrumb: 'Referral Request to PAS'
    }
  },
  {
    path: '/claims/review',
    name: 'claims-review',
    component: () => import('../components/claims/ClaimsReviewPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Review Claims',
      description: 'Review and approve submitted claims',
      breadcrumb: 'Review Claims'
    }
  },
  {
    path: '/claims/approval',
    name: 'claims-approval',
    component: () => import('../components/claims/ClaimsApprovalPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'claims_officer'],
      title: 'Approve Claims',
      description: 'Batch approve submitted claims with shared payment code and comments',
      breadcrumb: 'Approve Claims'
    }
  },
  {
    path: '/claims/history',
    name: 'claims-history',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Claims History', subtitle: 'View claims history and reports', icon: 'mdi-history' }
  },
  {
    path: '/claims/payment-batches',
    name: 'payment-batch-management',
    component: () => import('../components/claims/PaymentBatchManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Payment Batch Management',
      description: 'Create and manage payment batches for approved claims',
      breadcrumb: 'Payment Batches'
    }
  },

  // Claims Automation Routes (Bundle/FFS Hybrid Payment Model)
  {
    path: '/claims/automation/admissions',
    name: 'claims-automation-admissions',
    component: () => import('../components/claims/automation/AdmissionManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Admission Management',
      description: 'Manage patient admissions for episode-of-care tracking',
      breadcrumb: 'Admission Management'
    }
  },
  {
    path: '/facility/admissions',
    name: 'facility-admissions',
    component: () => import('../components/facility/FacilityAdmissionManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['facility_admin', 'facility_user', 'Super Admin', 'desk_officer'],
      title: 'Admission Management',
      description: 'Create admissions from validated UTNs and manage patient episodes',
      breadcrumb: 'Admission Management'
    }
  },
  {
    path: '/facility/claims/submit',
    name: 'facility-claim-submission',
    component: () => import('../components/facility/FacilityClaimSubmissionPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['facility_admin', 'facility_user', 'Super Admin', 'desk_officer'],
      title: 'Submit Claim',
      description: 'Submit claims for discharged patients with validated UTN and approved PA codes',
      breadcrumb: 'Submit Claim'
    }
  },
  {
    path: '/claims/automation/admissions/:id',
    name: 'claims-automation-admission-detail',
    component: () => import('../components/claims/automation/AdmissionDetailPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Admission Details',
      description: 'View admission details and linked claims',
      breadcrumb: 'Admission Details'
    }
  },
  {
    path: '/claims/automation/process',
    name: 'claims-automation-process',
    component: () => import('../components/claims/automation/ClaimsProcessingPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Claims Processing',
      description: 'Process claims with bundle classification and FFS top-ups',
      breadcrumb: 'Claims Processing'
    }
  },
  {
    path: '/claims/automation/process/:id',
    name: 'claims-automation-process-claim',
    component: () => import('../components/claims/automation/ClaimsProcessingPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Process Claim',
      description: 'Process and build claim sections',
      breadcrumb: 'Process Claim'
    }
  },
  {
    path: '/claims/automation/bundles',
    name: 'claims-automation-bundles',
    component: () => import('../components/claims/automation/BundleManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Bundle Management',
      description: 'Manage bundle tariffs and configurations',
      breadcrumb: 'Bundle Management'
    }
  },

  // Facilities
  {
    path: '/facilities',
    name: 'facilities',
    component: FacilitiesPage,
    meta: {
      requiresAuth: true,
      title: 'Facilities',
      description: 'Manage healthcare facilities and providers',
      breadcrumb: 'Facilities'
    },
  },

  // Management Module Dashboard
  {
    path: '/management',
    name: 'management-dashboard',
    component: () => import('../components/management/ManagementDashboard.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Management Module',
      description: 'Manage tariff items, bundles, and system configurations',
      breadcrumb: 'Management Dashboard'
    }
  },

  // Management Routes (Master Data)
  // Note: Drug, Lab, Professional Service, Radiology, Consultation, and Consumable management
  // is now handled through the unified Case Management page with polymorphic details
  {
    path: '/management/drugs',
    name: 'management-drugs',
    component: () => import('../components/management/DrugsManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Drugs Management',
      description: 'Manage drug tariff items and pricing',
      breadcrumb: 'Drugs Management'
    }
  },
  {
    path: '/management/laboratories',
    name: 'management-laboratories',
    component: () => import('../components/management/LaboratoriesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Laboratories Management',
      description: 'Manage laboratory test tariff items',
      breadcrumb: 'Laboratories Management'
    }
  },
  {
    path: '/management/professional-services',
    name: 'management-professional-services',
    component: () => import('../components/management/ProfessionalServicesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Professional Services Management',
      description: 'Manage professional services (consultations, procedures)',
      breadcrumb: 'Professional Services Management'
    }
  },

  {
    path: '/management/cases',
    name: 'management-cases',
    component: () => import('../components/management/CaseManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Case Management',
      description: 'Manage case records and service tariffs',
      breadcrumb: 'Case Management'
    }
  },

  {
    path: '/management/bundle-services',
    name: 'management-bundle-services',
    component: () => import('../components/management/BundleServicesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Bundle Services Management',
      description: 'Manage service bundles and configurations',
      breadcrumb: 'Bundle Services'
    }
  },

  {
    path: '/management/bundle-components',
    name: 'management-bundle-components',
    component: () => import('../components/management/BundleComponentsManagementPage.vue'),
    meta: {
      requiresAuth: true,
      roles: ['admin', 'Super Admin', 'tariff_manager'],
      title: 'Bundle Components Management',
      description: 'Manage components within service bundles',
      breadcrumb: 'Bundle Components'
    }
  },

  // Settings Routes
  {
    path: '/settings/users',
    name: 'settings-users',
    component: UsersPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/benefactors',
    name: 'settings-benefactors',
    component: BenefactorsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/roles',
    name: 'settings-roles',
    component: RolesPermissionsPage,
    meta: { requiresAuth: true },
  },
  {
    path: '/settings/departments',
    name: 'settings-departments',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Department', subtitle: 'Manage organizational departments', icon: 'mdi-office-building' }
  },
  {
    path: '/settings/designations',
    name: 'settings-designations',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Designation', subtitle: 'Manage job designations', icon: 'mdi-badge-account' }
  },

  // Default redirect
  {
    path: '/',
    redirect: '/dashboard',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

// Navigation guard for authentication and role-based routing
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();

  if (to.meta.requiresAuth) {
    // Only initialize if not already authenticated
    if (!authStore.isAuthenticated && !authStore._initializing) {
      await authStore.initializeAuth();
    }

    if (authStore.isAuthenticated) {
      // Check permission-based access first (preferred method)
      const requiredPermissions = to.meta.permissions || (to.meta.permission ? [to.meta.permission] : null);

      if (requiredPermissions && requiredPermissions.length > 0) {
        // Check if user has at least one of the required permissions
        const hasRequiredPermission = requiredPermissions.some(permission => authStore.hasPermission(permission));

        if (!hasRequiredPermission) {
          // User doesn't have required permissions, redirect to appropriate dashboard
          const userRole = authStore.userRoles[0]?.name;
          if (userRole === 'desk_officer') {
            next({ path: '/do-dashboard', replace: true });
          } else if (userRole === 'facility_admin' || userRole === 'facility_user') {
            next({ path: '/facility-dashboard', replace: true });
          } else {
            next({ path: '/dashboard', replace: true });
          }
          return;
        }
      }

      // Fallback to role-based access (for backward compatibility)
      const requiredRoles = to.meta.roles || (to.meta.role ? [to.meta.role] : null);

      if (requiredRoles && requiredRoles.length > 0) {
        // Check if user has at least one of the required roles
        const hasRequiredRole = requiredRoles.some(role => authStore.hasRole(role));

        if (!hasRequiredRole) {
          // User doesn't have any of the required roles, redirect to appropriate dashboard
          const userRole = authStore.userRoles[0]?.name;
          if (userRole === 'desk_officer') {
            next({ path: '/do-dashboard', replace: true });
          } else if (userRole === 'facility_admin' || userRole === 'facility_user') {
            next({ path: '/facility-dashboard', replace: true });
          } else {
            next({ path: '/dashboard', replace: true });
          }
          return;
        }
      }

      // Special handling for desk officers accessing general dashboard
      if (to.name === 'dashboard' && authStore.hasRole('desk_officer')) {
        // Redirect desk officers to their specialized dashboard
        next({ path: '/do-dashboard', replace: true });
        return;
      }

      // Special handling for facility users accessing general dashboard
      if (to.name === 'dashboard' && (authStore.hasRole('facility_admin') || authStore.hasRole('facility_user'))) {
        // Redirect facility users to their specialized dashboard
        next({ path: '/facility-dashboard', replace: true });
        return;
      }

      next();
    } else {
      next({ path: '/login', replace: true });
    }
  } else {
    // Handle login page redirect for already authenticated users
    if (to.name === 'login' && authStore.isAuthenticated) {
      // Redirect authenticated users away from login page
      const userRole = authStore.userRoles[0]?.name;
      if (userRole === 'desk_officer') {
        next({ path: '/do-dashboard', replace: true });
      } else if (userRole === 'facility_admin' || userRole === 'facility_user') {
        next({ path: '/facility-dashboard', replace: true });
      } else {
        next({ path: '/dashboard', replace: true });
      }
      return;
    }

    next();
  }
});

// Navigation guard for page titles
router.afterEach((to) => {
  // Set page title based on route meta or default
  const baseTitle = 'NGSCHA';
  const pageTitle = to.meta?.title;

  if (pageTitle) {
    document.title = `${pageTitle} - ${baseTitle}`;
  } else {
    document.title = baseTitle;
  }
});

export default router;