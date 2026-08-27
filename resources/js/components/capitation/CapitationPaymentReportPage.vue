<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Capitation Payment Report"
        subtitle="Generate the current single-period spreadsheet or trace facility capitation history across multiple capitation periods."
        kicker="Capitation"
        icon="mdi-file-chart-outline"
      />

      <AppTabs v-model="activeTab" :tabs="tabs">
        <template v-if="activeTab === 'period'">
          <AppCard
            title="Single Period Report"
            subtitle="Generate the existing facility payment report with funding-source columns for one capitation period and one processing status."
            icon="mdi-calendar-month-outline"
            tone="primary"
          >
            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <v-select
                v-model="periodFilters.periodId"
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
                v-model="periodFilters.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Payment status"
                density="comfortable"
                variant="outlined"
                hide-details
              />
            </div>

            <div class="tw-mt-5 tw-flex tw-flex-wrap tw-items-center tw-gap-3">
              <AppExportButton
                label="Generate Excel Report"
                :loading="exportingPeriod"
                :disabled="!periodFilters.periodId"
                @click="exportPeriodReport"
              />
              <span class="tw-text-sm tw-text-slate-500">
                The spreadsheet contains one row per facility, with BHCPF, NiCare, BHCPF-CF, GAC, NiCare-Formal, Unicef, and total amount columns.
              </span>
            </div>
          </AppCard>
        </template>

        <template v-else>
          <AppCard
            title="Historical Report Filters"
            subtitle="Select one facility to see one row per capitation period, or leave the facility blank to summarise all facilities over a selected capitation range."
            icon="mdi-history"
            tone="primary"
          >
            <div class="tw-grid tw-gap-4 lg:tw-grid-cols-3">
              <v-autocomplete
                v-model="historyFilters.facilityId"
                :items="facilityOptions"
                item-title="label"
                item-value="id"
                label="Facility"
                density="comfortable"
                variant="outlined"
                clearable
                hide-details
                :loading="loadingFacilities"
              />
              <v-select
                v-model="historyFilters.status"
                :items="statusOptions"
                item-title="label"
                item-value="value"
                label="Payment status"
                density="comfortable"
                variant="outlined"
                hide-details
              />
              <v-select
                v-model="historyFilters.fundingTypeId"
                :items="fundingTypes"
                item-title="name"
                item-value="id"
                label="Funding type"
                density="comfortable"
                variant="outlined"
                clearable
                hide-details
                :loading="loadingFundingTypes"
              />
              <v-select
                v-model="historyFilters.rangeMode"
                :items="rangeModeOptions"
                item-title="label"
                item-value="value"
                label="Range mode"
                density="comfortable"
                variant="outlined"
                hide-details
              />
              <v-select
                v-model="historyFilters.fromPeriodId"
                :items="periodOptions"
                item-title="label"
                item-value="id"
                label="From period"
                density="comfortable"
                variant="outlined"
                clearable
                hide-details
                :disabled="historyFilters.rangeMode !== 'custom'"
              />
              <v-select
                v-model="historyFilters.toPeriodId"
                :items="periodOptions"
                item-title="label"
                item-value="id"
                label="To period"
                density="comfortable"
                variant="outlined"
                clearable
                hide-details
                :disabled="historyFilters.rangeMode !== 'custom'"
              />
            </div>

            <div class="tw-mt-5 tw-flex tw-flex-wrap tw-items-center tw-gap-3">
              <v-btn
                color="primary"
                prepend-icon="mdi-table-search"
                :loading="loadingHistory"
                @click="reloadHistoryPreview"
              >
                Load History Preview
              </v-btn>
              <AppExportButton
                label="Export Historical Report"
                :loading="exportingHistory"
                :disabled="!canExportHistory"
                @click="exportHistoryReport"
              />
            </div>

            <p class="tw-mt-4 tw-text-sm tw-text-slate-500">
              Selecting a facility gives you one row per capitation period for that provider. Leaving it blank aggregates each facility across the chosen period range.
            </p>
          </AppCard>

          <AppCard
            title="Historical Preview"
            subtitle="Preview the grouped capitation report before downloading the Excel file."
            icon="mdi-table-eye"
            tone="neutral"
            :padded="false"
          >
            <template #actions>
              <AppBadge :label="historyScopeLabel" :tone="isFacilityHistoryScope ? 'info' : 'primary'" size="sm" />
              <AppBadge :label="historySummary.status_label || 'All statuses'" tone="neutral" size="sm" />
              <AppBadge :label="historySummary.range_label || 'All time'" tone="success" size="sm" />
            </template>

            <div v-if="historyLoaded && historySummary.row_count > 0" class="tw-grid tw-gap-3 tw-px-4 tw-pb-4 md:tw-grid-cols-4">
              <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
                <p class="tw-text-xs tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Rows</p>
                <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-slate-900">{{ historySummary.row_count }}</p>
              </div>
              <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
                <p class="tw-text-xs tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">
                  {{ isFacilityHistoryScope ? 'Capitation Periods' : 'Facilities' }}
                </p>
                <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-slate-900">
                  {{ isFacilityHistoryScope ? historySummary.period_count : historySummary.facility_count }}
                </p>
              </div>
              <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
                <p class="tw-text-xs tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Total Enrollees</p>
                <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-slate-900">{{ historySummary.total_enrollees }}</p>
              </div>
              <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
                <p class="tw-text-xs tw-uppercase tw-tracking-[0.18em] tw-text-slate-500">Total Amount</p>
                <p class="tw-mt-2 tw-text-xl tw-font-semibold tw-text-slate-900">
                  <MoneyDisplay :value="historySummary.total_amount" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
                </p>
              </div>
            </div>

            <AppDataTable
              :headers="historyHeaders"
              :items="historyRows"
              :loading="loadingHistory"
              :items-length="historyPagination.total"
              :page="historyFilters.page"
              :items-per-page="historyFilters.perPage"
              density="comfortable"
              @update:page="handleHistoryPageChange"
              @update:items-per-page="handleHistoryPerPageChange"
            >
              <template #no-data>
                <AppEmptyState
                  v-if="!historyLoaded"
                  icon="mdi-history"
                  title="Ready to preview history"
                  description="Pick your report filters, then load the historical capitation preview."
                />
                <AppEmptyState
                  v-else
                  icon="mdi-filter-off-outline"
                  title="No historical capitation rows matched"
                  description="Try widening the period range, clearing the facility or funding type filter, or choosing a different processing status."
                />
              </template>

              <template #item.capitation_period="{ item }">
                <div class="tw-text-sm tw-text-slate-700">
                  <div class="tw-font-medium tw-text-slate-900">{{ item.capitation_period || 'N/A' }}</div>
                  <div class="tw-text-xs tw-text-slate-400">{{ formatDate(item.cutoff_date) }}</div>
                </div>
              </template>

              <template #item.provider_name="{ item }">
                <div class="tw-text-sm tw-text-slate-700">
                  <div class="tw-font-medium tw-text-slate-900">{{ item.provider_name || 'N/A' }}</div>
                  <div v-if="item.facility_code" class="tw-text-xs tw-text-slate-400">{{ item.facility_code }}</div>
                </div>
              </template>

              <template #item.period_window="{ item }">
                <div class="tw-text-sm tw-text-slate-700">
                  <div class="tw-font-medium tw-text-slate-900">{{ item.first_capitation_period || 'N/A' }}</div>
                  <div class="tw-text-xs tw-text-slate-400">
                    {{ item.last_capitation_period || 'N/A' }}
                  </div>
                </div>
              </template>

              <template #item.processing_status="{ item }">
                <AppBadge
                  :label="item.processing_status || 'Unknown'"
                  :tone="item.processing_status_tone || 'neutral'"
                  size="sm"
                />
              </template>

              <template #item.total_enrollees="{ item }">
                {{ Number(item.total_enrollees || 0).toLocaleString() }}
              </template>

              <template #item.period_count="{ item }">
                {{ Number(item.period_count || 0).toLocaleString() }}
              </template>

              <template #item.bhcpf="{ item }">
                <MoneyDisplay :value="item.bhcpf" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.nicare="{ item }">
                <MoneyDisplay :value="item.nicare" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.bhcpf_cf="{ item }">
                <MoneyDisplay :value="item.bhcpf_cf" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.gac="{ item }">
                <MoneyDisplay :value="item.gac" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.nicare_formal="{ item }">
                <MoneyDisplay :value="item.nicare_formal" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.unicef="{ item }">
                <MoneyDisplay :value="item.unicef" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>

              <template #item.total_amount="{ item }">
                <MoneyDisplay :value="item.total_amount" :minimum-fraction-digits="2" :maximum-fraction-digits="2" />
              </template>
            </AppDataTable>
          </AppCard>
        </template>
      </AppTabs>
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
import AppExportButton from '../common/AppExportButton.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppTabs from '../common/AppTabs.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'
import { capitationAPI, facilityAPI, fundingTypeAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()

const activeTab = ref('period')
const loadingPeriods = ref(false)
const loadingFacilities = ref(false)
const loadingFundingTypes = ref(false)
const exportingPeriod = ref(false)
const loadingHistory = ref(false)
const exportingHistory = ref(false)
const periods = ref([])
const facilities = ref([])
const fundingTypes = ref([])
const historyRows = ref([])
const historyLoaded = ref(false)
const historySummary = ref(defaultHistorySummary())
const historyPagination = reactive({ total: 0 })

const periodFilters = reactive({
  periodId: null,
  status: 'all',
})

const historyFilters = reactive({
  facilityId: null,
  status: 'all',
  fundingTypeId: null,
  rangeMode: 'all_time',
  fromPeriodId: null,
  toPeriodId: null,
  page: 1,
  perPage: 25,
})

const tabs = [
  { value: 'period', label: 'Single Period', icon: 'mdi-calendar-month-outline' },
  { value: 'history', label: 'Facility History', icon: 'mdi-history' },
]

const statusOptions = [
  { label: 'All statuses', value: 'all' },
  { label: 'Generated (pending review)', value: 'generated' },
  { label: 'Reviewed', value: 'reviewed' },
  { label: 'Approved', value: 'approved' },
  { label: 'Paid', value: 'paid' },
]

const rangeModeOptions = [
  { label: 'All time', value: 'all_time' },
  { label: 'Custom period range', value: 'custom' },
]

const facilityHistoryHeaders = [
  { title: 'Capitation Period', key: 'capitation_period' },
  { title: 'Status', key: 'processing_status' },
  { title: 'Funding Types', key: 'funding_type_summary' },
  { title: 'Enrollees', key: 'total_enrollees', align: 'end' },
  { title: 'BHCPF', key: 'bhcpf', align: 'end' },
  { title: 'NiCare', key: 'nicare', align: 'end' },
  { title: 'BHCPF-CF', key: 'bhcpf_cf', align: 'end' },
  { title: 'GAC', key: 'gac', align: 'end' },
  { title: 'NiCare-Formal', key: 'nicare_formal', align: 'end' },
  { title: 'Unicef', key: 'unicef', align: 'end' },
  { title: 'Total Amount', key: 'total_amount', align: 'end' },
]

const facilitySummaryHeaders = [
  { title: 'Facility', key: 'provider_name' },
  { title: 'LGA', key: 'lga' },
  { title: 'Ward', key: 'ward' },
  { title: 'Period Window', key: 'period_window' },
  { title: 'Periods', key: 'period_count', align: 'end' },
  { title: 'Status', key: 'processing_status' },
  { title: 'Funding Types', key: 'funding_type_summary' },
  { title: 'Enrollees', key: 'total_enrollees', align: 'end' },
  { title: 'Total Amount', key: 'total_amount', align: 'end' },
]

const periodOptions = computed(() => periods.value.map((period) => ({
  ...period,
  label: `${period.name} (${period.year || 'N/A'})`,
})))

const facilityOptions = computed(() => facilities.value.map((facility) => ({
  ...facility,
  label: facility.hcp_code ? `${facility.name} (${facility.hcp_code})` : facility.name,
})))

const isFacilityHistoryScope = computed(() => {
  const scope = historySummary.value.scope || (historyFilters.facilityId ? 'facility_history' : 'facility_summary')
  return scope === 'facility_history'
})

const historyScopeLabel = computed(() => historySummary.value.scope_label || (isFacilityHistoryScope.value ? 'Facility History' : 'Facilities Summary'))
const historyHeaders = computed(() => (isFacilityHistoryScope.value ? facilityHistoryHeaders : facilitySummaryHeaders))
const canExportHistory = computed(() => historyLoaded.value && Number(historySummary.value.row_count || 0) > 0)

watch(() => historyFilters.rangeMode, (value) => {
  if (value !== 'custom') {
    historyFilters.fromPeriodId = null
    historyFilters.toPeriodId = null
  }
})

watch(
  () => [
    historyFilters.facilityId,
    historyFilters.status,
    historyFilters.fundingTypeId,
    historyFilters.rangeMode,
    historyFilters.fromPeriodId,
    historyFilters.toPeriodId,
  ],
  () => {
    resetHistoryPreview()
  }
)

const loadPeriods = async () => {
  loadingPeriods.value = true
  try {
    const response = await capitationAPI.periods({ per_page: 100 })
    periods.value = apiItems(response)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load capitation periods.')
  } finally {
    loadingPeriods.value = false
  }
}

const loadFacilities = async () => {
  loadingFacilities.value = true
  try {
    const response = await facilityAPI.getAll({ per_page: 500, sort_by: 'name', sort_direction: 'asc' })
    facilities.value = apiItems(response)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load facilities.')
  } finally {
    loadingFacilities.value = false
  }
}

const loadFundingTypes = async () => {
  loadingFundingTypes.value = true
  try {
    const response = await fundingTypeAPI.getAll({ per_page: 500 })
    fundingTypes.value = apiItems(response)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load funding types.')
  } finally {
    loadingFundingTypes.value = false
  }
}

const exportPeriodReport = async () => {
  if (!periodFilters.periodId) {
    error('Select a capitation period before generating the report.')
    return
  }

  exportingPeriod.value = true
  try {
    const response = await capitationAPI.exportPaymentReport(periodFilters.periodId, {
      status: periodFilters.status,
    })
    downloadBlobResponse(response, `capitation_payment_report_${periodFilters.periodId}.xlsx`)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to generate the capitation payment report.')
  } finally {
    exportingPeriod.value = false
  }
}

const loadHistoryPreview = async () => {
  if (!hasValidHistoryRange()) {
    return
  }

  loadingHistory.value = true
  try {
    const response = await capitationAPI.paymentHistoryReport(buildHistoryParams({ includePagination: true }))
    const payload = response.data?.data
    historyRows.value = payload?.data || []
    historyPagination.total = payload?.total || historyRows.value.length
    historySummary.value = response.data?.summary || defaultHistorySummary()
    historyLoaded.value = true
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load the historical capitation report.')
  } finally {
    loadingHistory.value = false
  }
}

const reloadHistoryPreview = async () => {
  historyFilters.page = 1
  await loadHistoryPreview()
}

const exportHistoryReport = async () => {
  if (!canExportHistory.value) {
    return
  }

  exportingHistory.value = true
  try {
    const response = await capitationAPI.exportPaymentHistoryReport(buildHistoryParams())
    downloadBlobResponse(response, 'capitation_facility_history_report.xlsx')
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to export the historical capitation report.')
  } finally {
    exportingHistory.value = false
  }
}

const handleHistoryPageChange = async (value) => {
  historyFilters.page = value
  await loadHistoryPreview()
}

const handleHistoryPerPageChange = async (value) => {
  historyFilters.perPage = value
  historyFilters.page = 1
  await loadHistoryPreview()
}

const buildHistoryParams = ({ includePagination = false } = {}) => {
  const params = {
    status: historyFilters.status,
    range_mode: historyFilters.rangeMode,
    facility_id: historyFilters.facilityId || undefined,
    funding_type_id: historyFilters.fundingTypeId || undefined,
    from_period_id: historyFilters.rangeMode === 'custom' ? historyFilters.fromPeriodId || undefined : undefined,
    to_period_id: historyFilters.rangeMode === 'custom' ? historyFilters.toPeriodId || undefined : undefined,
  }

  if (includePagination) {
    params.page = historyFilters.page
    params.per_page = historyFilters.perPage
  }

  return params
}

const hasValidHistoryRange = () => {
  if (historyFilters.rangeMode !== 'custom') {
    return true
  }

  if (!historyFilters.fromPeriodId || !historyFilters.toPeriodId) {
    error('Select both the starting and ending capitation periods for a custom historical range.')
    return false
  }

  return true
}

const resetHistoryPreview = () => {
  historyLoaded.value = false
  historyRows.value = []
  historyPagination.total = 0
  historySummary.value = defaultHistorySummary()
}

const formatDate = (value) => {
  if (!value) return 'No cutoff date'

  return new Intl.DateTimeFormat('en-NG', {
    dateStyle: 'medium',
    timeZone: 'UTC',
  }).format(new Date(`${value}T00:00:00Z`))
}

const apiItems = (response) => {
  const payload = response?.data?.data
  return payload?.data || payload || []
}

const downloadBlobResponse = (response, fallbackFilename) => {
  const contentType = response.headers?.['content-type'] || 'application/octet-stream'
  const blob = response.data instanceof Blob
    ? response.data
    : new Blob([response.data], { type: contentType })
  const url = URL.createObjectURL(blob)
  const disposition = response.headers?.['content-disposition'] || ''
  const filename = disposition.match(/filename="?([^"]+)"?/i)?.[1] || fallbackFilename
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

function defaultHistorySummary() {
  return {
    scope: null,
    scope_label: '',
    status: 'all',
    status_label: 'All statuses',
    range_mode: 'all_time',
    range_label: 'All time',
    facility_id: null,
    facility_name: null,
    funding_type_id: null,
    funding_type_name: null,
    row_count: 0,
    facility_count: 0,
    period_count: 0,
    total_enrollees: 0,
    total_amount: 0,
    from_period: null,
    to_period: null,
  }
}

onMounted(async () => {
  await Promise.all([
    loadPeriods(),
    loadFacilities(),
    loadFundingTypes(),
  ])
})
</script>
