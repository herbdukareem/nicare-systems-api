<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Header / Welcome -->
      <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6">
        <div class="tw-flex tw-items-center tw-justify-between tw-gap-4">
          <div>
            <h2 class="tw-text-2xl tw-font-bold tw-text-gray-900">Welcome back, {{ userName }}!</h2>
            <p class="tw-text-gray-600 tw-mt-1">Your programme snapshot at a glance.</p>
          </div>
          <div class="tw-flex tw-items-center tw-gap-3">
            <div class="tw-text-right">
              <p class="tw-text-xs tw-text-gray-500">Last updated</p>
              <p class="tw-text-sm tw-font-medium tw-text-gray-900">
                {{ lastUpdated ? new Date(lastUpdated).toLocaleString() : new Date().toLocaleString() }}
              </p>
            </div>
            <v-btn color="primary" variant="flat" @click="refreshAll" :loading="loading">
              <v-icon start>mdi-refresh</v-icon>
              Refresh
            </v-btn>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-py-16">
        <div class="tw-text-center">
          <v-progress-circular indeterminate color="primary" size="48" />
          <p class="tw-text-gray-600 tw-mt-4">Loading dashboard data…</p>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div v-else class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <StatCard :item="stats.totalEnrollees"/>
        <StatCard :item="stats.activeEnrollees"/>
        <StatCard :item="stats.notActiveEnrollees"/>
        <StatCard :item="stats.totalFacilities"/>
      </div>

      <!-- Helper Filters (quick access) -->
      <div v-if="!loading" class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-px-6 tw-pt-4 tw-pb-2">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-3">
          <v-select
            class="tw-w-72"
            :items="lgaOptions"
            item-title="label"
            item-value="value"
            v-model="selectedLgaId"
            label="Filter by LGA (affects tabs)"
            clearable density="comfortable"
          />
          <v-select
            class="tw-w-72"
            :items="benefactorOptions"
            item-title="label"
            item-value="value"
            v-model="selectedBenefactorId"
            label="Filter by Benefactor (affects tabs)"
            clearable density="comfortable"
          />
          <v-chip
            v-if="selectedLgaId || selectedBenefactorId"
            class="tw-ml-auto"
            variant="tonal"
            color="primary"
            @click="clearFilters"
          >
            <v-icon start>mdi-filter-remove</v-icon> Clear filters
          </v-chip>
        </div>
      </div>

      <!-- Main Content Tabs -->
      <div v-if="!loading" class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100">
        <v-tabs v-model="activeTab" color="primary" class="tw-border-b tw-border-gray-200">
          <v-tab value="enrollees"><v-icon left>mdi-account-group</v-icon> Enrollees Statistics</v-tab>
          <v-tab value="facilities"><v-icon left>mdi-hospital-building</v-icon> Facility Statistics</v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab" class="tw-p-6">
          <v-tabs-window-item value="enrollees">
            <EnrolleesTab :stats="filteredEnrolleeStats" :loading="loading" />
          </v-tabs-window-item>

          <v-tabs-window-item value="facilities">
            <FacilitiesTab :stats="filteredFacilityStats" :loading="loading" />
          </v-tabs-window-item>
        </v-tabs-window>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, h, resolveComponent } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { dashboardAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import AdminLayout from '../layout/AdminLayout.vue'
import EnrolleesTab from './tabs/EnrolleesTab.vue'
import FacilitiesTab from './tabs/FacilitiesTab.vue'

/* Stores */
const authStore = useAuthStore()
const { error, success, warn } = useToast?.() || { error: console.error, success: console.log, warn: console.warn }

/* State */
const activeTab = ref('enrollees')
const loading = ref(false)
const lastUpdated = ref(null)

const stats = ref({
  totalEnrollees: { value: 0, change: null, title: 'Total Enrollees', icon: 'mdi-account-group', color: 'blue' },
  activeEnrollees: { value: 0, change: null, title: 'Active Enrollees', icon: 'mdi-account-check', color: 'green' },
  notActiveEnrollees: { value: 0, change: null, title: 'Enrollees Not Active', icon: 'mdi-clock-outline', color: 'orange' },
  totalFacilities: { value: 0, change: null, title: 'Total Facilities', icon: 'mdi-hospital-building', color: 'purple' },
})

const enrolleeStats = ref({
  byGender: [],
  byType: [],
  byBenefactor: [],
  byFundingType: [],
  byWard: [],
  byFacility: [],
  monthlyTrend: [],
  // send to EnrolleesTab in the shape it accepts (it supports both)
  ageStats: { average: 0, groups: {} },
})

const facilityStats = ref({ byLga: [], byWard: [], byFacility: [], topFacilities: [] })

/* Lookups */
const lgas = ref([])
const benefactors = ref([])

/* Filters */
const selectedLgaId = ref(null)
const selectedBenefactorId = ref(null)

/* Computed */
const userName = computed(() => authStore.userName)

const lgaById = computed(() => Object.fromEntries(lgas.value.map(l => [l.id, l])))
const lgaOptions = computed(() => lgas.value.filter(l => l.status === 1).map(l => ({ label: l.name, value: l.id })))

const benefactorById = computed(() => Object.fromEntries(benefactors.value.map(b => [b.id, b])))
const benefactorOptions = computed(() => benefactors.value.filter(b => b.status === 1).map(b => ({ label: b.name, value: b.id })))

/* Enrollee stats filtered view */
const filteredEnrolleeStats = computed(() => {
  const lgaName = selectedLgaId.value ? lgaById.value[selectedLgaId.value]?.name : null
  const benefName = selectedBenefactorId.value ? benefactorById.value[selectedBenefactorId.value]?.name : null
  const filterList = (arr, key, val) => (val ? arr.filter(x => x[key] === val) : arr)

  return {
    ...enrolleeStats.value,
    byWard: enrolleeStats.value.byWard?.length ? filterList(enrolleeStats.value.byWard, 'lga', lgaName || undefined) : [],
    byFacility: enrolleeStats.value.byFacility?.length ? filterList(enrolleeStats.value.byFacility, 'lga', lgaName || undefined) : [],
    byBenefactor: benefName ? enrolleeStats.value.byBenefactor.filter(x => x.benefactor === benefName) : enrolleeStats.value.byBenefactor,
  }
})

/* Facility stats filtered view */
const filteredFacilityStats = computed(() => {
  const lgaName = selectedLgaId.value ? lgaById.value[selectedLgaId.value]?.name : null
  const filterList = (arr, key, val) => (val ? arr.filter(x => x[key] === val) : arr)

  return {
    ...facilityStats.value,
    byLga: filterList(facilityStats.value.byLga, 'lga', lgaName || undefined),
    byWard: facilityStats.value.byWard, // API items have no lga
    byFacility: filterList(facilityStats.value.byFacility, 'lga', lgaName || undefined),
    topFacilities: lgaName ? facilityStats.value.topFacilities.filter(f => f.lga === lgaName) : facilityStats.value.topFacilities,
  }
})

/* Helpers */
const safePct = (n) => {
  if (n === null || n === undefined) return null
  const x = Number(n)
  if (!isFinite(x) || Math.abs(x) > 1000) return null
  return x
}

function normalizeOverview(payload) {
  const pick = (key, fallbackTitle) => {
    const v = payload?.[key] || {}
    return {
      value: Number(v.value ?? 0),
      change: safePct(v.change),
      title: v.title ?? fallbackTitle,
      icon: v.icon ?? 'mdi-information-outline',
      color: v.color ?? 'gray',
    }
  }
  return {
    totalEnrollees: pick('totalEnrollees', 'Total Enrollees'),
    activeEnrollees: pick('activeEnrollees', 'Active Enrollees'),
    notActiveEnrollees: pick('notActiveEnrollees', 'Enrollees Not Active'),
    totalFacilities: pick('totalFacilities', 'Total Facilities'),
  }
}

function normalizeEnrolleeStats(payload) {
  // monthlyTrend supports array or keyed object
  let trend = []
  const rawTrend = payload?.monthlyTrend ?? []
  if (Array.isArray(rawTrend)) {
    trend = rawTrend.map(t => ({ month: t.month, count: Number(t.count ?? 0) }))
  } else {
    trend = Object.values(rawTrend).map(t => ({ month: t.month, count: Number(t.count ?? 0) }))
  }

  return {
    byGender: payload?.byGender ?? [],
    byType: payload?.byType ?? [],
    byBenefactor: payload?.byBenefactor ?? [],
    byFundingType: payload?.byFundingType ?? [],
    byWard: payload?.byWard ?? [],
    byFacility: payload?.byFacility ?? [],
    monthlyTrend: trend,
    // send both-friendly shape; EnrolleesTab supports average or average_age, groups or age_groups
    ageStats: {
      average: Number(payload?.ageStats?.average_age ?? payload?.ageStats?.average ?? 0),
      groups: payload?.ageStats?.age_groups ?? payload?.ageStats?.groups ?? {},
    },
  }
}

function normalizeFacilityStats(payload) {
  const toNum = (v) => (v == null ? 0 : Number(v))
  const topFacilities = (payload?.topFacilities ?? []).map(f => ({
    name: f.name,
    lga: f.lga,
    enrollees: toNum(f.enrollees),
    capacity: toNum(f.capacity),
    utilization: toNum(f.utilization),
  }))
  return {
    byLga: payload?.byLga ?? [],
    byWard: payload?.byWard ?? [],
    byFacility: payload?.byFacility ?? [],
    topFacilities,
  }
}

function clearFilters() {
  selectedLgaId.value = null
  selectedBenefactorId.value = null
}

/* Loaders (each catches its own errors so partial data can render) */
async function loadOverview() {
  try {
    const res = await dashboardAPI.getOverview() // /overview
    if (res?.data?.success) {
      stats.value = normalizeOverview(res.data.data)
      lastUpdated.value = Date.now()
    } else {
      throw new Error('Overview request failed')
    }
  } catch (e) {
    error('Failed to load overview.')
    console.error(e)
  }
}

async function loadEnrolleeStats() {
  try {
    const res = await dashboardAPI.getEnrolleeStats() // /enrollee-stats
    if (res?.data?.success) {
      enrolleeStats.value = normalizeEnrolleeStats(res.data.data)
    } else {
      throw new Error('Enrollee stats request failed')
    }
  } catch (e) {
    error('Failed to load enrollee statistics.')
    console.error(e)
  }
}

async function loadFacilityStats() {
  try {
    const res = await dashboardAPI.getFacilityStats() // /dashboard/facility-stats
    if (res?.data?.success) {
      facilityStats.value = normalizeFacilityStats(res.data.data)
    } else {
      throw new Error('Facility stats request failed')
    }
  } catch (e) {
    error('Failed to load facility statistics.')
    console.error(e)
  }
}

async function loadLgas() {
  try {
    const res = await dashboardAPI.getLgas() // /lgas
    if (res?.data?.success) {
      lgas.value = res.data.data ?? []
    } else {
      throw new Error('LGAs request failed')
    }
  } catch (e) {
    warn?.('Could not load LGAs.')
    console.error(e)
    lgas.value = []
  }
}

async function loadBenefactors() {
  try {
    const res = await dashboardAPI.getBenefactors() // /benefactors
    if (res?.data?.success) {
      benefactors.value = res.data.data ?? []
    } else {
      throw new Error('Benefactors request failed')
    }
  } catch (e) {
    warn?.('Could not load benefactors.')
    console.error(e)
    benefactors.value = []
  }
}

async function refreshAll() {
  loading.value = true
  try {
    const results = await Promise.allSettled([
      loadOverview(),
      loadEnrolleeStats(),
      loadFacilityStats(),
      loadLgas(),
      loadBenefactors(),
    ])
    const failed = results.filter(r => r.status === 'rejected').length
    if (failed) {
      error(`${failed} section${failed > 1 ? 's' : ''} failed to load.`)
    } else {
      success('Dashboard refreshed')
    }
  } finally {
    loading.value = false
  }
}

onMounted(refreshAll)

/* -------- Render-function components (no inline templates) -------- */

const ChangePill = {
  name: 'ChangePill',
  props: { change: { type: Number, default: null } },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    return () => {
      if (props.change === null || props.change === undefined) return null
      const isUp = Number(props.change) >= 0
      const tone = isUp
        ? 'tw-bg-green-100 tw-text-green-700'
        : 'tw-bg-red-100 tw-text-red-700'
      return h(
        'span',
        { class: `tw-text-xs tw-font-semibold tw-rounded-full tw-px-2 tw-py-0.5 ${tone}` },
        [
          h(VIcon, {
            size: 14,
            class: 'tw-mr-1',
            icon: isUp ? 'mdi-arrow-up' : 'mdi-arrow-down'
          }),
          `${Math.abs(Number(props.change) || 0).toLocaleString()}%`
        ]
      )
    }
  }
}

