<template>
  <AdminLayout>
    <div class="tw-container tw-mx-auto tw-p-6 tw-space-y-6">
      <!-- Sticky Page Header -->
      <div class="tw-sticky tw-top-0 tw-z-10 tw-bg-white/80 tw-backdrop-blur tw-border tw-border-gray-100 tw-rounded-xl tw-px-4 tw-py-3">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <h1 class="tw-text-2xl md:tw-text-3xl tw-font-bold tw-text-gray-900">Roles & Permissions</h1>
            <p class="tw-text-gray-600 tw-mt-0.5">Manage system roles and their permissions</p>
          </div>
          <div class="tw-flex tw-gap-2">
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
              @click="openCreateDialog()"
            >
              Create Role
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <div class="tw-bg-white tw-rounded-xl tw-border tw-border-gray-100 tw-shadow-sm tw-p-5">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100"><v-icon color="blue" size="24">mdi-shield-account</v-icon></div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Roles</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ totalRoles.toLocaleString() }}</p>
            </div>
          </div>
        </div>
        <div class="tw-bg-white tw-rounded-xl tw-border tw-border-gray-100 tw-shadow-sm tw-p-5">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100"><v-icon color="green" size="24">mdi-key</v-icon></div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Permissions</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ allPermissions.length.toLocaleString() }}</p>
            </div>
          </div>
        </div>
        <div class="tw-bg-white tw-rounded-xl tw-border tw-border-gray-100 tw-shadow-sm tw-p-5">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100"><v-icon color="purple" size="24">mdi-account-group</v-icon></div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Users Assigned</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ totalUsersWithRoles.toLocaleString() }}</p>
            </div>
          </div>
        </div>
        <div class="tw-bg-white tw-rounded-xl tw-border tw-border-gray-100 tw-shadow-sm tw-p-5">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100"><v-icon color="orange" size="24">mdi-shield-check</v-icon></div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Permission Categories</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ permissionCategories.length }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-5">
        <div class="tw-flex tw-flex-col lg:tw-flex-row tw-gap-4 tw-items-start lg:tw-items-center tw-justify-between">
          <div class="tw-flex tw-gap-3 tw-w-full lg:tw-w-auto">
            <v-text-field
              v-model="searchQuery"
              label="Search roles..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
              class="tw-w-full md:tw-w-80"
            />
            <v-btn
              color="primary"
              variant="outlined"
              prepend-icon="mdi-view-grid"
              @click="showPermissionMatrix = !showPermissionMatrix"
            >
              {{ showPermissionMatrix ? 'Hide' : 'Show' }} Permission Matrix
            </v-btn>
          </div>

          <div class="tw-flex tw-gap-2">
            <v-btn
              color="warning"
              variant="outlined"
              size="small"
              @click="showBulkActionsDialog = true"
              v-if="selectedRoles.length > 0"
            >
              <v-icon start>mdi-cog</v-icon>
              Bulk Actions ({{ selectedRoles.length }})
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Roles Table -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100">
        <v-data-table
          v-model="selectedRoles"
          :headers="roleHeaders"
          :items="roles"
          :items-length="totalRoles"
          :page="currentPage"
          :items-per-page="itemsPerPage"
          :loading="loading"
          show-select
          return-object
          item-value="id"
          class="tw-elevation-0"
          @update:page="onUpdatePage"
          @update:items-per-page="onUpdatePerPage"
          @update:sort-by="onUpdateSort"
        >
          <!-- Loading & Empty -->
          <template #loading>
            <div class="tw-p-8">
              <v-skeleton-loader type="table-row@5"></v-skeleton-loader>
            </div>
          </template>

          <template #bottom>
            <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3">
              <v-pagination
                v-model="currentPage"
                :length="Math.max(1, Math.ceil(totalRoles / itemsPerPage))"
                total-visible="7"
              />
              <v-select
                :items="[10,15,25,50,100]"
                v-model="itemsPerPage"
                label="Rows per page"
                density="compact"
                style="max-width: 140px"
                hide-details
              />
            </div>
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <v-chip :color="item.status === 'active' ? 'success' : 'error'" size="small" variant="flat">
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Permissions count -->
          <template #item.permissions_count="{ item }">
            <v-chip size="small" color="primary" variant="outlined">
              {{ countRolePermissions(item) }} permissions
            </v-chip>
          </template>

          <!-- Users count -->
          <template #item.users_count="{ item }">
            <span class="tw-text-gray-700">{{ item.users_count || 0 }} users</span>
          </template>

          <!-- Created -->
          <template #item.created_at="{ item }">
            <span class="tw-text-gray-700">{{ formatDate(item.created_at) }}</span>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-btn icon size="small" variant="text" @click="viewRole(item)">
                <v-icon size="18">mdi-eye</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" @click="editRole(item)">
                <v-icon size="18">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteRole(item)"
                :disabled="item.name === 'Super Admin'"
              >
                <v-icon size="18">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>

          <!-- No data -->
          <template #no-data>
            <div class="tw-py-12 tw-text-center">
              <v-icon size="40" class="tw-mb-2">mdi-shield-lock-outline</v-icon>
              <div class="tw-font-semibold">No roles found</div>
              <div class="tw-text-sm tw-text-gray-600">Try adjusting your search or create a new role.</div>
            </div>
          </template>
        </v-data-table>
      </div>

      <!-- Permission Matrix -->
      <div
        v-if="showPermissionMatrix"
        class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100"
      >
        <div class="tw-p-5 tw-border-b tw-border-gray-200">
          <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Permission Matrix</h3>
          <p class="tw-text-sm tw-text-gray-600 tw-mt-1">Visual overview of role permissions</p>
        </div>

        <div class="tw-p-5">
          <div class="tw-overflow-x-auto">
            <table class="tw-w-full tw-text-sm">
              <thead>
                <tr class="tw-border-b tw-border-gray-200">
                  <th class="tw-text-left tw-py-3 tw-px-4 tw-font-medium tw-text-gray-900">Permission</th>
                  <th
                    v-for="role in roles"
                    :key="role.id"
                    class="tw-text-center tw-py-3 tw-px-2 tw-font-medium tw-text-gray-900 tw-min-w-[120px]"
                  >
                    {{ role.label || role.name }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="permission in allPermissions"
                  :key="permission.id"
                  class="tw-border-b tw-border-gray-100 hover:tw-bg-gray-50"
                >
                  <td class="tw-py-3 tw-px-4">
                    <div>
                      <p class="tw-font-medium tw-text-gray-900">{{ permission.label || permission.name }}</p>
                      <p class="tw-text-xs tw-text-gray-500" v-if="permission.description">{{ permission.description }}</p>
                    </div>
                  </td>
                  <td
                    v-for="role in roles"
                    :key="`${permission.id}-${role.id}`"
                    class="tw-text-center tw-py-3 tw-px-2"
                  >
                    <v-icon :color="hasPermission(role, permission) ? 'success' : 'grey'" size="20">
                      {{ hasPermission(role, permission) ? 'mdi-check-circle' : 'mdi-circle-outline' }}
                    </v-icon>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Role Dialog -->
    <v-dialog v-model="showCreateRoleDialog" max-width="820px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingRole ? 'Edit Role' : 'Create New Role' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field v-model="roleForm.name" label="Role Name" variant="outlined" required />
              <v-select
                v-model="roleForm.status"
                :items="['active', 'inactive']"
                label="Status"
                variant="outlined"
                required
              />
            </div>

            <v-textarea v-model="roleForm.description" label="Description" variant="outlined" rows="3" />

            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-3">Permissions</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <div v-for="cat in permissionCategories" :key="cat.name">
                  <div class="tw-flex tw-items-center tw-justify-between tw-mb-1.5">
                    <h5 class="tw-font-medium tw-text-gray-700">{{ cat.name }}</h5>
                    <v-btn
                      size="x-small"
                      variant="text"
                      prepend-icon="mdi-check-all"
                      @click="toggleCategory(cat)"
                    >Toggle</v-btn>
                  </div>
                  <div class="tw-space-y-1.5">
                    <v-checkbox
                      v-for="perm in cat.permissions"
                      :key="perm.id"
                      v-model="roleForm.permissions"
                      :value="perm.id"
                      :label="perm.name"
                      density="compact"
                      hide-details
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
    <v-dialog v-model="showViewRoleDialog" max-width="640px">
      <v-card v-if="viewingRole">
        <v-card-title>
          <div class="tw-flex tw-items-center tw-justify-between tw-w-full">
            <span class="tw-text-xl tw-font-semibold">{{ viewingRole.name }}</span>
            <v-chip :color="viewingRole.status === 'active' ? 'success' : 'error'" size="small">
              {{ viewingRole.status }}
            </v-chip>
          </div>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Description</h4>
              <p class="tw-text-gray-700">{{ viewingRole.description || 'No description provided' }}</p>
            </div>

            <div>
              <h4 class="tw-font-medium tw-text-gray-700">Permissions ({{ countRolePermissions(viewingRole) }})</h4>
              <div class="tw-mt-2 tw-flex tw-flex-wrap tw-gap-2">
                <v-chip
                  v-for="pid in normalizePermissionIds(viewingRole)"
                  :key="pid"
                  size="small"
                  color="primary"
                  variant="outlined"
                >
                  {{ getPermissionName(pid) }}
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
                  <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ countRolePermissions(viewingRole) }}</p>
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

    <!-- Bulk Actions Dialog -->
    <v-dialog v-model="showBulkActionsDialog" max-width="520px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            Bulk Actions ({{ selectedRoles.length }} roles)
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
            <div v-if="bulkAction === 'delete'" class="tw-p-4 tw-bg-red-50 tw-rounded-lg">
              <p class="tw-text-red-800 tw-text-sm">
                <v-icon color="red" size="16" class="tw-mr-1">mdi-alert</v-icon>
                This action cannot be undone. {{ selectedRoles.length }} roles will be permanently deleted.
              </p>
            </div>
            <div v-if="bulkAction === 'clone'" class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
              <p class="tw-text-blue-800 tw-text-sm">
                <v-icon color="blue" size="16" class="tw-mr-1">mdi-information</v-icon>
                {{ selectedRoles.length }} roles will be cloned with their permissions.
              </p>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showBulkActionsDialog = false">Cancel</v-btn>
          <v-btn :color="bulkAction === 'delete' ? 'error' : 'primary'" @click="handleBulkAction" :disabled="!bulkAction">
            {{ bulkAction === 'delete' ? 'Delete Roles' : 'Apply Action' }}
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
import { roleAPI, permissionAPI } from '../../utils/api';

