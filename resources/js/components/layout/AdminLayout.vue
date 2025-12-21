<template>
  <div class="tw-flex tw-h-screen tw-bg-gray-50">
    <!-- Mobile Backdrop -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="tw-fixed tw-inset-0 tw-bg-black tw-bg-opacity-50 tw-z-40 md:tw-hidden"
    ></div>

    <!-- Sidebar -->
    <div
      :class="[
        'tw-fixed tw-inset-y-0 tw-left-0 tw-z-50 tw-w-64 tw-bg-white tw-shadow-lg tw-transform tw-transition-transform tw-duration-300 tw-ease-in-out tw-flex tw-flex-col tw-max-h-screen',
        sidebarOpen ? 'tw-translate-x-0' : '-tw-translate-x-full',
        'md:tw-relative md:tw-translate-x-0'
      ]"
    >
      <!-- Sidebar Header -->
      <div class="tw-flex tw-items-center tw-justify-center tw-h-16 tw-border-b tw-flex-shrink-0" style="background-color: #0885AB; border-color: #076d8f;">
        <div class="tw-flex tw-items-center tw-space-x-3">
          <Logo
            size="md"
            variant="square"
            icon-color="white"
            class="tw-bg-white tw-bg-opacity-20 tw-backdrop-blur-sm"
          />
          <span class="tw-text-white tw-font-bold tw-text-xl tw-tracking-wide">NGSCHA</span>
        </div>
      </div>

      <!-- Navigation Menu -->
      <nav class="tw-flex-1 tw-overflow-y-auto tw-scrollbar-thin tw-scrollbar-thumb-gray-300 tw-scrollbar-track-gray-100 tw-min-h-0">
        <div class="tw-px-3 tw-py-4 tw-space-y-1">
          <template v-for="item in filteredMenuItems" :key="item.name">
            <!-- Regular menu item -->
            <router-link
              v-if="!item.children"
              :to="item.path"
              :class="[
                'tw-group tw-flex tw-items-center tw-px-3 tw-py-2.5 tw-text-sm tw-font-medium tw-rounded-lg tw-transition-all tw-duration-300 tw-hover-slide-right',
                $route.path === item.path
                  ? 'tw-text-white tw-shadow-md tw-animate-fade-in'
                  : 'tw-text-gray-600 hover:tw-shadow-sm'
              ]"
              :style="$route.path === item.path ? 'background-color: #0885AB;' : ''"
              @mouseenter="$event.currentTarget.style.backgroundColor = $route.path !== item.path ? '#e0f2f7' : '#0885AB'"
              @mouseleave="$event.currentTarget.style.backgroundColor = $route.path !== item.path ? '' : '#0885AB'"
            >
              <v-icon
                :size="20"
                :class="[
                  'tw-mr-3 tw-transition-all tw-duration-300',
                  $route.path === item.path ? 'tw-text-white tw-drop-shadow-sm' : 'tw-text-gray-400'
                ]"
                :style="$route.path !== item.path ? 'color: inherit;' : ''"
              >
                {{ item.icon }}
              </v-icon>
              <span class="tw-transition-all tw-duration-300">{{ item.name }}</span>
              <span
                v-if="item.badge"
                :class="[
                  'tw-ml-auto tw-inline-flex tw-items-center tw-px-2 tw-py-1 tw-rounded-full tw-text-xs tw-font-medium tw-transition-all tw-duration-300',
                  $route.path === item.path
                    ? 'tw-bg-white'
                    : ''
                ]"
                :style="$route.path === item.path ? 'color: #0885AB;' : 'background-color: #e0f2f7; color: #0885AB;'"
              >
                {{ item.badge }}
              </span>
            </router-link>

            <!-- Menu item with submenu -->
            <div v-else>
              <button
                @click="toggleSubmenu(item.name)"
                :class="[
                  'tw-group tw-flex tw-items-center tw-w-full tw-px-4 tw-py-3 tw-text-sm tw-font-medium tw-rounded-xl tw-transition-all tw-duration-300 tw-transform hover:tw-scale-105 tw-hover-glow',
                  isSubmenuActive(item) || expandedMenus.includes(item.name)
                    ? 'tw-text-white tw-shadow-lg tw-animate-bounce-in'
                    : 'tw-text-gray-600 hover:tw-shadow-md'
                ]"
                :style="isSubmenuActive(item) || expandedMenus.includes(item.name) ? 'background-color: #0885AB;' : ''"
                @mouseenter="$event.currentTarget.style.backgroundColor = !(isSubmenuActive(item) || expandedMenus.includes(item.name)) ? '#e0f2f7' : '#0885AB'"
                @mouseleave="$event.currentTarget.style.backgroundColor = !(isSubmenuActive(item) || expandedMenus.includes(item.name)) ? '' : '#0885AB'"
              >
                <v-icon
                  :size="20"
                  :class="[
                    'tw-mr-3 tw-transition-all tw-duration-300',
                    isSubmenuActive(item) || expandedMenus.includes(item.name) ? 'tw-text-white tw-drop-shadow-sm' : 'tw-text-gray-400'
                  ]"
                >
                  {{ item.icon }}
                </v-icon>
                <span class="tw-transition-all tw-duration-300 tw-flex-1 tw-text-left">{{ item.name }}</span>
                <v-icon
                  :size="16"
                  :class="[
                    'tw-transition-transform tw-duration-300',
                    expandedMenus.includes(item.name) ? 'tw-rotate-180' : '',
                    isSubmenuActive(item) || expandedMenus.includes(item.name) ? 'tw-text-white' : 'tw-text-gray-400'
                  ]"
                >
                  mdi-chevron-down
                </v-icon>
              </button>

              <!-- Submenu items -->
              <div
                v-show="expandedMenus.includes(item.name)"
                class="tw-mt-1 tw-ml-3 tw-space-y-0.5 tw-transition-all tw-duration-300 tw-animate-slide-up"
              >
                <router-link
                  v-for="(child, index) in item.children"
                  :key="child.name"
                  :to="child.path"
                  :class="[
                    'tw-group tw-flex tw-items-center tw-px-3 tw-py-2 tw-text-xs tw-rounded-md tw-transition-all tw-duration-300 tw-hover-slide-right',
                    $route.path === child.path
                      ? 'tw-font-medium tw-animate-fade-in'
                      : 'tw-text-gray-600 hover:tw-bg-gray-50 hover:tw-text-gray-900'
                  ]"
                  :style="$route.path === child.path ? `background-color: #e0f2f7; color: #0885AB; animation-delay: ${index * 0.1}s;` : `animation-delay: ${index * 0.1}s;`"
                >
                  <v-icon
                    :size="14"
                    :class="[
                      'tw-mr-2 tw-transition-all tw-duration-200',
                      $route.path === child.path ? '' : 'tw-text-gray-400 group-hover:tw-text-gray-600'
                    ]"
                    :style="$route.path === child.path ? 'color: #0885AB;' : ''"
                  >
                    {{ child.icon }}
                  </v-icon>
                  <span class="tw-truncate">{{ child.name }}</span>
                </router-link>
              </div>
            </div>
          </template>
        </div>
      </nav>

      <!-- User Info at Bottom -->
      <div class="tw-flex-shrink-0 tw-p-4 tw-border-t tw-border-gray-200 tw-bg-gray-50">
        <div class="tw-flex tw-items-center tw-space-x-3">
          <v-avatar size="32" class="tw-text-white tw-flex-shrink-0" style="background-color: #0885AB;">
            <v-icon size="18">mdi-account</v-icon>
          </v-avatar>
          <div class="tw-flex-1 tw-min-w-0">
            <p class="tw-text-sm tw-font-medium tw-text-gray-900 tw-truncate">
              {{ userName }}
            </p>
            <p class="tw-text-xs tw-text-gray-500 tw-truncate">
              {{ userRoles.length > 0 ? userRoles[0].name : 'User' }}
            </p>
          </div>
          <v-btn
            icon
            variant="text"
            size="small"
            @click="handleLogout"
            :loading="logoutLoading"
            class="tw-flex-shrink-0"
          >
            <v-icon size="18">mdi-logout</v-icon>
          </v-btn>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="tw-flex-1 tw-flex tw-flex-col tw-overflow-hidden">
      <!-- Top Header -->
      <header class="tw-bg-white tw-shadow-sm tw-border-b tw-border-gray-200">
        <div class="tw-flex tw-items-center tw-justify-between tw-h-16 tw-px-4">
          <!-- Mobile menu button -->
          <button
            @click="sidebarOpen = !sidebarOpen"
            class="tw-md:hidden tw-p-2 tw-rounded-md tw-text-gray-400 hover:tw-text-gray-500 hover:tw-bg-gray-100"
          >
            <v-icon>mdi-menu</v-icon>
          </button>

          <!-- Page title -->
          <div class="tw-flex-1 tw-md:ml-0 tw-flex tw-items-center">
            <div class="tw-hidden md:tw-flex tw-items-center tw-mr-4">
              <Logo size="md" class="tw-mr-2" />
            </div>
            <h1 class="tw-text-2xl tw-font-semibold tw-text-gray-900">
              {{ pageTitle }}
            </h1>
          </div>

          <!-- Header actions -->
          <div class="tw-flex tw-items-center tw-space-x-4">
            <!-- Module Switcher -->
            <v-select
              v-model="selectedModule"
              :items="moduleOptions"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="compact"
              hide-details
              class="tw-w-48 tw-hidden md:tw-block"
              label="Module"
            />

            <!-- Role Switcher -->
            <RoleSwitcher />

            <!-- Notifications -->
            <v-btn icon variant="text" size="small">
              <v-icon>mdi-bell-outline</v-icon>
            </v-btn>

            <!-- User menu -->
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn icon variant="text" size="small" v-bind="props">
                  <v-icon>mdi-dots-vertical</v-icon>
                </v-btn>
              </template>
              <v-list>
                <v-list-item @click="handleLogout">
                  <v-list-item-title>
                    <v-icon left>mdi-logout</v-icon>
                    Logout
                  </v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="tw-flex-1 tw-overflow-y-auto tw-bg-gray-50">
        <div class="tw-p-4 sm:tw-p-6 lg:tw-p-8">
          <!-- Breadcrumb Navigation -->
          <Breadcrumb />
          <slot />
        </div>
      </main>
    </div>

    <!-- Mobile sidebar overlay -->
    <div
      v-if="sidebarOpen"
      @click="sidebarOpen = false"
      class="tw-fixed tw-inset-0 tw-z-40 tw-bg-black tw-bg-opacity-50 md:tw-hidden"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';
