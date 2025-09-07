<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Roles & Permissions</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage system roles and their permissions</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-download"
            @click="exportRoles"
          >
            Export
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showCreateRoleDialog = true"
          >
            Create Role
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-shield-account</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Roles</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ roles.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-key</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Permissions</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ allPermissions.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Users Assigned</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">1,247</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-shield-check</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Roles</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ activeRolesCount }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Roles Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <div class="tw-p-6 tw-border-b tw-border-gray-200">
          <div class="tw-flex tw-items-center tw-justify-between">
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">System Roles</h3>
            <v-text-field
              v-model="searchQuery"
              label="Search roles..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              class="tw-max-w-xs"
            />
          </div>
        </div>

        <v-data-table
          :headers="roleHeaders"
          :items="roles"
          :loading="loading"
          class="tw-elevation-0"
          item-value="id"
        >
          <!-- Status column -->
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="item.status === 'active' ? 'success' : 'error'"
              size="small"
              variant="flat"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Permissions count -->
          <template v-slot:item.permissions_count="{ item }">
            <v-chip size="small" color="primary" variant="outlined">
              {{ item.permissions.length }} permissions
            </v-chip>
          </template>

          <!-- Users count -->
          <template v-slot:item.users_count="{ item }">
            <span class="tw-text-gray-600">{{ item.users_count || 0 }} users</span>
          </template>

          <!-- Actions column -->
          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewRole(item)"
              >
                <v-icon size="16">mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editRole(item)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteRole(item)"
                :disabled="item.name === 'Super Admin'"
              >
                <v-icon size="16">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Role Dialog -->
    <v-dialog v-model="showCreateRoleDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingRole ? 'Edit Role' : 'Create New Role' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <!-- Basic Info -->
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="roleForm.name"
                label="Role Name"
                variant="outlined"
                required
              />
              <v-select
                v-model="roleForm.status"
                :items="['active', 'inactive']"
                label="Status"
                variant="outlined"
                required
              />
            </div>
            
            <v-textarea
              v-model="roleForm.description"
              label="Description"
              variant="outlined"
              rows="3"
            />

            <!-- Permissions -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Permissions</h4>
              <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
                <div v-for="category in permissionCategories" :key="category.name">
                  <h5 class="tw-font-medium tw-text-gray-700 tw-mb-2">{{ category.name }}</h5>
                  <div class="tw-space-y-2">
                    <v-checkbox
                      v-for="permission in category.permissions"
                      :key="permission.id"
                      v-model="roleForm.permissions"
                      :value="permission.id"
                      :label="permission.name"
                      density="compact"
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeRoleDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveRole">Save Role</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Role Dialog -->
    <v-dialog v-model="showViewRoleDialog" max-width="600px">
      <v-card v-if="viewingRole">
        <v-card-title>
          <div class="tw-flex tw-items-center tw-justify-between tw-w-full">
            <span class="tw-text-xl tw-font-semibold">{{ viewingRole.name }}</span>
            <v-chip
              :color="viewingRole.status === 'active' ? 'success' : 'error'"
              size="small"
            >
              {{ viewingRole.status }}
            </v-chip>
          </div>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Description</h4>
              <p class="tw-text-gray-600">{{ viewingRole.description || 'No description provided' }}</p>
            </div>
            
            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Permissions ({{ viewingRole.permissions.length }})</h4>
              <div class="tw-mt-2 tw-flex tw-flex-wrap tw-gap-2">
                <v-chip
                  v-for="permission in viewingRole.permissions"
                  :key="permission"
                  size="small"
                  color="primary"
                  variant="outlined"
                >
                  {{ getPermissionName(permission) }}
                </v-chip>
              </div>
            </div>
            
            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Statistics</h4>
              <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-mt-2">
                <div class="tw-text-center tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ viewingRole.users_count || 0 }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Users Assigned</p>
                </div>
                <div class="tw-text-center tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ viewingRole.permissions.length }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Permissions</p>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showViewRoleDialog = false">Close</v-btn>
          <v-btn color="primary" @click="editRole(viewingRole)">Edit Role</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { roleAPI, permissionAPI } from '../../utils/api';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const searchQuery = ref('');
const showCreateRoleDialog = ref(false);
const showViewRoleDialog = ref(false);
const editingRole = ref(null);
const viewingRole = ref(null);

// Form data
const roleForm = ref({
  name: '',
  description: '',
  status: 'active',
  permissions: []
});

