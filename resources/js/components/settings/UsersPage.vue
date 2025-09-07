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
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-download"
            @click="exportUsers"
          >
            Export
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showCreateUserDialog = true"
          >
            Add User
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Users</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ users.length }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Users</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ activeUsersCount }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-clock-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Pending</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ pendingUsersCount }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-shield-account</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Admins</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ adminUsersCount }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-4 tw-gap-4">
          <!-- Search -->
          <div class="tw-lg:tw-col-span-2">
            <v-text-field
              v-model="searchQuery"
              label="Search users..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>
          
          <!-- Role Filter -->
          <v-select
            v-model="filters.role"
            :items="roleOptions"
            label="Role"
            variant="outlined"
            density="compact"
            clearable
          />
          
          <!-- Status Filter -->
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
          />
        </div>
      </div>

      <!-- Users Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="filteredUsers"
          :loading="loading"
          :search="searchQuery"
          class="tw-elevation-0"
          item-value="id"
        >
          <!-- Custom header -->
          <template v-slot:top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
                  Users List
                </h3>
                <v-chip size="small" color="primary">
                  {{ filteredUsers.length }} users
                </v-chip>
              </div>
            </div>
          </template>

          <!-- Avatar column -->
          <template v-slot:item.avatar="{ item }">
            <v-avatar size="32" color="primary">
              <span class="tw-text-white tw-text-sm">{{ getInitials(item.name) }}</span>
            </v-avatar>
          </template>

          <!-- Status column -->
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Roles column -->
          <template v-slot:item.roles="{ item }">
            <div class="tw-flex tw-flex-wrap tw-gap-1">
              <v-chip
                v-for="role in item.roles"
                :key="role"
                size="small"
                color="primary"
                variant="outlined"
              >
                {{ role }}
              </v-chip>
            </div>
          </template>

          <!-- Last Login column -->
          <template v-slot:item.last_login="{ item }">
            <span class="tw-text-gray-600">
              {{ item.last_login ? formatDate(item.last_login) : 'Never' }}
            </span>
          </template>

          <!-- Actions column -->
          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewUser(item)"
              >
                <v-icon size="16">mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editUser(item)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="warning"
                @click="manageRoles(item)"
              >
                <v-icon size="16">mdi-shield-account</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteUser(item)"
              >
                <v-icon size="16">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit User Dialog -->
    <v-dialog v-model="showCreateUserDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingUser ? 'Edit User' : 'Create New User' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="userForm.name"
                label="Full Name"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="userForm.email"
                label="Email"
                type="email"
                variant="outlined"
                required
              />
            </div>
            
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="userForm.phone"
                label="Phone Number"
                variant="outlined"
              />
              <v-select
                v-model="userForm.status"
                :items="statusOptions"
                label="Status"
                variant="outlined"
                required
              />
            </div>

            <v-select
              v-model="userForm.roles"
              :items="roleOptions"
              label="Roles"
              variant="outlined"
              multiple
              chips
              required
            />

            <v-text-field
              v-if="!editingUser"
              v-model="userForm.password"
              label="Password"
              type="password"
              variant="outlined"
              required
            />
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
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            Manage Roles - {{ managingUser.name }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700 tw-mb-2">Current Roles</h4>
              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <v-chip
                  v-for="role in managingUser.roles"
                  :key="role"
                  size="small"
                  color="primary"
                  closable
                  @click:close="removeRole(role)"
                >
                  {{ role }}
                </v-chip>
              </div>
            </div>
            
            <v-select
              v-model="selectedRoles"
              :items="availableRoles"
              label="Add Roles"
              variant="outlined"
              multiple
              chips
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showRolesDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="updateUserRoles">Update Roles</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const searchQuery = ref('');
const showCreateUserDialog = ref(false);
const showRolesDialog = ref(false);
const editingUser = ref(null);
const managingUser = ref(null);
const selectedRoles = ref([]);
const itemsPerPage = ref(10);

// Filters
const filters = ref({
  role: null,
  status: null
});

// Form data
const userForm = ref({
  name: '',
  email: '',
  phone: '',
  status: 'active',
  roles: [],
  password: ''
});

// Options
const roleOptions = ['Super Admin', 'Admin', 'Manager', 'Staff'];
const statusOptions = ['active', 'inactive', 'pending'];

// Table headers
const headers = [
  { title: '', key: 'avatar', sortable: false, width: '60px' },
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Email', key: 'email', sortable: true },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Roles', key: 'roles', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Last Login', key: 'last_login', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '160px' }
];

// Mock data
const users = ref([
  {
    id: 1,
    name: 'John Doe',
    email: 'john@ngscha.gov.ng',
    phone: '+234 801 234 5678',
    roles: ['Super Admin'],
    status: 'active',
    last_login: '2024-01-15T10:30:00Z',
    created_at: '2024-01-01'
  },
  {
    id: 2,
    name: 'Jane Smith',
    email: 'jane@ngscha.gov.ng',
    phone: '+234 802 345 6789',
    roles: ['Admin'],
    status: 'active',
    last_login: '2024-01-14T15:45:00Z',
    created_at: '2024-01-02'
  },
  {
    id: 3,
    name: 'Mike Johnson',
    email: 'mike@ngscha.gov.ng',
    phone: '+234 803 456 7890',
    roles: ['Manager'],
    status: 'active',
    last_login: '2024-01-13T09:15:00Z',
    created_at: '2024-01-03'
  },
  {
    id: 4,
    name: 'Sarah Wilson',
    email: 'sarah@ngscha.gov.ng',
    phone: '+234 804 567 8901',
    roles: ['Staff'],
    status: 'pending',
    last_login: null,
    created_at: '2024-01-15'
  }
]);

// Computed properties
const filteredUsers = computed(() => {
  let filtered = users.value;
  
  if (filters.value.role) {
    filtered = filtered.filter(user => user.roles.includes(filters.value.role));
  }
  
  if (filters.value.status) {
    filtered = filtered.filter(user => user.status === filters.value.status);
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(user =>
      user.name.toLowerCase().includes(query) ||
      user.email.toLowerCase().includes(query) ||
      user.phone.includes(query)
    );
  }
  
  return filtered;
});

const activeUsersCount = computed(() => {
  return users.value.filter(user => user.status === 'active').length;
});

const pendingUsersCount = computed(() => {
  return users.value.filter(user => user.status === 'pending').length;
});

const adminUsersCount = computed(() => {
  return users.value.filter(user => 
    user.roles.includes('Super Admin') || user.roles.includes('Admin')
  ).length;
});

const availableRoles = computed(() => {
  if (!managingUser.value) return roleOptions;
  return roleOptions.filter(role => !managingUser.value.roles.includes(role));
});

// Methods
const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase();
};

