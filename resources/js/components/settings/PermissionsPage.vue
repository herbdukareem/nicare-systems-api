<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Permission Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage system permissions and categories</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-download"
            @click="exportPermissions"
          >
            Export
          </v-btn>
          <v-btn 
            color="warning" 
            variant="outlined" 
            prepend-icon="mdi-plus-box-multiple"
            @click="showBulkCreateDialog = true"
          >
            Bulk Create
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showCreatePermissionDialog = true"
          >
            Add Permission
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-key</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Permissions</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ totalPermissions.toLocaleString() }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-folder</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Categories</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ categories.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-shield-account</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Assigned to Roles</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ assignedPermissionsCount.toLocaleString() }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-key-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Unassigned</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ unassignedPermissionsCount.toLocaleString() }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters and Actions -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
        <div class="tw-flex tw-flex-col tw-lg:tw-flex-row tw-gap-4 tw-items-start tw-lg:tw-items-center tw-justify-between">
          <!-- Filters -->
          <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-3 tw-gap-4 tw-flex-1">
            <!-- Search -->
            <v-text-field
              v-model="searchQuery"
              label="Search permissions..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
            
            <!-- Category Filter -->
            <v-select
              v-model="filters.category"
              :items="categoryOptions"
              label="Filter by Category"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
            
            <!-- View Toggle -->
            <v-btn-toggle
              v-model="viewMode"
              variant="outlined"
              density="compact"
              mandatory
            >
              <v-btn value="table" size="small">
                <v-icon>mdi-table</v-icon>
                Table
              </v-btn>
              <v-btn value="category" size="small">
                <v-icon>mdi-folder-multiple</v-icon>
                Categories
              </v-btn>
            </v-btn-toggle>
          </div>
          
          <!-- Bulk Actions -->
          <div class="tw-flex tw-gap-2" v-if="selectedPermissions.length > 0">
            <v-btn
              color="warning"
              variant="outlined"
              size="small"
              @click="showBulkActionsDialog = true"
            >
              <v-icon start>mdi-cog</v-icon>
              Bulk Actions ({{ selectedPermissions.length }})
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Table View -->
      <div v-if="viewMode === 'table'" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <v-data-table
          v-model="selectedPermissions"
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="permissions"
          :loading="loading"
          :items-length="totalPermissions"
          class="tw-elevation-0"
          item-value="id"
          show-select
          return-object
        >
          <!-- Custom header -->
          <template v-slot:top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
                  Permissions List
                </h3>
                <v-chip size="small" color="primary">
                  {{ totalPermissions.toLocaleString() }} permissions
                </v-chip>
              </div>
            </div>
          </template>

          <!-- Permission Name column -->
          <template v-slot:item.name="{ item }">
            <div>
              <p class="tw-font-medium tw-text-gray-900">{{ item.label || item.name }}</p>
              <p class="tw-text-sm tw-text-gray-500 tw-font-mono">{{ item.name }}</p>
            </div>
          </template>

          <!-- Category column -->
          <template v-slot:item.category="{ item }">
            <v-chip
              size="small"
              color="primary"
              variant="outlined"
            >
              {{ item.category || 'General' }}
            </v-chip>
          </template>

          <!-- Roles count -->
          <template v-slot:item.roles_count="{ item }">
            <span class="tw-text-gray-600">{{ item.roles_count || 0 }} roles</span>
          </template>

          <!-- Created At column -->
          <template v-slot:item.created_at="{ item }">
            <span class="tw-text-gray-600">
              {{ formatDate(item.created_at) }}
            </span>
          </template>

          <!-- Actions column -->
          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-tooltip text="View Details">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    @click="viewPermission(item)"
                  >
                    <v-icon size="16">mdi-eye</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
              
              <v-tooltip text="Edit Permission">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    @click="editPermission(item)"
                  >
                    <v-icon size="16">mdi-pencil</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
              
              <v-tooltip text="Delete Permission">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    color="error"
                    @click="deletePermission(item)"
                  >
                    <v-icon size="16">mdi-delete</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
            </div>
          </template>
        </v-data-table>
      </div>

      <!-- Category View -->
      <div v-if="viewMode === 'category'" class="tw-space-y-6">
        <div 
          v-for="category in categorizedPermissions" 
          :key="category.name"
          class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100"
        >
          <div class="tw-p-6 tw-border-b tw-border-gray-200">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ category.name }}</h3>
                <p class="tw-text-sm tw-text-gray-600">{{ category.permissions.length }} permissions</p>
              </div>
              <v-btn
                color="primary"
                variant="outlined"
                size="small"
                @click="selectCategoryPermissions(category)"
              >
                Select All
              </v-btn>
            </div>
          </div>
          
          <div class="tw-p-6">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-3 tw-gap-4">
              <div 
                v-for="permission in category.permissions" 
                :key="permission.id"
                class="tw-p-4 tw-border tw-border-gray-200 tw-rounded-lg hover:tw-border-blue-300 tw-transition-colors"
                :class="{ 'tw-border-blue-500 tw-bg-blue-50': selectedPermissions.some(p => p.id === permission.id) }"
              >
                <div class="tw-flex tw-items-start tw-justify-between">
                  <div class="tw-flex-1">
                    <h4 class="tw-font-medium tw-text-gray-900">{{ permission.label || permission.name }}</h4>
                    <p class="tw-text-sm tw-text-gray-500 tw-font-mono tw-mt-1">{{ permission.name }}</p>
                    <p class="tw-text-sm tw-text-gray-600 tw-mt-2">{{ permission.description }}</p>
                    <div class="tw-flex tw-items-center tw-mt-3">
                      <v-chip size="x-small" color="primary" variant="outlined">
                        {{ permission.roles_count || 0 }} roles
                      </v-chip>
                    </div>
                  </div>
                  <v-checkbox
                    :model-value="selectedPermissions.some(p => p.id === permission.id)"
                    @update:model-value="togglePermissionSelection(permission)"
                    hide-details
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Permission Dialog -->
    <v-dialog v-model="showCreatePermissionDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingPermission ? 'Edit Permission' : 'Create New Permission' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="permissionForm.name"
                label="Permission Name"
                variant="outlined"
                required
                hint="e.g., users.create"
              />
              <v-text-field
                v-model="permissionForm.label"
                label="Display Label"
                variant="outlined"
                required
                hint="e.g., Create Users"
              />
            </div>

            <v-select
              v-model="permissionForm.category"
              :items="categoryOptions"
              label="Category"
              variant="outlined"
              required
            />

            <v-textarea
              v-model="permissionForm.description"
              label="Description"
              variant="outlined"
              rows="3"
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closePermissionDialog">Cancel</v-btn>
          <v-btn color="primary" @click="savePermission">Save Permission</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Permission Dialog -->
    <v-dialog v-model="showViewPermissionDialog" max-width="600px">
      <v-card v-if="viewingPermission">
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Permission Details</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Basic Information</h4>
              <div class="tw-mt-2 tw-space-y-2">
                <div class="tw-flex tw-justify-between">
                  <span class="tw-text-gray-600">Name:</span>
                  <span class="tw-font-mono">{{ viewingPermission.name }}</span>
                </div>
                <div class="tw-flex tw-justify-between">
                  <span class="tw-text-gray-600">Label:</span>
                  <span>{{ viewingPermission.label }}</span>
                </div>
                <div class="tw-flex tw-justify-between">
                  <span class="tw-text-gray-600">Category:</span>
                  <v-chip size="small" color="primary" variant="outlined">
                    {{ viewingPermission.category }}
                  </v-chip>
                </div>
                <div class="tw-flex tw-justify-between">
                  <span class="tw-text-gray-600">Description:</span>
                  <span>{{ viewingPermission.description || 'No description' }}</span>
                </div>
              </div>
            </div>

            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Usage Statistics</h4>
              <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-mt-2">
                <div class="tw-text-center tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ viewingPermission.roles_count || 0 }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Roles Assigned</p>
                </div>
                <div class="tw-text-center tw-p-3 tw-bg-gray-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ formatDate(viewingPermission.created_at) }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Created</p>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showViewPermissionDialog = false">Close</v-btn>
          <v-btn color="primary" @click="editPermission(viewingPermission)">Edit Permission</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Create Dialog -->
    <v-dialog v-model="showBulkCreateDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Bulk Create Permissions</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-select
              v-model="bulkCreateForm.category"
              :items="categoryOptions"
              label="Category"
              variant="outlined"
              required
            />

            <v-textarea
              v-model="bulkCreateForm.permissions"
              label="Permissions (one per line)"
              variant="outlined"
              rows="10"
              hint="Format: permission_name|Display Label|Description"
              placeholder="users.create|Create Users|Allow creating new users
