<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Enrollment Intelligence"
        subtitle="Monitor NIN verification outcomes, spot backlogs quickly, and drill into recent verification activity."
        kicker="Enrollment"
        icon="mdi-chart-box-outline"
      >
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadReport">
          Refresh
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-3 2xl:tw-grid-cols-6">
        <AppMetricCard title="Attempts" icon="mdi-timeline-check-outline" tone="neutral" :value="summary.total_attempts" helper="Verification attempts in the selected date range" />
        <AppMetricCard title="Verified" icon="mdi-check-decagram-outline" tone="success" :value="summary.verified" helper="Successful NIN verifications" />
        <AppMetricCard title="Failed" icon="mdi-alert-circle-outline" tone="danger" :value="summary.failed" helper="Failed NIN verification attempts" />
        <AppMetricCard title="Success Rate" icon="mdi-chart-arc" tone="info" :value="`${summary.success_rate}%`" helper="Verified attempts divided by total attempts" />
        <AppMetricCard title="Pending Backlog" icon="mdi-timer-sand" tone="warning" :value="summary.pending_backlog" helper="Enrollees with NIN still waiting for verification" />
        <AppMetricCard title="Mobile Verified" icon="mdi-cellphone-check" tone="secondary" :value="summary.mobile_verified" helper="Verified attempts from mobile-officer enrollment" />
      </div>

      <AppCard title="Filters" icon="mdi-filter-variant" tone="primary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-6">
          <v-text-field v-model="filters.date_from" label="Date from" type="date" density="compact" variant="outlined" hide-details />
          <v-text-field v-model="filters.date_to" label="Date to" type="date" density="compact" variant="outlined" hide-details />
          <v-select v-model="filters.lga_id" :items="lookups.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.facility_id" :items="facilityOptions" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.source" :items="lookups.sources" item-title="label" item-value="value" label="Enrollment source" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.status" :items="lookups.statuses" item-title="label" item-value="value" label="Verification status" density="compact" variant="outlined" clearable hide-details />
        </div>

        <div class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" prepend-icon="mdi-magnify" :loading="loading" @click="applyFilters">Load Report</v-btn>
          <v-btn variant="outlined" prepend-icon="mdi-filter-off-outline" @click="resetFilters">Reset</v-btn>
        </div>
      </AppCard>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.4fr_0.9fr]">
        <AppCard title="Verification Trend" icon="mdi-chart-line" tone="primary">
          <LineChart :data="trendChartData" :height="280" />
        </AppCard>

        <AppCard title="Outcome Mix" icon="mdi-chart-donut" tone="success">
          <DoughnutChart :data="statusChartData" :height="280" />
        </AppCard>
      </div>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.2fr_1fr]">
        <AppCard title="Source Breakdown" icon="mdi-source-branch" tone="secondary">
          <BarChart :data="sourceChartData" :height="260" />
        </AppCard>

        <AppCard title="Provider Breakdown" icon="mdi-account-network-outline" tone="info">
          <div v-if="providerBreakdown.length" class="tw-space-y-3">
            <div
              v-for="provider in providerBreakdown"
              :key="provider.label"
              class="tw-flex tw-items-center tw-justify-between tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2"
            >
              <div class="tw-min-w-0">
                <p class="tw-truncate tw-text-sm tw-font-semibold tw-text-slate-900">{{ provider.label }}</p>
                <p class="tw-text-xs tw-text-slate-500">Distinct verified NINs in the selected range</p>
              </div>
              <AppBadge tone="info" :label="String(provider.value)" size="sm" />
            </div>
          </div>
          <AppEmptyState
            v-else
            icon="mdi-database-search-outline"
            title="No provider activity"
            description="No NIN verification attempts matched the current filters."
          />
        </AppCard>
      </div>

      <AppCard title="Recent Verification Records" icon="mdi-table-search" tone="primary">
        <AppDataTable
          v-model:page="table.page"
          v-model:items-per-page="table.perPage"
          v-model:search="table.search"
          :headers="headers"
          :items="table.rows"
          :items-length="table.total"
          :loading="loading"
          searchable
          search-placeholder="Search by enrollee, enrollee ID, NIN, or phone"
          @search="handleSearch"
        >
          <template #toolbar>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
              <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                {{ table.total }} record{{ table.total === 1 ? '' : 's' }}
              </span>
              <span>{{ activeDateRangeLabel }}</span>
            </div>
          </template>

          <template #item.enrollee="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-semibold tw-text-slate-900">{{ item.full_name || 'Unknown enrollee' }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.enrollee_id || 'Pending ID' }}</p>
              <p class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ item.nin }}</p>
            </div>
          </template>

          <template #item.status="{ item }">
            <AppStatusBadge :status="item.status" :label="item.status_label" size="sm" />
          </template>

          <template #item.source="{ item }">
            <div class="tw-text-sm tw-text-slate-700">{{ item.source_label }}</div>
          </template>

          <template #item.provider="{ item }">
            <div class="tw-text-sm tw-text-slate-700">{{ item.provider }}</div>
          </template>

          <template #item.facility="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-medium tw-text-slate-900">{{ item.facility_name || 'N/A' }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.lga_name || 'No LGA' }}</p>
            </div>
          </template>

          <template #item.verified_at="{ item }">
            <DateDisplay :value="item.verified_at" format="medium" />
          </template>

          <template #item.failure_message="{ item }">
            <span class="tw-text-sm tw-text-slate-600">{{ item.failure_message || '-' }}</span>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-card-search-outline"
              title="No verification records"
              description="No NIN verification activity matched the selected filters."
            >
              <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadReport">
                Reload
              </v-btn>
            </AppEmptyState>
          </template>
        </AppDataTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppMetricCard from '../common/AppMetricCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import BarChart from '../charts/BarChart.vue'
