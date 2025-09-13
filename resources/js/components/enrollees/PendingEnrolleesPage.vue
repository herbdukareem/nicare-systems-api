<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Pending Enrollees</h1>
          <p class="tw-text-gray-600 tw-mt-1">Review and approve pending enrollee applications</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-chip
            color="orange"
            variant="flat"
            size="large"
          >
            {{ totalItems }} pending
          </v-chip>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-4 tw-animate-slide-up tw-animate-stagger-1">
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-3 tw-lg:grid-cols-4 tw-gap-4">
          <v-text-field
            v-model="searchQuery"
            label="Search enrollees..."
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-magnify"
            clearable
            @input="debounceSearch"
          />
          
          <v-select
            v-model="filters.lga"
            :items="lgaOptions"
            item-title="name"
            item-value="id"
            label="LGA"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />
          
          <v-select
            v-model="filters.facility"
            :items="facilityOptions"
            item-title="name"
            item-value="id"
            label="Facility"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="applyFilters"
          />
          
          <v-btn
            variant="outlined"
            color="primary"
            @click="clearAllFilters"
          >
            Clear Filters
          </v-btn>
        </div>
      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-animate-slide-up tw-animate-stagger-2">
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
        >
          <!-- Top toolbar -->
          <template #top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <div class="tw-flex tw-items-center tw-gap-4">
                  <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
                    Pending Enrollees
                  </h3>
                  <v-chip size="small" color="orange" variant="flat">
                    {{ totalItems }} total
                  </v-chip>
                </div>
              </div>
            </div>
          </template>

          <!-- Status column -->
          <template #item.status="{ item }">
            <v-chip
              color="orange"
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
                color="success"
                @click="approveEnrollee(item)"
              >
                <v-icon size="16">mdi-check</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="rejectEnrollee(item)"
              >
                <v-icon size="16">mdi-close</v-icon>
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
              <v-icon size="48" color="grey">mdi-account-clock</v-icon>
              <p class="tw-text-gray-500 tw-mt-2">No pending enrollees found</p>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- View Enrollee Dialog -->
    <v-dialog v-model="showViewDialog" max-width="800px">
      <v-card v-if="viewingEnrollee">
        <v-card-title class="tw-flex tw-items-center tw-justify-between">
          <span class="tw-text-xl tw-font-semibold">Enrollee Details</span>
          <v-btn icon variant="text" @click="showViewDialog = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-6">
            <!-- Personal Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Personal Information</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Full Name</label>
                  <p class="tw-text-gray-900">{{ viewingEnrollee.name }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Phone</label>
                  <p class="tw-text-gray-900">{{ viewingEnrollee.phone }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Email</label>
                  <p class="tw-text-gray-900">{{ viewingEnrollee.email || 'N/A' }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Date of Birth</label>
                  <p class="tw-text-gray-900">{{ formatDate(viewingEnrollee.date_of_birth) }}</p>
                </div>
              </div>
            </div>

            <!-- Location Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Location Information</h4>
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">LGA</label>
                  <p class="tw-text-gray-900">{{ viewingEnrollee.lga_name }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Facility</label>
                  <p class="tw-text-gray-900">{{ viewingEnrollee.facility_name }}</p>
                </div>
              </div>
            </div>

            <!-- Status Change -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Change Status</h4>
              <v-select
                v-model="selectedStatus"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Select New Status"
                variant="outlined"
                density="compact"
              />
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showViewDialog = false">Cancel</v-btn>
          <v-btn 
            color="primary" 
            @click="updateEnrolleeStatus" 
            :loading="updating"
            :disabled="!selectedStatus"
          >
            Update Status
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { enrolleeAPI } from '../../utils/api';
import axios from 'axios';

// Toasts
const { success, error } = useToast();

// Reactive state
const loading = ref(false);
const updating = ref(false);
const searchQuery = ref('');
const showViewDialog = ref(false);
const viewingEnrollee = ref(null);
const selectedStatus = ref(null);

const currentPage = ref(1);
const itemsPerPage = ref(50);
const totalItems = ref(0);

// Filters
const filters = ref({
  lga: null,
  facility: null,
});

// Options
const statusOptions = ref([]);
const lgaOptions = ref([]);
const facilityOptions = ref([]);

// Headers
const headers = [
  { title: 'ID', key: 'enrollee_id', sortable: true },
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Phone', key: 'phone', sortable: true },
  { title: 'LGA', key: 'lga_name', sortable: true },
  { title: 'Facility', key: 'facility_name', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Date Applied', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' },
];

// Data
const enrollees = ref([]);

// Methods
const debounceSearch = () => {
  // Implement debounced search
  loadEnrollees();
};

const applyFilters = () => {
  currentPage.value = 1;
  loadEnrollees();
};

const clearAllFilters = () => {
  filters.value = {
    lga: null,
    facility: null,
  };
  searchQuery.value = '';
  applyFilters();
};

const loadEnrollees = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      status: 0, // Only pending enrollees
      search: searchQuery.value,
      ...filters.value,
    };
    
    const response = await enrolleeAPI.getAll(params);
    enrollees.value = response.data.data;
    totalItems.value = response.data.total;
  } catch (err) {
    error('Failed to load pending enrollees');
  } finally {
    loading.value = false;
  }
};

const viewEnrollee = (enrollee) => {
  viewingEnrollee.value = enrollee;
  selectedStatus.value = null;
  showViewDialog.value = true;
};

const approveEnrollee = async (enrollee) => {
  if (confirm(`Are you sure you want to approve ${enrollee.name}?`)) {
    try {
      // TODO: Call approve API
      success('Enrollee approved successfully');
      loadEnrollees();
    } catch (err) {
      error('Failed to approve enrollee');
    }
  }
};

const rejectEnrollee = async (enrollee) => {
  if (confirm(`Are you sure you want to reject ${enrollee.name}?`)) {
    try {
      // TODO: Call reject API
      success('Enrollee rejected successfully');
      loadEnrollees();
    } catch (err) {
      error('Failed to reject enrollee');
    }
  }
};

const updateEnrolleeStatus = async () => {
  if (!selectedStatus.value || !viewingEnrollee.value) return;
  
  updating.value = true;
  try {
    // TODO: Call update status API
    success('Enrollee status updated successfully');
    showViewDialog.value = false;
    loadEnrollees();
  } catch (err) {
    error('Failed to update enrollee status');
  } finally {
    updating.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

// Load options
const loadStatusOptions = async () => {
  try {
    const response = await axios.get('/api/dashboard/status-options');
    if (response.data.success) {
      statusOptions.value = Object.entries(response.data.data).map(([value, label]) => ({
        value: parseInt(value),
        label: label.charAt(0).toUpperCase() + label.slice(1)
      }));
    }
  } catch (err) {
    console.error('Failed to load status options:', err);
  }
};

const loadLgaOptions = async () => {
  try {
    const response = await axios.get('/api/v1/lgas');
    if (response.data.success) {
      lgaOptions.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load LGA options:', err);
  }
};

const loadFacilityOptions = async () => {
  try {
    const response = await axios.get('/api/v1/facilities');
    if (response.data.success) {
      facilityOptions.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load facility options:', err);
  }
};

// Lifecycle
onMounted(() => {
  loadStatusOptions();
  loadLgaOptions();
  loadFacilityOptions();
  loadEnrollees();
});
</script>
