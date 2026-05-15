<template>
  <div class="tw-flex tw-h-screen tw-bg-slate-50 tw-text-slate-900">
    <div
      v-if="sidebarOpen"
      class="tw-fixed tw-inset-0 tw-z-40 tw-bg-slate-950/40 lg:tw-hidden"
      @click="sidebarOpen = false"
    />

    <aside
      :class="[
        'tw-fixed tw-inset-y-0 tw-left-0 tw-z-50 tw-flex tw-w-72 tw-flex-col tw-border-r tw-border-slate-200 tw-bg-white tw-transition-transform tw-duration-200 lg:tw-static lg:tw-translate-x-0',
        sidebarOpen ? 'tw-translate-x-0' : '-tw-translate-x-full',
      ]"
    >
      <div class="tw-flex tw-h-16 tw-items-center tw-gap-3 tw-border-b tw-border-slate-200 tw-px-4">
        <Logo size="lg" variant="square" />
        <div class="tw-min-w-0">
          <p class="tw-text-sm tw-font-bold tw-leading-5 tw-text-slate-950">NGSCHA</p>
          <p class="tw-text-xs tw-text-slate-500">NiCare Systems</p>
        </div>
      </div>

      <div class="tw-border-b tw-border-slate-200 tw-p-3">
        <div class="tw-flex tw-items-center tw-gap-2 tw-rounded-md tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2">
          <v-icon size="17" class="tw-text-slate-400">mdi-magnify</v-icon>
          <input
            v-model="menuSearch"
            class="tw-w-full tw-bg-transparent tw-text-sm tw-text-slate-700 tw-outline-none placeholder:tw-text-slate-400"
            placeholder="Search menu"
            type="search"
          />
        </div>
      </div>

      <nav class="tw-min-h-0 tw-flex-1 tw-overflow-y-auto tw-px-3 tw-py-3">
        <div class="tw-space-y-1">
          <template v-for="item in filteredMenuItems" :key="item.name">
            <router-link
              v-if="!item.children"
              :to="item.path"
              :class="linkClass(item.path)"
            >
              <v-icon size="19">{{ item.icon }}</v-icon>
              <span class="tw-truncate">{{ item.name }}</span>
            </router-link>

            <div v-else class="tw-space-y-1">
              <button
                class="tw-flex tw-w-full tw-items-center tw-gap-3 tw-rounded-md tw-px-3 tw-py-2 tw-text-left tw-text-sm tw-font-semibold tw-transition"
                :class="isSubmenuActive(item) ? 'tw-bg-cyan-50 tw-text-cyan-800' : 'tw-text-slate-700 hover:tw-bg-slate-100'"
                @click="toggleSubmenu(item.name)"
              >
                <v-icon size="19">{{ item.icon }}</v-icon>
                <span class="tw-min-w-0 tw-flex-1 tw-truncate">{{ item.name }}</span>
                <v-icon size="16" :class="expandedMenus.includes(item.name) ? 'tw-rotate-180' : ''">mdi-chevron-down</v-icon>
              </button>

              <div v-show="expandedMenus.includes(item.name)" class="tw-ml-4 tw-border-l tw-border-slate-200 tw-pl-2">
                <router-link
                  v-for="child in item.children"
                  :key="child.path"
                  :to="child.path"
                  :class="linkClass(child.path, true)"
                >
                  <v-icon size="16">{{ child.icon }}</v-icon>
                  <span class="tw-truncate">{{ child.name }}</span>
                </router-link>
              </div>
            </div>
          </template>
        </div>
      </nav>

      <div class="tw-border-t tw-border-slate-200 tw-p-3">
        <div class="tw-flex tw-items-center tw-gap-3">
          <v-avatar size="34" color="primary">
            <v-icon size="18">mdi-account</v-icon>
          </v-avatar>
          <div class="tw-min-w-0 tw-flex-1">
            <p class="tw-truncate tw-text-sm tw-font-semibold tw-text-slate-950">{{ userName }}</p>
            <p class="tw-truncate tw-text-xs tw-text-slate-500">{{ currentRoleName }}</p>
          </div>
          <v-btn icon variant="text" size="small" :loading="logoutLoading" @click="handleLogout">
            <v-icon size="18">mdi-logout</v-icon>
          </v-btn>
        </div>
      </div>
    </aside>

    <section class="tw-flex tw-min-w-0 tw-flex-1 tw-flex-col">
      <header class="tw-flex tw-h-16 tw-items-center tw-justify-between tw-border-b tw-border-slate-200 tw-bg-white tw-px-4 lg:tw-px-6">
        <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
          <v-btn icon variant="text" class="lg:tw-hidden" @click="sidebarOpen = true">
            <v-icon>mdi-menu</v-icon>
          </v-btn>
          <div class="tw-min-w-0">
            <h1 class="tw-truncate tw-text-lg tw-font-semibold tw-text-slate-950">{{ pageTitle }}</h1>
            <p class="tw-hidden tw-text-xs tw-text-slate-500 sm:tw-block">{{ activeGroupName }}</p>
          </div>
        </div>

        <div class="tw-flex tw-items-center tw-gap-2">
          <RoleSwitcher />
          <v-btn icon variant="text" size="small">
            <v-icon>mdi-bell-outline</v-icon>
          </v-btn>
        </div>
      </header>

      <main class="tw-min-h-0 tw-flex-1 tw-overflow-y-auto tw-bg-slate-50">
        <div class="tw-p-4 sm:tw-p-5 lg:tw-p-6">
          <Breadcrumb />
          <slot />
        </div>
      </main>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';
