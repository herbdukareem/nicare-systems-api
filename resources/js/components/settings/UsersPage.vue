<template>
  <AdminLayout>
    <div class="tw-space-y-6 tw-pb-10">
      <!-- Header -->
      <header class="tw-flex tw-items-start md:tw-items-center tw-justify-between tw-gap-4">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Users</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage user accounts, roles, and permissions</p>
        </div>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-download" @click="exportUsers">Export</v-btn>
          <v-btn color="warning" variant="outlined" prepend-icon="mdi-upload" @click="showImportDialog = true">Import</v-btn>
          <v-btn color="primary" prepend-icon="mdi-plus" @click="showCreateUserDialog = true">Add User</v-btn>
        </div>
      </header>

      <!-- Stats -->
      <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Users</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ users.length.toLocaleString() }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ activeUsersCount.toLocaleString() }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-clock-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Pending</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ pendingUsersCount.toLocaleString() }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-red-100">
              <v-icon color="red" size="24">mdi-account-cancel</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Suspended</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ suspendedUsersCount.toLocaleString() }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Filters (sticky) -->
      <section
        class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-sticky tw-top-0 tw-z-30 tw-backdrop-blur-sm"
        :class="{'tw-shadow-md': isStuck}"
        ref="stickyRef"
      >
        <div class="tw-p-4 tw-flex tw-flex-col lg:tw-flex-row tw-gap-4 tw-items-start lg:tw-items-center tw-justify-between">
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
            <v-select
              v-model="filters.role"
              :items="roleOptions"
              label="Filter by Role"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              hide-details
            />
          </div>

          <div v-if="selectedUsers.length" class="tw-flex tw-gap-2">
            <v-btn color="warning" variant="outlined" size="small" @click="showBulkActionsDialog = true">
              <v-icon start>mdi-cog</v-icon>
              Bulk Actions ({{ selectedUsers.length }})
            </v-btn>
          </div>
        </div>
      </section>

      <!-- Table -->
      <section class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100">
        
        <v-data-table
          v-model="selectedUsers"
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="filteredUsers"
          :loading="loading"
          class="elevation-0"
          item-value="id"
          item-key="id"
          show-select
          density="comfortable"
          fixed-header
          height="640"
          :items-per-page-options="[10,15,25,50]"
        >
          <!-- Top -->
          <template #top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200 tw-flex tw-items-center tw-justify-between">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Users List</h3>
              <v-chip size="small" color="primary" variant="tonal">
                {{ users.length.toLocaleString() }} users
              </v-chip>
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

          <!-- User -->
          <template #item.name="{ item }">
            <div class="tw-flex tw-items-center tw-gap-3">
              <v-avatar size="32" color="primary">
                <span class="tw-text-white tw-text-sm">
                  {{ getInitials(item.name, item.username, item.email) }}
                </span>
              </v-avatar>
              <div class="tw-min-w-0">
                <p class="tw-font-medium tw-text-gray-900 tw-truncate">{{ item.name || '—' }}</p>
                <p class="tw-text-sm tw-text-gray-500 tw-truncate">{{ item.username || item.email }}</p>
              </div>
            </div>
          </template>

          <!-- Email (show dimmed if empty) -->
          <template #item.email="{ item }">
            <span :class="item.email ? 'tw-text-gray-700' : 'tw-text-gray-400'">
              {{ item.email || '—' }}
            </span>
          </template>

          <!-- Phone -->
          <template #item.phone="{ item }">
            <span class="tw-text-gray-700">{{ item.phone || '—' }}</span>
          </template>

          <!-- Roles -->
          <template #item.roles="{ item }">
            <div class="tw-flex tw-flex-wrap tw-gap-1">
              <v-chip
                v-for="role in (item.roles || [])"
                :key="role.id || role.name"
                size="small"
                color="primary"
                variant="outlined"
              >
                {{ role.label || role.name }}
              </v-chip>
              <span v-if="!item.roles || item.roles.length === 0" class="tw-text-gray-400 tw-text-sm">No roles</span>
            </div>
          </template>

          <!-- Status -->
          <template #item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small" variant="flat">
              {{ getStatusLabel(item.status, item.status_label) }}
            </v-chip>
          </template>

          <!-- Created -->
          <template #item.created_at="{ item }">
            <span class="tw-text-gray-600">{{ formatDate(item.created_at) }}</span>
          </template>

          <!-- Actions -->
          <template #item.actions="{ item }">
            <div class="tw-flex tw-flex tw-gap-1">
              <v-tooltip text="View Profile">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" @click="viewUser(item)">
                    <v-icon size="16">mdi-eye</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Edit User">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" @click="editUser(item)">
                    <v-icon size="16">mdi-pencil</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Manage Roles">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="warning" @click="manageRoles(item)">
                    <v-icon size="16">mdi-shield-account</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip :text="item.status === 1 ? 'Suspend User' : 'Activate User'">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    :color="item.status === 1 ? 'orange' : 'green'"
                    @click="toggleUserStatus(item)"
                  >
                    <v-icon size="16">{{ item.status === 1 ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Impersonate User" v-if="canImpersonate(item)">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="purple" @click="impersonateUser(item)">
                    <v-icon size="16">mdi-account-switch</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Delete User">
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon size="small" variant="text" color="error" @click="deleteUser(item)">
                    <v-icon size="16">mdi-delete</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
            </div>
          </template>

          <!-- Bottom -->
          <template #bottom>
            <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-200">
              <div class="tw-text-sm tw-text-gray-600">
                Showing
                <span class="tw-font-medium">{{ pageStart }}</span>
                –
                <span class="tw-font-medium">{{ pageEnd }}</span>
                of
                <span class="tw-font-medium">{{ filteredUsers.length.toLocaleString() }}</span>
              </div>
              <v-pagination
                v-model="currentPage"
                :length="Math.max(1, Math.ceil(filteredUsers.length / itemsPerPage))"
                density="comfortable"
                prev-icon="mdi-chevron-left"
                next-icon="mdi-chevron-right"
              />
            </div>
          </template>
        </v-data-table>
      </section>
    </div>

    <!-- Create/Edit User Dialog -->
    <v-dialog v-model="showCreateUserDialog" max-width="640px" persistent>
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingUser ? 'Edit User' : 'Create New User' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <!-- User Type Selection (only for new users) -->
            <div v-if="!editingUser" class="tw-grid tw-grid-cols-1 tw-gap-4">
              <v-select
                v-model="userForm.userable_type"
                :items="userableTypeOptions"
                label="User Type *"
                variant="outlined"
                required
                :error-messages="validationErrors.userable_type"
              />
            </div>

            <!-- Basic User Information -->
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="userForm.name"
                label="Full Name"
                variant="outlined"
                required
                :error-messages="validationErrors.name"
              />
              <v-text-field
                v-model="userForm.email"
                label="Email"
                type="email"
                variant="outlined"
                required
                :error-messages="validationErrors.email"
              />
            </div>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="userForm.phone"
                label="Phone Number"
                variant="outlined"
                :error-messages="validationErrors.phone"
              />
              <v-text-field
                v-model="userForm.username"
                label="Username"
                variant="outlined"
                :error-messages="validationErrors.username"
              />
            </div>

            <!-- Profile Information (only for new users) -->
            <div v-if="!editingUser" class="tw-space-y-4">
              <v-divider />
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900">Profile Information</h4>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
                <v-text-field
                  v-model="userForm.first_name"
                  label="First Name *"
                  variant="outlined"
                  required
                  :error-messages="validationErrors.first_name"
                />
                <v-text-field
                  v-model="userForm.last_name"
                  label="Last Name *"
                  variant="outlined"
                  required
                  :error-messages="validationErrors.last_name"
                />
                <v-text-field
                  v-model="userForm.middle_name"
                  label="Middle Name"
                  variant="outlined"
                  :error-messages="validationErrors.middle_name"
                />
              </div>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-text-field
                  v-model="userForm.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  variant="outlined"
                  :error-messages="validationErrors.date_of_birth"
                />
                <v-select
                  v-model="userForm.gender"
                  :items="genderOptions"
                  label="Gender"
                  variant="outlined"
                  :error-messages="validationErrors.gender"
                />
              </div>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-select
                  v-model="userForm.department_id"
                  :items="departmentOptions"
                  label="Department"
                  variant="outlined"
                  item-title="name"
                  item-value="id"
                  :error-messages="validationErrors.department_id"
                />
                <v-select
                  v-model="userForm.designation_id"
                  :items="designationOptions"
                  label="Designation"
                  variant="outlined"
                  item-title="title"
                  item-value="id"
                  :error-messages="validationErrors.designation_id"
                />
              </div>

              <v-textarea
                v-model="userForm.address"
                label="Address"
                variant="outlined"
                rows="2"
                :error-messages="validationErrors.address"
              />
            </div>

            <!-- System Settings -->
            <div class="tw-space-y-4">
              <v-divider />
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900">System Settings</h4>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-select
                  v-model="userForm.status"
                  :items="statusOptions"
                  label="Status"
                  variant="outlined"
                  required
                  :error-messages="validationErrors.status"
                />
                <v-select
                  v-model="userForm.roles"
                  :items="roleOptions"
                  label="Roles"
                  variant="outlined"
                  multiple
                  chips
                  :error-messages="validationErrors.roles"
                />
              </div>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4" v-if="!editingUser">
                <v-text-field
                  v-model="userForm.password"
                  label="Password"
                  type="password"
                  variant="outlined"
                  required
                  :error-messages="validationErrors.password"
                />
                <v-text-field
                  v-model="userForm.password_confirmation"
                  label="Confirm Password"
                  type="password"
                  variant="outlined"
                  required
                  :error-messages="validationErrors.password_confirmation"
                />
              </div>
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

    <!-- Manage Roles -->
    <v-dialog v-model="showRolesDialog" max-width="520px" persistent>
      <v-card v-if="managingUser">
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Manage Roles — {{ managingUser.name }}</span>
        </v-card-title>
        <v-card-text>
          <v-select v-model="selectedRoles" :items="roleOptions" label="Select Roles" variant="outlined" multiple chips />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRolesDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="updateUserRoles">Update Roles</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Actions -->
    <v-dialog v-model="showBulkActionsDialog" max-width="520px" persistent>
      <v-card>
        <v-card-title><span class="tw-text-xl tw-font-semibold">Bulk Actions ({{ selectedUsers.length }} users)</span></v-card-title>
        <v-card-text class="tw-space-y-4">
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

    <!-- Import -->
    <v-dialog v-model="showImportDialog" max-width="640px" persistent>
      <v-card>
        <v-card-title><span class="tw-text-xl tw-font-semibold">Import Users</span></v-card-title>
        <v-card-text class="tw-space-y-4">
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
            <ul class="tw-text-blue-800 tw-text-sm tw-list-disc tw-list-inside tw-space-y-1">
              <li><strong>name</strong> (required)</li>
              <li><strong>email</strong> (required)</li>
              <li><strong>username</strong> (optional)</li>
              <li><strong>phone</strong> (optional)</li>
              <li><strong>password</strong> (optional; default: password123)</li>
              <li><strong>status</strong> (Active / Pending / Suspended)</li>
              <li><strong>roles</strong> (comma-separated role names)</li>
            </ul>
          </div>
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-download" @click="downloadTemplate" block>
            Download CSV Template
          </v-btn>
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
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { useToast } from '../../composables/useToast'
import { userAPI, roleAPI, departmentAPI, designationAPI } from '../../utils/api'

const { success, error } = useToast()

/** ===== state ===== */
const loading = ref(false)
const searchQuery = ref('')
const showCreateUserDialog = ref(false)
const showRolesDialog = ref(false)
const showBulkActionsDialog = ref(false)
const showImportDialog = ref(false)
const editingUser = ref(null)
const managingUser = ref(null)
const selectedRoles = ref([])
const selectedUsers = ref([]) // array of IDs
const itemsPerPage = ref(15)
const currentPage = ref(1)
const users = ref([])
const roles = ref([])
const departments = ref([])
const designations = ref([])
const validationErrors = ref({})
const importFile = ref(null)
const importing = ref(false)

/** sticky helper */
const stickyRef = ref(null)
const isStuck = ref(false)
const onScroll = () => {
  if (!stickyRef.value) return
  const rect = stickyRef.value.getBoundingClientRect()
  isStuck.value = rect.top <= 0
}
window.addEventListener('scroll', onScroll, { passive: true })
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

/** filters/form/bulk/options */
const filters = ref({ role: null, status: null, search: '' })
const userForm = ref({
  name: '', email: '', phone: '', username: '',
  status: 1, roles: [], password: '', password_confirmation: '',
  userable_type: 'Staff', first_name: '', last_name: '', middle_name: '',
  date_of_birth: '', gender: '', department_id: '', designation_id: '', address: ''
})
const bulkAction = ref('')
const bulkActionData = ref({})
const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Pending', value: 0 },
  { title: 'Suspended', value: 2 },
]
const bulkActionOptions = [
  { title: 'Update Status', value: 'status' },
  { title: 'Delete Users', value: 'delete' },
]
const userableTypeOptions = [
  { title: 'Staff', value: 'Staff' },
  { title: 'Desk Officer', value: 'DeskOfficer' },
]
const genderOptions = [
  { title: 'Male', value: 'Male' },
  { title: 'Female', value: 'Female' },
]

/** headers */
const headers = [
  { title: 'User', key: 'name', sortable: true, minWidth: 260 },
  { title: 'Email', key: 'email', sortable: true, minWidth: 220 },
  { title: 'Phone', key: 'phone', sortable: false, minWidth: 140 },
  { title: 'Roles', key: 'roles', sortable: false, minWidth: 220 },
  { title: 'Status', key: 'status', sortable: true, width: 130, align: 'start' },
  { title: 'Created', key: 'created_at', sortable: true, width: 140 },
  { title: 'Actions', key: 'actions', sortable: false, width: 400, align: 'start' }
]

/** derived */
const roleOptions = computed(() => roles.value.map(r => ({ title: r.label || r.name, value: r.id })))
const departmentOptions = computed(() => departments.value.map(d => ({ name: d.name, id: d.id })))
const designationOptions = computed(() => designations.value.map(d => ({ title: d.title, id: d.id })))
const filteredUsers = computed(() => {
  // local filtering for client mode (fast and robust)
  let list = [...users.value]

  if (filters.value.search) {
    const q = filters.value.search.toLowerCase()
    list = list.filter(u =>
      (u.name || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      (u.username || '').toLowerCase().includes(q) ||
      (u.phone || '').toLowerCase().includes(q)
    )
  }
  if (filters.value.status !== null && filters.value.status !== undefined) {
    list = list.filter(u => u.status === filters.value.status)
  }
  if (filters.value.role) {
    const roleId = filters.value.role
    list = list.filter(u => (u.roles || []).some(r => (r.id ?? r) === roleId))
  }
  return list
})
const pageStart = computed(() => (filteredUsers.value.length ? ((currentPage.value - 1) * itemsPerPage.value) + 1 : 0))
const pageEnd = computed(() => Math.min(currentPage.value * itemsPerPage.value, filteredUsers.value.length))

/** counts */
const activeUsersCount = computed(() => users.value.filter(u => u.status === 1).length)
const pendingUsersCount = computed(() => users.value.filter(u => u.status === 0).length)
const suspendedUsersCount = computed(() => users.value.filter(u => u.status === 2).length)

/** api */
const fetchUsers = async () => {
  try {
    loading.value = true
    // We still pass params (useful when you later switch back to server mode),
    // but we render client-side with the full array.
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      search: filters.value.search,
      status: filters.value.status,
      role: filters.value.role,
    }
    const res = await userAPI.getAll(params)

    // Robust parsing for your sample: { success, message, data: [...] }
    const payload = res?.data ?? res ?? {}
    const list =
      Array.isArray(payload.data)                      ? payload.data :
      (payload.data && Array.isArray(payload.data.data)) ? payload.data.data :
      Array.isArray(payload.items)                     ? payload.items :
      Array.isArray(payload)                           ? payload : []

    users.value = list
  } catch (e) {
    error('Failed to fetch users')
    console.error(e)
  } finally {
    loading.value = false
  }
}


const fetchRoles = async () => {
  try {
    // ask backend for a big page so you get “all” roles at once (adjust if needed)
    const res = await roleAPI.getAll({ per_page: 1000, page: 1 })

    const payload = res?.data ?? res ?? {}
    // normalize to a plain array regardless of backend shape
    const list =
      Array.isArray(payload.data) ? payload.data :
      (payload.data && Array.isArray(payload.data.data)) ? payload.data.data :
      Array.isArray(payload.items) ? payload.items :
      Array.isArray(payload) ? payload : []

    roles.value = list
  } catch (e) {
    console.error('Failed to fetch roles:', e)
    roles.value = []
    // If authentication fails, show a helpful message
    if (e.response?.status === 401) {
      error('Please log in to access user management features')
    }
  }
}


const fetchDepartments = async () => {
  try {
    const res = await departmentAPI.getAll()
    const payload = res?.data ?? res ?? {}
    departments.value = Array.isArray(payload.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  } catch (e) {
    console.error('Failed to fetch departments:', e)
  }
}

const fetchDesignations = async () => {
  try {
    const res = await designationAPI.getAll()
    const payload = res?.data ?? res ?? {}
    designations.value = Array.isArray(payload.data) ? payload.data : (Array.isArray(payload) ? payload : [])
  } catch (e) {
    console.error('Failed to fetch designations:', e)
  }
}

/** actions */
const createUser = async () => {
  try {
    loading.value = true
    validationErrors.value = {} // Clear previous validation errors
    const payload = { ...userForm.value }
    payload.roles = payload.roles.map(r => (typeof r === 'object' ? r.id : r))
    await userAPI.create(payload)
    success('User created successfully')
    closeUserDialog()
    fetchUsers()
  } catch (e) {
    console.error('Error creating user:', e)

    // Handle validation errors
    if (e.response?.status === 422 && e.response?.data?.errors) {
      validationErrors.value = e.response.data.errors
      const message = e.response?.data?.message || 'Validation failed'
      error(message)
    } else {
      error('Failed to create user')
    }
  } finally {
    loading.value = false
  }
}

const updateUser = async () => {
  try {
    loading.value = true
    validationErrors.value = {} // Clear previous validation errors
    const payload = { ...userForm.value }
    delete payload.password_confirmation
    if (!payload.password) delete payload.password
    payload.roles = payload.roles.map(r => (typeof r === 'object' ? r.id : r))
    await userAPI.update(editingUser.value.id, payload)
    success('User updated successfully')
    closeUserDialog()
    fetchUsers()
  } catch (e) {
    console.error('Error updating user:', e)

    // Handle validation errors
    if (e.response?.status === 422 && e.response?.data?.errors) {
      validationErrors.value = e.response.data.errors
      const message = e.response?.data?.message || 'Validation failed'
      error(message)
    } else {
      error('Failed to update user')
    }
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

/** dialogs */
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
  validationErrors.value = {} // Clear validation errors
  Object.assign(userForm.value, {
    name: '', email: '', phone: '', username: '',
    status: 1, roles: [], password: '', password_confirmation: '',
    userable_type: 'Staff', first_name: '', last_name: '', middle_name: '',
    date_of_birth: '', gender: '', department_id: '', designation_id: '', address: ''
  })
}

/** export/import */
const exportUsers = async () => {
  try {
    const res = await userAPI.export(filters.value)
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
      // fallback: export what we have on screen (client mode)
      const rows = [
        ['name','email','username','phone','status','roles'],
        ...users.value.map(u => [
          u.name, u.email, u.username ?? '', u.phone ?? '', getStatusLabel(u.status, u.status_label),
          (u.roles || []).map(r => r.label || r.name).join('|'),
        ])
      ]
      const csv = rows.map(r => r.map(v => `"${String(v ?? '').replace(/"/g,'""')}"`).join(',')).join('\n')
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

/** utils */
const currentUser = computed(() => {
  try { return JSON.parse(localStorage.getItem('user') || '{}') } catch { return {} }
})
const canImpersonate = (user) => user?.id && user.id !== currentUser.value?.id
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
const getInitials = (name, username, email) => {
  const source = (name && name.trim()) || username || email || '?'
  return source.split(/[ .@_-]+/).filter(Boolean).slice(0, 2).map(s => s[0]).join('').toUpperCase()
}
const getStatusColor = (status) => (status === 1 ? 'success' : status === 0 ? 'warning' : status === 2 ? 'error' : 'grey')
const getStatusLabel = (status, label = '') =>
  label || (status === 1 ? 'Active' : status === 0 ? 'Pending' : status === 2 ? 'Suspended' : 'Unknown')
const formatDate = (d) => {
  if (!d) return 'Never'
  const date = typeof d === 'string' ? new Date(d) : d
  return isNaN(date.getTime()) ? '—' : date.toLocaleDateString()
}

/** watchers */
watch([() => filters.value.role, () => filters.value.status, () => filters.value.search], () => {
  currentPage.value = 1
})
let searchDebounce
watch(searchQuery, (val) => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => { filters.value.search = val }, 300)
})

/** lifecycle */
onMounted(() => {
  fetchUsers()
  fetchRoles()
  fetchDepartments()
  fetchDesignations()
  onScroll()
})
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.75rem; /* smoother */
}
</style>
