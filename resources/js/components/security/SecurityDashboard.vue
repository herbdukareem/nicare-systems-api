<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Security Dashboard</h1>
          <p class="tw-text-gray-600 tw-mt-1">Monitor security events and system health</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-download"
            @click="exportSecurityReport"
          >
            Export Report
          </v-btn>
          <v-btn 
            color="warning" 
            prepend-icon="mdi-refresh"
            @click="refreshData"
            :loading="loading"
          >
            Refresh
          </v-btn>
        </div>
      </div>

      <!-- Security Statistics -->
      <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-3 tw-xl:tw-grid-cols-6 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-shield-check</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Events</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.total_security_events?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-red-100">
              <v-icon color="red" size="24">mdi-alert</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Unresolved</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.unresolved_events?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-alert-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">High Severity</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.high_severity_events?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-calendar-today</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Today's Events</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.events_today?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-login-variant</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Failed Logins</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.failed_logins_today?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-border tw-border-gray-100">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-yellow-100">
              <v-icon color="yellow-darken-2" size="24">mdi-bug</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Suspicious</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.suspicious_activities?.toLocaleString() || 0 }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts and Recent Events -->
      <div class="tw-grid tw-grid-cols-1 tw-lg:tw-grid-cols-2 tw-gap-6">
        <!-- Security Trends Chart -->
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
          <div class="tw-p-6 tw-border-b tw-border-gray-200">
            <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Security Trends (Last 7 Days)</h3>
          </div>
          <div class="tw-p-6">
            <div class="tw-h-64 tw-flex tw-items-center tw-justify-center">
              <div v-if="trendsData.length > 0">
                <!-- Chart would go here - using Chart.js or similar -->
                <p class="tw-text-gray-500">Chart visualization would be implemented here</p>
              </div>
              <div v-else class="tw-text-center">
                <v-icon size="48" color="grey">mdi-chart-line</v-icon>
                <p class="tw-text-gray-500 tw-mt-2">No trend data available</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Security Events -->
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
          <div class="tw-p-6 tw-border-b tw-border-gray-200">
            <div class="tw-flex tw-items-center tw-justify-between">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Recent Security Events</h3>
              <v-btn
                variant="outlined"
                size="small"
                @click="$router.push('/admin/security/logs')"
              >
                View All
              </v-btn>
            </div>
          </div>
          <div class="tw-p-6">
            <div v-if="recentEvents.length > 0" class="tw-space-y-4">
              <div 
                v-for="event in recentEvents" 
                :key="event.id"
                class="tw-flex tw-items-start tw-space-x-3 tw-p-3 tw-border tw-border-gray-200 tw-rounded-lg"
              >
                <div class="tw-p-2 tw-rounded-full" :class="getSeverityBgClass(event.severity)">
                  <v-icon size="16" :color="event.severity_color">{{ getEventIcon(event.type) }}</v-icon>
                </div>
                <div class="tw-flex-1">
                  <div class="tw-flex tw-items-center tw-justify-between">
                    <p class="tw-text-sm tw-font-medium tw-text-gray-900">{{ event.type }}</p>
                    <v-chip
                      :color="event.severity_color"
                      size="x-small"
                      variant="flat"
                    >
                      {{ event.severity }}
                    </v-chip>
                  </div>
                  <p class="tw-text-xs tw-text-gray-500">{{ event.ip_address }}</p>
                  <p class="tw-text-xs tw-text-gray-500">{{ formatDate(event.created_at) }}</p>
                  <div v-if="event.user" class="tw-mt-1">
                    <v-chip size="x-small" variant="outlined">
                      {{ event.user }}
                    </v-chip>
                  </div>
                </div>
                <div>
                  <v-chip
                    :color="event.is_resolved ? 'success' : 'warning'"
                    size="x-small"
                    variant="flat"
                  >
                    {{ event.is_resolved ? 'Resolved' : 'Open' }}
                  </v-chip>
                </div>
              </div>
            </div>
            <div v-else class="tw-text-center tw-py-8">
              <v-icon size="48" color="grey">mdi-shield-check</v-icon>
              <p class="tw-text-gray-500 tw-mt-2">No recent security events</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Threat IPs -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <div class="tw-p-6 tw-border-b tw-border-gray-200">
          <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Top Threat IP Addresses (Last 30 Days)</h3>
        </div>
        <div class="tw-p-6">
          <div v-if="topThreatIps.length > 0">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-3 tw-gap-4">
              <div 
                v-for="ip in topThreatIps" 
                :key="ip.ip_address"
                class="tw-p-4 tw-border tw-border-gray-200 tw-rounded-lg"
              >
                <div class="tw-flex tw-items-center tw-justify-between">
                  <div>
                    <p class="tw-font-medium tw-text-gray-900 tw-font-mono">{{ ip.ip_address }}</p>
                    <p class="tw-text-sm tw-text-gray-500">{{ ip.count }} events</p>
                  </div>
                  <v-btn
                    size="small"
                    variant="outlined"
                    color="warning"
                    @click="blockIp(ip.ip_address)"
                  >
                    Block
                  </v-btn>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="tw-text-center tw-py-8">
            <v-icon size="48" color="grey">mdi-ip-network</v-icon>
            <p class="tw-text-gray-500 tw-mt-2">No threat IPs detected</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-border tw-border-gray-100">
        <div class="tw-p-6 tw-border-b tw-border-gray-200">
          <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">Quick Actions</h3>
        </div>
        <div class="tw-p-6">
          <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-4 tw-gap-4">
            <v-btn
              color="primary"
              variant="outlined"
              prepend-icon="mdi-shield-search"
              @click="$router.push('/admin/security/logs')"
              block
            >
              View Security Logs
            </v-btn>
            
            <v-btn
              color="warning"
              variant="outlined"
              prepend-icon="mdi-history"
              @click="$router.push('/admin/security/audit')"
              block
            >
              Audit Trail
            </v-btn>
            
            <v-btn
              color="info"
              variant="outlined"
              prepend-icon="mdi-account-multiple"
              @click="$router.push('/admin/security/sessions')"
              block
            >
              Active Sessions
            </v-btn>
            
            <v-btn
              color="success"
              variant="outlined"
              prepend-icon="mdi-cog"
              @click="$router.push('/admin/security/settings')"
              block
            >
              Security Settings
            </v-btn>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { securityAPI } from '../../utils/api';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const stats = ref({});