import { useToast } from '../../composables/useToast';
import RoleSwitcher from '../common/RoleSwitcher.vue';
import Breadcrumb from '../common/Breadcrumb.vue';
import Logo from '../common/Logo.vue';

// Reactive data and stores

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const uiStore = useUiStore();
const { success } = useToast();

const sidebarOpen = ref(false);
const logoutLoading = ref(false);
const expandedMenus = ref(['Dashboard']); // Dashboard expanded by default

// --- Menu definitions grouped by module ---
const dashboardMenu = {
  name: 'Dashboard',
  icon: 'mdi-view-dashboard',
  children: [
    {
      name: 'Enrollee Dashboard',
      path: '/dashboard',
      icon: 'mdi-account-group',
      roles: ['admin', 'doctor', 'pharmacist', 'reviewer', 'confirmer', 'approver', 'Super Admin'],
    },
    {
      name: 'DO Dashboard',
      path: '/do-dashboard',
      icon: 'mdi-desk',
      roles: ['desk_officer', 'Super Admin'],
    },
    {
      name: 'Facility Dashboard',
      path: '/facility-dashboard',
      icon: 'mdi-hospital-building',
      roles: ['facility_admin', 'facility_user', 'Super Admin'],
    },
  ],
};

const userManagementMenu = {
  name: 'User Management',
  icon: 'mdi-account-cog',
  children: [
    {
      name: 'Users',
      path: '/settings/users',
      icon: 'mdi-account-multiple',
      roles: ['admin', 'Super Admin'],
    },
    {
      name: 'Roles & Permissions',
      path: '/settings/roles',
      icon: 'mdi-shield-account',
      roles: ['admin', 'Super Admin'],
    },
  ],
};

