<template>
  <div v-if="canSwitchRoles" class="tw-relative">
    <v-menu>
      <template v-slot:activator="{ props }">
        <v-btn
          v-bind="props"
          variant="outlined"
          size="small"
          :prepend-icon="currentRoleIcon"
          class="tw-text-xs"
        >
          <span class="tw-hidden sm:tw-inline">{{ currentRoleDisplay }}</span>
          <span class="tw-sm:tw-hidden">Role</span>
          <v-icon size="16" class="tw-ml-1">mdi-chevron-down</v-icon>
        </v-btn>
      </template>

      <v-list density="compact" min-width="200">
        <!-- Current Role Header -->
        <v-list-subheader class="tw-text-xs tw-font-medium tw-text-gray-600">
          Current Role
        </v-list-subheader>
        
        <!-- All Roles Option -->
        <v-list-item
          @click="switchToAllRoles"
          :class="{ 'tw-bg-blue-50': !currentRole }"
        >
          <template v-slot:prepend>
            <v-icon 
              :color="!currentRole ? 'primary' : 'grey'" 
              size="20"
            >
              mdi-account-multiple
            </v-icon>
          </template>
          <v-list-item-title class="tw-text-sm">
            All Roles
          </v-list-item-title>
          <v-list-item-subtitle class="tw-text-xs">
            Access all permissions
          </v-list-item-subtitle>
          <template v-slot:append v-if="!currentRole">
            <v-icon color="primary" size="16">mdi-check</v-icon>
          </template>
        </v-list-item>

        <v-divider class="tw-my-1" />

        <!-- Available Roles -->
        <v-list-subheader class="tw-text-xs tw-font-medium tw-text-gray-600">
          Switch to Role
        </v-list-subheader>
        
        <v-list-item
          v-for="role in availableRoles"
          :key="role.id"
          @click="switchRole(role)"
          :class="{ 'tw-bg-blue-50': currentRole?.id === role.id }"
        >
          <template v-slot:prepend>
            <v-icon 
              :color="currentRole?.id === role.id ? 'primary' : getRoleColor(role.name)" 
              size="20"
            >
              {{ getRoleIcon(role.name) }}
            </v-icon>
          </template>
          <v-list-item-title class="tw-text-sm">
            {{ role.name }}
          </v-list-item-title>
          <v-list-item-subtitle class="tw-text-xs">
            {{ getPermissionCount(role) }} permissions
          </v-list-item-subtitle>
          <template v-slot:append v-if="currentRole?.id === role.id">
            <v-icon color="primary" size="16">mdi-check</v-icon>
          </template>
        </v-list-item>

        <v-divider class="tw-my-1" />

        <!-- Role Info -->
        <v-list-item class="tw-bg-gray-50" disabled>
          <v-list-item-title class="tw-text-xs tw-text-gray-600">
            <v-icon size="14" class="tw-mr-1">mdi-information-outline</v-icon>
            Role switching affects your permissions
          </v-list-item-title>
        </v-list-item>
      </v-list>
    </v-menu>

    <!-- Role Change Notification -->
    <v-snackbar
      v-model="showRoleChangeNotification"
      :timeout="3000"
      color="success"
      location="top"
    >
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-check-circle</v-icon>
        <span>Switched to {{ lastSwitchedRole }}</span>
      </div>
    </v-snackbar>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useToast } from '../../composables/useToast';

const authStore = useAuthStore();
const { success } = useToast();

// Reactive data
const showRoleChangeNotification = ref(false);
const lastSwitchedRole = ref('');

// Computed properties
const canSwitchRoles = computed(() => authStore.canSwitchRoles);
const availableRoles = computed(() => authStore.getUserAvailableRoles);
const currentRole = computed(() => authStore.getUserCurrentRole);

const currentRoleDisplay = computed(() => {
  if (!currentRole.value) return 'All Roles';
  return currentRole.value.name;
});

const currentRoleIcon = computed(() => {
  if (!currentRole.value) return 'mdi-account-multiple';
  return getRoleIcon(currentRole.value.name);
});

// Methods
const getRoleIcon = (roleName) => {
  switch (roleName.toLowerCase()) {
    case 'super admin': return 'mdi-shield-crown';
    case 'admin': return 'mdi-shield-account';
    case 'manager': return 'mdi-account-tie';
    case 'staff': return 'mdi-account';
    case 'user': return 'mdi-account-circle';
    default: return 'mdi-account-group';
  }
};

const getRoleColor = (roleName) => {
  switch (roleName.toLowerCase()) {
    case 'super admin': return 'red';
    case 'admin': return 'purple';
    case 'manager': return 'blue';
    case 'staff': return 'green';
    case 'user': return 'orange';
    default: return 'grey';
  }
};

const getPermissionCount = (role) => {
  return role.permissions?.length || 0;
};

const switchRole = (role) => {
  try {
    authStore.switchRole(role);
    lastSwitchedRole.value = role.name;
    showRoleChangeNotification.value = true;
    success(`Switched to ${role.name} role`);
  } catch (error) {
    console.error('Failed to switch role:', error);
  }
};

const switchToAllRoles = () => {
  authStore.resetToAllRoles();
  lastSwitchedRole.value = 'All Roles';
  showRoleChangeNotification.value = true;
  success('Switched to All Roles mode');
};
</script>

<style scoped>
/* Custom styles for role switcher */
:deep(.v-btn) {
  text-transform: none;
}

:deep(.v-list-item) {
  min-height: 48px;
}

:deep(.v-list-item--active) {
  background-color: rgb(239 246 255);
}
</style>
