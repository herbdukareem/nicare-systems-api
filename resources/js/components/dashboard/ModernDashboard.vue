<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header with Animation -->
      <div class="tw-animate-fade-in-up">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <div>
            <h1 class="tw-text-3xl tw-font-bold tw-bg-gradient-to-r tw-from-blue-600 tw-to-purple-600 tw-bg-clip-text tw-text-transparent">
              Dashboard
            </h1>
            <p class="tw-text-gray-600 tw-mt-1">Welcome back, {{ userName }}! Here's your overview.</p>
          </div>
          <div class="tw-flex tw-space-x-3">
            <v-btn 
              color="primary" 
              variant="outlined" 
              prepend-icon="mdi-download"
              class="tw-transition-all tw-duration-300 hover:tw-scale-105"
            >
              Export Data
            </v-btn>
            <v-btn 
              color="primary" 
              prepend-icon="mdi-plus"
              class="tw-transition-all tw-duration-300 hover:tw-scale-105 tw-shadow-lg"
            >
              Add Enrollee
            </v-btn>
          </div>
        </div>
        
        <!-- Quick Stats Bar -->
        <div class="tw-bg-gradient-to-r tw-from-blue-50 tw-to-purple-50 tw-rounded-xl tw-p-4 tw-border tw-border-blue-100">
          <div class="tw-flex tw-items-center tw-justify-between tw-text-sm">
            <div class="tw-flex tw-items-center tw-space-x-6">
              <div class="tw-flex tw-items-center tw-space-x-2">
                <div class="tw-w-3 tw-h-3 tw-bg-green-500 tw-rounded-full tw-animate-pulse"></div>
                <span class="tw-text-gray-600">System Online</span>
              </div>
              <div class="tw-text-gray-600">
                Last updated: {{ lastUpdated }}
              </div>
            </div>
            <div class="tw-flex tw-items-center tw-space-x-4 tw-text-gray-600">
              <span>{{ currentTime }}</span>
              <v-btn 
                icon 
                size="small" 
                @click="refreshData"
                :loading="refreshing"
                class="tw-transition-all tw-duration-300 hover:tw-rotate-180"
              >
                <v-icon size="16">mdi-refresh</v-icon>
              </v-btn>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-py-20">
        <div class="tw-text-center">
          <div class="tw-relative">
            <v-progress-circular indeterminate color="primary" size="64" width="4" />
            <div class="tw-absolute tw-inset-0 tw-flex tw-items-center tw-justify-center">
              <v-icon size="24" color="primary" class="tw-animate-pulse">mdi-chart-line</v-icon>
            </div>
          </div>
          <p class="tw-text-gray-600 tw-mt-6 tw-text-lg">Loading dashboard data...</p>
          <p class="tw-text-gray-400 tw-text-sm tw-mt-2">This may take a few moments</p>
        </div>
      </div>

      <!-- Dashboard Content -->
      <div v-else class="tw-space-y-8">
        <!-- Statistics Cards with Staggered Animation -->
        <div class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4 lg:tw-gap-6">
          <div
            v-for="(stat, index) in statsCards"
            :key="stat.title"
            :class="[
              'tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-4 lg:tw-p-6 tw-border tw-border-gray-100 tw-transition-all tw-duration-500 tw-transform hover:tw-scale-105 hover:tw-shadow-xl tw-min-h-[120px] tw-flex tw-flex-col tw-justify-between',
              `tw-animate-fade-in-up-delay-${index + 1}`
            ]"
          >
            <div class="tw-flex tw-items-start tw-justify-between tw-h-full">
              <div class="tw-flex-1 tw-min-w-0">
                <p class="tw-text-sm tw-font-medium tw-text-gray-600 tw-mb-2">{{ stat.title }}</p>
                <p class="tw-text-2xl lg:tw-text-3xl tw-font-bold tw-text-gray-900 tw-mb-2">
                  {{ stat.value }}
                </p>
                <div class="tw-flex tw-items-center">
                  <span
                    :class="[
                      'tw-text-xs lg:tw-text-sm tw-font-medium tw-flex tw-items-center',
                      stat.change >= 0 ? 'tw-text-green-600' : 'tw-text-red-600'
                    ]"
                  >
                    <v-icon
                      :size="14"
                      :class="stat.change >= 0 ? 'tw-text-green-600' : 'tw-text-red-600'"
                    >
                      {{ stat.change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down' }}
                    </v-icon>
                    {{ Math.abs(stat.change) }}%
                  </span>
                  <span class="tw-text-gray-500 tw-text-xs lg:tw-text-sm tw-ml-1">vs last month</span>
                </div>
              </div>
              <div :class="[
                'tw-p-3 lg:tw-p-4 tw-rounded-2xl tw-transition-all tw-duration-300 tw-flex-shrink-0',
                stat.bgColor
              ]">
                <v-icon :size="28" :color="stat.iconColor">{{ stat.icon }}</v-icon>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-4 lg:tw-gap-8">
          <!-- Enrollment Trend Chart -->
          <div class="lg:tw-col-span-2 tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-4 lg:tw-p-6 tw-border tw-border-gray-100 tw-animate-fade-in-left">
            <div class="tw-flex tw-items-center tw-justify-between tw-mb-6">
              <div>
                <h3 class="tw-text-xl tw-font-bold tw-text-gray-900">Enrollment Trend</h3>
                <p class="tw-text-gray-600 tw-text-sm tw-mt-1">Monthly enrollment statistics</p>
              </div>
              <v-btn-toggle v-model="chartPeriod" mandatory class="tw-bg-gray-100 tw-rounded-lg">
                <v-btn size="small" value="6m" class="tw-text-xs">6M</v-btn>
                <v-btn size="small" value="1y" class="tw-text-xs">1Y</v-btn>
                <v-btn size="small" value="all" class="tw-text-xs">All</v-btn>
              </v-btn-toggle>
            </div>
            <div class="tw-h-80">
              <LineChart :data="enrollmentChartData" :height="320" />
            </div>
          </div>

          <!-- Enrollee Distribution -->
          <div class="tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-4 lg:tw-p-6 tw-border tw-border-gray-100 tw-animate-fade-in-right">
            <div class="tw-mb-6">
              <h3 class="tw-text-xl tw-font-bold tw-text-gray-900">Distribution</h3>
              <p class="tw-text-gray-600 tw-text-sm tw-mt-1">By gender and type</p>
            </div>
            <div class="tw-h-80">
              <DoughnutChart :data="distributionChartData" :height="320" />
            </div>
          </div>
        </div>

        <!-- Facilities and Recent Activity -->
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-4 lg:tw-gap-8">
          <!-- Enrollees by LGA -->
          <div class="tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-4 lg:tw-p-6 tw-border tw-border-gray-100 tw-animate-fade-in-up">
            <div class="tw-mb-6">
              <h3 class="tw-text-xl tw-font-bold tw-text-gray-900">Enrollees by LGA</h3>
              <p class="tw-text-gray-600 tw-text-sm tw-mt-1">Enrollee distribution across local government areas</p>
            </div>
            <div class="tw-h-80">
              <BarChart :data="facilitiesChartData" :height="320" />
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="tw-bg-white tw-rounded-2xl tw-shadow-lg tw-p-4 lg:tw-p-6 tw-border tw-border-gray-100 tw-animate-fade-in-up">
            <div class="tw-flex tw-items-center tw-justify-between tw-mb-6">
              <div>
                <h3 class="tw-text-xl tw-font-bold tw-text-gray-900">Recent Activity</h3>
                <p class="tw-text-gray-600 tw-text-sm tw-mt-1">Latest enrollments and updates</p>
              </div>
              <v-btn variant="text" color="primary" size="small">
                View All
                <v-icon right size="16">mdi-arrow-right</v-icon>
              </v-btn>
            </div>
            
            <div class="tw-space-y-4 tw-max-h-80 tw-overflow-y-auto">
              <div 
                v-for="(activity, index) in recentActivities" 
                :key="activity.id"
                :class="[
                  'tw-flex tw-items-center tw-space-x-4 tw-p-4 tw-rounded-xl tw-border tw-border-gray-100 tw-transition-all tw-duration-300 hover:tw-shadow-md hover:tw-border-blue-200',
                  `tw-animate-fade-in-up-delay-${index + 1}`
                ]"
              >
                <div :class="[
                  'tw-w-10 tw-h-10 tw-rounded-full tw-flex tw-items-center tw-justify-center',
                  activity.type === 'enrollment' ? 'tw-bg-green-100' : 
                  activity.type === 'update' ? 'tw-bg-blue-100' : 'tw-bg-orange-100'
                ]">
                  <v-icon 
                    :size="20" 
                    :color="activity.type === 'enrollment' ? 'green' : 
                           activity.type === 'update' ? 'blue' : 'orange'"
                  >
                    {{ activity.icon }}
                  </v-icon>
                </div>
                <div class="tw-flex-1 tw-min-w-0">
                  <p class="tw-text-sm tw-font-medium tw-text-gray-900 tw-truncate">
                    {{ activity.title }}
                  </p>
                  <p class="tw-text-xs tw-text-gray-500 tw-mt-1">
                    {{ activity.description }}
                  </p>
                </div>
                <div class="tw-text-xs tw-text-gray-400">
                  {{ activity.time }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../../stores/auth';
import AdminLayout from '../layout/AdminLayout.vue';
import LineChart from '../charts/LineChart.vue';
import DoughnutChart from '../charts/DoughnutChart.vue';
import BarChart from '../charts/BarChart.vue';
import { dashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const authStore = useAuthStore();
const { success, error } = useToast();

// Reactive data
const loading = ref(true);
const refreshing = ref(false);
const chartPeriod = ref('6m');
const currentTime = ref(new Date().toLocaleTimeString());
const lastUpdated = ref(new Date().toLocaleString());

// Computed properties
const userName = computed(() => authStore.userName || 'User');

// Dashboard data
const statsCards = ref([]);
const enrollmentChartData = ref({});
const distributionChartData = ref({});
const facilitiesChartData = ref({});
const recentActivities = ref([]);

// API Methods
const loadDashboardData = async () => {
  try {
    loading.value = true;

    // Load overview stats
    const overviewResponse = await dashboardAPI.getOverview();
    if (overviewResponse.data.success) {
      const data = overviewResponse.data.data;
      statsCards.value = [
        {
          title: data.totalEnrollees.title,
          value: data.totalEnrollees.value.toLocaleString(),
          change: data.totalEnrollees.change,
          icon: data.totalEnrollees.icon,
          iconColor: data.totalEnrollees.color,
          bgColor: `tw-bg-${data.totalEnrollees.color}-50`
        },
        {
          title: data.activeEnrollees.title,
          value: data.activeEnrollees.value.toLocaleString(),
          change: data.activeEnrollees.change,
          icon: data.activeEnrollees.icon,
          iconColor: data.activeEnrollees.color,
          bgColor: `tw-bg-${data.activeEnrollees.color}-50`
        },
        {
          title: data.pendingApplications.title,
          value: data.pendingApplications.value.toLocaleString(),
          change: data.pendingApplications.change,
          icon: data.pendingApplications.icon,
          iconColor: data.pendingApplications.color,
          bgColor: `tw-bg-${data.pendingApplications.color}-50`
        },
        {
          title: data.totalFacilities.title,
          value: data.totalFacilities.value.toLocaleString(),
          change: data.totalFacilities.change,
          icon: data.totalFacilities.icon,
          iconColor: data.totalFacilities.color,
          bgColor: `tw-bg-${data.totalFacilities.color}-50`
        }
      ];
    }

    // Load chart data
    const chartResponse = await dashboardAPI.getChartData();
    if (chartResponse.data.success) {
      const chartData = chartResponse.data.data;

      // Enrollment trend chart
      enrollmentChartData.value = {
        labels: chartData.enrollmentTrend?.map(item => item.month) || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
          label: 'New Enrollments',
          data: chartData.enrollmentTrend?.map(item => item.enrollees) || [1200, 1900, 3000, 5000, 2000, 3000],
          borderColor: 'rgb(59, 130, 246)',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          fill: true,
          tension: 0.4
        }]
      };

      // Gender distribution chart
      distributionChartData.value = {
        labels: chartData.genderDistribution?.map(item => item.label) || ['Male', 'Female', 'Individual', 'Family'],
        datasets: [{
          data: chartData.genderDistribution?.map(item => item.value) || [45, 55, 30, 70],
          backgroundColor: [
            'rgba(59, 130, 246, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(34, 197, 94, 0.8)',
            'rgba(251, 146, 60, 0.8)'
          ],
          borderWidth: 0
        }]
      };

      // Enrollees by LGA chart (updated to use real data)
      facilitiesChartData.value = {
        labels: chartData.enrolleesByLga?.map(item => item.lga) || ['Abuja Municipal', 'Gwagwalada', 'Kuje', 'Bwari', 'Kwali', 'Abaji'],
        datasets: [{
          label: 'Enrollees',
          data: chartData.enrolleesByLga?.map(item => item.enrollees) || [2500, 1800, 1200, 980, 750, 450],
          backgroundColor: 'rgba(59, 130, 246, 0.8)',
          borderRadius: 8
        }]
      };
    }

    // Load recent activities
    const activitiesResponse = await dashboardAPI.getRecentActivities();
    if (activitiesResponse.data.success) {
      recentActivities.value = activitiesResponse.data.data;
    }

    lastUpdated.value = new Date().toLocaleString();
  } catch (err) {
    console.error('Failed to load dashboard data:', err);
    error('Failed to load dashboard data');
  } finally {
    loading.value = false;
  }
};

