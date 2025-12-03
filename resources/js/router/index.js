import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

// Components
import LoginPage from '../components/auth/LoginPage.vue';
import Dashboard from '../components/dashboard/Dashboard.vue';
import PremiumDashboard from '../components/dashboard/PremiumDashboard.vue';
import PreAuthDashboard from '../components/dashboard/PreAuthDashboard.vue';
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
    component: () => import('../components/do/DODashboardPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Desk Officer Dashboard',
      description: 'Manage referrals and PA codes for assigned facilities',
      breadcrumb: 'DO Dashboard',
      role: 'desk_officer'
    },
  },
  {
    path: '/dashboard/premium',
    name: 'dashboard-premium',
    component: PremiumDashboard,
    meta: {
      requiresAuth: true,
      title: 'Premium Dashboard',
      description: 'Premium collection and payment tracking',
      breadcrumb: 'Premium Dashboard'
    },
  },
  {
    path: '/dashboard/preauth',
    name: 'dashboard-preauth',
    component: PreAuthDashboard,
    meta: {
      requiresAuth: true,
      title: 'Preauthorization Dashboard',
      description: 'Preauthorization requests and approvals',
      breadcrumb: 'Preauth Dashboard'
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

  // PAS Routes
  {
    path: '/pas',
    name: 'pas-management',
    component: () => import('../components/pas/PASManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Pre-Authorisation System',
      description: 'Manage referrals and PA codes for Fee-For-Service claims',
      breadcrumb: 'PAS Management'
    },
  },
  {
    path: '/pas/create-referral',
    name: 'pas-create-referral',
    component: () => import('../components/pas/ReferralCreationWizard.vue'),
    meta: {
      requiresAuth: true,
      title: 'Create Referral Request',
      description: 'Submit a new referral request for patient authorization',
      breadcrumb: 'Create Referral'
    }
  },
  {
    path: '/pas/generate-pa-code',
    name: 'pas-generate-pa-code',
    component: () => import('../components/pas/PACodeGenerationWizard.vue'),
    meta: {
      requiresAuth: true,
      title: 'Generate PA Code',
      description: 'Generate a Pre-Authorization code from approved referral',
      breadcrumb: 'Generate PA Code'
    }
  },
  {
    path: '/pas/modify-referral',
    name: 'pas-modify-referral',
    component: () => import('../components/pas/ModifyReferralWizard.vue'),
    meta: {
      requiresAuth: true,
      title: 'Modify Referral Service',
      description: 'Modify the service details of an existing referral',
      breadcrumb: 'Modify Referral'
    }
  },
  {
    path: '/pas/validate-utn',
    name: 'pas-validate-utn',
    component: () => import('../components/pas/UTNValidationPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'UTN Validation',
      description: 'Validate Unique Transaction Numbers for referrals',
      breadcrumb: 'UTN Validation'
    }
  },
  {
    path: '/pas/request-pa-code',
    name: 'pas-request-pa-code',
    component: () => import('../components/pas/PACodeRequestPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Request PA Code',
      description: 'Request Pre-Authorization codes for services outside bundle',
      breadcrumb: 'Request PA Code'
    }
  },
  {
    path: '/pas/generate',
    name: 'pas-generate',
    component: () => import('../components/pas/CreateReferralPAPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Create Referral/PA Code (Legacy)',
      description: 'Legacy unified workflow - use separate workflows instead',
      breadcrumb: 'Create Referral/PA Code'
    }
  },
  {
    path: '/pas/programmes',
    name: 'pas-programmes',
    component: () => import('../components/pas/CasesManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Case Management',
      description: 'Manage healthcare cases, pricing, and PA requirements',
      breadcrumb: 'Case Management'
    }
  },
  {
    path: '/pas/referrals/:referralCode',
    name: 'pas-referral-detail',
    component: () => import('../components/pas/ReferralDetailPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Referral Details',
      description: 'View referral information and status',
      breadcrumb: 'Referral Details'
    }
  },
  {
    path: '/pas/pa-codes/:paCodeId',
    name: 'pas-pa-code-detail',
    component: () => import('../components/pas/PACodeDetailPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'PA Code Details',
      description: 'View PA code information and status',
      breadcrumb: 'PA Code Details'
    }
  },

  // Drug Management Routes
  {
    path: '/drugs',
    name: 'drugs-management',
    component: () => import('../components/pas/DrugsManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Drug Management',
      description: 'Manage drug formulary and pricing',
      breadcrumb: 'Drug Management'
    }
  },

  // Tariff Item Management Routes
  {
    path: '/tariff-items',
    name: 'tariff-items-management',
    component: () => import('../components/pas/TariffItemManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Tariff Item Management',
      description: 'Manage tariff items and pricing structure',
      breadcrumb: 'Tariff Item Management'
    }
  },
  {
    path: '/drugs/:drugId',
    name: 'drug-detail',
    component: () => import('../components/pas/DrugDetailPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Drug Details',
      description: 'View drug information and details',
      breadcrumb: 'Drug Details'
    }
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
 
  {
    path: '/pas/drugs',
    name: 'pas-drugs',
    component: () => import('../components/pas/DrugsManagementPage.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/pas/labs',
    name: 'pas-labs',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Labs', subtitle: 'Manage laboratory services', icon: 'mdi-test-tube' }
  },
  {
    path: '/pas/clinical',
    name: 'pas-clinical',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Clinical Services', subtitle: 'Manage clinical services', icon: 'mdi-medical-bag' }
  },

  // Case Category Management Routes
  {
    path: '/case-categories',
    name: 'case-categories-management',
    component: () => import('../components/pas/CaseCategoryManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Case Categories',
      description: 'Manage medical case categories',
      breadcrumb: 'Case Categories'
    }
  },

  // Service Category Management Routes
  {
    path: '/service-categories',
    name: 'service-categories-management',
    component: () => import('../components/pas/ServiceCategoryManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Service Categories',
      description: 'Manage healthcare service categories',
      breadcrumb: 'Service Categories'
    }
  },

  // DOFacility Management Routes
  {
    path: '/do-facilities',
    name: 'do-facilities-management',
    component: () => import('../components/pas/DOFacilityManagementPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Desk Officer Facility Assignments',
      description: 'Assign facilities to desk officers',
      breadcrumb: 'DO Facility Assignments'
    }
  },

  // Document Requirements Management Routes
  {
    path: '/document-requirements',
    name: 'document-requirements-management',
    component: () => import('../components/pas/DocumentRequirementsPage.vue'),
    meta: {
      requiresAuth: true,
      title: 'Document Requirements',
      description: 'Manage document requirements for referrals and PA codes',
      breadcrumb: 'Document Requirements'
    }
  },

  // Claims Routes
  {
    path: '/claims/referrals',
    name: 'claims-referrals',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Manage Referrals', subtitle: 'Manage patient referrals', icon: 'mdi-account-arrow-right' }
  },
  {
    path: '/claims/submissions',
    name: 'claims-submissions',
    component: () => import('../components/claims/ClaimSubmissionPage.vue'),
    meta: { requiresAuth: true, title: 'Submit Claim' }
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
    path: '/claims/history',
    name: 'claims-history',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Claims History', subtitle: 'View claims history and reports', icon: 'mdi-history' }
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
      // Check role-based access
      if (to.meta.role) {
        if (!authStore.hasRole(to.meta.role)) {
          // User doesn't have required role, redirect to appropriate dashboard
          const userRole = authStore.userRoles[0]?.name;
          if (userRole === 'desk_officer') {
            next({ path: '/do-dashboard', replace: true });
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

      next();
    } else {
      next({ path: '/login', replace: true });
    }
  } else {
    // Handle login page redirect for already authenticated users
    if (to.name === 'login' && authStore.isAuthenticated) {
      // Redirect authenticated users away from login page
      if (authStore.hasRole('desk_officer')) {
        next({ path: '/do-dashboard', replace: true });
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