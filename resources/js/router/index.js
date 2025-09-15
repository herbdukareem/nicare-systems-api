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
import PASManagementPage from '../components/pas/PASManagementPage.vue';
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
    component: PASManagementPage,
    meta: {
      requiresAuth: true,
      title: 'Pre-Authorisation System',
      description: 'Manage referrals and PA codes for Fee-For-Service claims',
      breadcrumb: 'PAS Management'
    },
  },
  {
    path: '/pas/generate',
    name: 'pas-generate',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Generate Referral/PA-Code', subtitle: 'Generate preauthorization codes', icon: 'mdi-qrcode' }
  },
  {
    path: '/pas/programmes',
    name: 'pas-programmes',
    component: () => import('../components/pas/ServicesManagementPage.vue'),
    meta: { requiresAuth: true }
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
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Claim Submissions', subtitle: 'Submit and track claims', icon: 'mdi-upload' }
  },
  {
    path: '/claims/history',
    name: 'claims-history',
    component: () => import('../components/common/ComingSoonPage.vue'),
    meta: { requiresAuth: true },
    props: { title: 'Claims History', subtitle: 'View claims history and reports', icon: 'mdi-history' }
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

// Navigation guard for authentication
router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();

  // Initialize auth if not already done - AWAIT the initialization
  if (!authStore.token) {
    await authStore.initializeAuth();
  }

  const isAuthenticated = authStore.isLoggedIn;

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login');
  } else if (to.path === '/login' && isAuthenticated) {
    next('/dashboard');
  } else {
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