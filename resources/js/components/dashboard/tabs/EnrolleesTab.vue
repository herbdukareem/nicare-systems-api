<template>
  <div class="tw-space-y-6">
    <!-- Row 0: Age Snapshot -->
    <div v-if="hasAge" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-6">
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6 tw-flex tw-items-center tw-justify-between">
        <div>
          <p class="tw-text-sm tw-text-gray-600">Average Age</p>
          <p class="tw-text-3xl tw-font-bold tw-text-gray-900">{{ ageAverage.toFixed(1) }}</p>
        </div>
        <v-icon size="28" color="primary">mdi-cake-variant</v-icon>
      </div>

      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-justify-between tw-text-sm tw-text-gray-600">
          <span>Under 18</span>
          <span>{{ ageGroups.under_18?.toLocaleString?.() || 0 }}</span>
        </div>
        <v-progress-linear class="tw-mt-3" rounded :model-value="pct(ageGroups.under_18)"></v-progress-linear>
      </div>

      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-justify-between tw-text-sm tw-text-gray-600">
          <span>18–30</span>
          <span>{{ ageGroups['18_30']?.toLocaleString?.() || 0 }}</span>
        </div>
        <v-progress-linear class="tw-mt-3" color="green" rounded :model-value="pct(ageGroups['18_30'])"></v-progress-linear>
      </div>

      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-justify-between tw-text-sm tw-text-gray-600">
          <span>31–50</span>
          <span>{{ ageGroups['31_50']?.toLocaleString?.() || 0 }}</span>
        </div>
        <v-progress-linear class="tw-mt-3" color="purple" rounded :model-value="pct(ageGroups['31_50'])"></v-progress-linear>

        <div class="tw-flex tw-justify-between tw-text-sm tw-text-gray-600 tw-mt-3">
          <span>Over 50</span>
          <span>{{ ageGroups.over_50?.toLocaleString?.() || 0 }}</span>
        </div>
        <v-progress-linear class="tw-mt-3" color="orange" rounded :model-value="pct(ageGroups.over_50)"></v-progress-linear>
      </div>
    </div>

    <!-- Rows with charts (mount after tab becomes visible) -->
    <div v-show="showCharts" class="tw-space-y-6">
      
      <!-- Row 1 -->
      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
       
        <ChartCard title="Distribution by Gender" icon="mdi-gender-male-female">
  
           
            <DoughnutChart
              :key="genderKey"
              :data="genderChartData"
              :options="legendBottom"
              :height="320"
            />
       
      
        </ChartCard>

        <ChartCard title="Enrollees by Benefactor" icon="mdi-account-heart">
          <template v-if="hasBenefactor">
            <DoughnutChart
              :key="benefactorKey"
              :data="benefactorChartData"
              :options="legendBottom"
              :height="320"
            />
          </template>
          <EmptyState v-else icon="mdi-account-heart" text="No benefactor data available" />
        </ChartCard>
      </div>

      <!-- Row 2 -->
      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
        <ChartCard title="Enrollees by Funding Type" icon="mdi-cash-multiple">
          <template v-if="hasFunding">
            <DoughnutChart
              :key="fundingKey"
              :data="fundingTypeChartData"
              :options="legendBottom"
              :height="320"
            />
          </template>
          <EmptyState v-else icon="mdi-cash-multiple" text="No funding type data available" />
        </ChartCard>

        <ChartCard title="Monthly Enrollment Trend" icon="mdi-chart-line">
          <template v-if="hasTrend">
            <LineChart
              :key="trendKey"
              :data="monthlyTrendChartData"
              :options="lineOptions"
              :height="320"
            />
          </template>
          <EmptyState v-else icon="mdi-chart-line" text="No trend data available" />
        </ChartCard>
      </div>

      <!-- Row 3 -->
      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-2 tw-gap-6">
        <ChartCard title="Enrollees by Ward (Top 10)" icon="mdi-map-marker-multiple">
          <template v-if="hasWard">
            <BarChart
              :key="wardKey"
              :data="wardChartData"
              :options="barOptions"
              :height="320"
            />
          </template>
          <EmptyState v-else icon="mdi-map-marker-multiple" text="No ward data available" />
        </ChartCard>

        <ChartCard title="Distribution by Facilities (Top 10)" icon="mdi-hospital-building">
          <template v-if="hasFacility">
            <BarChart
              :key="facilityKey"
              :data="facilityChartData"
              :options="barOptions"
              :height="320"
            />
          </template>
          <EmptyState v-else icon="mdi-hospital-building" text="No facility data available" />
        </ChartCard>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed, nextTick, onMounted, ref, h, resolveComponent } from 'vue'
import DoughnutChart from '../../charts/DoughnutChart.vue'
import BarChart from '../../charts/BarChart.vue'
import LineChart from '../../charts/LineChart.vue'

defineOptions({ name: 'EnrolleesTab' })

/* --------------------------- Reusable subcomponents ------------------------ */
/* Use render functions so we don't need the runtime template compiler */
const ChartCard = {
  name: 'ChartCard',
  props: { title: String, icon: String },
  setup(props, { slots }) {
    const VIcon = resolveComponent('v-icon')
    return () =>
      h('div', { class: 'tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6' }, [
        h('div', { class: 'tw-flex tw-items-center tw-justify-between tw-mb-4' }, [
          h('h3', { class: 'tw-text-lg tw-font-semibold tw-text-gray-900' }, props.title),
          h(VIcon, { color: 'primary' }, { default: () => props.icon })
        ]),
        h('div', { class: 'tw-h-80' }, slots.default ? slots.default() : null)
      ])
  }
}

