<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Capitation Enrollee List"
        subtitle="Review and export the auditable enrollee snapshot captured when capitation was generated."
        kicker="Capitation"
        icon="mdi-account-multiple-outline"
      />

      <AppCard
        title="Snapshot Filters"
        subtitle="Select a capitation period and optionally narrow the stored snapshot by funding type or facility."
        icon="mdi-filter-variant"
        tone="primary"
      >
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-3">
          <v-select
            v-model="filters.periodId"
            :items="periodOptions"
            item-title="label"
            item-value="id"
            label="Capitation period"
            density="comfortable"
            variant="outlined"
            :loading="loadingPeriods"
            hide-details
          />
          <v-select
            v-model="filters.fundingTypeId"
            :items="fundingTypeOptions"
            item-title="name"
            item-value="id"
            label="Funding type"
            density="comfortable"
            variant="outlined"
            clearable
            hide-details
            :disabled="!filters.periodId || loadingContext"
          />
          <v-select
            v-model="filters.facilityId"
            :items="facilityOptions"
            item-title="label"
            item-value="id"
            label="Facility"
            density="comfortable"
            variant="outlined"
            clearable
            hide-details
            :disabled="!filters.periodId || loadingContext"
          />
        </div>

        <div class="tw-mt-5 tw-flex tw-flex-wrap tw-items-center tw-gap-3">
          <v-btn
            color="primary"
            prepend-icon="mdi-database-search"
            :loading="loadingSnapshots"
            :disabled="!filters.periodId"
            @click="reloadSnapshots"
          >
            Load Enrollee List
          </v-btn>
          <AppExportButton
            label="Export CSV"
            :loading="exporting"
            :disabled="!canExport"
            @click="exportSnapshots"
          />
        </div>
      </AppCard>

      <AppCard
        title="Snapshot Preview"
      
        icon="mdi-table-eye"
        tone="neutral"
        :padded="false"
      >
      

        <AppDataTable
          :headers="headers"
          :items="snapshots"
          :loading="loadingSnapshots"
          :items-length="pagination.total"
          :page="filters.page"
          :items-per-page="filters.perPage"
          :search="filters.search"
          searchable
          search-placeholder="Search enrollee ID, name, NIN, facility, or phone"
          density="comfortable"
          @update:page="handlePageChange"
          @update:items-per-page="handlePerPageChange"
          @search="handleSearch"
        >
          <template #no-data>
            <AppEmptyState
              v-if="!filters.periodId"
              icon="mdi-account-search-outline"
              title="No period selected"
              description="Select a capitation period to preview its stored enrollee snapshot."
            />
            <AppEmptyState
              v-else-if="!hasLoadedSnapshots"
              icon="mdi-database-search"
              title="Ready to load enrollee list"
              description="Choose optional funding type or facility filters, then click Load Enrollee List."
            />
            <AppEmptyState
              v-else-if="hasLegacyGap"
              icon="mdi-history"
              title="No historical snapshot rows"
              description="This capitation period predates the enrollee snapshot feature, so there is no auditable roster to export from this page."
            />
            <AppEmptyState
              v-else
              icon="mdi-filter-off-outline"
              title="No snapshot rows match these filters"
              description="Try clearing the facility, funding type, or search filters to widen the stored capitation snapshot."
            />
          </template>
        </AppDataTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppExportButton from '../common/AppExportButton.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { capitationAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()

const loadingPeriods = ref(false)
const loadingContext = ref(false)
const loadingSnapshots = ref(false)
const exporting = ref(false)
const hasLoadedSnapshots = ref(false)
const periods = ref([])
const periodContext = ref(null)
const snapshots = ref([])
const pagination = reactive({ total: 0 })
const summary = ref(defaultSummary())

const filters = reactive({
  periodId: null,
  fundingTypeId: null,
  facilityId: null,
  search: '',
  page: 1,
  perPage: 25,
})

const headers = [
  { title: 'Enrollee ID', key: 'enrollee_number' },
  { title: 'Name', key: 'full_name' },
  { title: 'NIN', key: 'nin' },
  { title: 'LGA', key: 'lga_name' },
  { title: 'Ward', key: 'ward_name' },
  { title: 'Facility', key: 'facility_name' },
  { title: 'Phone', key: 'phone' },
]

const periodOptions = computed(() => periods.value.map((period) => ({
  ...period,
  label: `${period.name} (${period.year || 'N/A'})`,
})))

const capitationDetails = computed(() => periodContext.value?.capitation_details || [])

const fundingTypeOptions = computed(() => {
  const options = new Map()

  capitationDetails.value.forEach((detail) => {
    const fundingType = detail?.funding_type
    if (fundingType?.id && fundingType?.name && !options.has(fundingType.id)) {
      options.set(fundingType.id, { id: fundingType.id, name: fundingType.name })
    }
  })

  return Array.from(options.values()).sort((a, b) => a.name.localeCompare(b.name))
})

