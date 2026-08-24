<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Enrollment Intelligence"
        subtitle="Track capture progress, approval movement, NIN outcomes, and the operational value flowing through the enrollment pipeline."
        kicker="Enrollment"
        icon="mdi-chart-box-outline"
      >
        <AppExportButton label="Export Excel" :loading="exporting" @click="exportExcel" />
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadReport">
          Refresh
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-3 xl:tw-grid-cols-4 2xl:tw-grid-cols-8">
        <AppStatCard
          v-for="card in summaryCards"
          :key="card.key"
          compact
          :label="card.label"
          :icon="card.icon"
          :color="card.color"
          :value="card.count"
          :sub-label="`NIN value ${formatCurrency(card.ninValue)}`"
          :loading="loading"
        />
      </div>

      <AppCard title="Filters" icon="mdi-filter-variant" tone="primary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-6">
          <v-text-field v-model="filters.date_from" label="Date from" type="date" density="compact" variant="outlined" hide-details :min="dateBounds.minimum" :max="dateBounds.maximum" />
          <v-text-field v-model="filters.date_to" label="Date to" type="date" density="compact" variant="outlined" hide-details :min="filters.date_from || dateBounds.minimum" :max="dateBounds.maximum" />
          <v-select v-model="filters.lga_id" :items="lookups.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.facility_id" :items="facilityOptions" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.source" :items="lookups.sources" item-title="label" item-value="value" label="Enrollment source" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.status" :items="lookups.statuses" item-title="label" item-value="value" label="NIN status" density="compact" variant="outlined" clearable hide-details />
        </div>

        <div class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" prepend-icon="mdi-magnify" :loading="loading" @click="applyFilters">Load Dashboard</v-btn>
          <v-btn variant="outlined" prepend-icon="mdi-filter-off-outline" @click="resetFilters">Reset</v-btn>
        </div>
      </AppCard>

      <AppCard tone="primary">
        <v-tabs v-model="activeTab" color="primary" density="comfortable">
          <v-tab value="overview">Overview</v-tab>
          <v-tab value="nin">NIN Monitoring</v-tab>
          <v-tab value="geography">Geography</v-tab>
          <v-tab value="operations">Operations</v-tab>
        </v-tabs>

        <v-window v-model="activeTab" class="tw-mt-4">
          <v-window-item value="overview">
            <div class="tw-space-y-5">
              <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.4fr_0.9fr]">
                <AppCard title="Enrollment Progress Trend" icon="mdi-chart-line" tone="primary">
                  <LineChart :data="enrollmentTrendChartData" :height="300" />
                </AppCard>

                <AppCard title="Progress Mix" icon="mdi-chart-donut" tone="success">
                  <DoughnutChart :data="statusChartData" :height="300" />
                </AppCard>
              </div>

              <AppCard title="Daily Overview Table" icon="mdi-calendar-month-outline" tone="secondary">
                <AppDataTable
                  :headers="dailyOverviewHeaders"
                  :items="dailyOverviewTable.rows"
                  :items-length="dailyOverviewTable.total"
                  :loading="loading"
                  :items-per-page="dailyOverviewTable.perPage"
                  :per-page-options="[15, 31, 62, 100]"
                >
                  <template #toolbar>
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
                      <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                        {{ dailyOverviewTable.total }} day{{ dailyOverviewTable.total === 1 ? '' : 's' }}
                      </span>
                      <span>{{ activeDateRangeLabel }}</span>
                    </div>
                  </template>

                  <template #item.captured="{ item }">
                    <span class="tw-font-medium tw-text-slate-900">{{ Number(item.captured || 0).toLocaleString() }}</span>
                  </template>

                  <template #item.verified="{ item }">
                    <span class="tw-font-medium tw-text-emerald-700">{{ Number(item.verified || 0).toLocaleString() }}</span>
                  </template>

                  <template #item.failed="{ item }">
                    <span class="tw-font-medium tw-text-rose-700">{{ Number(item.failed || 0).toLocaleString() }}</span>
                  </template>

                  <template #item.duplicates="{ item }">
                    <span class="tw-font-medium tw-text-amber-700">{{ Number(item.duplicates || 0).toLocaleString() }}</span>
                  </template>

                  <template #item.nin_value="{ item }">
                    <span class="tw-font-medium tw-text-slate-900">{{ formatCurrency(item.nin_value) }}</span>
                  </template>
                </AppDataTable>

                <div class="tw-mt-4 tw-overflow-hidden tw-rounded-lg tw-border tw-border-slate-200">
                  <div class="tw-grid tw-gap-px tw-bg-slate-200 lg:tw-grid-cols-[1.25fr_repeat(5,minmax(0,1fr))]">
                    <div class="tw-bg-slate-900 tw-px-4 tw-py-3 tw-text-sm tw-font-semibold tw-uppercase tw-tracking-[0.18em] tw-text-white">
                      Grand Total
                    </div>
                    <div class="tw-bg-white tw-px-4 tw-py-3">
                      <p class="tw-text-[11px] tw-font-medium tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Captured</p>
                      <p class="tw-mt-1 tw-text-lg tw-font-semibold tw-text-slate-900">{{ Number(dailyOverviewTable.grandTotal.captured || 0).toLocaleString() }}</p>
                    </div>
                    <div class="tw-bg-white tw-px-4 tw-py-3">
                      <p class="tw-text-[11px] tw-font-medium tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Verified</p>
                      <p class="tw-mt-1 tw-text-lg tw-font-semibold tw-text-emerald-700">{{ Number(dailyOverviewTable.grandTotal.verified || 0).toLocaleString() }}</p>
                    </div>
                    <div class="tw-bg-white tw-px-4 tw-py-3">
                      <p class="tw-text-[11px] tw-font-medium tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Failed</p>
                      <p class="tw-mt-1 tw-text-lg tw-font-semibold tw-text-rose-700">{{ Number(dailyOverviewTable.grandTotal.failed || 0).toLocaleString() }}</p>
                    </div>
                    <div class="tw-bg-white tw-px-4 tw-py-3">
                      <p class="tw-text-[11px] tw-font-medium tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Duplicates</p>
                      <p class="tw-mt-1 tw-text-lg tw-font-semibold tw-text-amber-700">{{ Number(dailyOverviewTable.grandTotal.duplicates || 0).toLocaleString() }}</p>
                    </div>
                    <div class="tw-bg-white tw-px-4 tw-py-3">
                      <p class="tw-text-[11px] tw-font-medium tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Total NIN Value</p>
                      <p class="tw-mt-1 tw-text-lg tw-font-semibold tw-text-slate-900">{{ formatCurrency(dailyOverviewTable.grandTotal.nin_value) }}</p>
                    </div>
                  </div>
                </div>
              </AppCard>
            </div>
          </v-window-item>

          <v-window-item value="nin">
            <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.35fr_0.95fr]">
              <AppCard title="NIN Verification Trend" icon="mdi-chart-line" tone="primary">
                <LineChart :data="ninTrendChartData" :height="300" />
              </AppCard>

              <AppCard title="NIN Outcome Mix" icon="mdi-chart-donut" tone="success">
                <DoughnutChart :data="ninStatusChartData" :height="300" />
              </AppCard>
            </div>

            <AppCard title="Recent NIN Verification Records" icon="mdi-table-search" tone="primary" class="tw-mt-5">
              <AppDataTable
                v-model:page="verificationTable.page"
                v-model:items-per-page="verificationTable.perPage"
                v-model:search="verificationTable.search"
                :headers="verificationHeaders"
                :items="verificationTable.rows"
                :items-length="verificationTable.total"
                :loading="loading"
                searchable
                search-placeholder="Search by enrollee, enrollee ID, NIN, or phone"
                @search="handleVerificationSearch"
              >
                <template #toolbar>
                  <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
                    <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                      {{ verificationTable.total }} record{{ verificationTable.total === 1 ? '' : 's' }}
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
              </AppDataTable>
            </AppCard>
          </v-window-item>

          <v-window-item value="geography">
            <div class="tw-space-y-5">
              <AppCard title="LGA Breakdown" icon="mdi-map-legend" tone="warning">
                <BarChart :data="lgaBreakdownChartData" :height="320" />
              </AppCard>

              <AppCard title="Ward Breakdown" icon="mdi-map-marker-radius-outline" tone="info">
                <BarChart :data="wardBreakdownChartData" :height="320" />
              </AppCard>

              <AppCard title="Facility Breakdown" icon="mdi-hospital-building" tone="secondary">
                <BarChart :data="facilityBreakdownChartData" :height="320" />
              </AppCard>
            </div>
          </v-window-item>

          <v-window-item value="operations">
            <div class="tw-space-y-5">
              <AppCard title="Facility Summary Table" icon="mdi-table-large" tone="primary">
                <AppDataTable
                  :headers="facilityHeaders"
                  :items="facilityTable.rows"
                  :items-length="facilityTable.total"
                  :loading="loading"
                  :items-per-page="facilityTable.perPage"
                >
                  <template #toolbar>
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
                      <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                        {{ facilityTable.total }} facility row{{ facilityTable.total === 1 ? '' : 's' }}
                      </span>
                      <span>Value is derived from the configured NIN provider verification amount per attempt.</span>
                    </div>
                  </template>

                  <template #item.facility="{ item }">
                    <div class="tw-min-w-0">
                      <p class="tw-font-semibold tw-text-slate-900">{{ item.facility_name }}</p>
                      <p class="tw-text-xs tw-text-slate-500">{{ item.lga_name }}</p>
                    </div>
                  </template>

                  <template #item.value="{ item }">
                    <span class="tw-text-sm tw-font-medium tw-text-slate-700">{{ formatCurrency(item.value) }}</span>
                  </template>
                </AppDataTable>
              </AppCard>

              <AppCard title="Summary Table by Enrollment Officers" icon="mdi-account-supervisor-outline" tone="secondary">
                <AppDataTable
                  :headers="officerHeaders"
                  :items="officerTable.rows"
                  :items-length="officerTable.total"
                  :loading="loading"
                  :items-per-page="officerTable.perPage"
                >
                  <template #toolbar>
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
                      <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                        {{ officerTable.total }} officer row{{ officerTable.total === 1 ? '' : 's' }}
                      </span>
                      <span>Officer value uses the same configured NIN provider verification amount per attempt.</span>
                    </div>
                  </template>

                  <template #item.officer="{ item }">
                    <div class="tw-min-w-0">
                      <p class="tw-font-semibold tw-text-slate-900">{{ item.officer_name }}</p>
                      <p class="tw-text-xs tw-text-slate-500">{{ item.source_label }}</p>
                    </div>
                  </template>

                  <template #item.value="{ item }">
                    <span class="tw-text-sm tw-font-medium tw-text-slate-700">{{ formatCurrency(item.value) }}</span>
                  </template>
                </AppDataTable>
              </AppCard>
            </div>
          </v-window-item>
        </v-window>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppExportButton from '../common/AppExportButton.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import BarChart from '../charts/BarChart.vue'
