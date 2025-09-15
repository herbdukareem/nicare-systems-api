<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Security Logs</h1>
          <p class="tw-text-gray-600 tw-mt-1">Monitor and manage security events</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="success" 
            prepend-icon="mdi-check-all"
            @click="showBulkResolveDialog = true"
            :disabled="selectedLogs.length === 0"
          >
            Bulk Resolve ({{ selectedLogs.length }})
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-refresh"
            @click="fetchLogs"
            :loading="loading"
          >
            Refresh
          </v-btn>
        </div>
      </div>

      <!-- Filters -->
      <v-card class="tw-border tw-border-gray-200">
        <v-card-text>
          <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-4 tw-gap-4">
            <v-text-field
              v-model="filters.search"
              label="Search logs..."
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-magnify"
              clearable
            />
            
            <v-select
              v-model="filters.type"
              label="Event Type"
              variant="outlined"
              density="compact"
              :items="eventTypes"
              clearable
            />
            
            <v-select
              v-model="filters.severity"
              label="Severity"
              variant="outlined"
              density="compact"
              :items="severityOptions"
              clearable
            />
            
            <v-select
              v-model="filters.resolved"
              label="Status"
              variant="outlined"
              density="compact"
              :items="statusOptions"
              clearable
            />
          </div>
          
          <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4 tw-mt-4">
            <v-text-field
              v-model="filters.date_from"
              label="From Date"
              variant="outlined"
              density="compact"
              type="date"
            />
            
            <v-text-field
              v-model="filters.date_to"
              label="To Date"
              variant="outlined"
              density="compact"
              type="date"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Security Logs Table -->
      <v-card class="tw-border tw-border-gray-200">
        <v-data-table
          v-model="selectedLogs"
          :headers="headers"
          :items="logs"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :server-items-length="totalLogs"
          show-select
          item-value="id"
          @update:options="handleTableUpdate"
        >
          <template v-slot:item.type="{ item }">
            <div class="tw-flex tw-items-center tw-space-x-2">
              <v-icon :color="getSeverityColor(item.severity)" size="16">
                {{ getEventIcon(item.type) }}
              </v-icon>
              <span class="tw-text-sm">{{ item.type_label }}</span>
            </div>
          </template>

          <template v-slot:item.severity="{ item }">
            <v-chip
              :color="item.severity_color"
              size="small"
              variant="flat"
            >
              {{ item.severity_label }}
            </v-chip>
          </template>

          <template v-slot:item.ip_address="{ item }">
            <span class="tw-font-mono tw-text-sm">{{ item.ip_address }}</span>
          </template>

          <template v-slot:item.user="{ item }">
            <div v-if="item.user">
              <v-chip size="small" variant="outlined">
                {{ item.user.name }}
              </v-chip>
            </div>
            <span v-else class="tw-text-gray-500">-</span>
          </template>

          <template v-slot:item.created_at="{ item }">
            <span class="tw-text-sm">{{ formatDate(item.created_at) }}</span>
          </template>

          <template v-slot:item.resolved_at="{ item }">
            <div v-if="item.resolved_at">
              <v-chip color="success" size="small" variant="flat">
                Resolved
              </v-chip>
              <div class="tw-text-xs tw-text-gray-500 tw-mt-1">
                {{ formatDate(item.resolved_at) }}
              </div>
            </div>
            <v-chip v-else color="warning" size="small" variant="flat">
              Open
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-tooltip text="View Details">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    color="primary"
                    @click="viewDetails(item)"
                  >
                    <v-icon size="16">mdi-eye</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>

              <v-tooltip text="Resolve" v-if="!item.resolved_at">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon
                    size="small"
                    variant="text"
                    color="success"
                    @click="resolveLog(item)"
                  >
                    <v-icon size="16">mdi-check</v-icon>
                  </v-btn>
                </template>
              </v-tooltip>
            </div>
          </template>
        </v-data-table>
      </v-card>

      <!-- Log Details Dialog -->
      <v-dialog v-model="showDetailsDialog" max-width="800px">
        <v-card v-if="selectedLog">
          <v-card-title>
            <span class="tw-text-xl tw-font-semibold">Security Log Details</span>
          </v-card-title>
          <v-card-text>
            <div class="tw-space-y-4">
              <div class="tw-grid tw-grid-cols-2 tw-gap-4">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Event Type</label>
                  <p class="tw-text-gray-900">{{ selectedLog.type_label }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Severity</label>
                  <v-chip :color="selectedLog.severity_color" size="small" variant="flat">
                    {{ selectedLog.severity_label }}
                  </v-chip>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">IP Address</label>
                  <p class="tw-font-mono tw-text-gray-900">{{ selectedLog.ip_address }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">User</label>
                  <p class="tw-text-gray-900">{{ selectedLog.user?.name || 'N/A' }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Date/Time</label>
                  <p class="tw-text-gray-900">{{ formatDate(selectedLog.created_at) }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-600">Status</label>
                  <v-chip 
                    :color="selectedLog.resolved_at ? 'success' : 'warning'" 
                    size="small" 
                    variant="flat"
                  >
                    {{ selectedLog.resolved_at ? 'Resolved' : 'Open' }}
                  </v-chip>
                </div>
              </div>
              
              <div>
                <label class="tw-text-sm tw-font-medium tw-text-gray-600">URL</label>
                <p class="tw-text-gray-900 tw-break-all">{{ selectedLog.url }}</p>
              </div>
              
              <div>
                <label class="tw-text-sm tw-font-medium tw-text-gray-600">User Agent</label>
                <p class="tw-text-gray-900 tw-break-all">{{ selectedLog.user_agent || 'N/A' }}</p>
              </div>
              
              <div v-if="selectedLog.details">
                <label class="tw-text-sm tw-font-medium tw-text-gray-600">Additional Details</label>
                <pre class="tw-bg-gray-100 tw-p-3 tw-rounded tw-text-sm tw-overflow-auto">{{ JSON.stringify(selectedLog.details, null, 2) }}</pre>
              </div>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="showDetailsDialog = false">Close</v-btn>
            <v-btn 
              v-if="!selectedLog.resolved_at"
              color="success" 
              @click="resolveLog(selectedLog)"
            >
              Resolve
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Bulk Resolve Dialog -->
      <v-dialog v-model="showBulkResolveDialog" max-width="500px">
        <v-card>
          <v-card-title>
            <span class="tw-text-xl tw-font-semibold">
              Bulk Resolve ({{ selectedLogs.length }} logs)
            </span>
          </v-card-title>
          <v-card-text>
            <v-textarea
              v-model="bulkResolutionNotes"
              label="Resolution Notes (Optional)"
              variant="outlined"
              rows="3"
              placeholder="Add notes about how these issues were resolved..."
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="showBulkResolveDialog = false">Cancel</v-btn>
            <v-btn 
              color="success" 
              @click="bulkResolve"
              :loading="resolving"
            >
              Resolve All
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { securityAPI } from '../../utils/api';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const resolving = ref(false);
const logs = ref([]);
const selectedLogs = ref([]);
const selectedLog = ref(null);
const totalLogs = ref(0);
const itemsPerPage = ref(15);
const currentPage = ref(1);
const showDetailsDialog = ref(false);
const showBulkResolveDialog = ref(false);
const bulkResolutionNotes = ref('');

// Filters
const filters = ref({
  search: '',
  type: null,
  severity: null,
  resolved: null,
  date_from: '',
  date_to: '',
});

// Table headers
const headers = [
  { title: 'Event Type', key: 'type', sortable: true },
  { title: 'Severity', key: 'severity', sortable: true },
  { title: 'IP Address', key: 'ip_address', sortable: true },
  { title: 'User', key: 'user', sortable: false },
  { title: 'Date/Time', key: 'created_at', sortable: true },
  { title: 'Status', key: 'resolved_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: 120 },
];

// Options
const eventTypes = [
  { title: 'Failed Login', value: 'failed_login' },
  { title: 'SQL Injection Attempt', value: 'sql_injection_attempt' },
  { title: 'Rate Limit Exceeded', value: 'rate_limit_exceeded' },
  { title: 'Suspicious User Agent', value: 'suspicious_user_agent' },
  { title: 'Rapid Requests', value: 'rapid_requests' },
];

const severityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
];

const statusOptions = [
  { title: 'Open', value: 'false' },
  { title: 'Resolved', value: 'true' },
];

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

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'high':
      return 'error';
    case 'medium':
      return 'warning';
    case 'low':
      return 'info';
    default:
      return 'grey';
  }
};

const getEventIcon = (type) => {
  switch (type) {
    case 'failed_login':
      return 'mdi-login-variant';
    case 'sql_injection_attempt':
      return 'mdi-database-alert';
    case 'rate_limit_exceeded':
      return 'mdi-speedometer';
    case 'suspicious_user_agent':
      return 'mdi-robot';
    case 'rapid_requests':
      return 'mdi-flash';
    default:
      return 'mdi-alert';
  }
};

const fetchLogs = async () => {
  loading.value = true;
  try {
    const params = {
      ...filters.value,
      page: currentPage.value,
      per_page: itemsPerPage.value,
    };

    const response = await securityAPI.getLogs(params);
    if (response?.data?.success) {
      const data = response.data.data;
      logs.value = data.data;
      totalLogs.value = data.total;
    }
  } catch (err) {
    console.error('Failed to fetch security logs:', err);
    error('Failed to fetch security logs');
  } finally {
    loading.value = false;
  }
};

const handleTableUpdate = (options) => {
  currentPage.value = options.page;
  itemsPerPage.value = options.itemsPerPage;
  fetchLogs();
};

const viewDetails = (log) => {
  selectedLog.value = log;
  showDetailsDialog.value = true;
};

const resolveLog = async (log) => {
  try {
    const response = await securityAPI.resolve(log.id, {});
    if (response?.data?.success) {
      success('Security log resolved successfully');
      fetchLogs();
      showDetailsDialog.value = false;
    }
  } catch (err) {
    error('Failed to resolve security log');
    console.error(err);
  }
};

const bulkResolve = async () => {
  resolving.value = true;
  try {
    const response = await securityAPI.bulkResolve({
      log_ids: selectedLogs.value,
      resolution_notes: bulkResolutionNotes.value || undefined,
    });

    if (response?.data?.success) {
      success(`Resolved ${selectedLogs.value.length} security logs successfully`);
      selectedLogs.value = [];
      bulkResolutionNotes.value = '';
      showBulkResolveDialog.value = false;
      fetchLogs();
    }
  } catch (err) {
    error('Failed to resolve security logs');
    console.error(err);
  } finally {
    resolving.value = false;
  }
};

// Watchers
watch(filters, () => {
  currentPage.value = 1;
  fetchLogs();
}, { deep: true });

// Lifecycle
onMounted(() => {
  fetchLogs();
});
</script>

<style scoped>
:deep(.v-data-table) {
  border: 1px solid #e5e7eb;
}

:deep(.v-chip) {
  font-size: 0.75rem;
}

pre {
  white-space: pre-wrap;
  word-wrap: break-word;
}
</style>
