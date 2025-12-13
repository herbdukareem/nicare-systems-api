<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Header -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between tw-gap-4">
          <div>
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900">Facility Dashboard</h2>
            <p class="tw-text-gray-600 tw-mt-1">Manage your facility operations and requests</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
        <v-card
          v-for="action in quickActions"
          :key="action.title"
          class="tw-cursor-pointer tw-transition-all tw-duration-300 hover:tw-shadow-xl hover:tw-scale-105"
          @click="navigateTo(action.route)"
          elevation="2"
        >
          <v-card-text class="tw-p-6">
            <div class="tw-flex tw-items-start tw-gap-4">
              <div
                class="tw-flex tw-items-center tw-justify-center tw-w-14 tw-h-14 tw-rounded-xl tw-flex-shrink-0"
                :style="{ backgroundColor: action.color + '20' }"
              >
                <v-icon :color="action.color" size="28">{{ action.icon }}</v-icon>
              </div>
              <div class="tw-flex-1">
                <div class="tw-flex tw-items-center tw-gap-2 tw-mb-2">
                  <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ action.title }}</h3>
                  <v-chip
                    v-if="action.badge"
                    :color="action.badgeColor || 'primary'"
                    size="x-small"
                    variant="flat"
                  >
                    {{ action.badge }}
                  </v-chip>
                </div>
                <p class="tw-text-sm tw-text-gray-600">{{ action.description }}</p>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Statistics Overview -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <h3 class="tw-text-xl tw-font-bold tw-text-gray-900 tw-mb-4">Facility Statistics</h3>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4">
          <div class="tw-bg-blue-50 tw-rounded-lg tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <p class="tw-text-sm tw-text-gray-600">Pending Referrals</p>
                <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ stats.pendingReferrals }}</p>
              </div>
              <v-icon color="blue" size="32">mdi-file-send</v-icon>
            </div>
          </div>
          <div class="tw-bg-green-50 tw-rounded-lg tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <p class="tw-text-sm tw-text-gray-600">Active Admissions</p>
                <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ stats.activeAdmissions }}</p>
              </div>
              <v-icon color="green" size="32">mdi-hospital-box</v-icon>
            </div>
          </div>
          <div class="tw-bg-orange-50 tw-rounded-lg tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <p class="tw-text-sm tw-text-gray-600">Pending PA Codes</p>
                <p class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ stats.pendingPACodes }}</p>
              </div>
              <v-icon color="orange" size="32">mdi-shield-check</v-icon>
            </div>
          </div>
          <div class="tw-bg-purple-50 tw-rounded-lg tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between">
              <div>
                <p class="tw-text-sm tw-text-gray-600">Submitted Claims</p>
                <p class="tw-text-2xl tw-font-bold tw-text-purple-600">{{ stats.submittedClaims }}</p>
              </div>
              <v-icon color="purple" size="32">mdi-file-document-plus</v-icon>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const { error: showError } = useToast();

// Quick actions for facility users
const quickActions = ref([
  {
    title: 'Referral Request to PAS',
    description: 'Submit referral requests for your facility to Pre-Authorization System',
    icon: 'mdi-file-send',
    color: '#0885AB',
    route: '/claims/referral-request',
  },
  {
    title: 'Validate UTN',
    description: 'Validate Unique Treatment Numbers for patient admissions',
    icon: 'mdi-shield-check',
    color: '#4CAF50',
    route: '/pas/validate-utn',
  },
  {
    title: 'Admission Management',
    description: 'Manage patient admissions and discharges',
    icon: 'mdi-hospital-box',
    color: '#FF9800',
    route: '/facility/admissions',
  },
  {
    title: 'Request FU-PA Code',
    description: 'Request Follow-up Pre-Authorization codes for additional services',
    icon: 'mdi-file-plus',
    color: '#9C27B0',
    route: '/pas/facility-pa-codes',
  },
  {
    title: 'Submit Claim',
    description: 'Submit claims for reimbursement',
    icon: 'mdi-file-document-plus',
    color: '#2196F3',
    route: '/facility/claims/submit',
  },
]);

const stats = ref({
  pendingReferrals: 0,
  activeAdmissions: 0,
  pendingPACodes: 0,
  submittedClaims: 0,
});

const navigateTo = (route) => {
  router.push(route);
};

onMounted(() => {
  // TODO: Fetch facility statistics from API
  // For now, using placeholder data
});
</script>