const pasMenu = {
  name: 'Pre-authorization System (PAS)',
  icon: 'mdi-shield-check',
  children: [
    {
      name: 'PAS Dashboard',
      path: '/pas',
      icon: 'mdi-view-dashboard',
      roles: ['admin', 'Super Admin', 'claims_officer'],
    },
    {
      name: 'DO Facility Assignments',
      path: '/do-facilities',
      icon: 'mdi-hospital-marker',
      roles: ['admin', 'Super Admin'],
    },
    {
      name: 'Assigned Facilities Referrals',
      path: '/do/assigned-referrals',
      icon: 'mdi-file-document-multiple',
      roles: ['desk_officer', 'facility_admin', 'facility_user', 'Super Admin'],
    },
    {
      name: 'Validate UTN',
      path: '/pas/validate-utn',
      icon: 'mdi-shield-check',
      roles: ['facility_admin', 'facility_user', 'desk_officer', 'Super Admin'],
    },
    {
      name: 'Admission Management',
      path: '/facility/admissions',
      icon: 'mdi-hospital-box',
      roles: ['facility_admin', 'facility_user', 'Super Admin', 'desk_officer'],
    },
    {
      name: 'FU-PA Code Management',
      path: '/pas/facility-pa-codes',
      icon: 'mdi-shield-check',
      roles: ['desk_officer', 'facility_admin', 'facility_user', 'Super Admin'],
    },
    {
      name: 'Submit Claim',
      path: '/facility/claims/submit',
      icon: 'mdi-file-document-plus',
      roles: ['facility_admin', 'facility_user', 'Super Admin', 'desk_officer'],
    },
    {
      name: 'FU-PA Code Approval',
      path: '/pas/fu-pa-approval',
      icon: 'mdi-check-decagram',
      roles: ['admin', 'Super Admin', 'claims_officer'],
    },
    {
      name: 'Referral Management',
      path: '/pas/referral-management',
      icon: 'mdi-file-document-check',
      roles: ['admin', 'Super Admin', 'claims_officer', 'desk_officer', 'facility_admin', 'facility_user'],
    },
    {
      name: 'Document Requirements',
      path: '/document-requirements',
      icon: 'mdi-file-document-multiple',
      roles: ['admin', 'Super Admin', 'claims_officer'],
    },
  ],
};

