<template>
  <AdminLayout>
    <div
      ref="dashboardRoot"
      class="tw-space-y-6"
      :class="{
        'tw-min-h-screen tw-overflow-auto tw-bg-slate-50 tw-p-4 md:tw-p-6': isFullscreen,
      }"
    >
      <AppPageHeader
        title="BHCPF 65,000 Executive Dashboard"
        subtitle="Monitor captured vulnerable-group enrollments across Niger State against the official BHCPF campaign allocation."
        kicker="Executive Monitoring"
        icon="mdi-chart-box-multiple-outline"
      >
        <template #meta>
          <span class="tw-rounded-full tw-bg-slate-900 tw-px-3 tw-py-1.5 tw-text-xs tw-font-semibold tw-text-white">
            Start date: {{ formatDate(campaign.start_date) }}
          </span>
          <span class="tw-rounded-full tw-bg-cyan-50 tw-px-3 tw-py-1.5 tw-text-xs tw-font-semibold tw-text-cyan-800">
            Reporting to: {{ formatDate(filters.date_to) }}
          </span>
          <span
            v-if="!campaign.campaign_started"
            class="tw-rounded-full tw-bg-amber-50 tw-px-3 tw-py-1.5 tw-text-xs tw-font-semibold tw-text-amber-800"
          >
            Campaign starts tomorrow
          </span>
        </template>
      
        <div class="tw-flex tw-items-center tw-gap-3 tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-px-3 tw-py-2 tw-shadow-sm">
          <img
            :src="firstLadyImage"
            alt="Her Excellency Hajiya Fatima Mohammed Bago, First Lady of Niger State"
            class="tw-h-12 tw-w-12 tw-rounded-xl tw-object-cover tw-object-top"
          >
          <div class="tw-min-w-0">
            <p class="tw-truncate tw-text-sm tw-font-bold tw-text-slate-900">
              Hajiya Fatima Mohammed Bago
            </p>
            <p class="tw-text-xs tw-font-medium tw-text-slate-600">
              First Lady of Niger State
            </p>
          </div>
        </div>
        <v-btn
          variant="outlined"
          color="secondary"
          :prepend-icon="isFullscreen ? 'mdi-fullscreen-exit' : 'mdi-fullscreen'"
          @click="toggleFullscreen"
        >
          {{ isFullscreen ? 'Exit Full Screen' : 'Full Screen' }}
        </v-btn>
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadDashboard">
          Refresh
        </v-btn>
      </AppPageHeader>

      <AppCard title="Report Window" icon="mdi-calendar-range" tone="primary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
          <v-text-field v-model="localFilters.date_from" label="Date from" type="date" density="compact" variant="outlined" hide-details />
          <v-text-field v-model="localFilters.date_to" label="Date to" type="date" density="compact" variant="outlined" hide-details />
          <div class="tw-flex tw-items-end tw-gap-2">
            <v-btn color="primary" prepend-icon="mdi-magnify" :loading="loading" @click="applyFilters">Load Dashboard</v-btn>
            <v-btn variant="outlined" prepend-icon="mdi-filter-off-outline" @click="resetFilters">Reset</v-btn>
          </div>
        </div>
      </AppCard>

      <div class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-7">
        <AppStatCard compact label="Overall Target" icon="mdi-bullseye-arrow" color="primary" :value="summary.overall_target" :loading="loading" />
        <AppStatCard compact label="Enrolled Today" icon="mdi-calendar-today" color="info" :value="summary.enrolled_today" :loading="loading" />
        <AppStatCard compact label="Total Enrolled" icon="mdi-account-multiple-check-outline" color="success" :value="summary.total_enrolled" :loading="loading" />
        <AppStatCard compact label="Remaining" icon="mdi-timer-sand" color="warning" :value="summary.remaining" :loading="loading" />
        <AppStatCard compact label="LGAs" icon="mdi-map-outline" color="secondary" :value="summary.total_lgas" :loading="loading" />
        <AppStatCard compact label="Wards" icon="mdi-map-marker-radius-outline" color="secondary" :value="summary.total_wards" :loading="loading" />
        <AppStatCard compact label="Progress" icon="mdi-chart-arc" color="primary" :value="formatPercent(summary.overall_progress_percent)" :loading="loading" />
      </div>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.45fr_0.9fr]">
        <AppCard title="LGA Progress Overview" icon="mdi-chart-bar" tone="primary">
          <BarChart :data="lgaProgressChartData" :options="lgaProgressChartOptions" :height="720" />
        </AppCard>

        <div class="tw-space-y-5">
          <AppCard title="Best Performing LGA" icon="mdi-trophy-outline" tone="success">
            <div v-if="summary.best_performing_lga" class="tw-rounded-2xl tw-bg-emerald-50 tw-p-4">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.2em] tw-text-emerald-700">Best Performance</p>
              <p class="tw-mt-2 tw-text-2xl tw-font-black tw-text-slate-900">{{ summary.best_performing_lga.lga_name }}</p>
              <p class="tw-mt-1 tw-text-sm tw-text-slate-600">
                {{ formatNumber(summary.best_performing_lga.captured) }} captured of {{ formatNumber(summary.best_performing_lga.target) }}
              </p>
              <p class="tw-mt-3 tw-text-lg tw-font-bold tw-text-emerald-700">{{ formatPercent(summary.best_performing_lga.progress_percent) }}</p>
            </div>
          </AppCard>

          <AppCard title="Lowest Performing LGA" icon="mdi-alert-outline" tone="warning">
            <div v-if="summary.lowest_performing_lga" class="tw-rounded-2xl tw-bg-amber-50 tw-p-4">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.2em] tw-text-amber-700">Needs Attention</p>
              <p class="tw-mt-2 tw-text-2xl tw-font-black tw-text-slate-900">{{ summary.lowest_performing_lga.lga_name }}</p>
              <p class="tw-mt-1 tw-text-sm tw-text-slate-600">
                {{ formatNumber(summary.lowest_performing_lga.captured) }} captured of {{ formatNumber(summary.lowest_performing_lga.target) }}
              </p>
              <p class="tw-mt-3 tw-text-lg tw-font-bold tw-text-amber-700">{{ formatPercent(summary.lowest_performing_lga.progress_percent) }}</p>
            </div>
          </AppCard>

          <AppCard title="Status Guide" icon="mdi-lightbulb-on-outline" tone="info">
            <div class="tw-grid tw-gap-2">
              <div v-for="status in statusLegend" :key="status.label" class="tw-flex tw-items-center tw-justify-between tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-white tw-px-4 tw-py-3">
                <div class="tw-flex tw-items-center tw-gap-3">
                  <span class="tw-inline-flex tw-h-3 tw-w-3 tw-rounded-full" :style="{ backgroundColor: status.color }"></span>
                  <span class="tw-text-sm tw-font-semibold tw-text-slate-700">{{ status.label }}</span>
                </div>
                <span class="tw-text-xs tw-font-medium tw-text-slate-500">{{ status.range }}</span>
              </div>
            </div>
          </AppCard>
        </div>
      </div>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-2">
        <AppCard title="Top 5 Performing LGAs" icon="mdi-trending-up" tone="success">
          <div class="tw-space-y-3">
            <div v-for="item in topPerformers" :key="item.lga_id" class="tw-flex tw-items-center tw-justify-between tw-rounded-2xl tw-bg-emerald-50 tw-p-4">
              <div>
                <p class="tw-font-semibold tw-text-slate-900">{{ item.lga_name }}</p>
                <p class="tw-text-xs tw-text-slate-600">{{ formatNumber(item.captured) }} of {{ formatNumber(item.target) }}</p>
              </div>
              <span class="tw-text-lg tw-font-black tw-text-emerald-700">{{ formatPercent(item.progress_percent) }}</span>
            </div>
          </div>
        </AppCard>

        <AppCard title="LGAs Requiring Support" icon="mdi-lifebuoy" tone="warning">
          <div class="tw-space-y-3">
            <div v-for="item in supportList" :key="item.lga_id" class="tw-flex tw-items-center tw-justify-between tw-rounded-2xl tw-bg-rose-50 tw-p-4">
              <div>
                <p class="tw-font-semibold tw-text-slate-900">{{ item.lga_name }}</p>
                <p class="tw-text-xs tw-text-slate-600">{{ formatNumber(item.remaining) }} still remaining</p>
              </div>
              <span class="tw-text-lg tw-font-black tw-text-rose-700">{{ formatPercent(item.progress_percent) }}</span>
            </div>
          </div>
        </AppCard>
      </div>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-[1.2fr_0.8fr]">
        <AppCard title="Daily Enrollment Trend" icon="mdi-chart-line" tone="primary">
          <LineChart :data="dailyTrendChartData" :height="320" />
        </AppCard>

        <AppCard title="Demographic Breakdown" icon="mdi-account-group" tone="secondary">
          <BarChart :data="demographicChartData" :options="demographicChartOptions" :height="320" />
        </AppCard>
      </div>

      <AppCard title="LGA Progress Table" icon="mdi-table-large" tone="primary">
        <AppDataTable
          :headers="lgaHeaders"
          :items="lgaRows"
          :items-length="lgaRows.length"
          :loading="loading"
          :items-per-page="25"
        >
          <template #toolbar>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 tw-text-xs tw-text-slate-500">
              <span class="tw-rounded-full tw-bg-slate-200 tw-px-2.5 tw-py-1 tw-font-semibold tw-text-slate-700">
                {{ lgaRows.length }} LGA row{{ lgaRows.length === 1 ? '' : 's' }}
              </span>
              <span>Progress is based on captured divided by target.</span>
            </div>
          </template>

          <template #item.lga="{ item }">
            <div class="tw-min-w-0">
              <p class="tw-font-semibold tw-text-slate-900">{{ item.lga_name }}</p>
              <p class="tw-text-xs tw-text-slate-500">{{ item.ward_count }} wards</p>
            </div>
          </template>

          <template #item.progress="{ item }">
            <div class="tw-flex tw-items-center tw-gap-3">
              <div class="tw-h-2.5 tw-w-28 tw-overflow-hidden tw-rounded-full tw-bg-slate-200">
                <div class="tw-h-full tw-rounded-full" :style="{ width: `${Math.min(item.progress_percent, 100)}%`, backgroundColor: item.status_color }"></div>
              </div>
              <span class="tw-text-sm tw-font-semibold tw-text-slate-700">{{ formatPercent(item.progress_percent) }}</span>
            </div>
          </template>

          <template #item.status="{ item }">
            <span class="tw-inline-flex tw-items-center tw-gap-2 tw-rounded-full tw-px-3 tw-py-1 tw-text-xs tw-font-semibold" :class="statusClass(item.status_tone)">
              <span class="tw-h-2 tw-w-2 tw-rounded-full" :style="{ backgroundColor: item.status_color }"></span>
              {{ item.status }}
            </span>
          </template>
        </AppDataTable>
      </AppCard>

      <div class="tw-grid tw-gap-5 xl:tw-grid-cols-2">
        <AppCard title="Daily Performance Table" icon="mdi-calendar-text-outline" tone="primary">
          <AppDataTable
            :headers="dailyHeaders"
            :items="dailyRows"
            :items-length="dailyRows.length"
            :loading="loading"
            :items-per-page="15"
          />
        </AppCard>

        <AppCard title="Demographic Target vs Captured" icon="mdi-chart-bar-stacked" tone="secondary">
          <AppDataTable
            :headers="demographicHeaders"
            :items="demographicRows"
            :items-length="demographicRows.length"
            :loading="loading"
            :items-per-page="10"
          />
        </AppCard>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import BarChart from '../charts/BarChart.vue'
