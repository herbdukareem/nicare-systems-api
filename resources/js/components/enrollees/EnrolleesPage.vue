<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
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
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Export
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="showAddDialog = true"
            class="tw-hover-lift tw-transition-all tw-duration-300 tw-shadow-lg"
          >
            Add Enrollee
          </v-btn>
        </div>
      </div>

      <!-- Enhanced Filters Section -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-animate-slide-up tw-animate-stagger-1">
        <!-- Filter Header -->
        <div class="tw-flex tw-items-center tw-justify-between tw-p-4 tw-border-b tw-border-gray-200">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
              <v-icon class="tw-mr-2" color="primary">mdi-filter</v-icon>
              FILTER
            </h3>
          </div>
          <div class="tw-flex tw-items-center tw-space-x-2">
            <v-chip
              v-if="activeFiltersCount > 0"
              size="small"
              color="primary"
              variant="flat"
            >
              {{ activeFiltersCount }} active
            </v-chip>
            <v-btn
              variant="text"
              size="small"
              @click="clearAllFilters"
              class="tw-text-gray-500 hover:tw-text-gray-700"
            >
              Clear All
            </v-btn>
          </div>
        </div>

        <!-- Main Filter Row -->
        <div class="tw-p-4">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <!-- Office/LGA Filter -->
            <div class="tw-flex tw-items-center tw-space-x-2">
              <label class="tw-text-sm tw-font-medium tw-text-gray-700 tw-whitespace-nowrap">Office</label>
              <v-select
                v-model="filters.lga"
                :items="lgaOptions"
                item-title="name"
                item-value="id"
                placeholder="Select LGA"
                variant="outlined"
                density="compact"
                class="tw-min-w-48"
                clearable
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Contains/Search Filter -->
            <div class="tw-flex tw-items-center tw-space-x-2">
              <label class="tw-text-sm tw-font-medium tw-text-gray-700 tw-whitespace-nowrap">Contains</label>
              <v-text-field
                v-model="searchQuery"
                placeholder="Search enrollees..."
                variant="outlined"
                density="compact"
                class="tw-min-w-64"
                prepend-inner-icon="mdi-magnify"
                clearable
                @input="debounceSearch"
              />
            </div>

            <!-- Add Filter Button -->
            <v-btn
              variant="outlined"
              size="small"
              prepend-icon="mdi-plus"
              @click="showAddFilterDialog = true"
              class="tw-whitespace-nowrap"
            >
              ADD A FILTER
            </v-btn>
          </div>

          <!-- Active Filter Tags -->
          <div v-if="activeFilterTags.length > 0" class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
            <v-chip
              v-for="tag in activeFilterTags"
              :key="tag.key"
              size="small"
              color="primary"
              variant="outlined"
              closable
              @click:close="removeFilter(tag.key)"
            >
              <strong>{{ tag.label }}:</strong> {{ tag.displayValue }}
            </v-chip>
          </div>
        </div>


      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
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
                    color="orange"
                    variant="outlined"
                    @click="bulkDisable"
                  >
                    Disable Selected
                  </v-btn>
                  <v-btn
                    size="small"
                    color="green"
                    variant="outlined"
                    @click="bulkEnable"
                  >
                    Enable Selected
                  </v-btn>
                </div>
              </div>
            </div>
          </template>

           <template #item.lga="{ item }">
            <span>{{ formatTitle(item.lga?.name) }}</span>
          </template>
           <template #item.ward="{ item }">
            <span>{{ formatTitle(item.ward?.name) }}</span>
          </template>

           <template #item.facility_name="{ item }">
            <span>{{ formatTitle(item.facility?.name) }}</span>
          </template>

              <template #item.benefactor="{ item }">
            <span>{{ formatTitle(item.benefactor?.name) }}</span>
          </template>

        <template #item.funding_type="{ item }">
            <span>{{ formatTitle(item.funding_type?.name) }}</span>
          </template>

          <template #item.enrollment_date="{ item }">
            <span>{{ formatDate(item.enrollment_date) }}</span>
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
                  <p class="tw-font-medium">{{ viewingEnrollee.lga?.name }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Date Enrolled</p>
                  <p class="tw-font-medium">{{ formatDate(viewingEnrollee.enrollment_date) }}</p>
                </div>
              </div>
            </div>

            <!-- Passport Upload -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Passport Photo</h4>
              <div class="tw-flex tw-items-center tw-space-x-4">
                <div class="tw-w-24 tw-h-24 tw-bg-gray-200 tw-rounded-lg tw-flex tw-items-center tw-justify-center">
                  <img
                    v-if="viewingEnrollee.image_url"
                    :src="viewingEnrollee.image_url"
                    alt="Passport"
                    class="tw-w-full tw-h-full tw-object-cover tw-rounded-lg"
                  />
                  <v-icon v-else size="32" color="grey">mdi-account</v-icon>
                </div>
                <div>
                  <v-file-input
                    v-model="passportFile"
                    label="Upload Passport"
                    variant="outlined"
                    density="compact"
                    accept="image/*"
                    prepend-icon="mdi-camera"
                    @change="uploadPassport"
                  />
                  <p class="tw-text-xs tw-text-gray-500 tw-mt-1">
                    Supported formats: JPG, PNG, GIF (Max: 2MB)
                  </p>
                </div>
              </div>
            </div>

            <!-- Status Management -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Status Management</h4>
              <div class="tw-flex tw-items-center tw-space-x-4">
                <v-select
                  v-model="newStatus"
                  :items="statusOptions"
                  item-title="label"
                  item-value="value"
                  label="Change Status"
                  variant="outlined"
                  density="compact"
                  class="tw-flex-1"
                />
                <v-btn
                  color="primary"
                  @click="updateEnrolleeStatus"
                  :loading="updatingStatus"
                  :disabled="!newStatus || newStatus === viewingEnrollee.status"
                >
                  Update Status
                </v-btn>
              </div>
              <div class="tw-mt-4 tw-flex tw-space-x-2">
                <v-btn
                  v-if="viewingEnrollee.status !== 'suspended'"
                  color="orange"
                  variant="outlined"
                  size="small"
                  @click="disableEnrollee"
                  :loading="disabling"
                >
                  <v-icon left>mdi-account-off</v-icon>
                  Disable Enrollee
                </v-btn>
                <v-btn
                  v-if="viewingEnrollee.status === 'suspended'"
                  color="green"
                  variant="outlined"
                  size="small"
                  @click="enableEnrollee"
                  :loading="enabling"
                >
                  <v-icon left>mdi-account-check</v-icon>
                  Enable Enrollee
                </v-btn>
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

    <!-- Add Filter Dialog -->
    <v-dialog v-model="showAddFilterDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Add Filter</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-6">
            <!-- Status Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Status</h4>
              <v-select
                v-model="filters.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Select Status"
                variant="outlined"
                multiple
                clearable
                chips
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Benefactor Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Benefactor</h4>
              <v-select
                v-model="filters.benefactor"
                :items="benefactorOptions"
                item-title="name"
                item-value="id"
                label="Select Benefactor"
                variant="outlined"
                multiple
                clearable
                chips
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Funding Type Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Funding Type</h4>
              <v-select
                v-model="filters.fundingType"
                :items="fundingTypeOptions"
                item-title="name"
                item-value="id"
                label="Select Funding Type"
                variant="outlined"
                multiple
                clearable
                chips
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Facility Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Facility</h4>
              <v-select
                v-model="filters.facility"
                :items="facilityOptions"
                item-title="name"
                item-value="id"
                label="Select Facility"
                variant="outlined"
                multiple
                clearable
                chips
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Gender Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Gender</h4>
              <v-select
                v-model="filters.gender"
                :items="genderOptions"
                label="Select Gender"
                variant="outlined"
                multiple
                clearable
                chips
                @update:model-value="applyFilters"
              />
            </div>

            <!-- Date Range Filter -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Date Range</h4>
              <v-menu>
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    variant="outlined"
                    color="primary"
                    class="tw-w-full tw-h-12"
                  >
                    <v-icon left>mdi-calendar</v-icon>
                    {{ filters.dateRange && Array.isArray(filters.dateRange)
                        ? `${filters.dateRange[0]} - ${filters.dateRange[1]}`
                        : 'Select Date Range' }}
                  </v-btn>
                </template>
                <v-date-picker
                  v-model="filters.dateRange"
                  range
                  @update:model-value="applyFilters"
                />
              </v-menu>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showAddFilterDialog = false">Close</v-btn>
          <v-btn color="primary" @click="showAddFilterDialog = false">Apply Filters</v-btn>
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
import { useFormat } from '../../composables/useFormat';
import axios from 'axios';

// Toasts
const { success, error } = useToast();
const { formatTitle } = useFormat();

// Reactive state
const loading = ref(false);
const exporting = ref(false);
const searchQuery = ref('');
const selectedItems = ref([]);
const showAddDialog = ref(false);
const showViewDialog = ref(false);
const showAddFilterDialog = ref(false);
const editingEnrollee = ref(null);
const viewingEnrollee = ref(null);
const saving = ref(false);
const passportFile = ref(null);
const newStatus = ref(null);
const updatingStatus = ref(false);
const disabling = ref(false);
const enabling = ref(false);

const currentPage = ref(1);
const itemsPerPage = ref(200);
const totalItems = ref(0);

// Filters
const filters = ref({
  status: [],
  lga: [],
  benefactor: [],
  fundingType: [],
  facility: [],
  gender: [],
  dateRange: null,
});

// Options
const statusOptions = ref([]);
const lgaOptions = ref([]);
const benefactorOptions = ref([]);
const fundingTypeOptions = ref([]);
const facilityOptions = ref([]);
const genderOptions = [
  { title: 'Male', value: 'Male' },
  { title: 'Female', value: 'Female' }
];

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
  { title: 'Phone',         key: 'phone',       sortable: true },
  { title: 'Status',        key: 'status',      sortable: true },
  { title: 'LGA',           key: 'lga',         sortable: true },
  { title: 'Ward',          key: 'ward',        sortable: true },
  { title: 'Facility',      key: 'facility_name', sortable: true },
  { title: 'Benefactor',    key: 'benefactor',  sortable: true },
  { title: 'Funding Type',  key: 'funding_type', sortable: true },
  { title: 'Date Enrolled', key: 'enrollment_date', sortable: true },
  { title: 'Actions',       key: 'actions',     sortable: false, width: '100px' },
];


