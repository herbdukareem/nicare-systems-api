<template>
  <div class="tw-max-w-7xl tw-mx-auto tw-p-6">
    <!-- Header -->
    <div class="tw-mb-8">
      <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900 tw-mb-2">
        Pre-Authorization System (PAS)
      </h1>
      <p class="tw-text-lg tw-text-gray-600">
        Choose a workflow to get started with your healthcare authorization process
      </p>
    </div>

    <!-- Workflow Cards -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-8 tw-mb-8">
      <!-- Create Referral -->
      <v-card 
        class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-xl tw-hover:scale-105"
        @click="navigateToReferralCreation"
      >
        <div class="tw-p-6 tw-text-center">
          <div class="tw-mb-4">
            <v-icon 
              size="64" 
              color="blue"
              class="tw-mb-2"
            >
              mdi-file-document-plus
            </v-icon>
          </div>
          <h3 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-2">
            Create Referral Request
          </h3>
          <p class="tw-text-gray-600 tw-mb-4">
            Submit a new referral request for patient authorization to receive specialized care
          </p>
          <v-btn 
            color="blue" 
            variant="flat"
            size="large"
            class="tw-w-full"
          >
            <v-icon left>mdi-plus</v-icon>
            Start Referral
          </v-btn>
        </div>
      </v-card>

      <!-- Generate PA Code -->
      <v-card 
        class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-xl tw-hover:scale-105"
        @click="navigateToPACodeGeneration"
      >
        <div class="tw-p-6 tw-text-center">
          <div class="tw-mb-4">
            <v-icon 
              size="64" 
              color="green"
              class="tw-mb-2"
            >
              mdi-qrcode
            </v-icon>
          </div>
          <h3 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-2">
            Generate PA Code
          </h3>
          <p class="tw-text-gray-600 tw-mb-4">
            Generate a Pre-Authorization code from an approved referral for service delivery
          </p>
          <v-btn 
            color="green" 
            variant="flat"
            size="large"
            class="tw-w-full"
          >
            <v-icon left>mdi-qrcode</v-icon>
            Generate Code
          </v-btn>
        </div>
      </v-card>

      <!-- Modify Referral -->
      <v-card 
        class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-xl tw-hover:scale-105"
        @click="navigateToModifyReferral"
      >
        <div class="tw-p-6 tw-text-center">
          <div class="tw-mb-4">
            <v-icon 
              size="64" 
              color="orange"
              class="tw-mb-2"
            >
              mdi-file-edit
            </v-icon>
          </div>
          <h3 class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-2">
            Modify Referral Service
          </h3>
          <p class="tw-text-gray-600 tw-mb-4">
            Modify the service details of an existing pending referral request
          </p>
          <v-btn 
            color="orange" 
            variant="flat"
            size="large"
            class="tw-w-full"
          >
            <v-icon left>mdi-pencil</v-icon>
            Modify Referral
          </v-btn>
        </div>
      </v-card>
    </div>

    <!-- Quick Stats -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-6 tw-mb-8">
      <v-card class="tw-text-center tw-p-4">
        <div class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ stats.totalReferrals }}</div>
        <div class="tw-text-sm tw-text-gray-600">Total Referrals</div>
      </v-card>
      
      <v-card class="tw-text-center tw-p-4">
        <div class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ stats.pendingReferrals }}</div>
        <div class="tw-text-sm tw-text-gray-600">Pending Referrals</div>
      </v-card>
      
      <v-card class="tw-text-center tw-p-4">
        <div class="tw-text-2xl tw-font-bold tw-text-green-600">{{ stats.approvedReferrals }}</div>
        <div class="tw-text-sm tw-text-gray-600">Approved Referrals</div>
      </v-card>
      
      <v-card class="tw-text-center tw-p-4">
        <div class="tw-text-2xl tw-font-bold tw-text-purple-600">{{ stats.totalPACodes }}</div>
        <div class="tw-text-sm tw-text-gray-600">PA Codes Generated</div>
      </v-card>
    </div>

    <!-- Recent Activity -->
    <v-card>
      <v-card-title class="tw-bg-gray-50">
        <v-icon left>mdi-clock-outline</v-icon>
        Recent Activity
      </v-card-title>
      <v-card-text>
        <div v-if="recentActivity.length === 0" class="tw-text-center tw-py-8 tw-text-gray-500">
          No recent activity
        </div>
        <div v-else class="tw-space-y-3">
          <div 
            v-for="activity in recentActivity" 
            :key="activity.id"
            class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-border tw-border-gray-200 tw-rounded"
          >
            <div class="tw-flex tw-items-center tw-space-x-3">
              <v-icon :color="getActivityColor(activity.type)">
                {{ getActivityIcon(activity.type) }}
              </v-icon>
              <div>
                <div class="tw-font-medium">{{ activity.description }}</div>
                <div class="tw-text-sm tw-text-gray-500">{{ formatDate(activity.created_at) }}</div>
              </div>
            </div>
            <v-chip 
              size="small" 
              :color="getActivityColor(activity.type)"
              variant="outlined"
            >
              {{ activity.type }}
            </v-chip>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { pasAPI } from '../../utils/api.js';

const router = useRouter();

// Data
const stats = ref({
  totalReferrals: 0,
  pendingReferrals: 0,
  approvedReferrals: 0,
  totalPACodes: 0
});

const recentActivity = ref([]);

// Navigation methods
const navigateToReferralCreation = () => {
  router.push('/pas/create-referral');
};

const navigateToPACodeGeneration = () => {
  router.push('/pas/generate-pa-code');
};

const navigateToModifyReferral = () => {
  router.push('/pas/modify-referral');
};

// Utility methods
const getActivityColor = (type) => {
  switch (type?.toLowerCase()) {
    case 'referral': return 'blue';
    case 'pa_code': return 'green';
    case 'modification': return 'orange';
    default: return 'gray';
  }
};

const getActivityIcon = (type) => {
  switch (type?.toLowerCase()) {
    case 'referral': return 'mdi-file-document-plus';
    case 'pa_code': return 'mdi-qrcode';
    case 'modification': return 'mdi-file-edit';
    default: return 'mdi-information';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString();
};

// Load dashboard data
const loadDashboardData = async () => {
  try {
    // Load statistics
    const [referralStats, paCodeStats] = await Promise.all([
      pasAPI.getReferrals({ per_page: 1 }),
      pasAPI.getPACodes({ per_page: 1 })
    ]);

    // Update stats (you may need to adjust based on your API response structure)
    stats.value = {
      totalReferrals: referralStats.data.total || 0,
      pendingReferrals: 0, // You may need a separate API call for this
      approvedReferrals: 0, // You may need a separate API call for this
      totalPACodes: paCodeStats.data.total || 0
    };

    // Load recent activity (you may need to implement this API endpoint)
    // const activityResponse = await pasAPI.getRecentActivity();
    // recentActivity.value = activityResponse.data.data || [];
  } catch (error) {
    console.error('Failed to load dashboard data:', error);
  }
};

onMounted(() => {
  loadDashboardData();
});
</script>