// Methods
const refreshData = async () => {
  refreshing.value = true;
  try {
    await loadDashboardData();
    success('Dashboard data refreshed successfully');
  } catch (err) {
    error('Failed to refresh dashboard data');
  } finally {
    refreshing.value = false;
  }
};

// Lifecycle
let timeInterval = null;

onMounted(async () => {
  await loadDashboardData();

  // Update time every second
  timeInterval = setInterval(() => {
    currentTime.value = new Date().toLocaleTimeString();
  }, 1000);
});

onUnmounted(() => {
  if (timeInterval) {
    clearInterval(timeInterval);
  }
});
</script>

<style scoped>
/* Custom animations */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeInRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.tw-animate-fade-in-up {
  animation: fadeInUp 0.6s ease-out;
}

.tw-animate-fade-in-left {
  animation: fadeInLeft 0.8s ease-out;
}

.tw-animate-fade-in-right {
  animation: fadeInRight 0.8s ease-out;
}

.tw-animate-fade-in-up-delay-1 {
  animation: fadeInUp 0.6s ease-out 0.1s both;
}

.tw-animate-fade-in-up-delay-2 {
  animation: fadeInUp 0.6s ease-out 0.2s both;
}

.tw-animate-fade-in-up-delay-3 {
  animation: fadeInUp 0.6s ease-out 0.3s both;
}

.tw-animate-fade-in-up-delay-4 {
  animation: fadeInUp 0.6s ease-out 0.4s both;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