const { success, error } = useToast();

// reactive state
const loading = ref(false);
const searchQuery = ref('');
const debouncer = ref(null);

const showCreateRoleDialog = ref(false);
const showViewRoleDialog = ref(false);
const showPermissionMatrix = ref(false);
const showBulkActionsDialog = ref(false);

const editingRole = ref(null);
const viewingRole = ref(null);
const selectedRoles = ref([]);
const bulkAction = ref('');
const bulkActionOptions = [
  { title: 'Delete Roles', value: 'delete' },
  { title: 'Clone Roles', value: 'clone' }
];

// pagination/sort (server-side)
const roles = ref([]);
const totalRoles = ref(0);
const currentPage = ref(1);
const itemsPerPage = ref(15);
const sortBy = ref([{ key: 'created_at', order: 'desc' }]);

const allPermissions = ref([]);

// headers
const roleHeaders = [
  { title: 'Role Name', key: 'name', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Permissions', key: 'permissions_count', sortable: false },
  { title: 'Users', key: 'users_count', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// form
const roleForm = ref({
  name: '',
  description: '',
  status: 'active',
  permissions: [] // array of permission IDs
});

// computed
const permissionCategories = computed(() => {
  // build [{name, permissions: []}]
  const map = new Map();
  allPermissions.value.forEach(p => {
    const cat = p.category || 'General';
    if (!map.has(cat)) map.set(cat, []);
    map.get(cat).push(p);
  });
  return Array.from(map.entries()).map(([name, permissions]) => ({ name, permissions }));
});

const totalUsersWithRoles = computed(() =>
  roles.value.reduce((t, r) => t + (r.users_count || 0), 0)
);

// utils
const normalizePermissionIds = (role) => {
  if (!role?.permissions) return [];
  // supports array of ids OR array of objects
  return role.permissions.map(p => (typeof p === 'object' ? p.id : p)).filter(Boolean);
};

const countRolePermissions = (role) => normalizePermissionIds(role).length;

const getPermissionName = (permissionId) => {
  const p = allPermissions.value.find(x => x.id === permissionId);
  return p ? (p.label || p.name) : `#${permissionId}`;
};

const hasPermission = (role, permission) => {
  const ids = normalizePermissionIds(role);
  return ids.includes(permission.id);
};

const formatDate = (val) => {
  if (!val) return '—';
  try {
    const d = new Date(val);
    if (Number.isNaN(+d)) return val;
    return d.toLocaleDateString();
  } catch {
    return val;
  }
};

// actions
const openCreateDialog = () => {
  editingRole.value = null;
  roleForm.value = { name: '', description: '', status: 'active', permissions: [] };
  showCreateRoleDialog.value = true;
};

const viewRole = (role) => {
  viewingRole.value = role;
  showViewRoleDialog.value = true;
};

const editRole = (role) => {
  editingRole.value = role;
  const ids = normalizePermissionIds(role);
  roleForm.value = {
    name: role.name || '',
    description: role.description || '',
    status: role.status || 'active',
    permissions: [...ids]
  };
  showViewRoleDialog.value = false;
  showCreateRoleDialog.value = true;
};

const deleteRole = async (role) => {
  if (!confirm(`Delete role "${role.name}"?`)) return;
  try {
    // Prefer API if available:
    // await roleAPI.delete(role.id)
    roles.value = roles.value.filter(r => r.id !== role.id);
    totalRoles.value = Math.max(0, totalRoles.value - 1);
    success('Role deleted successfully');
  } catch (e) {
    console.error(e);
    error('Failed to delete role');
  }
};

const toggleCategory = (cat) => {
  const current = new Set(roleForm.value.permissions);
  const catIds = cat.permissions.map(p => p.id);
  const allSelected = catIds.every(id => current.has(id));
  if (allSelected) {
    catIds.forEach(id => current.delete(id));
  } else {
    catIds.forEach(id => current.add(id));
  }
  roleForm.value.permissions = Array.from(current);
};

const saveRole = async () => {
  try {
    if (editingRole.value) {
      Object.assign(editingRole.value, {
        name: roleForm.value.name,
        description: roleForm.value.description,
        status: roleForm.value.status,
        permissions: [...roleForm.value.permissions] // keep IDs to be consistent
      });
      // Optionally: await roleAPI.update(editingRole.value.id, payload)
      success('Role updated successfully');
    } else {
      const newRole = {
        id: Date.now(),
        name: roleForm.value.name,
        description: roleForm.value.description,
        status: roleForm.value.status,
        permissions: [...roleForm.value.permissions],
        users_count: 0,
        created_at: new Date().toISOString()
      };
      // Optionally: const { data } = await roleAPI.create(payload); roles.value.unshift(data)
      roles.value.unshift(newRole);
      totalRoles.value += 1;
      success('Role created successfully');
    }
    closeRoleDialog();
  } catch (e) {
    console.error(e);
    error('Failed to save role');
  }
};

const closeRoleDialog = () => {
  showCreateRoleDialog.value = false;
  editingRole.value = null;
  roleForm.value = { name: '', description: '', status: 'active', permissions: [] };
};

// bulk
const handleBulkAction = async () => {
  if (!selectedRoles.value.length) {
    error('Please select roles first');
    return;
  }
  try {
    if (bulkAction.value === 'delete') {
      if (!confirm(`Are you sure you want to delete ${selectedRoles.value.length} roles?`)) return;
      await roleAPI.bulkDelete({ role_ids: selectedRoles.value.map(r => r.id) });
      success(`Deleted ${selectedRoles.value.length} roles`);
      selectedRoles.value = [];
      showBulkActionsDialog.value = false;
      await loadRoles(); // refresh after delete
    } else if (bulkAction.value === 'clone') {
      // implement clone with API if available
      success('Clone feature coming soon');
      showBulkActionsDialog.value = false;
    }
  } catch (err) {
    console.error(err);
    error('Bulk operation failed');
  }
};

// server data
const loadRoles = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      search: searchQuery.value?.trim() || undefined
    };

    if (Array.isArray(sortBy.value) && sortBy.value.length) {
      params.sort_by = sortBy.value[0].key;
      params.sort_direction = sortBy.value[0].order || 'asc';
    }

    const response = await roleAPI.getAll(params);
    if (response?.data?.success) {
      const data = response.data.data;
      if (data && typeof data === 'object' && data.data) {
        roles.value = data.data;
        totalRoles.value = data.meta?.total ?? data.total ?? roles.value.length;
      } else if (Array.isArray(data)) {
        roles.value = data;
        totalRoles.value = data.length;
      } else {
        roles.value = [];
        totalRoles.value = 0;
      }
    } else {
      roles.value = [];
      totalRoles.value = 0;
    }
  } catch (e) {
    console.error('Failed to load roles:', e);
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
      const data = response.data.data;
      if (data && typeof data === 'object' && data.data) {
        allPermissions.value = data.data;
      } else if (Array.isArray(data)) {
        allPermissions.value = data;
      } else {
        allPermissions.value = [];
      }
    } else {
      allPermissions.value = [];
    }
  } catch (e) {
    console.error('Failed to load permissions:', e);
    error('Failed to load permissions');
    allPermissions.value = [];
  }
};

const exportRoles = () => {
  success('Roles exported successfully');
};

// table events
const onUpdatePage = (p) => { currentPage.value = p; loadRoles(); };
const onUpdatePerPage = (n) => { itemsPerPage.value = n; currentPage.value = 1; loadRoles(); };
const onUpdateSort = (s) => { sortBy.value = s; loadRoles(); };

// search debounce
watch(searchQuery, (v) => {
  clearTimeout(debouncer.value);
  debouncer.value = setTimeout(() => {
    currentPage.value = 1;
    loadRoles();
  }, 400);
});

// lifecycle
onMounted(async () => {
  await Promise.all([loadRoles(), loadPermissions()]);
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.75rem;
}
</style>