import DoughnutChart from '../charts/DoughnutChart.vue'
import LineChart from '../charts/LineChart.vue'
import { enrolleeAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()

const loading = ref(false)
const lookups = reactive({
  lgas: [],
  facilities: [],
  sources: [],
  statuses: [],
})

const filters = reactive(defaultFilters())
const summary = reactive({
  total_attempts: 0,
  verified: 0,
  failed: 0,
  success_rate: 0,
  pending_backlog: 0,
  distinct_nins: 0,
  mobile_verified: 0,
})

const charts = reactive({
  trend: { labels: [], verified: [], failed: [] },
  status_breakdown: [],
  source_breakdown: [],
  provider_breakdown: [],
})

const table = reactive({
  rows: [],
  page: 1,
  perPage: 25,
  total: 0,
  search: '',
})

const headers = [
  { title: 'Enrollee', key: 'enrollee', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Source', key: 'source', sortable: false },
  { title: 'Provider', key: 'provider', sortable: false },
  { title: 'Facility', key: 'facility', sortable: false },
  { title: 'Verified At', key: 'verified_at', sortable: false },
  { title: 'Failure Note', key: 'failure_message', sortable: false },
]

function defaultFilters() {
  const today = new Date()
  const end = today.toISOString().slice(0, 10)
  const startDate = new Date(today)
  startDate.setDate(startDate.getDate() - 29)

  return {
    date_from: startDate.toISOString().slice(0, 10),
    date_to: end,
    lga_id: null,
    facility_id: null,
    source: null,
    status: null,
    provider: null,
  }
}

const facilityOptions = computed(() => {
  if (!filters.lga_id) return lookups.facilities
  return lookups.facilities.filter((facility) => Number(facility.lga_id) === Number(filters.lga_id))
})

const providerBreakdown = computed(() => charts.provider_breakdown || [])

const activeDateRangeLabel = computed(() => `Showing verification activity from ${filters.date_from} to ${filters.date_to}`)

const trendChartData = computed(() => ({
  labels: charts.trend.labels || [],
  datasets: [
    {
      label: 'Verified',
      data: charts.trend.verified || [],
      borderColor: '#0f766e',
      backgroundColor: 'rgba(15, 118, 110, 0.16)',
      fill: true,
    },
    {
      label: 'Failed',
      data: charts.trend.failed || [],
      borderColor: '#dc2626',
      backgroundColor: 'rgba(220, 38, 38, 0.12)',
      fill: true,
    },
  ],
}))

const statusChartData = computed(() => ({
  labels: (charts.status_breakdown || []).map((item) => item.label),
  datasets: [
    {
      data: (charts.status_breakdown || []).map((item) => item.value),
      backgroundColor: ['#0f766e', '#dc2626'],
      borderColor: ['#ffffff', '#ffffff'],
    },
  ],
}))

const sourceChartData = computed(() => ({
  labels: (charts.source_breakdown || []).map((item) => item.label),
  datasets: [
    {
      label: 'Attempts',
      data: (charts.source_breakdown || []).map((item) => item.value),
      backgroundColor: ['#1d4ed8', '#0f766e', '#f59e0b', '#7c3aed'],
    },
  ],
}))

const buildParams = () => {
  const params = {
    ...filters,
    page: table.page,
    per_page: table.perPage,
    search: table.search || null,
  }

  Object.keys(params).forEach((key) => {
    if (params[key] === null || params[key] === '') delete params[key]
  })

  return params
}

const applyResponse = (payload = {}) => {
  Object.assign(summary, payload.summary || {})
  Object.assign(charts, payload.charts || {})
  Object.assign(lookups, payload.lookups || {})
  table.rows = payload.table?.data || []
  table.total = Number(payload.table?.meta?.total || 0)
  table.page = Number(payload.table?.meta?.current_page || 1)
  table.perPage = Number(payload.table?.meta?.per_page || table.perPage)
}

const loadReport = async () => {
  loading.value = true

  try {
    const response = await enrolleeAPI.ninVerificationIntelligence(buildParams())
    applyResponse(response.data?.data || {})
  } catch (err) {
    error(err.response?.data?.message || 'Unable to load enrollment intelligence.')
  } finally {
    loading.value = false
  }
}

const applyFilters = async () => {
  if (table.page !== 1) {
    table.page = 1
    return
  }

  await loadReport()
}

const resetFilters = async () => {
  Object.assign(filters, defaultFilters())
  table.search = ''

  if (table.page !== 1) {
    table.page = 1
    return
  }

  await loadReport()
}

const handleSearch = async () => {
  if (table.page !== 1) {
    table.page = 1
    return
  }

  await loadReport()
}

watch(() => filters.lga_id, () => {
  if (filters.facility_id && !facilityOptions.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) {
    filters.facility_id = null
  }
})

watch(() => table.page, () => {
  void loadReport()
})
watch(() => table.perPage, async () => {
  if (table.page !== 1) {
    table.page = 1
    return
  }

  await loadReport()
})

onMounted(loadReport)
</script>