const StatCard = {
  name: 'StatCard',
  props: { item: { type: Object, required: true } },
  setup(props) {
    const VIcon = resolveComponent('v-icon')
    return () => {
      const item = props.item || {}
      const bg =
        item.color === 'blue'   ? 'tw-bg-blue-100'   :
        item.color === 'green'  ? 'tw-bg-green-100'  :
        item.color === 'orange' ? 'tw-bg-yellow-100' :
        item.color === 'purple' ? 'tw-bg-purple-100' : 'tw-bg-gray-100'

      return h('div',
        { class: 'tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100 tw-p-6 tw-transition hover:tw-shadow-md' },
        [
          h('div', { class: 'tw-flex tw-items-center' }, [
            h('div', { class: `tw-p-3 tw-rounded-full ${bg}` }, [
              h(VIcon, { color: item.color || 'gray', size: 24, icon: item.icon || 'mdi-information-outline' })
            ]),
            h('div', { class: 'tw-ml-4 tw-w-full' }, [
              h('p', { class: 'tw-text-sm tw-font-medium tw-text-gray-600' }, item.title || ''),
              h('div', { class: 'tw-flex tw-items-baseline tw-gap-2 tw-mt-1' }, [
                h('p', { class: 'tw-text-3xl tw-font-bold tw-text-gray-900' },
                  (Number(item.value ?? 0)).toLocaleString()
                ),
                h(ChangePill, { change: item.change ?? null })
              ])
            ])
          ])
        ]
      )
    }
  }
}

</script>

<style scoped>
/* Subtle elevate on hover for cards */
</style>
