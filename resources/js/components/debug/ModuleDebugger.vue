<template>
  <v-card class="tw-m-4 tw-p-4">
    <v-card-title>Module Access Debugger</v-card-title>
    <v-card-text>
      <div class="tw-space-y-4">
        <!-- Auth Store State -->
        <div>
          <h3 class="tw-font-bold tw-text-lg tw-mb-2">Auth Store State</h3>
          <pre class="tw-bg-gray-100 tw-p-2 tw-rounded tw-text-xs tw-overflow-auto">{{ authStoreState }}</pre>
        </div>

        <!-- Current Role -->
        <div>
          <h3 class="tw-font-bold tw-text-lg tw-mb-2">Current Role</h3>
          <pre class="tw-bg-gray-100 tw-p-2 tw-rounded tw-text-xs tw-overflow-auto">{{ currentRole }}</pre>
        </div>

        <!-- Available Modules -->
        <div>
          <h3 class="tw-font-bold tw-text-lg tw-mb-2">Available Modules</h3>
          <pre class="tw-bg-gray-100 tw-p-2 tw-rounded tw-text-xs tw-overflow-auto">{{ availableModules }}</pre>
        </div>

        <!-- User Roles -->
        <div>
          <h3 class="tw-font-bold tw-text-lg tw-mb-2">User Roles</h3>
          <pre class="tw-bg-gray-100 tw-p-2 tw-rounded tw-text-xs tw-overflow-auto">{{ userRoles }}</pre>
        </div>

        <!-- Module Options -->
        <div>
          <h3 class="tw-font-bold tw-text-lg tw-mb-2">Module Options (Computed)</h3>
          <pre class="tw-bg-gray-100 tw-p-2 tw-rounded tw-text-xs tw-overflow-auto">{{ moduleOptions }}</pre>
        </div>

        <!-- Refresh Button -->
        <v-btn color="primary" @click="refresh">Refresh Data</v-btn>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

const authStoreState = computed(() => ({
  user: authStore.user,
  currentRole: authStore.currentRole,
  availableRoles: authStore.availableRoles,
  isAuthenticated: authStore.isAuthenticated,
}));

const currentRole = computed(() => authStore.currentRole);
const availableModules = computed(() => authStore.availableModules);
const userRoles = computed(() => authStore.user?.roles || []);

const allModuleOptions = [
  { value: 'general', label: 'Core & Admin' },
  { value: 'pas', label: 'Pre-Authorization (PAS)' },
  { value: 'claims', label: 'Claims' },
  { value: 'automation', label: 'Claims Automation' },
  { value: 'management', label: 'Management' },
];

const moduleOptions = computed(() => {
  const availableModules = authStore.availableModules || [];
  
  if (authStore.hasRole('Super Admin')) {
    return allModuleOptions;
  }

  if (availableModules.length === 0) {
    return [{ value: 'general', label: 'Core & Admin' }];
  }

  return allModuleOptions.filter(option => availableModules.includes(option.value));
});

const refresh = async () => {
  console.log('Refreshing user data...');
  await authStore.fetchUser();
  console.log('User data refreshed');
};
</script>

