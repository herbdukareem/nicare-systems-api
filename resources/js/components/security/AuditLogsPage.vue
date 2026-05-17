<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Audit Logs" subtitle="Track all system activity and user actions" icon="mdi-clipboard-text-clock" icon-color="primary">
        <v-btn variant="outlined" prepend-icon="mdi-download" :loading="exporting" @click="exportLogs">Export</v-btn>
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="fetchLogs">Refresh</v-btn>
      </AppPageHeader>

      <!-- Stats -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-4">
        <AppStatCard label="Total Events" :value="stats.total" icon="mdi-clipboard-list" color="blue" :loading="statsLoading" />
        <AppStatCard label="Today" :value="stats.today" icon="mdi-calendar-today" color="indigo" :loading="statsLoading" />
        <AppStatCard label="Warnings" :value="stats.warnings" icon="mdi-alert-circle" color="orange" :loading="statsLoading" />
        <AppStatCard label="Critical" :value="stats.critical" icon="mdi-shield-alert" color="red" :loading="statsLoading" />
      </div>

      <!-- Filters -->
      <AppFilterBar :active-count="activeFiltersCount" @clear="clearFilters" :cols="5">
        <v-text-field
          v-model="filters.search"
          label="Search by user, action…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined" density="compact" clearable hide-details
          @input="debounceSearch"
        />
        <v-select
          v-model="filters.event_type"
          label="Event Type"
          :items="eventTypes"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchLogs"
        />
        <v-select
          v-model="filters.severity"
          label="Severity"
          :items="severityOptions"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchLogs"
        />
        <v-text-field
          v-model="filters.date_from"
          label="From Date"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchLogs"
        />
        <v-text-field
          v-model="filters.date_to"
          label="To Date"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchLogs"
        />
      </AppFilterBar>

      <!-- Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <AppDataTable
          :headers="headers"
          :items="logs"
          :loading="loading"
          :items-per-page="pagination.per_page"
          class="tw-rounded-xl"
        >
          <template #item.created_at="{ item }">
            <div class="tw-text-xs">
              <div class="tw-font-medium">{{ formatDate(item.created_at) }}</div>
              <div class="tw-text-gray-400">{{ formatTime(item.created_at) }}</div>
            </div>
          </template>

          <template #item.user="{ item }">
            <div v-if="item.user || item.causer" class="tw-flex tw-items-center tw-gap-2">
              <v-avatar size="28" :color="avatarColor(item.causer?.name || item.user?.name)">
                <span class="tw-text-xs tw-text-white tw-font-bold">{{ initials(item.causer?.name || item.user?.name) }}</span>
              </v-avatar>
              <div>
                <div class="tw-text-sm tw-font-medium">{{ item.causer?.name || item.user?.name || '—' }}</div>
                <div class="tw-text-xs tw-text-gray-400">{{ item.causer?.email || item.user?.email || '' }}</div>
              </div>
            </div>
            <span v-else class="tw-text-gray-400 tw-text-sm">System</span>
          </template>

          <template #item.event="{ item }">
            <div>
              <div class="tw-text-sm tw-font-medium tw-capitalize">{{ formatEvent(item.event || item.description) }}</div>
              <div class="tw-text-xs tw-text-gray-400">{{ item.subject_type ? item.subject_type.split('\\').pop() : '' }}</div>
            </div>
          </template>

          <template #item.properties="{ item }">
            <v-btn
              size="x-small"
              variant="text"
              color="primary"
              @click="openDetail(item)"
            >
              View Details
            </v-btn>
          </template>

          <template #item.severity="{ item }">
            <AppStatusChip :status="item.severity || 'info'" show-icon />
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-clipboard-text-off"
              title="No audit logs found"
              description="No activity records match the current filters."
            />
          </template>

          <template #loading>
            <div class="tw-flex tw-items-center tw-justify-center tw-py-12">
              <v-progress-circular indeterminate color="primary" />
            </div>
          </template>
        </AppDataTable>

        <!-- Pagination -->
        <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-100">
          <span class="tw-text-sm tw-text-gray-500">
            Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total.toLocaleString() }} records
          </span>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="5"
            density="compact"
            @update:model-value="fetchLogs"
          />
        </div>
      </v-card>

      <!-- Detail Dialog -->
      <AppModal v-model="detailDialog" title="Event Details" size="md">
        <div v-if="selectedLog" class="tw-space-y-4">
          <div class="tw-grid tw-grid-cols-2 tw-gap-4">
            <div>
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">Timestamp</p>
              <p class="tw-text-sm tw-font-medium">{{ formatDate(selectedLog.created_at) }} {{ formatTime(selectedLog.created_at) }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">User</p>
              <p class="tw-text-sm tw-font-medium">{{ selectedLog.causer?.name || selectedLog.user?.name || 'System' }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">Action</p>
              <p class="tw-text-sm tw-font-medium tw-capitalize">{{ formatEvent(selectedLog.event || selectedLog.description) }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">Model</p>
              <p class="tw-text-sm tw-font-medium">{{ selectedLog.subject_type?.split('\\').pop() || '—' }}</p>
            </div>
            <div v-if="selectedLog.subject_id">
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">Record ID</p>
              <p class="tw-text-sm tw-font-mono">{{ selectedLog.subject_id }}</p>
            </div>
            <div v-if="selectedLog.ip_address">
              <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-1">IP Address</p>
              <p class="tw-text-sm tw-font-mono">{{ selectedLog.ip_address }}</p>
            </div>
          </div>

          <div v-if="selectedLog.properties && Object.keys(selectedLog.properties).length">
            <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-tracking-wide tw-mb-2">Properties</p>
            <pre class="tw-bg-gray-50 tw-rounded-lg tw-p-3 tw-text-xs tw-overflow-auto tw-max-h-64 tw-border tw-border-gray-200">{{ JSON.stringify(selectedLog.properties, null, 2) }}</pre>
          </div>
        </div>
        <template #actions>
          <v-btn color="primary" variant="flat" @click="detailDialog = false">Close</v-btn>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppStatCard from '../common/AppStatCard.vue';
import AppStatusChip from '../common/AppStatusChip.vue';
import AppFilterBar from '../common/AppFilterBar.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import AppModal from '../common/AppModal.vue';
import AppDataTable from '../common/AppDataTable.vue';
import { securityAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();

const loading = ref(false);
const statsLoading = ref(false);
const exporting = ref(false);
const logs = ref([]);
const detailDialog = ref(false);
const selectedLog = ref(null);

const stats = ref({ total: 0, today: 0, warnings: 0, critical: 0 });
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0, from: 0, to: 0 });

const filters = ref({
  search: '',
  event_type: null,
  severity: null,
  date_from: '',
  date_to: '',
});

const eventTypes = [
  'created', 'updated', 'deleted', 'login', 'logout', 'password_changed',
  'role_assigned', 'permission_changed', 'export', 'import',
];

const severityOptions = ['info', 'low', 'medium', 'high', 'critical'];

const headers = [
  { title: 'Timestamp', key: 'created_at', width: 140 },
  { title: 'User', key: 'user', minWidth: 160 },
  { title: 'Event', key: 'event', minWidth: 160 },
  { title: 'Severity', key: 'severity', width: 110 },
  { title: 'Details', key: 'properties', width: 110, sortable: false },
];

const activeFiltersCount = computed(() => {
  return Object.values(filters.value).filter(v => v !== null && v !== '' && v !== undefined).length;
});

async function fetchLogs() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== null && v !== '')),
    };
    const res = await securityAPI.getAuditTrail(params);
    const data = res.data?.data ?? res.data;
    logs.value = data.data ?? data ?? [];
    if (data.meta || data.current_page) {
      const m = data.meta ?? data;
      pagination.value = {
        current_page: m.current_page ?? 1,
        last_page: m.last_page ?? 1,
        per_page: m.per_page ?? 20,
        total: m.total ?? 0,
        from: m.from ?? 0,
        to: m.to ?? 0,
      };
    }
  } catch {
    showToast('Failed to load audit logs', 'error');
  } finally {
    loading.value = false;
  }
}

