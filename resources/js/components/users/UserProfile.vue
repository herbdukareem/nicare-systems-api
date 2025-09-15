<template>
  <AdminLayout>
    <div class="tw-space-y-6" v-if="user">
      <!-- Profile Header -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <div class="tw-relative tw-h-32 tw-bg-gradient-to-r tw-from-blue-500 tw-to-purple-600 tw-rounded-t-lg">
          <div class="tw-absolute tw-bottom-0 tw-left-6 tw-transform tw-translate-y-1/2">
            <div class="tw-relative">
              <v-avatar size="120" class="tw-border-4 tw-border-white">
                <v-img 
                  v-if="user.avatar" 
                  :src="user.avatar" 
                  :alt="user.name"
                />
                <v-icon v-else size="60" color="grey">mdi-account</v-icon>
              </v-avatar>
              <v-btn
                icon
                size="small"
                color="primary"
                class="tw-absolute tw-bottom-2 tw-right-2"
                @click="showAvatarDialog = true"
              >
                <v-icon size="16">mdi-camera</v-icon>
              </v-btn>
            </div>
          </div>
        </div>
        
        <div class="tw-pt-16 tw-pb-6 tw-px-6">
          <div class="tw-flex tw-flex-col tw-lg:tw-flex-row tw-lg:tw-items-center tw-lg:tw-justify-between">
            <div class="tw-flex-1">
              <div class="tw-flex tw-items-center tw-gap-3 tw-mb-2">
                <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ user.name }}</h1>
                <v-chip
                  :color="getStatusColor(user.status)"
                  size="small"
                  variant="flat"
                >
                  {{ getStatusLabel(user.status) }}
                </v-chip>
              </div>
              <p class="tw-text-gray-600 tw-mb-1">{{ user.email }}</p>
              <p class="tw-text-gray-500 tw-text-sm">{{ user.username }}</p>
              
              <!-- Roles -->
              <div class="tw-flex tw-flex-wrap tw-gap-2 tw-mt-3">
                <v-chip
                  v-for="role in user.roles"
                  :key="role.id"
                  size="small"
                  color="primary"
                  variant="outlined"
                >
                  {{ role.label || role.name }}
                </v-chip>
              </div>
            </div>
            
            <div class="tw-flex tw-gap-3 tw-mt-4 tw-lg:tw-mt-0">
              <v-btn
                color="primary"
                variant="outlined"
                prepend-icon="mdi-pencil"
                @click="showEditDialog = true"
              >
                Edit Profile
              </v-btn>
              <v-btn
                color="warning"
                variant="outlined"
                prepend-icon="mdi-key"
                @click="showPasswordDialog = true"
              >
                Change Password
              </v-btn>
              <v-menu>
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    variant="text"
                  >
                    <v-icon>mdi-dots-vertical</v-icon>
                  </v-btn>
                </template>
                <v-list>
                  <v-list-item @click="toggleUserStatus">
                    <v-list-item-title>
                      {{ user.status === 1 ? 'Deactivate' : 'Activate' }} User
                    </v-list-item-title>
                  </v-list-item>
                  <v-list-item @click="showRolesDialog = true">
                    <v-list-item-title>Manage Roles</v-list-item-title>
                  </v-list-item>
                  <v-list-item @click="showActivityDialog = true">
                    <v-list-item-title>View Activity Log</v-list-item-title>
                  </v-list-item>
                </v-list>
              </v-menu>
            </div>
          </div>
        </div>
      </div>

      <!-- Profile Stats -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-calendar</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Member Since</p>
              <p class="tw-text-lg tw-font-bold tw-text-gray-900">{{ formatDate(user.created_at) }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-login</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Last Login</p>
              <p class="tw-text-lg tw-font-bold tw-text-gray-900">{{ formatDate(user.last_login_at) }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-shield-account</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Roles</p>
              <p class="tw-text-lg tw-font-bold tw-text-gray-900">{{ user.roles?.length || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-history</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Activities</p>
              <p class="tw-text-lg tw-font-bold tw-text-gray-900">{{ user.activities_count || 0 }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Profile Details -->
      <div class="tw-grid tw-grid-cols-1 tw-lg:tw-grid-cols-3 tw-gap-6">
        <!-- Personal Information -->
        <div class="tw-lg:tw-col-span-2">
          <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
            <div class="tw-p-6 tw-border-b tw-border-gray-200">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Personal Information</h3>
            </div>
            <div class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-6">
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Full Name</label>
                  <p class="tw-text-gray-900">{{ user.name }}</p>
                </div>
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Username</label>
                  <p class="tw-text-gray-900 tw-font-mono">{{ user.username }}</p>
                </div>
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Email Address</label>
                  <p class="tw-text-gray-900">{{ user.email }}</p>
                </div>
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Phone Number</label>
                  <p class="tw-text-gray-900">{{ user.phone || 'Not provided' }}</p>
                </div>
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Account Status</label>
                  <v-chip
                    :color="getStatusColor(user.status)"
                    size="small"
                    variant="flat"
                  >
                    {{ getStatusLabel(user.status) }}
                  </v-chip>
                </div>
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Email Verified</label>
                  <v-chip
                    :color="user.email_verified_at ? 'success' : 'warning'"
                    size="small"
                    variant="flat"
                  >
                    {{ user.email_verified_at ? 'Verified' : 'Unverified' }}
                  </v-chip>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Account Security -->
        <div>
          <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
            <div class="tw-p-6 tw-border-b tw-border-gray-200">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Account Security</h3>
            </div>
            <div class="tw-p-6 tw-space-y-4">
              <div>
                <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Password</label>
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-gray-500">••••••••</span>
                  <v-btn
                    size="small"
                    variant="outlined"
                    @click="showPasswordDialog = true"
                  >
                    Change
                  </v-btn>
                </div>
              </div>
              
              <div>
                <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Two-Factor Auth</label>
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-gray-500">{{ user.two_factor_enabled ? 'Enabled' : 'Disabled' }}</span>
                  <v-btn
                    size="small"
                    variant="outlined"
                    @click="toggle2FA"
                  >
                    {{ user.two_factor_enabled ? 'Disable' : 'Enable' }}
                  </v-btn>
                </div>
              </div>
              
              <div>
                <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">Login Sessions</label>
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-gray-500">{{ user.active_sessions || 1 }} active</span>
                  <v-btn
                    size="small"
                    variant="outlined"
                    color="warning"
                    @click="revokeAllSessions"
                  >
                    Revoke All
                  </v-btn>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <div class="tw-p-6 tw-border-b tw-border-gray-200">
          <div class="tw-flex tw-items-center tw-justify-between">
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Recent Activity</h3>
            <v-btn
              variant="outlined"
              size="small"
              @click="showActivityDialog = true"
            >
              View All
            </v-btn>
          </div>
        </div>
        <div class="tw-p-6">
          <div v-if="recentActivities.length > 0" class="tw-space-y-4">
            <div 
              v-for="activity in recentActivities.slice(0, 5)" 
              :key="activity.id"
              class="tw-flex tw-items-start tw-space-x-3"
            >
              <div class="tw-p-2 tw-rounded-full tw-bg-gray-100">
                <v-icon size="16" color="grey">{{ getActivityIcon(activity.type) }}</v-icon>
              </div>
              <div class="tw-flex-1">
                <p class="tw-text-sm tw-text-gray-900">{{ activity.description }}</p>
                <p class="tw-text-xs tw-text-gray-500">{{ formatDate(activity.created_at) }}</p>
              </div>
            </div>
          </div>
          <div v-else class="tw-text-center tw-py-8">
            <v-icon size="48" color="grey">mdi-history</v-icon>
            <p class="tw-text-gray-500 tw-mt-2">No recent activity</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else-if="loading" class="tw-flex tw-items-center tw-justify-center tw-h-64">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>

    <!-- Error State -->
    <div v-else class="tw-text-center tw-py-16">
      <v-icon size="64" color="error">mdi-account-alert</v-icon>
      <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mt-4">User Not Found</h2>
      <p class="tw-text-gray-600 tw-mt-2">The user profile you're looking for doesn't exist.</p>
      <v-btn
        color="primary"
        class="tw-mt-4"
        @click="$router.push('/admin/users')"
      >
        Back to Users
      </v-btn>
    </div>

    <!-- Edit Profile Dialog -->
    <v-dialog v-model="showEditDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Edit Profile</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="editForm.name"
                label="Full Name"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="editForm.username"
                label="Username"
                variant="outlined"
                required
              />
            </div>

            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="editForm.email"
                label="Email Address"
                type="email"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="editForm.phone"
                label="Phone Number"
                variant="outlined"
              />
            </div>

            <v-select
              v-model="editForm.status"
              :items="statusOptions"
              label="Account Status"
              variant="outlined"
              required
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showEditDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="updateProfile">Save Changes</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Change Password Dialog -->
    <v-dialog v-model="showPasswordDialog" max-width="500px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Change Password</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-text-field
              v-model="passwordForm.current_password"
              label="Current Password"
              type="password"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="passwordForm.new_password"
              label="New Password"
              type="password"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="passwordForm.new_password_confirmation"
              label="Confirm New Password"
              type="password"
              variant="outlined"
              required
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showPasswordDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="changePassword">Change Password</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Manage Roles Dialog -->
    <v-dialog v-model="showRolesDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Manage User Roles</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div>
              <h4 class="tw-font-medium tw-text-gray-700 tw-mb-2">Current Roles</h4>
              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <v-chip
                  v-for="role in user.roles"
                  :key="role.id"
                  size="small"
                  color="primary"
                  closable
                  @click:close="removeRole(role.id)"
                >
                  {{ role.label || role.name }}
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

    <!-- Activity Log Dialog -->
    <v-dialog v-model="showActivityDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Activity Log</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-max-h-96 tw-overflow-y-auto">
            <div v-if="allActivities.length > 0" class="tw-space-y-3">
              <div
                v-for="activity in allActivities"
                :key="activity.id"
                class="tw-flex tw-items-start tw-space-x-3 tw-p-3 tw-border tw-border-gray-200 tw-rounded-lg"
              >
                <div class="tw-p-2 tw-rounded-full tw-bg-gray-100">
                  <v-icon size="16" color="grey">{{ getActivityIcon(activity.type) }}</v-icon>
                </div>
                <div class="tw-flex-1">
                  <p class="tw-text-sm tw-text-gray-900">{{ activity.description }}</p>
                  <p class="tw-text-xs tw-text-gray-500">{{ formatDate(activity.created_at) }}</p>
                  <div v-if="activity.properties" class="tw-mt-1">
                    <v-chip
                      v-for="(value, key) in activity.properties"
                      :key="key"
                      size="x-small"
                      variant="outlined"
                      class="tw-mr-1"
                    >
                      {{ key }}: {{ value }}
                    </v-chip>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="tw-text-center tw-py-8">
              <v-icon size="48" color="grey">mdi-history</v-icon>
              <p class="tw-text-gray-500 tw-mt-2">No activity found</p>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showActivityDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Avatar Upload Dialog -->
    <v-dialog v-model="showAvatarDialog" max-width="400px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Update Avatar</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-text-center tw-space-y-4">
            <v-avatar size="120">
              <v-img
                v-if="user.avatar"
                :src="user.avatar"
                :alt="user.name"
              />
              <v-icon v-else size="60" color="grey">mdi-account</v-icon>
            </v-avatar>

            <v-file-input
              v-model="avatarFile"
              label="Choose Avatar"
              accept="image/*"
              variant="outlined"
              prepend-icon="mdi-camera"
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAvatarDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="uploadAvatar">Upload</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { userAPI, roleAPI } from '../../utils/api';

const route = useRoute();
const router = useRouter();
const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const user = ref(null);
const recentActivities = ref([]);
const allActivities = ref([]);
const availableRoles = ref([]);

// Dialog states
const showEditDialog = ref(false);
const showPasswordDialog = ref(false);
const showRolesDialog = ref(false);
const showActivityDialog = ref(false);
const showAvatarDialog = ref(false);

// Form data
const editForm = ref({
  name: '',
  username: '',
  email: '',
  phone: '',
  status: 1
});

const passwordForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
});

const selectedRoles = ref([]);
const avatarFile = ref(null);

// Options
const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
  { title: 'Suspended', value: 2 }
];

// Computed properties
const userId = computed(() => route.params.id);

// Methods
const formatDate = (dateString) => {
  if (!dateString) return 'Never';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const getStatusColor = (status) => {
  switch (status) {
    case 1: return 'success';
    case 0: return 'warning';
    case 2: return 'error';
    default: return 'grey';
  }
};

const getStatusLabel = (status) => {
  switch (status) {
    case 1: return 'Active';
    case 0: return 'Inactive';
    case 2: return 'Suspended';
    default: return 'Unknown';
  }
};

const getActivityIcon = (type) => {
  switch (type) {
    case 'login': return 'mdi-login';
    case 'logout': return 'mdi-logout';
    case 'profile_update': return 'mdi-account-edit';
    case 'password_change': return 'mdi-key';
    case 'role_assigned': return 'mdi-shield-plus';
    case 'role_removed': return 'mdi-shield-minus';
    default: return 'mdi-information';
  }
};

const loadUser = async () => {
  loading.value = true;
  try {
    const response = await userAPI.getById(userId.value);
    if (response?.data?.success) {
      user.value = response.data.data;

      // Populate edit form
      Object.assign(editForm.value, {
        name: user.value.name,
        username: user.value.username,
        email: user.value.email,
        phone: user.value.phone || '',
        status: user.value.status
      });

      // Load recent activities
      loadRecentActivities();
    } else {
      error('Failed to load user profile');
    }
  } catch (err) {
    console.error('Failed to load user:', err);
    error('Failed to load user profile');
  } finally {
    loading.value = false;
  }
};

const loadRecentActivities = async () => {
  try {
    const response = await userAPI.getActivities(userId.value, { limit: 10 });
    if (response?.data?.success) {
      recentActivities.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load activities:', err);
  }
};

const loadAllActivities = async () => {
  try {
    const response = await userAPI.getActivities(userId.value);
    if (response?.data?.success) {
      allActivities.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load all activities:', err);
    error('Failed to load activity log');
  }
};

const loadAvailableRoles = async () => {
  try {
    const response = await roleAPI.getAll({ per_page: 1000 });
    if (response?.data?.success) {
      const roles = response.data.data?.data || response.data.data || [];
      availableRoles.value = roles.map(role => ({
        title: role.label || role.name,
        value: role.id
      }));
    }
  } catch (err) {
    console.error('Failed to load roles:', err);
  }
};

const updateProfile = async () => {
  try {
    const response = await userAPI.update(userId.value, editForm.value);
    if (response?.data?.success) {
      success('Profile updated successfully');
      showEditDialog.value = false;
      loadUser();
    } else {
      error('Failed to update profile');
    }
  } catch (err) {
    console.error('Failed to update profile:', err);
    error('Failed to update profile');
  }
};

const changePassword = async () => {
  if (passwordForm.value.new_password !== passwordForm.value.new_password_confirmation) {
    error('Password confirmation does not match');
    return;
  }

  try {
    const response = await userAPI.updatePassword(userId.value, passwordForm.value);
    if (response?.data?.success) {
      success('Password changed successfully');
      showPasswordDialog.value = false;
      passwordForm.value = {
        current_password: '',
        new_password: '',
        new_password_confirmation: ''
      };
    } else {
      error('Failed to change password');
    }
  } catch (err) {
    console.error('Failed to change password:', err);
    error('Failed to change password');
  }
};

const toggleUserStatus = async () => {
  const newStatus = user.value.status === 1 ? 0 : 1;
  const action = newStatus === 1 ? 'activate' : 'deactivate';

  if (!confirm(`Are you sure you want to ${action} this user?`)) return;

  try {
    const response = await userAPI.toggleStatus(userId.value);
    if (response?.data?.success) {
      success(`User ${action}d successfully`);
      loadUser();
    } else {
      error(`Failed to ${action} user`);
    }
  } catch (err) {
    console.error(`Failed to ${action} user:`, err);
    error(`Failed to ${action} user`);
  }
};

const removeRole = async (roleId) => {
  try {
    const currentRoleIds = user.value.roles.map(r => r.id).filter(id => id !== roleId);
    const response = await userAPI.updateRoles(userId.value, { role_ids: currentRoleIds });
    if (response?.data?.success) {
      success('Role removed successfully');
      loadUser();
    } else {
      error('Failed to remove role');
    }
  } catch (err) {
    console.error('Failed to remove role:', err);
    error('Failed to remove role');
  }
};

const updateUserRoles = async () => {
  try {
    const currentRoleIds = user.value.roles.map(r => r.id);
    const allRoleIds = [...new Set([...currentRoleIds, ...selectedRoles.value])];

    const response = await userAPI.updateRoles(userId.value, { role_ids: allRoleIds });
    if (response?.data?.success) {
      success('Roles updated successfully');
      showRolesDialog.value = false;
      selectedRoles.value = [];
      loadUser();
    } else {
      error('Failed to update roles');
    }
  } catch (err) {
    console.error('Failed to update roles:', err);
    error('Failed to update roles');
  }
};

const uploadAvatar = async () => {
  if (!avatarFile.value) {
    error('Please select an image file');
    return;
  }

  try {
    const formData = new FormData();
    formData.append('avatar', avatarFile.value[0]);

    const response = await userAPI.uploadAvatar(userId.value, formData);
    if (response?.data?.success) {
      success('Avatar updated successfully');
      showAvatarDialog.value = false;
      avatarFile.value = null;
      loadUser();
    } else {
      error('Failed to upload avatar');
    }
  } catch (err) {
    console.error('Failed to upload avatar:', err);
    error('Failed to upload avatar');
  }
};

const toggle2FA = async () => {
  try {
    const response = await userAPI.toggle2FA(userId.value);
    if (response?.data?.success) {
      const action = user.value.two_factor_enabled ? 'disabled' : 'enabled';
      success(`Two-factor authentication ${action}`);
      loadUser();
    } else {
      error('Failed to toggle two-factor authentication');
    }
  } catch (err) {
    console.error('Failed to toggle 2FA:', err);
    error('Failed to toggle two-factor authentication');
  }
};

const revokeAllSessions = async () => {
  if (!confirm('Are you sure you want to revoke all active sessions? This will log out the user from all devices.')) return;

  try {
    const response = await userAPI.revokeAllSessions(userId.value);
    if (response?.data?.success) {
      success('All sessions revoked successfully');
      loadUser();
    } else {
      error('Failed to revoke sessions');
    }
  } catch (err) {
    console.error('Failed to revoke sessions:', err);
    error('Failed to revoke sessions');
  }
};

// Lifecycle
onMounted(async () => {
  await Promise.all([
    loadUser(),
    loadAvailableRoles()
  ]);
});

// Watch for activity dialog opening
const handleActivityDialog = () => {
  if (showActivityDialog.value) {
    loadAllActivities();
  }
};

// Watch activity dialog
watch(showActivityDialog, handleActivityDialog);
</script>

<style scoped>
:deep(.v-avatar) {
  border: 4px solid white;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
</style>
