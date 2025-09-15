<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Manage Users</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage user accounts, roles, and permissions</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-download" @click="exportUsers">Export</v-btn>
          <v-btn color="warning" variant="outlined" prepend-icon="mdi-upload" @click="showImportDialog = true">Import</v-btn>
          <v-btn color="primary" prepend-icon="mdi-plus" @click="showCreateUserDialog = true">Add User</v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Users</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                 {{ tableItemsLength.toLocaleString() }}
                <!-- {{ (metaCounts.total ?? totalUsers).toLocaleString() }} -->
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Users</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ (metaCounts.active ?? activeUsersCount).toLocaleString() }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-clock-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Pending</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ (metaCounts.pending ?? pendingUsersCount).toLocaleString() }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-red-100">
              <v-icon color="red" size="24">mdi-account-cancel</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Suspended</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ (metaCounts.suspended ?? suspendedUsersCount).toLocaleString() }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100 tw-sticky tw-top-0 tw-z-10">
        <div class="tw-flex tw-flex-col lg:tw-flex-row tw-gap-4 tw-items-start lg:tw-items-center tw-justify-between">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4 tw-flex-1">
            <v-text-field
              v-model="searchQuery"
              label="Search users…"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
            <v-select v-model="filters.role" :items="roleOptions" label="Filter by Role" variant="outlined" density="compact" clearable hide-details />
            <v-select v-model="filters.status" :items="statusOptions" label="Filter by Status" variant="outlined" density="compact" clearable hide-details />
          </div>

          <div v-if="selectedUsers.length" class="tw-flex tw-gap-2">
            <v-btn color="warning" variant="outlined" size="small" @click="showBulkActionsDialog = true">
              <v-icon start>mdi-cog</v-icon>
              Bulk Actions ({{ selectedUsers.length }})
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Users Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <v-data-table
          v-model="selectedUsers"
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="users"
          :items-length="tableItemsLength"
          :loading="loading"
          class="elevation-0"
          item-value="id"
          show-select
          density="comfortable"
          :items-per-page-options="[10,15,25,50]"
        >
          <!-- Top bar -->
          <template #top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200 tw-flex tw-items-center tw-justify-between">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Users List</h3>
              <v-chip size="small" color="primary" variant="tonal">{{ totalUsers.toLocaleString() }} users</v-chip>
            </div>
          </template>

          <!-- Empty -->
          <template #no-data>
            <div class="tw-p-10 tw-text-center tw-text-gray-500">
              <v-icon size="28" class="tw-mb-2">mdi-account-search</v-icon>
              No users found. Adjust filters or try a different search.
            </div>
          </template>

          <!-- Loading -->
          <template #loading>
            <div class="tw-p-8">
              <v-skeleton-loader type="table" />
            </div>
          </template>

          <!-- User column -->
        
          <template #item.name="{ item }">
          <div class="tw-flex tw-items-center tw-space-x-3">
            <v-avatar size="32" color="primary">
              <span class="tw-text-white tw-text-sm">
                {{ getInitials(item.raw.name, item.raw.username, item.raw.email) }}
              </span>
            </v-avatar>
            <div>
              <p class="tw-font-medium tw-text-gray-900">{{ item.raw.name || '—' }}</p>
              <p class="tw-text-sm tw-text-gray-500">{{ item.raw.username || item.raw.email }}</p>
            </div>
          </div>
        </template>


          <!-- Status -->
          <template #item.status="{ item }">
            <v-chip :color="getStatusColor(item.raw.status)" size="small" variant="flat">
              {{ getStatusLabel(item.raw.status) }}
            </v-chip>
          </template>

          <!-- Roles -->
          <template #item.roles="{ item }">
            <div class="tw-flex tw-flex-wrap tw-gap-1">
              <v-chip
                v-for="role in (item.raw.roles || [])"
                :key="role.id"
                size="small"
                color="primary"
                variant="outlined"
              >
                {{ role.label || role.name }}
              </v-chip>
              <span v-if="!item.raw.roles || item.raw.roles.length === 0" class="tw-text-gray-400 tw-text-sm">No roles</span>
            </div>
          </template>

          <!-- Created -->
          <template #item.created_at="{ item }">
            <span class="tw-text-gray-600">{{ formatDate(item.raw.created_at) }}</span>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-tooltip text="View Profile">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" @click="viewUser(item.raw)">
                    <v-icon size="16">mdi-eye</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Edit User">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" @click="editUser(item.raw)">
                    <v-icon size="16">mdi-pencil</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Manage Roles">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="warning" @click="manageRoles(item.raw)">
                    <v-icon size="16">mdi-shield-account</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip :text="item.raw.status === 1 ? 'Suspend User' : 'Activate User'">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    :color="item.raw.status === 1 ? 'orange' : 'green'"
                    @click="toggleUserStatus(item.raw)"
                  >
                    <v-icon size="16">{{ item.raw.status === 1 ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Impersonate User" v-if="canImpersonate(item.raw)">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="purple" @click="impersonateUser(item.raw)">
                    <v-icon size="16">mdi-account-switch</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Delete User">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="error" @click="deleteUser(item.raw)">
                    <v-icon size="16">mdi-delete</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit User Dialog -->
    <v-dialog v-model="showCreateUserDialog" max-width="600px">
      <v-card>
        <v-card-title><span class="tw-text-xl tw-font-semibold">{{ editingUser ? 'Edit User' : 'Create New User' }}</span></v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field v-model="userForm.name" label="Full Name" variant="outlined" required />
              <v-text-field v-model="userForm.email" label="Email" type="email" variant="outlined" required />
            </div>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field v-model="userForm.phone" label="Phone Number" variant="outlined" />
              <v-text-field v-model="userForm.username" label="Username" variant="outlined" />
            </div>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-select v-model="userForm.status" :items="statusOptions" label="Status" variant="outlined" required />
              <v-select v-model="userForm.roles" :items="roleOptions" label="Roles" variant="outlined" multiple chips />
            </div>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4" v-if="!editingUser">
              <v-text-field v-model="userForm.password" label="Password" type="password" variant="outlined" required />
              <v-text-field v-model="userForm.password_confirmation" label="Confirm Password" type="password" variant="outlined" required />
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeUserDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveUser">Save User</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Manage Roles Dialog -->
    <v-dialog v-model="showRolesDialog" max-width="500px">
      <v-card v-if="managingUser">
        <v-card-title><span class="tw-text-xl tw-font-semibold">Manage Roles - {{ managingUser.name }}</span></v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700 tw-mb-2">Assign Roles</h4>
              <v-select v-model="selectedRoles" :items="roleOptions" label="Select Roles" variant="outlined" multiple chips />
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRolesDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="updateUserRoles">Update Roles</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Actions Dialog -->
    <v-dialog v-model="showBulkActionsDialog" max-width="500px">
      <v-card>
        <v-card-title><span class="tw-text-xl tw-font-semibold">Bulk Actions ({{ selectedUsers.length }} users)</span></v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-select v-model="bulkAction" :items="bulkActionOptions" label="Select Action" variant="outlined" required />
            <div v-if="bulkAction === 'status'">
              <v-select v-model="bulkActionData.status" :items="statusOptions" label="New Status" variant="outlined" required />
            </div>
            <div v-if="bulkAction === 'delete'" class="tw-p-4 tw-bg-red-50 tw-rounded-lg">
              <p class="tw-text-red-800 tw-text-sm">
                <v-icon color="red" size="16" class="tw-mr-1">mdi-alert</v-icon>
                This action cannot be undone. {{ selectedUsers.length }} users will be permanently deleted.
              </p>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showBulkActionsDialog = false">Cancel</v-btn>
          <v-btn :color="bulkAction === 'delete' ? 'error' : 'primary'" @click="handleBulkAction" :disabled="!bulkAction">
            {{ bulkAction === 'delete' ? 'Delete Users' : 'Apply Action' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Users Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title><span class="tw-text-xl tw-font-semibold">Import Users</span></v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-file-input
              v-model="importFile"
              label="Select CSV File"
              accept=".csv,.txt"
              variant="outlined"
              prepend-icon="mdi-file-upload"
              show-size
            />
            <div class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
              <h4 class="tw-font-medium tw-text-blue-900 tw-mb-2">CSV Format Requirements:</h4>
              <p class="tw-text-blue-800 tw-text-sm tw-mb-2">Your CSV file should have the following columns:</p>
              <ul class="tw-text-blue-800 tw-text-sm tw-list-disc tw-list-inside tw-space-y-1">
                <li><strong>name</strong> (required) - Full name</li>
                <li><strong>email</strong> (required) - Email address</li>
                <li><strong>username</strong> (optional) - Username</li>
                <li><strong>phone</strong> (optional) - Phone number</li>
                <li><strong>password</strong> (optional) - Password (defaults to 'password123')</li>
                <li><strong>status</strong> (optional) - Active/Inactive/Suspended</li>
                <li><strong>roles</strong> (optional) - Comma-separated role names</li>
              </ul>
            </div>
            <v-btn color="primary" variant="outlined" prepend-icon="mdi-download" @click="downloadTemplate" block>Download CSV Template</v-btn>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showImportDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="importUsers" :disabled="!importFile" :loading="importing">Import Users</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { useToast } from '../../composables/useToast'
import { userAPI, roleAPI } from '../../utils/api'

const { success, error } = useToast()

// state
const loading = ref(false)
const searchQuery = ref('')
const showCreateUserDialog = ref(false)
const showRolesDialog = ref(false)
const showBulkActionsDialog = ref(false)
const showImportDialog = ref(false)
const editingUser = ref(null)
const managingUser = ref(null)
const selectedRoles = ref([])
const selectedUsers = ref([]) // IDs because we removed `return-object`
const itemsPerPage = ref(15)
const currentPage = ref(1)
const totalUsers = ref(0)
const users = ref([])
const roles = ref([])
const importFile = ref(null)
const importing = ref(false)
const metaCounts = ref({ total: null, active: null, pending: null, suspended: null })

// filters
const filters = ref({ role: null, status: null, search: '' })

// form
const userForm = ref({
  name: '', email: '', phone: '', username: '',
  status: 1, roles: [], password: '', password_confirmation: ''
})

// bulk
const bulkAction = ref('')
const bulkActionData = ref({})

// options
const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Pending', value: 0 },
  { title: 'Suspended', value: 2 },
]
const bulkActionOptions = [
  { title: 'Update Status', value: 'status' },
  { title: 'Delete Users', value: 'delete' },
]

// table headers (do NOT include a "select" column; `show-select` adds it)

const headers = [
  { title: 'User', key: 'name', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px' }
];
// show-select will inject the checkbox column for you


// add this computed
const tableItemsLength = computed(() => totalUsers.value || users.value.length || 0);


// api
// replace your fetchUsers function with this
const fetchUsers = async () => {
  try {
    loading.value = true;

    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      search: filters.value.search,
      status: filters.value.status,
      role: filters.value.role,
    };

    const res = await userAPI.getAll(params);
    console.log('res', res);

    // Normalize: many backends return either {data:[...]} or {data:{data:[...], meta:{...}}}
    const list =
      Array.isArray(res?.data?.data)               ? res.data.data :
      Array.isArray(res?.data)                     ? res.data      :
      Array.isArray(res)                           ? res           : [];

    const total =
      res?.data?.meta?.total ??
      res?.data?.pagination?.total ??
      list.length;

    users.value = list;
    totalUsers.value = total;

    // optional: set card counts if your API doesn’t give them
    metaCounts.value = {
      total,
      active: list.filter(u => u.status === 1).length,
      pending: list.filter(u => u.status === 0).length,
      suspended: list.filter(u => u.status === 2).length,
    };
  } catch (e) {
    error('Failed to fetch users');
    console.error(e);
  } finally {
    loading.value = false;
  }
};


const fetchRoles = async () => {
  try {
    const res = await roleAPI.getAll()
    roles.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Failed to fetch roles:', e)
  }
}

// actions
const createUser = async () => {
  try {
    loading.value = true
    const payload = { ...userForm.value }
    payload.roles = payload.roles.map(r => (typeof r === 'object' ? r.id : r))
    await userAPI.create(payload)
    success('User created successfully')
    closeUserDialog()
    fetchUsers()
  } catch (e) {
    error('Failed to create user')
    console.error(e)
  } finally {
    loading.value = false
  }
}

const updateUser = async () => {
  try {
    loading.value = true
    const payload = { ...userForm.value }
    delete payload.password_confirmation
    if (!payload.password) delete payload.password
    payload.roles = payload.roles.map(r => (typeof r === 'object' ? r.id : r))
    await userAPI.update(editingUser.value.id, payload)
    success('User updated successfully')
    closeUserDialog()
    fetchUsers()
  } catch (e) {
    error('Failed to update user')
    console.error(e)
  } finally {
    loading.value = false
  }
}

const deleteUser = async (user) => {
  if (!confirm(`Delete ${user.name}?`)) return
  try {
    await userAPI.delete(user.id)
    success('User deleted successfully')
    fetchUsers()
  } catch (e) {
    error('Failed to delete user')
    console.error(e)
  }
}

const toggleUserStatus = async (user) => {
  try {
    await userAPI.toggleStatus(user.id)
    success('User status updated successfully')
    fetchUsers()
  } catch (e) {
    error('Failed to update user status')
    console.error(e)
  }
}

// computed
const currentUser = computed(() => JSON.parse(localStorage.getItem('user') || '{}'))

const roleOptions = computed(() =>
  roles.value.map(r => ({ title: r.label || r.name, value: r.id }))
)

const activeUsersCount = computed(() => users.value.filter(u => u.status === 1).length)
const pendingUsersCount = computed(() => users.value.filter(u => u.status === 0).length)
const suspendedUsersCount = computed(() => users.value.filter(u => u.status === 2).length)

// utils
const getInitials = (name, username, email) => {
  const source = (name && name.trim()) || username || email || '?';
  return source
    .split(/[ .@_-]+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(s => s[0])
    .join('')
    .toUpperCase();
};
const getStatusColor = (status) => (status === 1 ? 'success' : status === 0 ? 'warning' : status === 2 ? 'error' : 'grey')
const getStatusLabel = (status) => (status === 1 ? 'Active' : status === 0 ? 'Pending' : status === 2 ? 'Suspended' : 'Unknown')
const formatDate = (d) => (d ? new Date(d).toLocaleDateString() : 'Never')

// dialogs
const viewUser = async (user) => {
  try {
    const res = await userAPI.getProfile(user.id)
    console.log('User profile:', res.data)
  } catch {
    error('Failed to load user profile')
  }
}

const editUser = (user) => {
  editingUser.value = user
  Object.assign(userForm.value, {
    name: user.name,
    email: user.email,
    phone: user.phone,
    username: user.username,
    status: user.status,
    roles: user.roles?.map(r => r.id) || [],
    password: '',
    password_confirmation: '',
  })
  showCreateUserDialog.value = true
}

const manageRoles = (user) => {
  managingUser.value = user
  selectedRoles.value = user.roles?.map(r => r.id) || []
  showRolesDialog.value = true
}

const updateUserRoles = async () => {
  try {
    await userAPI.syncRoles(managingUser.value.id, selectedRoles.value)
    success('User roles updated successfully')
    showRolesDialog.value = false
    fetchUsers()
  } catch (e) {
    error('Failed to update user roles')
    console.error(e)
  }
}

const saveUser = () => (editingUser.value ? updateUser() : createUser())

const closeUserDialog = () => {
  showCreateUserDialog.value = false
  editingUser.value = null
  Object.assign(userForm.value, {
    name: '', email: '', phone: '', username: '',
    status: 1, roles: [], password: '', password_confirmation: '',
  })
}

// bulk
const handleBulkAction = async () => {
  if (!selectedUsers.value.length) {
    error('Please select users first')
    return
  }
  try {
    if (bulkAction.value === 'status') {
      await userAPI.bulkUpdateStatus({ user_ids: selectedUsers.value, status: bulkActionData.value.status })
      success(`Updated status for ${selectedUsers.value.length} users`)
    } else if (bulkAction.value === 'delete') {
      if (!confirm(`Delete ${selectedUsers.value.length} users?`)) return
      await userAPI.bulkDelete({ user_ids: selectedUsers.value })
      success(`Deleted ${selectedUsers.value.length} users`)
    }
    selectedUsers.value = []
    showBulkActionsDialog.value = false
    fetchUsers()
  } catch (e) {
    error('Bulk operation failed')
    console.error(e)
  }
}

// export/import
const exportUsers = async () => {
  try {
    const res = await userAPI.export(filters.value)
    // Fallback: export current page if API returns plain list
    if (res?.data?.success && res.data.data?.data) {
      const { filename, data } = res.data.data
      const csv = data.map(row => row.join(',')).join('\n')
      const blob = new Blob([csv], { type: 'text/csv' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url; a.download = filename || 'users.csv'; a.click()
      URL.revokeObjectURL(url)
      success('Export started')
    } else {
      // fallback CSV from current items
      const rows = [
        ['name','email','username','phone','status','roles'],
        ...users.value.map(u => [
          u.name, u.email, u.username ?? '', u.phone ?? '', getStatusLabel(u.status),
          (u.roles || []).map(r => r.label || r.name).join('|'),
        ])
      ]
      const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n')
      const blob = new Blob([csv], { type: 'text/csv' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url; a.download = 'users.csv'; a.click()
      URL.revokeObjectURL(url)
      success('Exported current list')
    }
  } catch (e) {
    error('Failed to export users')
    console.error(e)
  }
}

const importUsers = async () => {
  if (!importFile.value) {
    error('Please select a CSV file')
    return
  }
  importing.value = true
  try {
    const form = new FormData()
    // v-file-input (no multiple) => a single File
    form.append('file', importFile.value)
    const res = await userAPI.import(form)
    if (res?.data?.success) {
      const { imported, errors = [], total_rows } = res.data.data || {}
      if (errors.length) {
        console.warn('Import errors:', errors)
        error(`Imported with ${errors.length} errors`)
      } else {
        success(`Successfully imported ${imported} of ${total_rows} users`)
      }
      showImportDialog.value = false
      importFile.value = null
      fetchUsers()
    }
  } catch (e) {
    error('Failed to import users')
    console.error(e)
  } finally {
    importing.value = false
  }
}

const downloadTemplate = () => {
  const rows = [
    ['name','email','username','phone','password','status','roles'],
    ['John Doe','john@example.com','john.doe','+1234567890','password123','Active','User'],
    ['Jane Smith','jane@example.com','jane.smith','+0987654321','password123','Active','Admin,User'],
  ]
  const csv = rows.map(r => r.join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url; a.download = 'users_import_template.csv'; a.click()
  URL.revokeObjectURL(url)
}

const canImpersonate = (user) => user.id !== currentUser.value?.id
const impersonateUser = async (user) => {
  if (!confirm(`Impersonate ${user.name}?`)) return
  try {
    const res = await userAPI.impersonate(user.id)
    if (res?.data?.success) {
      success(`Now impersonating ${user.name}`)
      window.location.href = '/admin/dashboard'
    }
  } catch (e) {
    error('Failed to impersonate user')
    console.error(e)
  }
}

// watchers
watch([() => filters.value.role, () => filters.value.status, () => filters.value.search, currentPage, itemsPerPage], fetchUsers)

let searchDebounce
watch(searchQuery, (val) => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => { filters.value.search = val; }, 300)
})

// lifecycle
onMounted(() => {
  fetchUsers()
  fetchRoles()
})
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