import DoughnutChart from '../charts/DoughnutChart.vue'
import LineChart from '../charts/LineChart.vue'
import { enrolleeAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()

const loading = ref(false)
const exporting = ref(false)
const activeTab = ref('overview')
const MINIMUM_INTELLIGENCE_DATE = '2026-08-03'

const lookups = reactive({
  lgas: [],
  facilities: [],
  sources: [],
  statuses: [],
})

const dateBounds = reactive({
  minimum: MINIMUM_INTELLIGENCE_DATE,
  maximum: formatDateInput(new Date()),
})

const filters = reactive(defaultFilters())
const summary = reactive({
  captured: 0,
  pending_approval: 0,
  approved: 0,
  rejected: 0,
  duplicates: 0,
  total_value: 0,
  total_nin_value: 0,
  total_attempts: 0,
  verified: 0,
  failed: 0,
  success_rate: 0,
  pending_backlog: 0,
  distinct_nins: 0,
  mobile_verified: 0,
  verification_value_amount: 0,
  value_breakdown: defaultValueBreakdown(),
})

const charts = reactive({
  trend: { labels: [], verified: [], failed: [] },
  enrollment_trend: { labels: [], captured: [], pending_approval: [], approved: [], rejected: [] },
  status_breakdown: [],
  nin_status_breakdown: [],
  source_breakdown: [],
  provider_breakdown: [],
  lga_breakdown: [],
  ward_breakdown: [],
  facility_breakdown: [],
})

const verificationTable = reactive({
  rows: [],
  page: 1,
  perPage: 25,
  total: 0,
  search: '',
})

const facilityTable = reactive({
  rows: [],
  total: 0,
  perPage: 25,
})

const officerTable = reactive({
  rows: [],
  total: 0,
  perPage: 25,
})

const dailyOverviewTable = reactive({
  rows: [],
  total: 0,
  perPage: 31,
  grandTotal: {
    captured: 0,
    verified: 0,
    failed: 0,
    duplicates: 0,
    nin_value: 0,
  },
})

const verificationHeaders = [
  { title: 'Enrollee', key: 'enrollee', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Source', key: 'source', sortable: false },
  { title: 'Provider', key: 'provider', sortable: false },
  { title: 'Facility', key: 'facility', sortable: false },
  { title: 'Verified At', key: 'verified_at', sortable: false },
  { title: 'Failure Note', key: 'failure_message', sortable: false },
]

const facilityHeaders = [
  { title: 'Facility', key: 'facility', sortable: false },
  { title: 'Captured', key: 'captured', sortable: false },
  { title: 'Pending', key: 'pending_approval', sortable: false },
  { title: 'Approved', key: 'approved', sortable: false },
  { title: 'Rejected', key: 'rejected', sortable: false },
  { title: 'Duplicates', key: 'duplicates', sortable: false },
  { title: 'NIN Attempts', key: 'nin_attempts', sortable: false },
  { title: 'Verified', key: 'nin_verified', sortable: false },
  { title: 'Failed', key: 'nin_failed', sortable: false },
  { title: 'Value', key: 'value', sortable: false },
]

const officerHeaders = [
  { title: 'Enrollment Officer', key: 'officer', sortable: false },
  { title: 'Captured', key: 'captured', sortable: false },
  { title: 'Pending', key: 'pending_approval', sortable: false },
  { title: 'Approved', key: 'approved', sortable: false },
  { title: 'Rejected', key: 'rejected', sortable: false },
  { title: 'Duplicates', key: 'duplicates', sortable: false },
  { title: 'NIN Attempts', key: 'nin_attempts', sortable: false },
  { title: 'Verified', key: 'nin_verified', sortable: false },
  { title: 'Failed', key: 'nin_failed', sortable: false },
  { title: 'Value', key: 'value', sortable: false },
]

const dailyOverviewHeaders = [
  { title: 'Day', key: 'day', sortable: false },
  { title: 'Total Captured', key: 'captured', sortable: false },
  { title: 'Total Verified', key: 'verified', sortable: false },
  { title: 'Total Failed', key: 'failed', sortable: false },
  { title: 'Total Duplicate', key: 'duplicates', sortable: false },
  { title: 'Total NIN Value', key: 'nin_value', sortable: false },
]

function formatDateInput(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function defaultValueBreakdown() {
  return {
    captured: 0,
    pending_approval: 0,
    approved: 0,
    rejected: 0,
    duplicates: 0,
    total_attempts: 0,
    verified: 0,
    failed: 0,
  }
}

function defaultFilters() {
  const today = new Date()
  const end = formatDateInput(today)
  const minimumDate = new Date(`${MINIMUM_INTELLIGENCE_DATE}T00:00:00`)
  const startDate = new Date(today)
  startDate.setDate(startDate.getDate() - 29)
  const effectiveStart = startDate < minimumDate ? minimumDate : startDate

  return {
    date_from: formatDateInput(effectiveStart),
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

const activeDateRangeLabel = computed(() => `Showing intelligence from ${filters.date_from} to ${filters.date_to}`)
const summaryCards = computed(() => [
  { key: 'captured', label: 'Captured', icon: 'mdi-account-plus-outline', color: 'primary', count: summary.captured, ninValue: summary.value_breakdown?.captured ?? 0 },
  { key: 'pending_approval', label: 'Pending Approval', icon: 'mdi-timer-sand', color: 'warning', count: summary.pending_approval, ninValue: summary.value_breakdown?.pending_approval ?? 0 },
  { key: 'approved', label: 'Approved', icon: 'mdi-check-decagram-outline', color: 'success', count: summary.approved, ninValue: summary.value_breakdown?.approved ?? 0 },
  { key: 'rejected', label: 'Rejected', icon: 'mdi-close-octagon-outline', color: 'danger', count: summary.rejected, ninValue: summary.value_breakdown?.rejected ?? 0 },
  { key: 'duplicates', label: 'Duplicates', icon: 'mdi-content-duplicate', color: 'secondary', count: summary.duplicates, ninValue: summary.value_breakdown?.duplicates ?? 0 },
  { key: 'total_attempts', label: 'NIN Attempts', icon: 'mdi-timeline-check-outline', color: 'info', count: summary.total_attempts, ninValue: summary.value_breakdown?.total_attempts ?? 0 },
  { key: 'verified', label: 'NIN Verified', icon: 'mdi-card-account-details-outline', color: 'success', count: summary.verified, ninValue: summary.value_breakdown?.verified ?? 0 },
  { key: 'failed', label: 'Total Failed', icon: 'mdi-alert-circle-outline', color: 'danger', count: summary.failed, ninValue: summary.value_breakdown?.failed ?? 0 },
])

const enrollmentTrendChartData = computed(() => ({
  labels: charts.enrollment_trend.labels || [],
  datasets: [
    {
      label: 'Captured',
      data: charts.enrollment_trend.captured || [],
      borderColor: '#1d4ed8',
      backgroundColor: 'rgba(29, 78, 216, 0.12)',
      fill: true,
    },
    {
      label: 'Pending Approval',
      data: charts.enrollment_trend.pending_approval || [],
      borderColor: '#d97706',
      backgroundColor: 'rgba(217, 119, 6, 0.12)',
      fill: true,
    },
    {
      label: 'Approved',
      data: charts.enrollment_trend.approved || [],
      borderColor: '#0f766e',
      backgroundColor: 'rgba(15, 118, 110, 0.12)',
      fill: true,
    },
    {
      label: 'Rejected',
      data: charts.enrollment_trend.rejected || [],
      borderColor: '#dc2626',
      backgroundColor: 'rgba(220, 38, 38, 0.08)',
      fill: true,
    },
  ],
}))

const ninTrendChartData = computed(() => ({
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
      backgroundColor: ['#d97706', '#0f766e', '#dc2626', '#8b5cf6'],
      borderColor: ['#ffffff', '#ffffff', '#ffffff', '#ffffff'],
    },
  ],
}))

const ninStatusChartData = computed(() => ({
  labels: (charts.nin_status_breakdown || []).map((item) => item.label),
  datasets: [
    {
      data: (charts.nin_status_breakdown || []).map((item) => item.value),
      backgroundColor: ['#0f766e', '#dc2626'],
      borderColor: ['#ffffff', '#ffffff'],
    },
  ],
}))

const lgaBreakdownChartData = computed(() => ({
  labels: (charts.lga_breakdown || []).map((item) => item.label),
  datasets: [
    {
      label: 'Captured',
      data: (charts.lga_breakdown || []).map((item) => item.captured),
      backgroundColor: '#1d4ed8',
    },
    {
      label: 'Approved',
      data: (charts.lga_breakdown || []).map((item) => item.approved),
      backgroundColor: '#0f766e',
    },
    {
      label: 'Pending',
      data: (charts.lga_breakdown || []).map((item) => item.pending_approval),
      backgroundColor: '#d97706',
    },
  ],
}))

const wardBreakdownChartData = computed(() => ({
  labels: (charts.ward_breakdown || []).map((item) => item.label),
  datasets: [
    {
      label: 'Captured',
      data: (charts.ward_breakdown || []).map((item) => item.captured),
      backgroundColor: '#1d4ed8',
    },
    {
      label: 'Approved',
      data: (charts.ward_breakdown || []).map((item) => item.approved),
      backgroundColor: '#0f766e',
    },
    {
      label: 'Pending',
      data: (charts.ward_breakdown || []).map((item) => item.pending_approval),
      backgroundColor: '#d97706',
    },
  ],
}))

const facilityBreakdownChartData = computed(() => ({
  labels: (charts.facility_breakdown || []).map((item) => item.label),
  datasets: [
    {
      label: 'Captured',
      data: (charts.facility_breakdown || []).map((item) => item.captured),
      backgroundColor: '#1d4ed8',
    },
    {
      label: 'Approved',
      data: (charts.facility_breakdown || []).map((item) => item.approved),
      backgroundColor: '#0f766e',
    },
  ],
}))

const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    maximumFractionDigits: 2,
  }).format(Number(value || 0))
}

