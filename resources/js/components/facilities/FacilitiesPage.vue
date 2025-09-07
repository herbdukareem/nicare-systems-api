<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Facilities</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage healthcare facilities and providers</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-map-marker"
            @click="showMapView = !showMapView"
          >
            {{ showMapView ? 'List View' : 'Map View' }}
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showAddDialog = true"
          >
            Add Facility
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-hospital-building</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Facilities</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">342</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">298</p>
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
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">44</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-doctor</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Providers</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">1,247</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-5 tw-gap-4">
          <!-- Search -->
          <div class="tw-lg:tw-col-span-2">
            <v-text-field
              v-model="searchQuery"
              label="Search facilities..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>
          
          <!-- Type Filter -->
          <v-select
            v-model="filters.type"
            :items="facilityTypes"
            label="Facility Type"
            variant="outlined"
            density="compact"
            clearable
          />
          
          <!-- LGA Filter -->
          <v-select
            v-model="filters.lga"
            :items="lgaOptions"
            label="LGA"
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

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="facilities"
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
                  Facilities List
                </h3>
                <v-chip size="small" color="primary">
                  {{ facilities.length }} facilities
                </v-chip>
              </div>
            </div>
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

          <!-- Type column -->
          <template v-slot:item.type="{ item }">
            <div class="tw-flex tw-items-center tw-space-x-2">
              <v-icon :color="getTypeColor(item.type)" size="16">
                {{ getTypeIcon(item.type) }}
              </v-icon>
              <span>{{ item.type }}</span>
            </div>
          </template>

          <!-- Actions column -->
          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewFacility(item)"
              >
                <v-icon size="16">mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editFacility(item)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteFacility(item)"
              >
                <v-icon size="16">mdi-delete</v-icon>
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
            {{ editingFacility ? 'Edit Facility' : 'Add New Facility' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
            <v-text-field
              v-model="facilityForm.name"
              label="Facility Name"
              variant="outlined"
              required
            />
            <v-select
              v-model="facilityForm.type"
              :items="facilityTypes"
              label="Facility Type"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="facilityForm.address"
              label="Address"
              variant="outlined"
              required
            />
            <v-select
              v-model="facilityForm.lga"
              :items="lgaOptions"
              label="LGA"
              variant="outlined"
              required
            />
            <v-text-field
              v-model="facilityForm.phone"
              label="Phone Number"
              variant="outlined"
            />
            <v-text-field
              v-model="facilityForm.email"
              label="Email"
              variant="outlined"
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveFacility">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Facility Dialog -->
    <v-dialog v-model="showViewDialog" max-width="1200px">
      <v-card v-if="viewingFacility">
        <v-card-title>
          <div class="tw-flex tw-items-center tw-justify-between tw-w-full">
            <div>
              <h2 class="tw-text-xl tw-font-semibold">{{ viewingFacility.name }}</h2>
              <p class="tw-text-sm tw-text-gray-600">{{ viewingFacility.type }} • {{ viewingFacility.lga }}</p>
            </div>
            <div class="tw-flex tw-space-x-2">
              <v-btn
                size="small"
                color="primary"
                prepend-icon="mdi-pencil"
                @click="editFromView(viewingFacility)"
              >
                Edit Facility
              </v-btn>
            </div>
          </div>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-6">
            <!-- Facility Information -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Facility Information</h4>
              <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-3 tw-gap-4">
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Facility Name</p>
                  <p class="tw-font-medium">{{ viewingFacility.name }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Type</p>
                  <v-chip size="small" color="primary" variant="outlined">
                    {{ viewingFacility.type }}
                  </v-chip>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Status</p>
                  <v-chip
                    size="small"
                    :color="getStatusColor(viewingFacility.status)"
                    variant="flat"
                  >
                    {{ viewingFacility.status }}
                  </v-chip>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Address</p>
                  <p class="tw-font-medium">{{ viewingFacility.address }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">LGA</p>
                  <p class="tw-font-medium">{{ viewingFacility.lga }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Phone</p>
                  <p class="tw-font-medium">{{ viewingFacility.phone || 'N/A' }}</p>
                </div>
              </div>
            </div>

            <!-- Facility Statistics -->
            <div>
              <h4 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-4">Statistics</h4>
              <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-4 tw-gap-4">
                <div class="tw-text-center tw-p-4 tw-bg-blue-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ facilityEnrollees.length }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Total Enrollees</p>
                </div>
                <div class="tw-text-center tw-p-4 tw-bg-green-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ getActiveEnrollees() }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Active Enrollees</p>
                </div>
                <div class="tw-text-center tw-p-4 tw-bg-purple-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-purple-600">45</p>
                  <p class="tw-text-sm tw-text-gray-600">Monthly Visits</p>
                </div>
                <div class="tw-text-center tw-p-4 tw-bg-orange-50 tw-rounded-lg">
                  <p class="tw-text-2xl tw-font-bold tw-text-orange-600">₦125,000</p>
                  <p class="tw-text-sm tw-text-gray-600">Monthly Claims</p>
                </div>
              </div>
            </div>

            <!-- Enrollees List -->
            <div>
              <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
                <h4 class="tw-text-lg tw-font-medium tw-text-gray-900">Enrollees at this Facility</h4>
                <div class="tw-flex tw-space-x-2">
                  <v-text-field
                    v-model="enrolleeSearchQuery"
                    label="Search enrollees..."
                    prepend-inner-icon="mdi-magnify"
                    variant="outlined"
                    density="compact"
                    clearable
                    class="tw-w-64"
                  />
                  <v-btn
                    color="primary"
                    variant="outlined"
                    prepend-icon="mdi-download"
                    @click="exportFacilityEnrollees"
                  >
                    Export
                  </v-btn>
                </div>
              </div>

              <v-data-table
                :headers="enrolleeHeaders"
                :items="filteredFacilityEnrollees"
                :loading="loadingEnrollees"
                class="tw-elevation-0 tw-border tw-border-gray-200 tw-rounded-lg"
                item-value="id"
                :items-per-page="10"
              >
                <!-- Status column -->
                <template v-slot:item.status="{ item }">
                  <v-chip
                    size="small"
                    :color="getEnrolleeStatusColor(item.status)"
                    variant="flat"
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <!-- Type column -->
                <template v-slot:item.type="{ item }">
                  <v-chip size="small" color="primary" variant="outlined">
                    {{ item.type }}
                  </v-chip>
                </template>

                <!-- Actions column -->
                <template v-slot:item.actions="{ item }">
                  <div class="tw-flex tw-space-x-1">
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="viewEnrolleeFromFacility(item)"
                    >
                      <v-icon size="16">mdi-eye</v-icon>
                    </v-btn>
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="editEnrolleeFromFacility(item)"
                    >
                      <v-icon size="16">mdi-pencil</v-icon>
                    </v-btn>
                  </div>
                </template>

                <!-- No data slot -->
                <template v-slot:no-data>
                  <div class="tw-text-center tw-py-8">
                    <v-icon size="48" color="grey">mdi-account-group</v-icon>
                    <p class="tw-text-gray-500 tw-mt-2">No enrollees found for this facility</p>
                  </div>
                </template>
              </v-data-table>
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showViewDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { facilityAPI } from '../../utils/api';

const router = useRouter();
const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const loadingEnrollees = ref(false);
const searchQuery = ref('');
const enrolleeSearchQuery = ref('');
const showAddDialog = ref(false);
const showViewDialog = ref(false);
const showMapView = ref(false);
const editingFacility = ref(null);
const viewingFacility = ref(null);
const itemsPerPage = ref(10);
const saving = ref(false);

// Filters
const filters = ref({
  type: null,
  lga: null,
  status: null
});

// Form data
const facilityForm = ref({
  name: '',
  type: '',
  address: '',
  lga: '',
  phone: '',
  email: ''
});

// Options
const facilityTypes = ['Hospital', 'Clinic', 'Pharmacy', 'Laboratory', 'Diagnostic Center'];
const lgaOptions = ['Abuja Municipal', 'Gwagwalada', 'Kuje', 'Bwari', 'Kwali', 'Abaji'];
const statusOptions = ['Active', 'Inactive', 'Pending', 'Suspended'];

// Table headers
const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'LGA', key: 'lga', sortable: true },
  { title: 'Address', key: 'address', sortable: false },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// Enrollee table headers for facility view
const enrolleeHeaders = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Enrollee ID', key: 'enrollee_id', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, width: '100px' }
];

// Mock enrollee data for facilities
const facilityEnrollees = ref([
  {
    id: 1,
    name: 'John Doe',
    enrollee_id: 'ENG001',
    type: 'Principal',
    status: 'Active',
    phone: '+234 801 234 5678',
    email: 'john.doe@email.com',
    facility_id: 1
  },
  {
    id: 2,
    name: 'Jane Smith',
    enrollee_id: 'ENG002',
    type: 'Spouse',
    status: 'Active',
    phone: '+234 802 345 6789',
    email: 'jane.smith@email.com',
    facility_id: 1
  },
  {
    id: 3,
    name: 'Mike Johnson',
    enrollee_id: 'ENG003',
    type: 'Principal',
    status: 'Inactive',
    phone: '+234 803 456 7890',
    email: 'mike.johnson@email.com',
    facility_id: 2
  }
]);

// Data
const facilities = ref([]);
const totalFacilities = ref(0);
const currentPage = ref(1);

// Computed properties
const filteredFacilityEnrollees = computed(() => {
  if (!viewingFacility.value) return [];

  let enrollees = facilityEnrollees.value.filter(
    enrollee => enrollee.facility_id === viewingFacility.value.id
  );

  if (enrolleeSearchQuery.value) {
    const query = enrolleeSearchQuery.value.toLowerCase();
    enrollees = enrollees.filter(enrollee =>
      enrollee.name.toLowerCase().includes(query) ||
      enrollee.enrollee_id.toLowerCase().includes(query) ||
      enrollee.phone.includes(query)
    );
  }

  return enrollees;
});

// Methods
const getStatusColor = (status) => {
  switch (status.toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'inactive': return 'error';
    case 'suspended': return 'error';
    default: return 'grey';
  }
};

const getEnrolleeStatusColor = (status) => {
  switch (status?.toLowerCase()) {
    case 'active': return 'success';
    case 'inactive': return 'warning';
    case 'suspended': return 'error';
    case 'pending': return 'info';
    default: return 'grey';
  }
};

const getActiveEnrollees = () => {
  if (!viewingFacility.value) return 0;
  return facilityEnrollees.value.filter(
    enrollee => enrollee.facility_id === viewingFacility.value.id &&
    enrollee.status.toLowerCase() === 'active'
  ).length;
};

const getTypeColor = (type) => {
  switch (type.toLowerCase()) {
    case 'hospital': return 'blue';
    case 'clinic': return 'green';
    case 'pharmacy': return 'orange';
    case 'laboratory': return 'purple';
    case 'diagnostic center': return 'teal';
    default: return 'grey';
  }
};

const getTypeIcon = (type) => {
  switch (type.toLowerCase()) {
    case 'hospital': return 'mdi-hospital-building';
    case 'clinic': return 'mdi-medical-bag';
    case 'pharmacy': return 'mdi-pill';
    case 'laboratory': return 'mdi-test-tube';
    case 'diagnostic center': return 'mdi-stethoscope';
    default: return 'mdi-hospital-marker';
  }
};

const viewFacility = async (facility) => {
  viewingFacility.value = facility;
  showViewDialog.value = true;

  // Load enrollees for this facility
  loadingEnrollees.value = true;
  try {
    // In a real app, this would be an API call
    await new Promise(resolve => setTimeout(resolve, 500));
    // facilityEnrollees is already filtered in the computed property
  } catch (err) {
    error('Failed to load facility enrollees');
  } finally {
    loadingEnrollees.value = false;
  }
};

const editFacility = (facility) => {
  editingFacility.value = facility;
  Object.assign(facilityForm.value, facility);
  showAddDialog.value = true;
};

const editFromView = (facility) => {
  showViewDialog.value = false;
  editFacility(facility);
};

const deleteFacility = (facility) => {
  if (confirm(`Are you sure you want to delete ${facility.name}?`)) {
    success('Facility deleted successfully');
  }
};

const saveFacility = () => {
  // Save logic here
  closeDialog();
  success('Facility saved successfully');
};

const closeDialog = () => {
  showAddDialog.value = false;
  editingFacility.value = null;
  Object.keys(facilityForm.value).forEach(key => {
    facilityForm.value[key] = '';
  });
};

const viewEnrolleeFromFacility = (enrollee) => {
  // Navigate to enrollee detail or open enrollee view dialog
  router.push(`/enrollees/${enrollee.id}`);
};

const editEnrolleeFromFacility = (enrollee) => {
  // Navigate to enrollee edit page
  router.push(`/enrollees/${enrollee.id}/edit`);
};

const exportFacilityEnrollees = async () => {
  try {
    const enrollees = filteredFacilityEnrollees.value;
    const csvData = enrollees.map(enrollee => ({
      Name: enrollee.name,
      'Enrollee ID': enrollee.enrollee_id,
      Type: enrollee.type,
      Status: enrollee.status,
      Phone: enrollee.phone,
      Email: enrollee.email
    }));

    // Convert to CSV
    const headers = Object.keys(csvData[0]);
    const csvContent = [
      headers.join(','),
      ...csvData.map(row => headers.map(header => `"${row[header]}"`).join(','))
    ].join('\n');

    // Create download
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${viewingFacility.value.name.replace(/\s+/g, '_')}_enrollees.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    success('Enrollees data exported successfully');
  } catch (err) {
    error('Failed to export enrollees data');
  }
};

// API Methods
const loadFacilities = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      sort_by: 'created_at',
      sort_direction: 'desc'
    };

    // Add filters if they have values
    if (searchQuery.value && searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (filters.value.type) {
      params.type = filters.value.type;
    }
    if (filters.value.lga) {
      params.lga_id = filters.value.lga;
    }
    if (filters.value.status) {
      params.status = filters.value.status;
    }

    const response = await facilityAPI.getAll(params);

    if (response?.data?.success) {
      const responseData = response.data.data;

      if (responseData && typeof responseData === 'object' && responseData.data) {
        facilities.value = responseData.data;
        totalFacilities.value = responseData.meta?.total || responseData.total || 0;
      } else if (Array.isArray(responseData)) {
        facilities.value = responseData;
        totalFacilities.value = responseData.length;
      } else {
        facilities.value = [];
        totalFacilities.value = 0;
      }
    } else {
      facilities.value = [];
      totalFacilities.value = 0;
    }
  } catch (err) {
    console.error('Failed to load facilities:', err);
    error('Failed to load facilities');
    facilities.value = [];
    totalFacilities.value = 0;
  } finally {
    loading.value = false;
  }
};

// Watch for filter changes
watch([searchQuery, filters], () => {
  currentPage.value = 1;
  loadFacilities();
}, { deep: true, debounce: 300 });

onMounted(() => {
  loadFacilities();
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