import LineChart from '../charts/LineChart.vue'
import { dashboardAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()
const firstLadyImage = '/first-lady.jpg'

const loading = ref(false)
const dashboardRoot = ref(null)
const isFullscreen = ref(false)
const campaign = reactive({
  name: '',
  start_date: '',
  today: '',
  campaign_started: false,
})

const filters = reactive({
  date_from: '',
  date_to: '',
})

const localFilters = reactive({
  date_from: '',
  date_to: '',
})

const summary = reactive({
  overall_target: 0,
  enrolled_today: 0,
  total_enrolled: 0,
  remaining: 0,
  overall_progress_percent: 0,
  best_performing_lga: null,
  lowest_performing_lga: null,
  total_lgas: 0,
  total_wards: 0,
})

const lgaRows = ref([])
const dailyRows = ref([])
const demographicRows = ref([])
const topPerformers = ref([])
const supportList = ref([])

const statusLegend = [
  { label: 'Completed', range: '100%+', color: '#2563eb' },
  { label: 'On Track', range: '80% - 99.9%', color: '#15803d' },
  { label: 'In Progress', range: '50% - 79.9%', color: '#f59e0b' },
  { label: 'Needs Support', range: '25% - 49.9%', color: '#ea580c' },
  { label: 'Needs Push', range: 'Below 25%', color: '#dc2626' },
]

const lgaHeaders = [
  { title: 'LGA Name', key: 'lga', sortable: false },
  { title: 'Target', key: 'target', sortable: false },
  { title: 'Enrolled', key: 'captured', sortable: false },
  { title: 'Remaining', key: 'remaining', sortable: false },
  { title: 'Progress', key: 'progress', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
]

const dailyHeaders = [
  { title: 'Date', key: 'date_label', sortable: false },
  { title: 'Captured', key: 'captured', sortable: false },
  { title: 'Cumulative', key: 'cumulative', sortable: false },
]

const demographicHeaders = [
  { title: 'Group', key: 'label', sortable: false },
  { title: 'Captured', key: 'captured', sortable: false },
  { title: 'Target', key: 'target', sortable: false },
]

const lgaProgressChartData = computed(() => ({
  labels: lgaRows.value.map((item) => item.lga_name),
  datasets: [
    {
      label: 'Captured',
      data: lgaRows.value.map((item) => item.captured),
      backgroundColor: lgaRows.value.map((item) => item.status_color),
      borderColor: lgaRows.value.map((item) => item.status_color),
      borderWidth: 1,
    },
  ],
}))

const lgaProgressChartOptions = {
  indexAxis: 'y',
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        callback: (value) => Number(value).toLocaleString(),
      },
    },
  },
}