const getStatusColor = (status) => {
  switch (status.toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'inactive': return 'error';
    default: return 'grey';
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString();
};

const viewUser = (user) => {
  // Navigate to user detail page or show detail dialog
  console.log('View user:', user);
};

const editUser = (user) => {
  editingUser.value = user;
  Object.assign(userForm.value, {
    name: user.name,
    email: user.email,
    phone: user.phone,
    status: user.status,
    roles: [...user.roles],
    password: ''
  });
  showCreateUserDialog.value = true;
};

const deleteUser = (user) => {
  if (confirm(`Are you sure you want to delete ${user.name}?`)) {
    const index = users.value.findIndex(u => u.id === user.id);
    if (index > -1) {
      users.value.splice(index, 1);
      success('User deleted successfully');
    }
  }
};

const manageRoles = (user) => {
  managingUser.value = user;
  selectedRoles.value = [];
  showRolesDialog.value = true;
};

const removeRole = (role) => {
  const index = managingUser.value.roles.indexOf(role);
  if (index > -1) {
    managingUser.value.roles.splice(index, 1);
  }
};

const updateUserRoles = () => {
  if (selectedRoles.value.length > 0) {
    managingUser.value.roles.push(...selectedRoles.value);
  }
  showRolesDialog.value = false;
  success('User roles updated successfully');
};

const saveUser = () => {
  if (editingUser.value) {
    // Update existing user
    Object.assign(editingUser.value, userForm.value);
    success('User updated successfully');
  } else {
    // Create new user
    const newUser = {
      id: Date.now(),
      ...userForm.value,
      last_login: null,
      created_at: new Date().toISOString().split('T')[0]
    };
    users.value.push(newUser);
    success('User created successfully');
  }
  closeUserDialog();
};

const closeUserDialog = () => {
  showCreateUserDialog.value = false;
  editingUser.value = null;
  Object.keys(userForm.value).forEach(key => {
    if (key === 'roles') {
      userForm.value[key] = [];
    } else if (key === 'status') {
      userForm.value[key] = 'active';
    } else {
      userForm.value[key] = '';
    }
  });
};

const exportUsers = () => {
  success('Users exported successfully');
};
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