const normalizeDateFilters = () => {
  if (filters.date_from && filters.date_from < dateBounds.minimum) {
    filters.date_from = dateBounds.minimum
  }

  if (filters.date_from && filters.date_from > dateBounds.maximum) {
    filters.date_from = dateBounds.maximum
  }

  if (filters.date_to && filters.date_to > dateBounds.maximum) {
    filters.date_to = dateBounds.maximum
  }

  if (filters.date_to && filters.date_to < (filters.date_from || dateBounds.minimum)) {
    filters.date_to = filters.date_from || dateBounds.minimum
  }
}

const buildParams = () => {
  normalizeDateFilters()

  const params = {
    ...filters,
    page: verificationTable.page,
    per_page: verificationTable.perPage,
    search: verificationTable.search || null,
    facility_page: 1,
  }

  Object.keys(params).forEach((key) => {
    if (params[key] === null || params[key] === '') delete params[key]
  })

  return params
}

const applyResponse = (payload = {}) => {
  if (payload.constraints?.minimum_date) {
    dateBounds.minimum = payload.constraints.minimum_date
  }
  if (payload.constraints?.maximum_date) {
    dateBounds.maximum = payload.constraints.maximum_date
  }

  Object.assign(summary, payload.summary || {})
  Object.assign(charts, payload.charts || {})
  Object.assign(lookups, payload.lookups || {})

  const dailyOverviewPayload = payload.tables?.daily_overview || {}
  dailyOverviewTable.rows = dailyOverviewPayload.data || []
  dailyOverviewTable.total = Number(dailyOverviewPayload.meta?.total || dailyOverviewTable.rows.length || 0)
  dailyOverviewTable.perPage = Number(dailyOverviewPayload.meta?.per_page || dailyOverviewTable.perPage)
  Object.assign(dailyOverviewTable.grandTotal, {
    captured: 0,
    verified: 0,
    failed: 0,
    duplicates: 0,
    nin_value: 0,
  }, dailyOverviewPayload.grand_total || {})

  const verificationPayload = payload.tables?.recent_verifications || payload.table || {}
  verificationTable.rows = verificationPayload.data || []
  verificationTable.total = Number(verificationPayload.meta?.total || 0)
  verificationTable.page = Number(verificationPayload.meta?.current_page || 1)
  verificationTable.perPage = Number(verificationPayload.meta?.per_page || verificationTable.perPage)

  const facilityPayload = payload.tables?.facility_summary || {}
  facilityTable.rows = facilityPayload.data || []
  facilityTable.total = Number(facilityPayload.meta?.total || 0)
  facilityTable.perPage = Number(facilityPayload.meta?.per_page || facilityTable.perPage)

  const officerPayload = payload.tables?.officer_summary || {}
  officerTable.rows = officerPayload.data || []
  officerTable.total = Number(officerPayload.meta?.total || 0)
  officerTable.perPage = Number(officerPayload.meta?.per_page || officerTable.perPage)
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

const exportExcel = async () => {
  exporting.value = true

  try {
    const response = await enrolleeAPI.exportNinVerificationIntelligence(buildParams())
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const disposition = response.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename=\"?([^\"]+)\"?/i)
    const link = document.createElement('a')
    link.href = url
    link.download = match?.[1] || `enrollment_intelligence_${new Date().toISOString().slice(0, 10)}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err.response?.data?.message || 'Unable to export enrollment intelligence.')
  } finally {
    exporting.value = false
  }
}

const applyFilters = async () => {
  if (verificationTable.page !== 1) {
    verificationTable.page = 1
    return
  }

  await loadReport()
}

const resetFilters = async () => {
  Object.assign(filters, defaultFilters())
  verificationTable.search = ''

  if (verificationTable.page !== 1) {
    verificationTable.page = 1
    return
  }

  await loadReport()
}

const handleVerificationSearch = async () => {
  if (verificationTable.page !== 1) {
    verificationTable.page = 1
    return
  }

  await loadReport()
}

watch(() => filters.lga_id, () => {
  if (filters.facility_id && !facilityOptions.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) {
    filters.facility_id = null
  }
})

watch(() => verificationTable.page, () => {
  void loadReport()
})

watch(() => verificationTable.perPage, async () => {
  if (verificationTable.page !== 1) {
    verificationTable.page = 1
    return
  }

  await loadReport()
})

onMounted(loadReport)
</script>