const dailyTrendChartData = computed(() => ({
  labels: dailyRows.value.map((item) => item.date_label),
  datasets: [
    {
      label: 'Captured',
      data: dailyRows.value.map((item) => item.captured),
      borderColor: '#0891b2',
      backgroundColor: 'rgba(8, 145, 178, 0.12)',
      fill: true,
      pointBackgroundColor: '#0891b2',
    },
    {
      label: 'Cumulative',
      data: dailyRows.value.map((item) => item.cumulative),
      borderColor: '#0f172a',
      backgroundColor: 'rgba(15, 23, 42, 0.05)',
      fill: false,
      pointBackgroundColor: '#0f172a',
    },
  ],
}))

const demographicChartData = computed(() => ({
  labels: demographicRows.value.map((item) => item.label),
  datasets: [
    {
      label: 'Captured',
      data: demographicRows.value.map((item) => item.captured),
      backgroundColor: ['#2563eb', '#15803d', '#f59e0b', '#7c3aed', '#dc2626'],
    },
    {
      label: 'Target',
      data: demographicRows.value.map((item) => item.target),
      backgroundColor: 'rgba(15, 23, 42, 0.16)',
      borderColor: '#0f172a',
      borderWidth: 1,
    },
  ],
}))

const demographicChartOptions = {
  plugins: {
    legend: {
      position: 'top',
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: (value) => Number(value).toLocaleString(),
      },
    },
  },
}