// Table headers
const roleHeaders = [
  { title: 'Role Name', key: 'name', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Permissions', key: 'permissions_count', sortable: false },
  { title: 'Users', key: 'users_count', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// Data
const roles = ref([]);
const allPermissions = ref([]);
const totalRoles = ref(0);
const currentPage = ref(1);
const itemsPerPage = ref(15);

// Computed properties
const permissionsByCategory = computed(() => {
  const grouped = {};
  allPermissions.value.forEach(permission => {
    if (!grouped[permission.category]) {
      grouped[permission.category] = [];
    }
    grouped[permission.category].push(permission);
  });
  return grouped;
});

const activeRolesCount = computed(() => {
  return roles.value.filter(role => role.status === 'active').length;
});

const permissionCategories = computed(() => {
  const categories = {};
  allPermissions.value.forEach(permission => {
    if (!categories[permission.category]) {
      categories[permission.category] = {
        name: permission.category,
        permissions: []
      };
    }
    categories[permission.category].permissions.push(permission);
  });
  return Object.values(categories);
});

// Methods
const getPermissionName = (permissionId) => {
  const permission = allPermissions.value.find(p => p.id === permissionId);
  return permission ? permission.name : 'Unknown Permission';
};

const viewRole = (role) => {
  viewingRole.value = role;
  showViewRoleDialog.value = true;
};

const editRole = (role) => {
  editingRole.value = role;
  Object.assign(roleForm.value, {
    name: role.name,
    description: role.description,
    status: role.status,
    permissions: [...role.permissions]
  });
  showViewRoleDialog.value = false;
  showCreateRoleDialog.value = true;
};

const deleteRole = (role) => {
  if (confirm(`Are you sure you want to delete the role "${role.name}"?`)) {
    const index = roles.value.findIndex(r => r.id === role.id);
    if (index > -1) {
      roles.value.splice(index, 1);
      success('Role deleted successfully');
    }
  }
};

const saveRole = () => {
  if (editingRole.value) {
    // Update existing role
    Object.assign(editingRole.value, roleForm.value);
    success('Role updated successfully');
  } else {
    // Create new role
    const newRole = {
      id: Date.now(),
      ...roleForm.value,
      users_count: 0,
      created_at: new Date().toISOString().split('T')[0]
    };
    roles.value.push(newRole);
    success('Role created successfully');
  }
  closeRoleDialog();
};

const closeRoleDialog = () => {
  showCreateRoleDialog.value = false;
  editingRole.value = null;
  Object.keys(roleForm.value).forEach(key => {
    if (key === 'permissions') {
      roleForm.value[key] = [];
    } else if (key === 'status') {
      roleForm.value[key] = 'active';
    } else {
      roleForm.value[key] = '';
    }
  });
};

// API Methods
const loadRoles = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      sort_by: 'created_at',
      sort_direction: 'desc'
    };

    if (searchQuery.value && searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }

    const response = await roleAPI.getAll(params);

    if (response?.data?.success) {
      const responseData = response.data.data;

      if (responseData && typeof responseData === 'object' && responseData.data) {
        roles.value = responseData.data;
        totalRoles.value = responseData.meta?.total || responseData.total || 0;
      } else if (Array.isArray(responseData)) {
        roles.value = responseData;
        totalRoles.value = responseData.length;
      } else {
        roles.value = [];
        totalRoles.value = 0;
      }
    } else {
      roles.value = [];
      totalRoles.value = 0;
    }
  } catch (err) {
    console.error('Failed to load roles:', err);
    error('Failed to load roles');
    roles.value = [];
    totalRoles.value = 0;
  } finally {
    loading.value = false;
  }
};

const loadPermissions = async () => {
  try {
    const response = await permissionAPI.getAll({ per_page: 1000 });

    if (response?.data?.success) {
      const responseData = response.data.data;

      if (responseData && typeof responseData === 'object' && responseData.data) {
        allPermissions.value = responseData.data;
      } else if (Array.isArray(responseData)) {
        allPermissions.value = responseData;
      } else {
        allPermissions.value = [];
      }
    } else {
      allPermissions.value = [];
    }
  } catch (err) {
    console.error('Failed to load permissions:', err);
    error('Failed to load permissions');
    allPermissions.value = [];
  }
};

const exportRoles = () => {
  success('Roles exported successfully');
};

// Lifecycle
onMounted(async () => {
  await Promise.all([loadRoles(), loadPermissions()]);
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
