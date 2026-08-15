<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Capitation Payment Report"
        subtitle="Generate the facility payment report with funding-source columns for a selected capitation period and processing status."
        kicker="Capitation"
        icon="mdi-file-chart-outline"
      />

      <AppCard title="Report Filters" icon="mdi-filter-variant" tone="primary">
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
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
            v-model="filters.status"
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
            icon="mdi-file-excel"
            :loading="exporting"
            :disabled="!filters.periodId"
            @click="exportReport"
          />
          <span class="tw-text-sm tw-text-slate-500">The spreadsheet contains one row per facility, with BHCPF, NiCare, BHCPF-CF, GAC, NiCare-Formal, Unicef, and total amount columns.</span>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppCard from '../common/AppCard.vue'
import AppExportButton from '../common/AppExportButton.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { capitationAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()
const loadingPeriods = ref(false)
const exporting = ref(false)
const periods = ref([])
const filters = reactive({ periodId: null, status: 'all' })

const statusOptions = [
  { label: 'All statuses', value: 'all' },
  { label: 'Generated (pending review)', value: 'generated' },
  { label: 'Reviewed', value: 'reviewed' },
  { label: 'Approved', value: 'approved' },
  { label: 'Paid', value: 'paid' },
]

const periodOptions = computed(() => periods.value.map((period) => ({
  ...period,
  label: `${period.name} (${period.year || 'N/A'})`,
})))

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

const exportReport = async () => {
  if (!filters.periodId) {
    error('Select a capitation period before generating the report.')
    return
  }

  exporting.value = true
  try {
    const response = await capitationAPI.exportPaymentReport(filters.periodId, { status: filters.status })
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const disposition = response.headers?.['content-disposition'] || ''
    const filename = disposition.match(/filename="?([^"]+)"?/i)?.[1] || `capitation_payment_report_${filters.periodId}.xlsx`
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to generate the capitation payment report.')
  } finally {
    exporting.value = false
  }
}

onMounted(loadPeriods)
</script>
