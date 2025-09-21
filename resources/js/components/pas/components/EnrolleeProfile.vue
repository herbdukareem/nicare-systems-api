<template>
  <div class="tw-space-y-6">
    <!-- Profile Header -->
    <v-card class="tw-overflow-hidden">
      <div class="tw-bg-gradient-to-r tw-from-blue-500 tw-to-blue-600 tw-text-white tw-p-6">
        <div class="tw-flex tw-items-center tw-space-x-4">
          <v-avatar
            :color="enrollee.gender === 'Male' ? 'blue-darken-2' : 'pink-darken-2'"
            size="80"
          >
            <v-icon color="white" size="40">
              {{ enrollee.gender === 'Male' ? 'mdi-account' : 'mdi-account-outline' }}
            </v-icon>
          </v-avatar>
          <div class="tw-flex-1">
            <h2 class="tw-text-2xl tw-font-bold tw-mb-1">{{ $utils.formatName(enrollee) }}</h2>
            <p class="tw-text-blue-100 tw-mb-2">Enrollment Number: {{ enrollee.enrollee_id }}</p>
            <div class="tw-flex tw-items-center tw-space-x-4">
              <v-chip
                :color="$utils.getStatusColor(enrollee.status)"
                size="small"
                variant="flat"
              >
                {{ getStatusText(enrollee.status) }}
              </v-chip>
              <span class="tw-text-blue-100">{{ enrollee.gender }}</span>
            </div>
          </div>
        </div>
      </div>

      <v-card-text class="tw-p-6">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
          <!-- Personal Information -->
          <div>
            <h3 class="tw-text-lg tw-font-semibold tw-mb-3 tw-text-gray-900">Personal Information</h3>
            <div class="tw-space-y-3">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-calendar</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Date of Birth:</span>
                <span class="tw-ml-2 tw-font-medium">{{ formatDate(enrollee.date_of_birth) }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-phone</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Phone:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.phone || 'N/A' }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-email</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Email:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.email || 'N/A' }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-map-marker</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Address:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.address || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Enrollment Information -->
          <div>
            <h3 class="tw-text-lg tw-font-semibold tw-mb-3 tw-text-gray-900">Enrollment Details</h3>
            <div class="tw-space-y-3">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-calendar-check</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Enrolled:</span>
                <span class="tw-ml-2 tw-font-medium">{{ formatDate(enrollee.enrollment_date) }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-account-group</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Benefactor:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.benefactor?.name || 'N/A' }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-cash</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Funding Type:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.funding_type?.name || 'N/A' }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-map</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Ward:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.ward?.name || 'N/A' }}</span>
              </div>
            </div>
          </div>

          <!-- Facility Information -->
          <div>
            <h3 class="tw-text-lg tw-font-semibold tw-mb-3 tw-text-gray-900">Primary Facility</h3>
            <div class="tw-space-y-3">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-hospital-building</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Name:</span>
                <span class="tw-ml-2 tw-font-medium">{{ facility.name }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-identifier</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Code:</span>
                <span class="tw-ml-2 tw-font-medium">{{ facility.hcp_code }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-hospital</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Level:</span>
                <v-chip
                  :color="getLevelOfCareColor(facility.level_of_care)"
                  size="small"
                  variant="flat"
                >
                  {{ facility.level_of_care }}
                </v-chip>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-map-marker</v-icon>
                <span class="tw-text-sm tw-text-gray-600">LGA:</span>
                <span class="tw-ml-2 tw-font-medium">{{ facility.lga?.name || 'N/A' }}</span>
              </div>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Recent Activity -->
    <v-card>
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-history</v-icon>
        Recent Activity
      </v-card-title>
      <v-card-text>
        <div v-if="recentActivity.length === 0" class="tw-text-center tw-py-8">
          <v-icon size="48" color="grey" class="tw-mb-2">mdi-history</v-icon>
          <p class="tw-text-gray-600">No recent activity found</p>
        </div>
        <v-timeline v-else density="compact" class="tw-max-h-64 tw-overflow-y-auto">
          <v-timeline-item
            v-for="(activity, index) in recentActivity"
            :key="index"
            :dot-color="getActivityColor(activity.type)"
            size="small"
          >
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <p class="tw-font-medium">{{ activity.description }}</p>
                <p class="tw-text-sm tw-text-gray-600">{{ formatDateTime(activity.created_at) }}</p>
              </div>
              <v-chip
                :color="getActivityColor(activity.type)"
                size="small"
                variant="flat"
              >
                {{ activity.type }}
              </v-chip>
            </div>
          </v-timeline-item>
        </v-timeline>
      </v-card-text>
    </v-card>

    <!-- Medical History Summary -->
    <v-card>
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
        Medical Summary
      </v-card-title>
      <v-card-text>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
          <div class="tw-text-center tw-p-4 tw-bg-blue-50 tw-rounded-lg">
            <v-icon color="blue" size="32" class="tw-mb-2">mdi-account-arrow-right</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ medicalSummary.referrals || 0 }}</p>
            <p class="tw-text-sm tw-text-gray-600">Total Referrals</p>
          </div>
          <div class="tw-text-center tw-p-4 tw-bg-green-50 tw-rounded-lg">
            <v-icon color="green" size="32" class="tw-mb-2">mdi-qrcode</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ medicalSummary.pa_codes || 0 }}</p>
            <p class="tw-text-sm tw-text-gray-600">PA Codes Used</p>
          </div>
          <div class="tw-text-center tw-p-4 tw-bg-orange-50 tw-rounded-lg">
            <v-icon color="orange" size="32" class="tw-mb-2">mdi-calendar-clock</v-icon>
            <p class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ medicalSummary.last_visit || 'N/A' }}</p>
            <p class="tw-text-sm tw-text-gray-600">Last Visit</p>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { enrolleeAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
  enrollee: {
    type: Object,
    required: true
  },
  facility: {
    type: Object,
    required: true
  }
});

const { error } = useToast();

// Reactive data
const recentActivity = ref([]);
const medicalSummary = ref({});

// Methods
const loadRecentActivity = async () => {
  try {
    const response = await enrolleeAPI.getActivity(props.enrollee.id);
    if (response.data.success) {
      recentActivity.value = response.data.data.slice(0, 5); // Show last 5 activities
    }
  } catch (err) {
    console.error('Failed to load recent activity:', err);
  }
};

const loadMedicalSummary = async () => {
  try {
    const response = await enrolleeAPI.getMedicalSummary(props.enrollee.id);
    if (response.data.success) {
      medicalSummary.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load medical summary:', err);
  }
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
};

const formatDateTime = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};



const getStatusText = (status) => {
  return status;
};

const getLevelOfCareColor = (level) => {
  switch (level) {
    case 'Primary': return 'green';
    case 'Secondary': return 'orange';
    case 'Tertiary': return 'red';
    default: return 'grey';
  }
};

const getActivityColor = (type) => {
  switch (type) {
    case 'referral': return 'blue';
    case 'pa_code': return 'green';
    case 'enrollment': return 'purple';
    case 'payment': return 'orange';
    default: return 'grey';
  }
};

// Lifecycle
onMounted(() => {
  loadRecentActivity();
  loadMedicalSummary();
});
</script>