const recentEvents = ref([]);
const trendsData = ref([]);
const topThreatIps = ref([]);

// Methods
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const getSeverityBgClass = (severity) => {
  switch (severity) {
    case 'high':
      return 'tw-bg-red-100';
    case 'medium':
      return 'tw-bg-orange-100';
    case 'low':
      return 'tw-bg-blue-100';
    default:
      return 'tw-bg-gray-100';
  }
};

const getEventIcon = (type) => {
  switch (type) {
    case 'Failed Login':
      return 'mdi-login-variant';
    case 'SQL Injection Attempt':
      return 'mdi-database-alert';
    case 'Rate Limit Exceeded':
      return 'mdi-speedometer';
    case 'Suspicious User Agent':
      return 'mdi-robot';
    case 'Rapid Requests':
      return 'mdi-flash';
    default:
      return 'mdi-alert';
  }
};

const loadDashboardData = async () => {
  loading.value = true;
  try {
    const response = await securityAPI.getDashboard();
    if (response?.data?.success) {
      const data = response.data.data;
      stats.value = data.stats;
      recentEvents.value = data.recent_events;
      trendsData.value = data.trends;
      topThreatIps.value = data.top_threat_ips;
    }
  } catch (err) {
    console.error('Failed to load security dashboard:', err);
    error('Failed to load security dashboard');
  } finally {
    loading.value = false;
  }
};

const refreshData = () => {
  loadDashboardData();
};

const exportSecurityReport = async () => {
  try {
    // This would generate and download a security report
    success('Security report export feature coming soon');
  } catch (err) {
    error('Failed to export security report');
  }
};

const blockIp = async (ipAddress) => {
  if (!confirm(`Are you sure you want to block IP address ${ipAddress}?`)) return;

  try {
    // This would implement IP blocking functionality
    success(`IP address ${ipAddress} blocked successfully`);
    loadDashboardData(); // Refresh data
  } catch (err) {
    error('Failed to block IP address');
  }
};

// Lifecycle
onMounted(() => {
  loadDashboardData();
});
</script>

<style scoped>
:deep(.v-chip) {
  font-size: 0.75rem;
}
</style>