users.edit|Edit Users|Allow editing user information
users.delete|Delete Users|Allow deleting users"
            />

            <div class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
              <p class="tw-text-blue-800 tw-text-sm">
                <v-icon color="blue" size="16" class="tw-mr-1">mdi-information</v-icon>
                Use the format: <code>permission_name|Display Label|Description</code> (description is optional)
              </p>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showBulkCreateDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="bulkCreatePermissions">Create Permissions</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Actions Dialog -->
    <v-dialog v-model="showBulkActionsDialog" max-width="500px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            Bulk Actions ({{ selectedPermissions.length }} permissions)
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-select
              v-model="bulkAction"
              :items="bulkActionOptions"
              label="Select Action"
              variant="outlined"
              required
            />

            <div v-if="bulkAction === 'category'">
              <v-select
                v-model="bulkActionData.category"
                :items="categoryOptions"
                label="New Category"
                variant="outlined"
                required
              />
            </div>

            <div v-if="bulkAction === 'delete'" class="tw-p-4 tw-bg-red-50 tw-rounded-lg">
              <p class="tw-text-red-800 tw-text-sm">
                <v-icon color="red" size="16" class="tw-mr-1">mdi-alert</v-icon>
                This action cannot be undone. {{ selectedPermissions.length }} permissions will be permanently deleted.
              </p>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showBulkActionsDialog = false">Cancel</v-btn>
          <v-btn
            :color="bulkAction === 'delete' ? 'error' : 'primary'"
            @click="handleBulkAction"
            :disabled="!bulkAction"
          >
            {{ bulkAction === 'delete' ? 'Delete Permissions' : 'Apply Action' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { permissionAPI } from '../../utils/api';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const searchQuery = ref('');
const viewMode = ref('table');
const showCreatePermissionDialog = ref(false);
const showViewPermissionDialog = ref(false);
const showBulkCreateDialog = ref(false);
const showBulkActionsDialog = ref(false);
const editingPermission = ref(null);
const viewingPermission = ref(null);
const selectedPermissions = ref([]);
const bulkAction = ref('');
const bulkActionData = ref({});

// Form data
const permissionForm = ref({
  name: '',
  label: '',
  category: '',
  description: ''
});

const bulkCreateForm = ref({
  category: '',
  permissions: ''
});

// Filters
const filters = ref({
  category: null
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalPermissions = ref(0);

// Data
const permissions = ref([]);
const categories = ref([]);

// Table headers
const headers = [
  { title: 'Permission', key: 'name', sortable: true },
  { title: 'Category', key: 'category', sortable: true },
  { title: 'Roles', key: 'roles_count', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// Computed properties
const categoryOptions = computed(() => {
  const uniqueCategories = [...new Set(permissions.value.map(p => p.category || 'General'))];
  return uniqueCategories.map(cat => ({ title: cat, value: cat }));
});

const categorizedPermissions = computed(() => {
  const grouped = {};
  permissions.value.forEach(permission => {
    const category = permission.category || 'General';
    if (!grouped[category]) {
      grouped[category] = {
        name: category,
        permissions: []
      };
    }
    grouped[category].permissions.push(permission);
  });
  return Object.values(grouped);
});

const assignedPermissionsCount = computed(() => {
  return permissions.value.filter(p => (p.roles_count || 0) > 0).length;
});

const unassignedPermissionsCount = computed(() => {
  return permissions.value.filter(p => (p.roles_count || 0) === 0).length;
});

const bulkActionOptions = [
  { title: 'Change Category', value: 'category' },
  { title: 'Delete Permissions', value: 'delete' }
];

// Methods
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

const viewPermission = (permission) => {
  viewingPermission.value = permission;
  showViewPermissionDialog.value = true;
};

const editPermission = (permission) => {
  editingPermission.value = permission;
  Object.assign(permissionForm.value, {
    name: permission.name,
    label: permission.label || permission.name,
    category: permission.category || 'General',
    description: permission.description || ''
  });
  showViewPermissionDialog.value = false;
  showCreatePermissionDialog.value = true;
};

const deletePermission = async (permission) => {
  if (!confirm(`Are you sure you want to delete the permission "${permission.name}"?`)) return;

  try {
    await permissionAPI.delete(permission.id);
    success('Permission deleted successfully');
    loadPermissions();
  } catch (err) {
    error('Failed to delete permission');
    console.error(err);
  }
};

const savePermission = async () => {
  try {
    if (editingPermission.value) {
      await permissionAPI.update(editingPermission.value.id, permissionForm.value);
      success('Permission updated successfully');
    } else {
      await permissionAPI.create(permissionForm.value);
      success('Permission created successfully');
    }
    closePermissionDialog();
    loadPermissions();
  } catch (err) {
    error('Failed to save permission');
    console.error(err);
  }
};

const closePermissionDialog = () => {
  showCreatePermissionDialog.value = false;
  editingPermission.value = null;
  Object.keys(permissionForm.value).forEach(key => {
    permissionForm.value[key] = '';
  });
};

const togglePermissionSelection = (permission) => {
  const index = selectedPermissions.value.findIndex(p => p.id === permission.id);
  if (index > -1) {
    selectedPermissions.value.splice(index, 1);
  } else {
    selectedPermissions.value.push(permission);
  }
};

const selectCategoryPermissions = (category) => {
  category.permissions.forEach(permission => {
    if (!selectedPermissions.value.some(p => p.id === permission.id)) {
      selectedPermissions.value.push(permission);
    }
  });
};

const bulkCreatePermissions = async () => {
  if (!bulkCreateForm.value.permissions.trim()) {
    error('Please enter permissions to create');
    return;
  }

  try {
    const lines = bulkCreateForm.value.permissions.split('\n').filter(line => line.trim());
    const permissionsToCreate = lines.map(line => {
      const parts = line.split('|').map(part => part.trim());
      return {
        name: parts[0],
        label: parts[1] || parts[0],
        description: parts[2] || '',
        category: bulkCreateForm.value.category
      };
    });

    await permissionAPI.bulkCreate({ permissions: permissionsToCreate });
    success(`Created ${permissionsToCreate.length} permissions successfully`);
    showBulkCreateDialog.value = false;
    bulkCreateForm.value = { category: '', permissions: '' };
    loadPermissions();
  } catch (err) {
    error('Failed to create permissions');
    console.error(err);
  }
};

const handleBulkAction = async () => {
  if (selectedPermissions.value.length === 0) {
    error('Please select permissions first');
    return;
  }

  try {
    const permissionIds = selectedPermissions.value.map(p => p.id);

    if (bulkAction.value === 'delete') {
      if (!confirm(`Are you sure you want to delete ${selectedPermissions.value.length} permissions?`)) return;
      await permissionAPI.bulkDelete({ permission_ids: permissionIds });
      success(`Deleted ${selectedPermissions.value.length} permissions`);
    } else if (bulkAction.value === 'category') {
      await permissionAPI.bulkUpdate({
        permission_ids: permissionIds,
        category: bulkActionData.value.category
      });
      success(`Updated category for ${selectedPermissions.value.length} permissions`);
    }

    selectedPermissions.value = [];
    showBulkActionsDialog.value = false;
    bulkAction.value = '';
    bulkActionData.value = {};
    loadPermissions();
  } catch (err) {
    error('Bulk operation failed');
    console.error(err);
  }
};

const exportPermissions = () => {
  success('Export feature coming soon');
};

// API Methods
const loadPermissions = async () => {
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

    if (filters.value.category) {
      params.category = filters.value.category;
    }

    const response = await permissionAPI.getAll(params);

    if (response?.data?.success) {
      const responseData = response.data.data;

      if (responseData && typeof responseData === 'object' && responseData.data) {
        permissions.value = responseData.data;
        totalPermissions.value = responseData.total || responseData.data.length;
      } else if (Array.isArray(responseData)) {
        permissions.value = responseData;
        totalPermissions.value = responseData.length;
      } else {
        permissions.value = [];
        totalPermissions.value = 0;
      }
    } else {
      permissions.value = [];
      totalPermissions.value = 0;
    }
  } catch (err) {
    console.error('Failed to load permissions:', err);
    error('Failed to load permissions');
    permissions.value = [];
    totalPermissions.value = 0;
  } finally {
    loading.value = false;
  }
};

// Watchers
watch([searchQuery, filters], () => {
  currentPage.value = 1;
  loadPermissions();
}, { deep: true });

watch([currentPage, itemsPerPage], () => {
  loadPermissions();
});

// Lifecycle
onMounted(() => {
  loadPermissions();
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