const facilityOptions = computed(() => {
  const options = new Map()

  capitationDetails.value
    .filter((detail) => !filters.fundingTypeId || detail?.funding_type_id === filters.fundingTypeId)
    .forEach((detail) => {
      const facility = detail?.facility
      if (facility?.id && facility?.name && !options.has(facility.id)) {
        options.set(facility.id, {
          id: facility.id,
          label: facility.hcp_code ? `${facility.name} (${facility.hcp_code})` : facility.name,
        })
      }
    })

  return Array.from(options.values()).sort((a, b) => a.label.localeCompare(b.label))
})

const canExport = computed(() => Boolean(filters.periodId) && Number(summary.value.total_enrollees || 0) > 0)
const hasActiveSnapshotFilters = computed(() => Boolean(
  filters.fundingTypeId
  || filters.facilityId
  || String(filters.search || '').trim()
))
const hasLegacyGap = computed(() => (
  Boolean(filters.periodId)
  && !hasActiveSnapshotFilters.value
  && !summary.value.has_snapshot_rows
  && summary.value.has_generated_details
))

watch(() => filters.periodId, async (value) => {
  filters.fundingTypeId = null
  filters.facilityId = null
  filters.search = ''
  filters.page = 1
  periodContext.value = null
  snapshots.value = []
  pagination.total = 0
  summary.value = defaultSummary()
  hasLoadedSnapshots.value = false

  if (!value) {
    return
  }

  await loadPeriodContext(value)
})

watch(facilityOptions, (options) => {
  if (filters.facilityId && !options.some((option) => option.id === filters.facilityId)) {
    filters.facilityId = null
  }
})

const loadPeriods = async () => {
  loadingPeriods.value = true
  try {
    const response = await capitationAPI.periods({ per_page: 100 })
    const payload = response.data?.data
    periods.value = payload?.data || payload || []
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load capitation periods.')
  } finally {
    loadingPeriods.value = false
  }
}

const loadPeriodContext = async (periodId) => {
  loadingContext.value = true
  try {
    const response = await capitationAPI.showPeriod(periodId)
    periodContext.value = response.data?.data || null
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load capitation period context.')
  } finally {
    loadingContext.value = false
  }
}

const loadSnapshots = async () => {
  if (!filters.periodId) {
    return
  }

  loadingSnapshots.value = true
  try {
    const response = await capitationAPI.enrolleeList(filters.periodId, {
      funding_type_id: filters.fundingTypeId || undefined,
      facility_id: filters.facilityId || undefined,
      search: filters.search || undefined,
      page: filters.page,
      per_page: filters.perPage,
    })

    const payload = response.data?.data
    snapshots.value = payload?.data || []
    pagination.total = payload?.total || snapshots.value.length
    summary.value = response.data?.summary || defaultSummary()
    hasLoadedSnapshots.value = true
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load capitation enrollee snapshots.')
  } finally {
    loadingSnapshots.value = false
  }
}

const reloadSnapshots = async () => {
  filters.page = 1
  await loadSnapshots()
}

const exportSnapshots = async () => {
  if (!canExport.value) {
    return
  }

  exporting.value = true
  try {
    const response = await capitationAPI.exportEnrolleeList(filters.periodId, {
      funding_type_id: filters.fundingTypeId || undefined,
      facility_id: filters.facilityId || undefined,
      search: filters.search || undefined,
    })

    const contentType = response.headers?.['content-type'] || 'text/csv;charset=utf-8'
    const blob = response.data instanceof Blob
      ? response.data
      : new Blob([response.data], { type: contentType })
    const url = URL.createObjectURL(blob)
    const disposition = response.headers?.['content-disposition'] || ''
    const filename = disposition.match(/filename=\"?([^\"]+)\"?/i)?.[1] || `capitation_enrollee_list_${filters.periodId}.csv`
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to export the capitation enrollee snapshot.')
  } finally {
    exporting.value = false
  }
}

const handleSearch = async (value) => {
  filters.search = value || ''
  filters.page = 1
  await loadSnapshots()
}

const handlePageChange = async (value) => {
  filters.page = value
  await loadSnapshots()
}

const handlePerPageChange = async (value) => {
  filters.perPage = value
  filters.page = 1
  await loadSnapshots()
}

function defaultSummary() {
  return {
    total_enrollees: 0,
    facility_count: 0,
    funding_type_count: 0,
    captured_at: null,
    has_generated_details: false,
    has_snapshot_rows: false,
  }
}

onMounted(loadPeriods)
</script>