// Data
const enrollees = ref([]);

// Computed properties
const activeFiltersCount = computed(() => {
  return Object.values(filters.value).filter(value =>
    value !== null && value !== undefined &&
    (Array.isArray(value) ? value.length > 0 : value !== '')
  ).length;
});

// Active filter tags for display
const activeFilterTags = computed(() => {
  const tags = [];

  if (filters.value.status && filters.value.status.length > 0) {
    const statusLabels = filters.value.status.map(s =>
      statusOptions.value.find(opt => opt.value === s)?.label || s
    );
    tags.push({
      key: 'status',
      label: 'Status',
      displayValue: statusLabels.join(', ')
    });
  }

  if (filters.value.lga && filters.value.lga.length > 0) {
    const lgaLabels = filters.value.lga.map(l =>
      lgaOptions.value.find(opt => opt.id === l)?.name || l
    );
    tags.push({
      key: 'lga',
      label: 'LGA',
      displayValue: lgaLabels.join(', ')
    });
  }

  if (filters.value.benefactor && filters.value.benefactor.length > 0) {
    const benefactorLabels = filters.value.benefactor.map(b =>
      benefactorOptions.value.find(opt => opt.id === b)?.name || b
    );
    tags.push({
      key: 'benefactor',
      label: 'Benefactor',
      displayValue: benefactorLabels.join(', ')
    });
  }

  if (filters.value.fundingType && filters.value.fundingType.length > 0) {
    const fundingLabels = filters.value.fundingType.map(f =>
      fundingTypeOptions.value.find(opt => opt.id === f)?.name || f
    );
    tags.push({
      key: 'fundingType',
      label: 'Funding Type',
      displayValue: fundingLabels.join(', ')
    });
  }

  if (filters.value.facility && filters.value.facility.length > 0) {
    const facilityLabels = filters.value.facility.map(f =>
      facilityOptions.value.find(opt => opt.id === f)?.name || f
    );
    tags.push({
      key: 'facility',
      label: 'Facility',
      displayValue: facilityLabels.join(', ')
    });
  }

  if (filters.value.gender && filters.value.gender.length > 0) {
    tags.push({
      key: 'gender',
      label: 'Gender',
      displayValue: filters.value.gender.join(', ')
    });
  }

  if (filters.value.dateRange && Array.isArray(filters.value.dateRange)) {
    tags.push({
      key: 'dateRange',
      label: 'Date Range',
      displayValue: `${filters.value.dateRange[0]} - ${filters.value.dateRange[1]}`
    });
  }

  return tags;
});

