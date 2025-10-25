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
      <div class="tw-flex tw-items-center tw-justify-center tw-h-16 tw-bg-blue-600 tw-border-b tw-border-blue-700 tw-flex-shrink-0">
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
                  ? 'tw-bg-blue-600 tw-text-white tw-shadow-md tw-animate-fade-in'
                  : 'tw-text-gray-600 hover:tw-bg-blue-50 hover:tw-text-blue-700 hover:tw-shadow-sm'
              ]"
            >
              <v-icon
                :size="20"
                :class="[
                  'tw-mr-3 tw-transition-all tw-duration-300',
                  $route.path === item.path ? 'tw-text-white tw-drop-shadow-sm' : 'tw-text-gray-400 group-hover:tw-text-blue-500'
                ]"
              >
                {{ item.icon }}
              </v-icon>
              <span class="tw-transition-all tw-duration-300">{{ item.name }}</span>
              <span
                v-if="item.badge"
                :class="[
                  'tw-ml-auto tw-inline-flex tw-items-center tw-px-2 tw-py-1 tw-rounded-full tw-text-xs tw-font-medium tw-transition-all tw-duration-300',
                  $route.path === item.path
                    ? 'tw-bg-white tw-text-blue-600'
                    : 'tw-bg-blue-100 tw-text-blue-600 group-hover:tw-bg-blue-200'
                ]"
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
                    ? 'tw-bg-blue-600 tw-text-white tw-shadow-lg tw-animate-bounce-in'
                    : 'tw-text-gray-600 hover:tw-bg-blue-50 hover:tw-text-blue-700 hover:tw-shadow-md'
                ]"
              >
                <v-icon
                  :size="20"
                  :class="[
                    'tw-mr-3 tw-transition-all tw-duration-300',
                    isSubmenuActive(item) || expandedMenus.includes(item.name) ? 'tw-text-white tw-drop-shadow-sm' : 'tw-text-gray-400 group-hover:tw-text-blue-500'
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
                      ? 'tw-bg-blue-100 tw-text-blue-700 tw-font-medium tw-animate-fade-in'
                      : 'tw-text-gray-600 hover:tw-bg-gray-50 hover:tw-text-gray-900'
                  ]"
                  :style="{ animationDelay: `${index * 0.1}s` }"
                >
                  <v-icon
                    :size="14"
                    :class="[
                      'tw-mr-2 tw-transition-all tw-duration-200',
                      $route.path === child.path ? 'tw-text-blue-600' : 'tw-text-gray-400 group-hover:tw-text-gray-600'
                    ]"
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
          <v-avatar size="32" color="blue" class="tw-text-white tw-flex-shrink-0">
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
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';
import RoleSwitcher from '../common/RoleSwitcher.vue';
import Breadcrumb from '../common/Breadcrumb.vue';
import Logo from '../common/Logo.vue';

// Reactive data

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const { success } = useToast();

const sidebarOpen = ref(false);
const logoutLoading = ref(false);
const expandedMenus = ref(['Dashboard', 'Enrollment']); // Dashboard and Enrollment expanded by default