const EmptyState = {
  name: 'EmptyState',
  props: { icon: String, text: String },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    return () =>
      h('div', { class: 'tw-flex tw-items-center tw-justify-center tw-h-full' }, [
        h('div', { class: 'tw-text-center' }, [
          h(VIcon, { size: 48, color: 'grey' }, { default: () => props.icon }),
          h('p', { class: 'tw-text-gray-500 tw-mt-2' }, props.text)
        ])
      ])
  }
}

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      byGender: [],
      byType: [],
      byBenefactor: [],
      byFundingType: [],
      byWard: [],
      byFacility: [],
      monthlyTrend: [],
      ageStats: { average_age: 0, age_groups: {} },
    }),
  },
  loading: { type: Boolean, default: false },
})

/* ----------------------------- mount after tab ----------------------------- */
const showCharts = ref(false)
onMounted(async () => {
  await nextTick()
  showCharts.value = true
})

/* ------------------------------ Local helpers ------------------------------ */
const palette = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#EC4899', '#84CC16']
const legendBottom = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
const barOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
const lineOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }

/* ------------------------------- Age widget -------------------------------- */
const hasAge = computed(() => {
  const g = props.stats?.ageStats?.age_groups ?? props.stats?.ageStats?.groups ?? {}
  return !!g && Object.keys(g).length > 0
})
const ageAverage = computed(() => Number(props.stats?.ageStats?.average_age ?? props.stats?.ageStats?.average ?? 0) || 0)
const ageGroups  = computed(() => props.stats?.ageStats?.age_groups ?? props.stats?.ageStats?.groups ?? {})
function pct(v) {
  const total = Object.values(ageGroups.value).reduce((a, b) => a + (Number(b) || 0), 0) || 1
  return Math.round(((Number(v) || 0) / total) * 100)
}

/* ------------------------------ Chart Mappers ------------------------------ */
const genderChartData = computed(() => {
  const g = props.stats.byGender || []
  return g.length ? {
    labels: g.map(i => i.gender || 'Other'),
    datasets: [{ data: g.map(i => Number(i.count || 0)), backgroundColor: ['#3B82F6', '#EC4899', '#9CA3AF'] }],
  } : { labels: [], datasets: [] }
})

const benefactorChartData = computed(() => {
  const b = props.stats.byBenefactor || []
  return b.length ? {
    labels: b.map(i => i.benefactor || 'Self-Funded'),
    datasets: [{ data: b.map(i => Number(i.count || 0)), backgroundColor: palette.slice(0, Math.max(3, b.length)) }],
  } : { labels: [], datasets: [] }
})

const fundingTypeChartData = computed(() => {
  const f = props.stats.byFundingType || []
  return f.length ? {
    labels: f.map(i => i.funding_type || 'Not Specified'),
    datasets: [{ data: f.map(i => Number(i.count || 0)), backgroundColor: palette.slice(0, Math.max(3, f.length)) }],
  } : { labels: [], datasets: [] }
})

const wardChartData = computed(() => {
  const arr = (props.stats.byWard || []).slice(0, 10)
  return arr.length ? {
    labels: arr.map(i => i.ward),
    datasets: [{ label: 'Enrollees', data: arr.map(i => Number(i.count || 0)), backgroundColor: '#3B82F6', borderColor: '#1D4ED8', borderWidth: 1 }],
  } : { labels: [], datasets: [] }
})

const facilityChartData = computed(() => {
  const arr = (props.stats.byFacility || []).slice(0, 10)
  return arr.length ? {
    labels: arr.map(i => i.facility),
    datasets: [{ label: 'Enrollees', data: arr.map(i => Number(i.count || 0)), backgroundColor: '#10B981', borderColor: '#059669', borderWidth: 1 }],
  } : { labels: [], datasets: [] }
})

const monthlyTrendChartData = computed(() => {
  const t = props.stats.monthlyTrend || []
  return t.length ? {
    labels: t.map(i => i.month),
    datasets: [{ label: 'New Enrollments', data: t.map(i => Number(i.count || 0)), borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,.1)', tension: .35, fill: true }],
  } : { labels: [], datasets: [] }
})

/* ------------------------------- Has-data flags ---------------------------- */
const hasBenefactor= computed(() => (benefactorChartData.value.labels || []).length > 0)
const hasFunding   = computed(() => (fundingTypeChartData.value.labels || []).length > 0)
const hasWard      = computed(() => (wardChartData.value.labels || []).length > 0)
const hasFacility  = computed(() => (facilityChartData.value.labels || []).length > 0)
const hasTrend     = computed(() => (monthlyTrendChartData.value.labels || []).length > 0)

/* ------------------------- Keys to force chart redraw ---------------------- */
const hash = (o) => JSON.stringify(o)
const genderKey     = computed(() => 'g-' + hash(genderChartData.value))
const benefactorKey = computed(() => 'b-' + hash(benefactorChartData.value))
const fundingKey    = computed(() => 'f-' + hash(fundingTypeChartData.value))
const wardKey       = computed(() => 'w-' + hash(wardChartData.value))
const facilityKey   = computed(() => 'fc-' + hash(facilityChartData.value))
const trendKey      = computed(() => 't-' + hash(monthlyTrendChartData.value))
</script>