// Debounced search
let searchTimer = null;
const debounceSearch = () => {
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

const clearAllFilters = () => {
  filters.value = {
    status: [],
    lga: [],
    benefactor: [],
    fundingType: [],
    facility: [],
    gender: [],
    dateRange: null,
  };
  searchQuery.value = '';
  applyFilters();
};

const removeFilter = (filterKey) => {
  if (filterKey === 'dateRange') {
    filters.value.dateRange = null;
  } else {
    filters.value[filterKey] = [];
  }
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

const bulkDisable = async () => {
  if (selectedItems.value.length === 0) return;
  if (confirm(`Are you sure you want to disable ${selectedItems.value.length} enrollees?`)) {
    try {
      // TODO: call bulk disable API
      success(`${selectedItems.value.length} enrollees disabled successfully`);
      selectedItems.value = [];
      loadEnrollees();
    } catch (err) {
      error('Failed to disable enrollees');
    }
  }
};

const bulkEnable = async () => {
  if (selectedItems.value.length === 0) return;
  if (confirm(`Are you sure you want to enable ${selectedItems.value.length} enrollees?`)) {
    try {
      // TODO: call bulk enable API
      success(`${selectedItems.value.length} enrollees enabled successfully`);
      selectedItems.value = [];
      loadEnrollees();
    } catch (err) {
      error('Failed to enable enrollees');
    }
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
      date_enrolled: enrollee.enrollment_date,
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



// Passport upload
const uploadPassport = async () => {
  if (!passportFile.value || !viewingEnrollee.value) return;

  try {
    const formData = new FormData();
    formData.append('passport', passportFile.value);

    const response = await axios.post(`/api/v1/enrollees/${viewingEnrollee.value.id}/upload-passport`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      success('Passport uploaded successfully');
      viewingEnrollee.value.image_url = response.data.data.image_url;
      passportFile.value = null;
    }
  } catch (err) {
    error('Failed to upload passport');
  }
};

// Status management
const updateEnrolleeStatus = async () => {
  if (!newStatus.value || !viewingEnrollee.value) return;

  updatingStatus.value = true;
  try {
    const response = await axios.put(`/api/v1/enrollees/${viewingEnrollee.value.id}/status`, {
      status: newStatus.value,
      comment: 'Status updated by admin'
    });

    if (response.data.success) {
      success('Enrollee status updated successfully');
      viewingEnrollee.value.status = response.data.data.status;
      newStatus.value = null;
      loadEnrollees();
    }
  } catch (err) {
    error('Failed to update enrollee status');
  } finally {
    updatingStatus.value = false;
  }
};

const disableEnrollee = async () => {
  if (!viewingEnrollee.value) return;

  if (confirm(`Are you sure you want to disable ${viewingEnrollee.value.name}?`)) {
    disabling.value = true;
    try {
      const response = await axios.put(`/api/v1/enrollees/${viewingEnrollee.value.id}/status`, {
        status: 3, // SUSPENDED status
        comment: 'Enrollee disabled by admin'
      });

      if (response.data.success) {
        success('Enrollee disabled successfully');
        viewingEnrollee.value.status = response.data.data.status;
        loadEnrollees();
      }
    } catch (err) {
      error('Failed to disable enrollee');
    } finally {
      disabling.value = false;
    }
  }
};

const enableEnrollee = async () => {
  if (!viewingEnrollee.value) return;

  if (confirm(`Are you sure you want to enable ${viewingEnrollee.value.name}?`)) {
    enabling.value = true;
    try {
      const response = await axios.put(`/api/v1/enrollees/${viewingEnrollee.value.id}/status`, {
        status: 1, // ACTIVE status
        comment: 'Enrollee enabled by admin'
      });

      if (response.data.success) {
        success('Enrollee enabled successfully');
        viewingEnrollee.value.status = response.data.data.status;
        loadEnrollees();
      }
    } catch (err) {
      error('Failed to enable enrollee');
    } finally {
      enabling.value = false;
    }
  }
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
    params.sort_by = 'enrollment_date';
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

// Load filter options
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

const loadBenefactorOptions = async () => {
  try {
    const response = await axios.get('/api/v1/benefactors');
    if (response.data.success) {
      benefactorOptions.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load benefactor options:', err);
  }
};

const loadFundingTypeOptions = async () => {
  try {
    const response = await axios.get('/api/v1/funding-types');
    if (response.data.success) {
      fundingTypeOptions.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load funding type options:', err);
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
  loadBenefactorOptions();
  loadFundingTypeOptions();
  loadFacilityOptions();
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