import { canAccessNavigationItem, navigationItems } from '../../navigation';
import Breadcrumb from '../common/Breadcrumb.vue';
import Logo from '../common/Logo.vue';
import RoleSwitcher from '../common/RoleSwitcher.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { success } = useToast();

const sidebarOpen = ref(false);
const logoutLoading = ref(false);
const expandedMenus = ref([]);
const menuSearch = ref('');

const canSeeItem = (item) => canAccessNavigationItem(authStore, item);

const filteredMenuItems = computed(() => {
  const query = menuSearch.value.trim().toLowerCase();

  return navigationItems
    .map((item) => {
      if (!item.children) {
        if (!canSeeItem(item)) return null;
        if (query && !item.name.toLowerCase().includes(query)) return null;
        return item;
      }

      const children = item.children.filter((child) => {
        if (!canSeeItem(child)) return false;
        if (!query) return true;
        return `${item.name} ${child.name}`.toLowerCase().includes(query);
      });

      return children.length > 0 ? { ...item, children } : null;
    })
    .filter(Boolean);
});

const userName = computed(() => authStore.userName || 'User');
const currentRoleName = computed(() => {
  const role = authStore.currentRole || authStore.userRoles[0];
  return role?.label || role?.name || 'User';
});

const activeGroup = computed(() => {
  for (const item of filteredMenuItems.value) {
    if (!item.children && item.path === route.path) return item;
    if (item.children?.some((child) => route.path === child.path || route.path.startsWith(`${child.path}/`))) {
      return item;
    }
  }
  return null;
});

const activeGroupName = computed(() => activeGroup.value?.name || 'Workspace');

const pageTitle = computed(() => {
  for (const item of filteredMenuItems.value) {
    if (!item.children && item.path === route.path) return item.name;
    const child = item.children?.find((entry) => route.path === entry.path || route.path.startsWith(`${entry.path}/`));
    if (child) return child.name;
  }

  return route.meta?.title || 'Dashboard';
});

const linkClass = (path, child = false) => [
  'tw-flex tw-items-center tw-gap-3 tw-rounded-md tw-text-sm tw-transition',
  child ? 'tw-px-3 tw-py-2' : 'tw-px-3 tw-py-2.5',
  route.path === path || route.path.startsWith(`${path}/`)
    ? 'tw-bg-cyan-700 tw-text-white tw-shadow-sm'
    : 'tw-text-slate-600 hover:tw-bg-slate-100 hover:tw-text-slate-950',
];

const toggleSubmenu = (name) => {
  const index = expandedMenus.value.indexOf(name);
  if (index >= 0) {
    expandedMenus.value.splice(index, 1);
  } else {
    expandedMenus.value.push(name);
  }
};

const isSubmenuActive = (item) => item.children?.some((child) => route.path === child.path || route.path.startsWith(`${child.path}/`));

const autoExpand = () => {
  for (const item of navigationItems) {
    if (item.children?.some((child) => route.path === child.path || route.path.startsWith(`${child.path}/`))) {
      if (!expandedMenus.value.includes(item.name)) {
        expandedMenus.value.push(item.name);
      }
    }
  }
};

watch(() => route.path, () => {
  autoExpand();
  sidebarOpen.value = false;
}, { immediate: true });

const handleLogout = async () => {
  logoutLoading.value = true;
  try {
    await authStore.logout();
    success('Logged out successfully');
    router.push('/login');
  } finally {
    logoutLoading.value = false;
  }
};
</script>

<style scoped>
nav::-webkit-scrollbar {
  width: 6px;
}

nav::-webkit-scrollbar-track {
  background: transparent;
}

nav::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 999px;
}
</style>
