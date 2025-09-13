<template>
  <AdminLayout>
    <div class="tw-space-y-6" v-if="enrollee">
      <!-- Header Section -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-flex tw-items-start tw-justify-between">
          <div class="tw-flex tw-items-start tw-space-x-6">
            <!-- Profile Picture -->
            <div class="tw-relative">
              <div class="tw-w-32 tw-h-32 tw-bg-gray-200 tw-rounded-lg tw-flex tw-items-center tw-justify-center tw-overflow-hidden">
                <img 
                  v-if="enrollee.image_url" 
                  :src="enrollee.image_url" 
                  alt="Profile Picture"
                  class="tw-w-full tw-h-full tw-object-cover"
                />
                <v-icon v-else size="48" color="grey">mdi-account</v-icon>
              </div>
              <!-- Upload Button -->
              <v-btn
                icon
                size="small"
                color="primary"
                class="tw-absolute tw-bottom-2 tw-right-2"
                @click="triggerFileUpload"
              >
                <v-icon size="16">mdi-camera</v-icon>
              </v-btn>
              <input
                ref="fileInput"
                type="file"
                accept="image/*"
                class="tw-hidden"
                @change="handleFileUpload"
              />
            </div>

            <!-- Basic Info -->
            <div class="tw-flex-1">
              <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">{{ enrollee.name }}</h1>
              <p class="tw-text-lg tw-text-gray-600 tw-mt-1">{{ enrollee.enrollee_id }}</p>
              
              <div class="tw-flex tw-items-center tw-space-x-4 tw-mt-4">
                <v-chip
                  :color="getStatusColor(enrollee.status)"
                  size="large"
                  variant="flat"
                >
                  {{ enrollee.status }}
                </v-chip>
                <v-chip
                  color="primary"
                  size="large"
                  variant="outlined"
                >
                  {{ enrollee.type || 'N/A' }}
                </v-chip>
              </div>

              <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-mt-4">
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Phone</p>
                  <p class="tw-font-medium">{{ enrollee.phone }}</p>
                </div>
                <div>
                  <p class="tw-text-sm tw-text-gray-600">Email</p>
                  <p class="tw-font-medium">{{ enrollee.email || 'N/A' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="tw-flex tw-space-x-2">
            <v-btn
              color="primary"
              variant="outlined"
              prepend-icon="mdi-pencil"
              @click="editEnrollee"
            >
              Edit Profile
            </v-btn>
            <v-btn
              color="primary"
              prepend-icon="mdi-download"
              @click="downloadProfile"
            >
              Download PDF
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Status Management Section -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Status Management</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-lg:grid-cols-2 tw-gap-6">
          <!-- Current Status -->
          <div>
            <h3 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-3">Current Status</h3>
            <div class="tw-bg-gray-50 tw-rounded-lg tw-p-4">
              <div class="tw-flex tw-items-center tw-justify-between">
                <div>
                  <v-chip
                    :color="getStatusColor(enrollee.status)"
                    size="large"
                    variant="flat"
                  >
                    {{ enrollee.status }}
                  </v-chip>
                  <p class="tw-text-sm tw-text-gray-600 tw-mt-2">
                    Last updated: {{ formatDate(enrollee.updated_at) }}
                  </p>
                </div>
                <v-btn
                  :color="enrollee.status === 'active' ? 'orange' : 'green'"
                  variant="outlined"
                  @click="toggleStatus"
                  :loading="updatingStatus"
                >
                  {{ enrollee.status === 'active' ? 'Disable' : 'Enable' }}
                </v-btn>
              </div>
            </div>
          </div>

          <!-- Change Status -->
          <div>
            <h3 class="tw-text-lg tw-font-medium tw-text-gray-900 tw-mb-3">Change Status</h3>
            <div class="tw-space-y-4">
              <v-select
                v-model="newStatus"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Select New Status"
                variant="outlined"
                density="compact"
              />
              
              <v-textarea
                v-model="statusComment"
                label="Comment (Optional)"
                variant="outlined"
                density="compact"
                rows="3"
                placeholder="Add a comment about this status change..."
              />
              
              <v-btn
                color="primary"
                @click="updateStatus"
                :loading="updatingStatus"
                :disabled="!newStatus || newStatus === enrollee.status"
                block
              >
                Update Status
              </v-btn>
            </div>
          </div>
        </div>
      </div>

      <!-- Personal Information -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Personal Information</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-3 tw-gap-6">
          <div>
            <p class="tw-text-sm tw-text-gray-600">Full Name</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.name }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">NIN</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.nin || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Date of Birth</p>
            <p class="tw-font-medium tw-text-gray-900">{{ formatDate(enrollee.date_of_birth) }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Age</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.age || 'N/A' }} years</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Gender</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.gender || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Marital Status</p>
            <p class="tw-font-medium tw-text-gray-900">{{ getMaritalStatus(enrollee.marital_status) }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Phone Number</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.phone }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Email Address</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.email || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Address</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.address || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Location Information -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Location Information</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-4 tw-gap-6">
          <div>
            <p class="tw-text-sm tw-text-gray-600">LGA</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.lga_name || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Ward</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.ward?.name || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Village</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.village || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Primary Facility</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.facility_name || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Enrollment Details -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Enrollment Details</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-3 tw-gap-6">
          <div>
            <p class="tw-text-sm tw-text-gray-600">Enrollee ID</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.enrollee_id }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Enrollment Date</p>
            <p class="tw-font-medium tw-text-gray-900">{{ formatDate(enrollee.enrollment_date) }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Approval Date</p>
            <p class="tw-font-medium tw-text-gray-900">{{ formatDate(enrollee.approval_date) }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Benefactor</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.benefactor?.name || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Funding Type</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.funding_type?.name || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Premium ID</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.premium?.id || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Employment Details -->
      <div v-if="enrollee.employment_detail" class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Employment Details</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-3 tw-gap-6">
          <div>
            <p class="tw-text-sm tw-text-gray-600">Occupation</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.occupation || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">CNO</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.cno || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Basic Salary</p>
            <p class="tw-font-medium tw-text-gray-900">{{ formatCurrency(enrollee.basic_salary) }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Station</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.station || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Salary Scheme</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.salary_scheme || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Date of First Appointment</p>
            <p class="tw-font-medium tw-text-gray-900">{{ formatDate(enrollee.dfa) }}</p>
          </div>
        </div>
      </div>

      <!-- Next of Kin -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Next of Kin Information</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-4 tw-gap-6">
          <div>
            <p class="tw-text-sm tw-text-gray-600">Name</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.nok_name || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Phone Number</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.nok_phone_number || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Relationship</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.nok_relationship || 'N/A' }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-text-gray-600">Address</p>
            <p class="tw-font-medium tw-text-gray-900">{{ enrollee.nok_address || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <!-- Activity Statistics -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <h2 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-4">Activity Statistics</h2>
        
        <div class="tw-grid tw-grid-cols-1 tw-md:grid-cols-4 tw-gap-6">
          <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-blue-50">
            <v-icon size="32" color="blue">mdi-file-document</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-blue-700 tw-mt-2">{{ statistics.totalClaims }}</p>
            <p class="tw-text-sm tw-text-gray-600">Total Claims</p>
          </div>
          <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-green-50">
            <v-icon size="32" color="green">mdi-currency-ngn</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-green-700 tw-mt-2">{{ formatCurrency(statistics.totalBenefits) }}</p>
            <p class="tw-text-sm tw-text-gray-600">Total Benefits</p>
          </div>
          <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-purple-50">
            <v-icon size="32" color="purple">mdi-hospital-building</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-purple-700 tw-mt-2">{{ statistics.facilitiesVisited }}</p>
            <p class="tw-text-sm tw-text-gray-600">Facilities Visited</p>
          </div>
          <div class="tw-text-center tw-p-4 tw-rounded-lg tw-bg-orange-50">
            <v-icon size="32" color="orange">mdi-calendar-check</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-orange-700 tw-mt-2">{{ statistics.lastVisit }}</p>
            <p class="tw-text-sm tw-text-gray-600">Days Since Last Visit</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-else class="tw-flex tw-justify-center tw-items-center tw-h-64">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { enrolleeAPI } from '../../utils/api';
import axios from 'axios';

// Router and toast
const route = useRoute();
const { success, error } = useToast();

// Reactive state
const enrollee = ref(null);
const loading = ref(false);
const updatingStatus = ref(false);
const newStatus = ref(null);
const statusComment = ref('');
const fileInput = ref(null);
const statusOptions = ref([]);

// Statistics (mock data - replace with real API calls)
const statistics = ref({
  totalClaims: 12,
  totalBenefits: 45000,
  facilitiesVisited: 3,
  lastVisit: 15
});

// Methods
const loadEnrollee = async () => {
  loading.value = true;
  try {
    const response = await enrolleeAPI.getById(route.params.id);
    enrollee.value = response.data.data;
  } catch (err) {
    error('Failed to load enrollee details');
  } finally {
    loading.value = false;
  }
};

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

const triggerFileUpload = () => {
  fileInput.value.click();
};

const handleFileUpload = async (event) => {
  const file = event.target.files[0];
  if (!file) return;

  // Validate file type and size
  if (!file.type.startsWith('image/')) {
    error('Please select a valid image file');
    return;
  }

  if (file.size > 2 * 1024 * 1024) { // 2MB limit
    error('File size must be less than 2MB');
    return;
  }

  try {
    const formData = new FormData();
    formData.append('passport', file);

    const response = await axios.post(`/api/v1/enrollees/${enrollee.value.id}/upload-passport`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      success('Passport photo uploaded successfully');
      enrollee.value.image_url = response.data.data.image_url;
    }
  } catch (err) {
    error('Failed to upload passport photo');
  }
};

const toggleStatus = async () => {
  const newStatusValue = enrollee.value.status === 'active' ? 'suspended' : 'active';
  await updateEnrolleeStatus(newStatusValue, `Status ${newStatusValue === 'active' ? 'enabled' : 'disabled'} by admin`);
};

const updateStatus = async () => {
  if (!newStatus.value) return;
  await updateEnrolleeStatus(newStatus.value, statusComment.value);
};

const updateEnrolleeStatus = async (status, comment) => {
  updatingStatus.value = true;
  try {
    const response = await axios.put(`/api/v1/enrollees/${enrollee.value.id}/status`, {
      status: status,
      comment: comment
    });

    if (response.data.success) {
      success('Enrollee status updated successfully');
      enrollee.value.status = response.data.data.status;
      newStatus.value = null;
      statusComment.value = '';
    }
  } catch (err) {
    error('Failed to update enrollee status');
  } finally {
    updatingStatus.value = false;
  }
};

const editEnrollee = () => {
  // Navigate to edit page
  console.log('Edit enrollee:', enrollee.value.id);
};

const downloadProfile = async () => {
  try {
    // TODO: Replace with actual API endpoint
    // const response = await axios.get(`/api/v1/enrollees/${enrollee.value.id}/export-pdf`, {
    //   responseType: 'blob'
    // });
    
    success('Profile downloaded successfully');
  } catch (err) {
    error('Failed to download profile');
  }
};

// Utility functions
const getStatusColor = (status) => {
  switch (status?.toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'suspended': return 'error';
    case 'expired': return 'error';
    default: return 'grey';
  }
};

const getMaritalStatus = (status) => {
  switch (status) {
    case 1: return 'Single';
    case 2: return 'Married';
    case 3: return 'Divorced';
    case 4: return 'Widowed';
    default: return 'N/A';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString();
};

const formatCurrency = (amount) => {
  if (!amount) return 'N/A';
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN'
  }).format(amount);
};

// Lifecycle
onMounted(() => {
  loadEnrollee();
  loadStatusOptions();
});
</script>
