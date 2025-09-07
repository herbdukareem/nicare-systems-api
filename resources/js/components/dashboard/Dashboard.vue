<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Welcome Section -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900">
              Welcome back, {{ userName }}!
            </h2>
            <p class="tw-text-gray-600 tw-mt-1">
              Here's what's happening with your enrollees today.
            </p>
          </div>
          <div class="tw-flex tw-items-center tw-space-x-4">
            <div class="tw-text-right">
              <p class="tw-text-sm tw-text-gray-500">Last updated</p>
              <p class="tw-text-sm tw-font-medium tw-text-gray-900">
                {{ new Date().toLocaleString() }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-py-12">
        <div class="tw-text-center">
          <v-progress-circular indeterminate color="primary" size="48" />
          <p class="tw-text-gray-600 tw-mt-4">Loading dashboard data...</p>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div v-else class="tw-grid tw-grid-cols-1 tw-md:grid-cols-2 tw-lg:grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Enrollees</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ stats.totalEnrollees?.toLocaleString() || '0' }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Enrollees</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ stats.activeEnrollees?.toLocaleString() || '0' }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-yellow-100">
              <v-icon color="orange" size="24">mdi-clock-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Pending Approval</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ stats.pendingEnrollees?.toLocaleString() || '0' }}
              </p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-hospital-building</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Facilities</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">
                {{ stats.totalFacilities?.toLocaleString() || '0' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Tabs -->
      <div v-if="!loading" class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-tabs v-model="activeTab" color="primary" class="tw-border-b tw-border-gray-200">
          <v-tab value="enrollees">
            <v-icon left>mdi-account-group</v-icon>
            Enrollees Statistics
          </v-tab>
          <v-tab value="facilities">
            <v-icon left>mdi-hospital-building</v-icon>
            Facility Statistics
          </v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab" class="tw-p-6">
          <!-- Enrollees Tab -->
          <v-tabs-window-item value="enrollees">
            <EnrolleesTab :stats="enrolleeStats" :loading="loading" />
          </v-tabs-window-item>

          <!-- Facilities Tab -->
          <v-tabs-window-item value="facilities">
            <FacilitiesTab :stats="facilityStats" :loading="loading" />
          </v-tabs-window-item>
        </v-tabs-window>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { dashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';
import AdminLayout from '../layout/AdminLayout.vue';
import EnrolleesTab from './tabs/EnrolleesTab.vue';
import FacilitiesTab from './tabs/FacilitiesTab.vue';

const authStore = useAuthStore();
const { error } = useToast();
const activeTab = ref('enrollees');
const loading = ref(false);

// Reactive data from API
const stats = ref({
  totalEnrollees: 0,
  activeEnrollees: 0,
  pendingEnrollees: 0,
  totalFacilities: 0,
});

const enrolleeStats = ref({
  byGender: [],
  byType: [],
  byBenefactor: [],
  monthlyTrend: [],
});

const facilityStats = ref({
  byLga: [],
  byWard: [],
  byFacility: [],
  topFacilities: [],
});

// Computed properties
const userName = computed(() => authStore.userName);

// Methods
const loadDashboardData = async () => {
  loading.value = true;
  try {
    // Fetch overview statistics
    const overviewResponse = await dashboardAPI.getOverview();
    if (overviewResponse.data.success) {
      stats.value = overviewResponse.data.data;
    }

    // Fetch enrollee statistics
    const enrolleeResponse = await dashboardAPI.getEnrolleeStats();
    if (enrolleeResponse.data.success) {
      enrolleeStats.value = enrolleeResponse.data.data;
    }

    // Fetch facility statistics
    const facilityResponse = await dashboardAPI.getFacilityStats();
    if (facilityResponse.data.success) {
      facilityStats.value = facilityResponse.data.data;
    }
  } catch (err) {
    console.error('Failed to load dashboard data:', err);
    error('Failed to load dashboard data. Please try again.');
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadDashboardData();
});
</script>

<style scoped>
/* Additional custom styles */
</style>