const pasFeedbackMenu = {
  name: 'Feedback Management',
  icon: 'mdi-comment-text-multiple',
  children: [
    {
      name: 'Feedback List',
      path: '/feedback',
      icon: 'mdi-comment-text',
      roles: ['admin', 'Super Admin', 'claims_officer', 'facility_admin', 'facility_user'],
    },
    {
      name: 'Create Feedback',
      path: '/feedback/create',
      icon: 'mdi-message-plus',
      roles: ['admin', 'Super Admin', 'claims_officer', 'desk_officer'],
    },
  ],
};

const claimsMenu = {
  name: 'Claims Management',
  icon: 'mdi-file-document-multiple',
  children: [
    {
      name: 'Claims Dashboard',
      path: '/claims',
      icon: 'mdi-view-dashboard',
      roles: ['admin', 'Super Admin', 'claims_officer', 'claim_reviewer', 'claim_confirmer', 'claim_approver'],
    },
    {
      name: 'Submit Referral to PAS',
      path: '/claims/referrals',
      icon: 'mdi-account-arrow-right',
      roles: ['admin', 'Super Admin', 'claims_officer'],
    },
    {
      name: 'Review Claims',
      path: '/claims/review',
      icon: 'mdi-file-check',
      roles: ['admin', 'Super Admin', 'claim_reviewer', 'claim_confirmer', 'claim_approver'],
    },
    {
      name: 'Payment Batches',
      path: '/claims/payment-batches',
      icon: 'mdi-cash-multiple',
      roles: ['admin', 'Super Admin', 'claims_officer', 'claim_approver'],
    },
  ],
};