const syncFullscreenState = () => {
  isFullscreen.value = document.fullscreenElement === dashboardRoot.value
}

const toggleFullscreen = async () => {
  try {
    if (!document.fullscreenEnabled || !dashboardRoot.value) {
      error('Fullscreen is not available in this browser.')
      return
    }

    if (document.fullscreenElement === dashboardRoot.value) {
      await document.exitFullscreen()
      return
    }

    await dashboardRoot.value.requestFullscreen()
  } catch (err) {
    error(err?.message || 'Unable to toggle full screen mode.')
  }
}

const loadDashboard = async () => {
  loading.value = true

  try {
    const { data } = await dashboardAPI.getBhcpfExecutiveOverview({
      date_from: localFilters.date_from || undefined,
      date_to: localFilters.date_to || undefined,
    })

    const payload = data.data || {}
    Object.assign(campaign, payload.campaign || {})
    Object.assign(filters, payload.filters || {})
    Object.assign(localFilters, payload.filters || {})
    Object.assign(summary, payload.summary || {})
    lgaRows.value = payload.tables?.lga_progress || []
    dailyRows.value = payload.tables?.daily_performance || []
    demographicRows.value = payload.charts?.demographics || []
    topPerformers.value = payload.tables?.top_performing || []
    supportList.value = payload.tables?.needs_support || []
  } catch (err) {
    error(err.response?.data?.message || 'Unable to load the BHCPF executive dashboard.')
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  loadDashboard()
}

const resetFilters = () => {
  localFilters.date_from = campaign.start_date || ''
  localFilters.date_to = campaign.today || ''
  loadDashboard()
}

const formatNumber = (value) => Number(value || 0).toLocaleString()
const formatPercent = (value) => `${Number(value || 0).toFixed(1)}%`
const formatDate = (value) => {
  if (!value) return 'N/A'
  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' })
}

const statusClass = (tone) => ({
  'tw-bg-cyan-50 tw-text-cyan-800': tone === 'info',
  'tw-bg-emerald-50 tw-text-emerald-800': tone === 'success',
  'tw-bg-amber-50 tw-text-amber-800': tone === 'warning',
  'tw-bg-rose-50 tw-text-rose-800': tone === 'danger',
})

onMounted(() => {
  document.addEventListener('fullscreenchange', syncFullscreenState)
  loadDashboard()
})

onBeforeUnmount(() => {
  document.removeEventListener('fullscreenchange', syncFullscreenState)
})
</script>