// Menu items with new grouping structure
const menuItems = [
  {
    name: 'Dashboard',
    icon: 'mdi-view-dashboard',
    children: [
      {
        name: 'Enrollee Dashboard',
        path: '/dashboard',
        icon: 'mdi-account-group',
        roles: ['admin', 'doctor', 'pharmacist', 'reviewer', 'confirmer', 'approver'] // Hide from desk officers
      },
      {
        name: 'DO Dashboard',
        path: '/do-dashboard',
        icon: 'mdi-desk',
        roles: ['desk_officer'] // Only show to desk officers
      },
      {
        name: 'Premium Dashboard',
        path: '/dashboard/premium',
        icon: 'mdi-currency-usd'
      },
      {
        name: 'Preauthorization Dashboard',
        path: '/dashboard/preauth',
        icon: 'mdi-shield-check'
      }
    ]
  },
  {
    name: 'Enrollment',
    icon: 'mdi-account-plus',
    children: [
      {
        name: 'Enrollees List',
        path: '/enrollees',
        icon: 'mdi-format-list-bulleted',
        badge: '12.8k'
      },
       {
        name: 'Pending Enrollee List',
        path: '/enrollees/pending',
        icon: 'mdi-format-list-bulleted',
        badge: '12.8k'
      },
      {
        name: 'Change of Facility',
        path: '/enrollment/change-facility',
        icon: 'mdi-hospital-marker'
      },
      {
        name: 'ID Card Printing',
        path: '/enrollment/id-cards',
        icon: 'mdi-card-account-details'
      },
      {
        name: 'Enrollment Phases',
        path: '/enrollment/phases',
        icon: 'mdi-timeline'
      }
    ]
  },
  {
    name: 'User Management',
    icon: 'mdi-account-cog',
    children: [
      {
        name: 'Users',
        path: '/settings/users',
        icon: 'mdi-account-multiple'
      },
      {
        name: 'Roles & Permissions',
        path: '/settings/roles',
        icon: 'mdi-shield-account'
      }
    ]
  },
  
  {
    name: 'Pre-authorization System (PAS)',
    icon: 'mdi-shield-check',
    children: [
      {
        name: 'PAS Management',
        path: '/pas',
        icon: 'mdi-clipboard-list',
        badge: 'New'
      },
      {
        name: 'Manage Programmes/Services',
        path: '/pas/programmes',
        icon: 'mdi-format-list-bulleted-type'
      },
      {
        name: 'Manage Drugs',
        path: '/pas/drugs',
        icon: 'mdi-pill'
      },
      {
        name: 'Manage Labs',
        path: '/pas/labs',
        icon: 'mdi-test-tube'
      },
      {
        name: 'Manage Clinical Services',
        path: '/pas/clinical',
        icon: 'mdi-medical-bag'
      },
      {
        name: 'Case Categories',
        path: '/case-categories',
        icon: 'mdi-folder-multiple'
      },
      {
        name: 'Service Categories',
        path: '/service-categories',
        icon: 'mdi-format-list-bulleted'
      },
      {
        name: 'DO Facility Assignments',
        path: '/do-facilities',
        icon: 'mdi-hospital-marker'
      }
    ]
  },
   {
    name: 'Referrals and PA Codes Feedback',
    icon: 'mdi-shield-check',
    children: [
      {
        name: 'Feedback',
        path: '/feedback',
        icon: 'mdi-clipboard-list',
        badge: 'New'
      },
    ]
   },
   {
    name: 'Task Management',
    icon: 'mdi-clipboard-check',
    children: [
      {
        name: 'Task Management',
        path: '/task-management',
        icon: 'mdi-clipboard-check',
        badge: 'New'
      },
    ]
   },
  {
    name: 'Claims Management',
    icon: 'mdi-file-document-multiple',
    children: [
      {
        name: 'Manage Referrals',
        path: '/claims/referrals',
        icon: 'mdi-account-arrow-right'
      },
      {
        name: 'Claim Submissions',
        path: '/claims/submissions',
        icon: 'mdi-upload'
      },
      {
        name: 'Claims History',
        path: '/claims/history',
        icon: 'mdi-history'
      }
    ]
  },
  {
    name: 'Settings',
    icon: 'mdi-cog',
    children: [
      {
        name: 'Manage Benefactors',
        path: '/settings/benefactors',
        icon: 'mdi-account-heart'
      },
      {
        name: 'Facilities',
        path: '/facilities',
        icon: 'mdi-hospital-building',
        badge: '342'
      },
      {
        name: 'Manage Department',
        path: '/settings/departments',
        icon: 'mdi-office-building'
      },
      {
        name: 'Manage Designation',
        path: '/settings/designations',
        icon: 'mdi-badge-account'
      }
    ]
  }
];

// Computed properties
const userName = computed(() => authStore.userName);
const userRoles = computed(() => authStore.userRoles);

// Filter menu items based on user roles
const filteredMenuItems = computed(() => {
  return menuItems.map(item => {
    if (item.children) {
      const filteredChildren = item.children.filter(child => {
        if (!child.roles) return true; // Show if no role restriction
        return child.roles.some(role => authStore.hasRole(role));
      });

      return {
        ...item,
        children: filteredChildren
      };
    }

    // For items without children, check role restriction
    if (!item.roles) return item; // Show if no role restriction
    return item.roles.some(role => authStore.hasRole(role)) ? item : null;
  }).filter(item => item && (!item.children || item.children.length > 0));
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
  return item.children.some(child => route.path === child.path);
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