const claimsAutomationMenu = {
  name: 'Claims Automation',
  icon: 'mdi-robot',
  children: [
    {
      name: 'Admission Management',
      path: '/claims/automation/admissions',
      icon: 'mdi-hospital-building',
      roles: ['admin', 'Super Admin', 'claims_officer'],
    },
    {
      name: 'Claims Processing',
      path: '/claims/automation/process',
      icon: 'mdi-cog-transfer',
      roles: ['admin', 'Super Admin', 'claims_officer', 'claim_reviewer'],
    },
    {
      name: 'Bundle Management',
      path: '/claims/automation/bundles',
      icon: 'mdi-package-variant-closed',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
  ],
};

const managementMenu = {
  name: 'Management Module',
  icon: 'mdi-cog-outline',
  children: [
    {
      name: 'Management Dashboard',
      path: '/management',
      icon: 'mdi-view-dashboard-outline',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Drugs Management',
      path: '/management/drugs',
      icon: 'mdi-pill',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Laboratories',
      path: '/management/laboratories',
      icon: 'mdi-test-tube',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Professional Services',
      path: '/management/professional-services',
      icon: 'mdi-medical-bag',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Case Management',
      path: '/management/cases',
      icon: 'mdi-file-document-multiple-outline',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Bundle Services',
      path: '/management/bundle-services',
      icon: 'mdi-package-variant',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
    {
      name: 'Bundle Components',
      path: '/management/bundle-components',
      icon: 'mdi-package-variant-closed',
      roles: ['admin', 'Super Admin', 'tariff_manager'],
    },
  ],
};

// Module -> menu mapping
// Note: Dashboard is shared across modules so users can always return to key overviews.
const moduleMenus = {
	  general: [dashboardMenu, userManagementMenu],
	  pas: [dashboardMenu, pasMenu, pasFeedbackMenu],
	  claims: [dashboardMenu, claimsMenu],
	  automation: [dashboardMenu, claimsAutomationMenu],
	  management: [dashboardMenu, managementMenu],
	};

// Module switcher options - filtered by user's available modules
const allModuleOptions = [
  { value: 'general', label: 'Core & Admin' },
  { value: 'pas', label: 'Pre-Authorization (PAS)' },
  { value: 'claims', label: 'Claims' },
  { value: 'automation', label: 'Claims Automation' },
  { value: 'management', label: 'Management' },
];

// Filter modules based on user's current role
const moduleOptions = computed(() => {
  const availableModules = authStore.availableModules || [];

  // If no modules specified or user is admin, show all
  if (availableModules.length === 0 || authStore.hasRole('admin') || authStore.hasRole('Super Admin')) {
    return allModuleOptions;
  }

  // Filter to only show modules user has access to
  return allModuleOptions.filter(option => availableModules.includes(option.value));
});

const selectedModule = computed({
  get: () => uiStore.currentModule,
  set: (value) => {
    uiStore.setModule(value);
    switch (value) {
      case 'pas':
        router.push('/pas');
        break;
      case 'claims':
        router.push('/claims');
        break;
      case 'automation':
        router.push('/claims/automation');
        break;
      case 'management':
        router.push('/management');
        break;
      default:
        router.push('/dashboard');
        break;
    }
  },
});

const activeModuleMenuItems = computed(
  () => moduleMenus[uiStore.currentModule] || moduleMenus.general,
);

// Computed properties
const userName = computed(() => authStore.userName);
const userRoles = computed(() => authStore.userRoles);

// Filter menu items based on user roles
const filteredMenuItems = computed(() => {
  return activeModuleMenuItems.value
    .map((item) => {
      if (item.children) {
        const filteredChildren = item.children.filter((child) => {
          if (!child.roles) return true; // Show if no role restriction
          return child.roles.some((role) => authStore.hasRole(role));
        });

        return {
          ...item,
          children: filteredChildren,
        };
      }

      // For items without children, check role restriction
      if (!item.roles) return item; // Show if no role restriction
      return item.roles.some((role) => authStore.hasRole(role)) ? item : null;
    })
    .filter((item) => item && (!item.children || item.children.length > 0));
});

const pageTitle = computed(() => {
  // Check main menu items
  let currentItem = filteredMenuItems.value.find(item => item.path === route.path);

  // If not found, check submenu items
  if (!currentItem) {
    for (const item of filteredMenuItems.value) {
      if (item.children) {
        currentItem = item.children.find(child => child.path === route.path);
        if (currentItem) break;
      }
    }
  }

  return currentItem ? currentItem.name : 'Dashboard';
});

// Methods
const toggleSubmenu = (menuName) => {
  const index = expandedMenus.value.indexOf(menuName);
  if (index > -1) {
    expandedMenus.value.splice(index, 1);
  } else {
    expandedMenus.value.push(menuName);
  }
};

const isSubmenuActive = (item) => {
  if (!item.children) return false;
  return item.children.some((child) => route.path === child.path);
};

const handleLogout = async () => {
  logoutLoading.value = true;
  try {
    await authStore.logout();
    success('Logged out successfully');
    router.push('/login');
  } catch (error) {
    console.error('Logout error:', error);
  } finally {
    logoutLoading.value = false;
  }
};

// Map current route path to a module so deep links show the correct sidebar
const getModuleForPath = (path) => {
  if (path.startsWith('/claims/automation')) return 'automation';
  if (path.startsWith('/claims')) return 'claims';
  if (path.startsWith('/management')) return 'management';

  if (
    path.startsWith('/pas') ||
    path.startsWith('/tariff-items') ||
    path.startsWith('/case-categories') ||
    path.startsWith('/service-categories') ||
    path.startsWith('/do-facilities') ||
    path.startsWith('/do/assigned-referrals') ||
    path.startsWith('/document-requirements') ||
    path.startsWith('/feedback')
  ) {
    return 'pas';
  }

  return 'general';
};

onMounted(() => {
  const module = getModuleForPath(route.path);
  uiStore.setModule(module);
});

watch(
  () => route.path,
  (newPath) => {
    const module = getModuleForPath(newPath);
    uiStore.setModule(module);
  },
);

// Watch for changes in available modules and validate current module
watch(
  () => authStore.availableModules,
  (newModules) => {
    const currentModule = uiStore.currentModule;
    const availableModuleValues = moduleOptions.value.map(m => m.value);

    // If current module is not available, switch to first available module
    if (!availableModuleValues.includes(currentModule) && availableModuleValues.length > 0) {
      uiStore.setModule(availableModuleValues[0]);
      router.push(availableModuleValues[0] === 'general' ? '/dashboard' : `/${availableModuleValues[0]}`);
    }
  },
  { immediate: true }
);

// Close sidebar on route change (mobile)
router.afterEach(() => {
  sidebarOpen.value = false;
});
</script>

<style scoped>
/* Additional custom styles */
.router-link-active {
  background: #2563eb;
  color: white;
  box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.25);
}

/* Smooth animations */
.tw-transition-all {
  transition-property: all;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Custom scrollbar for sidebar */
nav::-webkit-scrollbar {
  width: 4px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: rgba(156, 163, 175, 0.3);
  border-radius: 2px;
}

nav::-webkit-scrollbar-thumb:hover {
  background: rgba(156, 163, 175, 0.5);
}

/* Responsive sidebar adjustments */
@media (max-width: 1024px) {
  .tw-w-64 {
    width: 15rem;
  }
}

@media (max-width: 640px) {
  .tw-w-64 {
    width: 14rem;
  }
}
</style>