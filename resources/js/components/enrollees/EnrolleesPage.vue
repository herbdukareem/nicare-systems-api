<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Enrollees</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage and view all enrollee information</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-download"
            @click="exportData"
            :loading="exporting"
          >
            Export
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="showAddDialog = true"
          >
            Add Enrollee
          </v-btn>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4 tw-mb-4">
          <!-- Search -->
          <div class="lg:tw-col-span-2">
            <v-text-field
              v-model="searchQuery"
              label="Search enrollees..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              @input="onSearchInput"
            />
          </div>

          <!-- Status Filter -->
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />

          <!-- Type Filter -->
          <v-select
            v-model="filters.type"
            :items="typeOptions"
            label="Type"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />
        </div>

        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
          <!-- Gender Filter -->
          <v-select
            v-model="filters.gender"
            :items="genderOptions"
            label="Gender"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />

          <!-- LGA Filter -->
          <v-select
            v-model="filters.lga"
            :items="lgaOptions"
            label="LGA"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />

          <!-- Date Range (placeholder) -->
          <v-text-field
            v-model="filters.dateRange"
            label="Date Range"
            prepend-inner-icon="mdi-calendar"
            variant="outlined"
            density="compact"
            readonly
            @click="showDatePicker = true"
          />
        </div>

        <!-- Active Filters -->
        <div v-if="activeFiltersCount > 0" class="tw-mt-4 tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
          <span class="tw-text-sm tw-text-gray-600">Active filters:</span>
          <v-chip
            v-for="filter in activeFilters"
            :key="filter.key"
            size="small"
            closable
            @click:close="removeFilter(filter.key)"
          >
            {{ filter.label }}: {{ filter.value }}
          </v-chip>
          <v-btn
            variant="text"
            size="small"
            color="error"
            @click="clearAllFilters"
          >
            Clear All
          </v-btn>
        </div>
      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          v-model:items-per-page="itemsPerPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="enrollees"
          :loading="loading"
          :items-length="totalItems"
          :search="searchQuery"
          class="tw-elevation-0"
          item-value="id"
          show-select
          v-model="selectedItems"
        >
          <!-- Top toolbar -->
          <template #top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <div class="tw-flex tw-items-center tw-gap-4">
                  <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
                    Enrollees List
                  </h3>
                  <v-chip size="small" color="primary" variant="flat">
                    {{ totalItems }} total
                  </v-chip>
                </div>
                <div v-if="selectedItems.length > 0" class="tw-flex tw-items-center tw-gap-2">
                  <span class="tw-text-sm tw-text-gray-600">
                    {{ selectedItems.length }} selected
                  </span>
                  <v-btn
                    size="small"
                    color="error"
                    variant="outlined"
                    @click="bulkDelete"
                  >
                    Delete Selected
                  </v-btn>
                </div>
              </div>
            </div>
          </template>

          <!-- Status column -->
          <template #item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Actions column -->
          <template #item.actions="{ item }">
            <div class="tw-flex tw-gap-1">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewEnrollee(item)"
              >
                <v-icon size="16">mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editEnrollee(item)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteEnrollee(item)"
              >
                <v-icon size="16">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>

          <!-- Loading -->
          <template #loading>
            <v-skeleton-loader type="table-row@10" />
          </template>

          <!-- No data -->
          <template #no-data>
            <div class="tw-text-center tw-py-8">
              <v-icon size="48" color="grey">mdi-account-group</v-icon>
              <p class="tw-text-gray-500 tw-mt-2">No enrollees found</p>
              <v-btn
                color="primary"
                variant="outlined"
                class="tw-mt-4"
                @click="clearAllFilters"
              >
                Clear Filters
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="showAddDialog" max-width="900px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingEnrollee ? 'Edit Enrollee' : 'Add New Enrollee' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-6">
            <!-- Personal Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Personal Information</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-text-field v-model="enrolleeForm.first_name" label="First Name" variant="outlined" required />
                <v-text-field v-model="enrolleeForm.last_name"  label="Last Name"  variant="outlined" required />
                <v-text-field v-model="enrolleeForm.email"      label="Email" type="email" variant="outlined" />
                <v-text-field v-model="enrolleeForm.phone"      label="Phone Number" variant="outlined" />
                <v-select     v-model="enrolleeForm.gender"     :items="genderOptions" label="Gender" variant="outlined" />
                <v-text-field v-model="enrolleeForm.date_of_birth" label="Date of Birth" type="date" variant="outlined" />
              </div>
            </div>

            <!-- Address Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Address Information</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-textarea
                  v-model="enrolleeForm.address"
                  label="Address"
                  variant="outlined"
                  rows="2"
                  class="md:tw-col-span-2"
                />
                <v-select v-model="enrolleeForm.lga"   :items="lgaOptions" label="LGA"   variant="outlined" />
                <v-select v-model="enrolleeForm.state" :items="['FCT']"     label="State" variant="outlined" />
              </div>
            </div>

            <!-- Enrollment Details -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Enrollment Details</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-select v-model="enrolleeForm.type"   :items="typeOptions"   label="Enrollment Type" variant="outlined" />
                <v-select v-model="enrolleeForm.status" :items="statusOptions" label="Status"          variant="outlined" />
                <v-text-field v-model="enrolleeForm.enrollee_id" label="Enrollee ID" variant="outlined" :readonly="!!editingEnrollee" />
                <v-text-field v-model="enrolleeForm.emergency_contact" label="Emergency Contact" variant="outlined" />
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeAddDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveEnrollee" :loading="saving">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Enrollee Dialog -->
    <v-dialog v-model="showViewDialog" max-width="800px">
      <v-card v-if="viewingEnrollee">
        <v-card-title>
          <div class="tw-flex tw-items-center tw-justify-between tw-w-full">
            <span class="tw-text-xl tw-font-semibold">{{ viewingEnrollee.name }}</span>
            <div class="tw-flex tw-gap-2">
              <v-btn
                size="small"
                color="primary"
                variant="outlined"
                prepend-icon="mdi-download"
                @click="downloadProfile(viewingEnrollee)"
              >
                Download Profile
              </v-btn>
              <v-btn
                size="small"
                color="primary"
                prepend-icon="mdi-pencil"
                @click="editFromView(viewingEnrollee)"
              >
                Edit
              </v-btn>
            </div>
          </div>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-6">
            <!-- Personal Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Personal Information</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Full Name</p>
                  <p class="tw-font-medium">{{ viewingEnrollee.name }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Enrollee ID</p>
                  <p class="tw-font-medium">{{ viewingEnrollee.enrollee_id }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Email</p>
                  <p class="tw-font-medium">{{ viewingEnrollee.email }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Phone</p>
                  <p class="tw-font-medium">{{ viewingEnrollee.phone }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Type</p>
                  <v-chip size="small" color="primary" variant="outlined">
                    {{ viewingEnrollee.type }}
                  </v-chip>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Status</p>
                  <v-chip
                    size="small"
                    :color="getStatusColor(viewingEnrollee.status)"
                    variant="flat"
                  >
                    {{ viewingEnrollee.status }}
                  </v-chip>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">LGA</p>
                  <p class="tw-font-medium">{{ viewingEnrollee.lga }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Date Enrolled</p>
                  <p class="tw-font-medium">{{ formatDate(viewingEnrollee.created_at) }}</p>
                </div>
              </div>
            </div>

            <!-- Enrollment Statistics -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Enrollment Statistics</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
                <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-blue-100">
                  <p class="tw-text-2xl tw-font-bold tw-text-blue-700">12</p>
                  <p class="tw-text-sm tw-text-gray-600">Claims Made</p>
                </div>
                <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-green-100">
                  <p class="tw-text-2xl tw-font-bold tw-text-green-700">₦45,000</p>
                  <p class="tw-text-sm tw-text-gray-600">Total Benefits</p>
                </div>
                <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-purple-100">
                  <p class="tw-text-2xl tw-font-bold tw-text-purple-700">3</p>
                  <p class="tw-text-sm tw-text-gray-600">Facilities Visited</p>
                </div>
              </div>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showViewDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Date Picker Dialog (placeholder) -->
    <v-dialog v-model="showDatePicker" max-width="400px">
      <v-card>
        <v-card-title>Select Date Range</v-card-title>
        <v-card-text>
          <p class="tw-text-gray-600">Date range picker will be implemented here...</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDatePicker = false">Cancel</v-btn>
          <v-btn color="primary" @click="applyDateFilter">Apply</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { enrolleeAPI } from '../../utils/api';

// Toasts
const { success, error } = useToast();

// Reactive state
const loading = ref(false);
const exporting = ref(false);
const searchQuery = ref('');
const selectedItems = ref([]);
const showAddDialog = ref(false);
const showDatePicker = ref(false);
const showViewDialog = ref(false);
const editingEnrollee = ref(null);
const viewingEnrollee = ref(null);
const saving = ref(false);

const currentPage = ref(1);
const itemsPerPage = ref(200);
const totalItems = ref(0);

// Filters
const filters = ref({
  status: null,
  type: null,
  gender: null,
  lga: null,
  dateRange: null,
});

// Options
const statusOptions = ['Active', 'Inactive', 'Pending', 'Suspended'];
const typeOptions = ['Principal', 'Spouse', 'Child', 'Dependent'];
const genderOptions = ['Male', 'Female'];
const lgaOptions = ['Abuja Municipal', 'Gwagwalada', 'Kuje', 'Bwari', 'Kwali', 'Abaji'];

// Form
const enrolleeForm = ref({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  date_of_birth: '',
  address: '',
  lga: '',
  state: 'FCT',
  type: '',
  status: 'Active',
  enrollee_id: '',
  emergency_contact: '',
});

// Headers
const headers = [
  { title: 'ID',            key: 'enrollee_id', sortable: true },
  { title: 'Name',          key: 'name',        sortable: true },
  { title: 'Email',         key: 'email',       sortable: true },
  { title: 'Phone',         key: 'phone',       sortable: true },
  { title: 'Type',          key: 'type',        sortable: true },
  { title: 'Status',        key: 'status',      sortable: true },
  { title: 'LGA',           key: 'lga',         sortable: true },
  { title: 'Date Enrolled', key: 'created_at',  sortable: true },
  { title: 'Actions',       key: 'actions',     sortable: false, width: '120px' },
];

// Data
const enrollees = ref([]);

// Active filters (chips)
const activeFilters = computed(() => {
  const active = [];
  Object.entries(filters.value).forEach(([key, value]) => {
    if (value) {
      active.push({
        key,
        label: key.charAt(0).toUpperCase() + key.slice(1),
        value,
      });
    }
  });
  return active;
});
const activeFiltersCount = computed(() => activeFilters.value.length);

// Debounced search
let searchTimer = null;
const onSearchInput = () => {
  if (searchTimer) clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentPage.value = 1;
    loadEnrollees();
  }, 300);
};

// Helpers
const applyFilters = () => {
  currentPage.value = 1;
  loadEnrollees();
};

const removeFilter = (key) => {
  filters.value[key] = null;
  applyFilters();
};

const clearAllFilters = () => {
  Object.keys(filters.value).forEach((k) => (filters.value[k] = null));
  searchQuery.value = '';
  applyFilters();
};

const getStatusColor = (status) => {
  if (!status) return 'grey';
  switch (String(status).toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'inactive': return 'error';
    case 'suspended': return 'error';
    default: return 'grey';
  }
};

// Row actions
const editEnrollee = (enrollee) => {
  editingEnrollee.value = enrollee;
  enrolleeForm.value = {
    first_name: enrollee.first_name || '',
    last_name: enrollee.last_name || '',
    email: enrollee.email || '',
    phone: enrollee.phone || '',
    gender: enrollee.gender || '',
    date_of_birth: enrollee.date_of_birth || '',
    address: enrollee.address || '',
    lga: enrollee.lga || '',
    state: enrollee.state || 'FCT',
    type: enrollee.type || '',
    status: enrollee.status || 'Active',
    enrollee_id: enrollee.enrollee_id || '',
    emergency_contact: enrollee.emergency_contact || '',
  };
  showAddDialog.value = true;
};

const deleteEnrollee = (enrollee) => {
  if (confirm(`Are you sure you want to delete ${enrollee.name}?`)) {
    // TODO: call delete API
    success('Enrollee deleted successfully');
    loadEnrollees();
  }
};

const bulkDelete = () => {
  if (selectedItems.value.length === 0) return;
  if (confirm(`Are you sure you want to delete ${selectedItems.value.length} enrollees?`)) {
    // TODO: call bulk delete API
    selectedItems.value = [];
    success('Enrollees deleted successfully');
    loadEnrollees();
  }
};

const saveEnrollee = async () => {
  saving.value = true;
  try {
    // TODO: replace with create/update API
    await new Promise((r) => setTimeout(r, 700));
    closeAddDialog();
    success(editingEnrollee.value ? 'Enrollee updated successfully' : 'Enrollee created successfully');
    await loadEnrollees();
  } catch (e) {
    error('Failed to save enrollee');
  } finally {
    saving.value = false;
  }
};

const viewEnrollee = (enrollee) => {
  viewingEnrollee.value = enrollee;
  showViewDialog.value = true;
};

const editFromView = (enrollee) => {
  showViewDialog.value = false;
  editEnrollee(enrollee);
};

const closeAddDialog = () => {
  showAddDialog.value = false;
  editingEnrollee.value = null;
  enrolleeForm.value = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    gender: '',
    date_of_birth: '',
    address: '',
    lga: '',
    state: 'FCT',
    type: '',
    status: 'Active',
    enrollee_id: '',
    emergency_contact: '',
  };
};

const downloadProfile = async (enrollee) => {
  try {
    const profileData = {
      name: enrollee.name,
      enrollee_id: enrollee.enrollee_id,
      email: enrollee.email,
      phone: enrollee.phone,
      type: enrollee.type,
      status: enrollee.status,
      lga: enrollee.lga,
      date_enrolled: enrollee.created_at,
    };
    const dataStr = JSON.stringify(profileData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${(enrollee.name || 'enrollee').replace(/\s+/g, '_')}_profile.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    success('Profile downloaded successfully');
  } catch {
    error('Failed to download profile');
  }
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

const exportData = () => {
  exporting.value = true;
  // TODO: hook to export endpoint
  setTimeout(() => {
    exporting.value = false;
    success('Data exported successfully');
  }, 800);
};

const applyDateFilter = () => {
  showDatePicker.value = false;
  applyFilters();
};

// API
const loadEnrollees = async () => {
  loading.value = true;
  try {
    // Clean up null/empty values for backend
    const params = {};

    // Always include pagination
    params.page = currentPage.value;
    params.per_page = itemsPerPage.value;
    params.sort_by = 'created_at';
    params.sort_direction = 'desc';

    // Only include filters that have values
    if (searchQuery.value && searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (filters.value.type) {
      params.type = filters.value.type;
    }
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    if (filters.value.lga) {
      params.lga = filters.value.lga;
    }
    if (filters.value.gender) {
      params.gender = filters.value.gender;
    }
    if (filters.value.dateRange) {
      params.date_range = filters.value.dateRange;
    }
    const response = await enrolleeAPI.getAll(params);

    // Handle different response structures
    if (response?.data?.success) {
      const responseData = response.data.data;

      // Check if it's paginated data
      if (responseData && typeof responseData === 'object' && responseData.data) {
        enrollees.value = responseData.data;
        totalItems.value = responseData.meta?.total || responseData.total || 0;
      } else if (Array.isArray(responseData)) {
        // Direct array response
        enrollees.value = responseData;
        totalItems.value = responseData.length;
      } else {
        enrollees.value = [];
        totalItems.value = 0;
      }
    } else if (response?.data?.data) {
      // fallback: non-wrapped data
      const data = response.data.data;
      enrollees.value = Array.isArray(data) ? data : [];
      totalItems.value = response.data.total || enrollees.value.length;
    } else {
      enrollees.value = [];
      totalItems.value = 0;
    }
  } catch (e) {
    console.error('Failed to load enrollees:', e);
    error('Failed to load enrollees');
  } finally {
    loading.value = false;
  }
};

// Lifecycle
onMounted(() => {
  loadEnrollees();
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
:deep(.v-data-table__wrapper) {
  border-radius: 0.5rem;
}
</style>