async function fetchStats() {
  statsLoading.value = true;
  try {
    const res = await securityAPI.getDashboard();
    const d = res.data?.data ?? res.data;
    stats.value = {
      total: d?.total_events ?? d?.total ?? 0,
      today: d?.today_events ?? d?.today ?? 0,
      warnings: d?.warning_count ?? d?.warnings ?? 0,
      critical: d?.critical_count ?? d?.critical ?? 0,
    };
  } catch {
    // stats not critical
  } finally {
    statsLoading.value = false;
  }
}

function clearFilters() {
  filters.value = { search: '', event_type: null, severity: null, date_from: '', date_to: '' };
  pagination.value.current_page = 1;
  fetchLogs();
}

const debounceSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchLogs();
}, 400);

function openDetail(log) {
  selectedLog.value = log;
  detailDialog.value = true;
}

async function exportLogs() {
  exporting.value = true;
  try {
    showToast('Export functionality coming soon', 'info');
  } finally {
    exporting.value = false;
  }
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatTime(d) {
  if (!d) return '';
  return new Date(d).toLocaleTimeString('en-NG', { hour: '2-digit', minute: '2-digit' });
}

function formatEvent(event) {
  if (!event) return '—';
  return event.replace(/_/g, ' ').replace(/\./g, ' → ');
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

const AVATAR_COLORS = ['blue', 'indigo', 'purple', 'teal', 'green'];
function avatarColor(name) {
  if (!name) return 'grey';
  return AVATAR_COLORS[name.charCodeAt(0) % AVATAR_COLORS.length];
}

onMounted(() => {
  fetchLogs();
  fetchStats();
});
</script>
