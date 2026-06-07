<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Reports" icon="mdi-file-chart-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="fetchData">Refresh</v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
        <AppStatCard compact label="Total Enrollees" :value="stats.total_enrollees" icon="mdi-account-group-outline" color="primary" :loading="loading" />
        <AppStatCard compact label="Active Facilities" :value="stats.active_facilities" icon="mdi-hospital-building" color="success" :loading="loading" />
        <AppStatCard compact label="Total Referrals" :value="stats.total_referrals" icon="mdi-file-send-outline" color="warning" :loading="loading" />
        <AppStatCard compact label="Paid Claims" :value="stats.paid_claims" icon="mdi-cash-check" color="info" :loading="loading" />
      </div>

      <AppFilterBar :active-count="activeFilterCount" :cols="4" @clear="resetFilters">
        <v-text-field
          v-model="reportForm.from_date"
          label="From Date"
          type="date"
          density="compact"
          variant="outlined"
          hide-details
        />
        <v-text-field
          v-model="reportForm.to_date"
          label="To Date"
          type="date"
          density="compact"
          variant="outlined"
          hide-details
        />
        <v-select
          v-model="reportForm.format"
          :items="formatOptions"
          label="Format"
          density="compact"
          variant="outlined"
          hide-details
        />
        <template #tags>
          <AppBadge v-if="reportForm.from_date" :label="`From: ${reportForm.from_date}`" tone="secondary" size="sm" />
          <AppBadge v-if="reportForm.to_date" :label="`To: ${reportForm.to_date}`" tone="secondary" size="sm" />
          <AppBadge :label="`Format: ${reportForm.format}`" tone="primary" size="sm" />
        </template>
        <template #actions>
          <v-btn size="small" color="primary" prepend-icon="mdi-file-refresh-outline" :loading="loading" @click="fetchData">Apply</v-btn>
        </template>
      </AppFilterBar>

      <AppCard
        title="Available Reports"
        icon="mdi-database-export-outline"
        tone="success"
      >
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-3">
          <AppCard
            v-for="report in availableReports"
            :key="report.key"
            :title="report.title"
            :subtitle="report.description"
            :icon="report.icon"
            :tone="report.tone"
            hover
            full-height
          >
            <template #actions>
              <AppBadge label="Available" tone="success" size="sm" />
            </template>

            <div class="tw-space-y-4">
              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <AppBadge
                  v-for="fmt in report.formats"
                  :key="fmt"
                  :label="fmt.toUpperCase()"
                  tone="primary"
                  size="sm"
                  :outline="reportForm.format.toLowerCase() !== fmt"
                />
              </div>
              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <AppExportButton
                  :label="`Download ${reportForm.format.toUpperCase()}`"
                  :loading="generating && activeReportKey === report.key"
                  @click="generateReport(report)"
                />
                <v-btn variant="text" prepend-icon="mdi-information-outline" @click="previewReport(report)">
                  Details
                </v-btn>
              </div>
            </div>
          </AppCard>
        </div>
      </AppCard>

      <AppCard
        title="Unavailable / TODO"
        icon="mdi-progress-clock"
        tone="warning"
      >
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-3">
          <AppCard
            v-for="report in todoReports"
            :key="report.key"
            :title="report.title"
            :subtitle="report.description"
            :icon="report.icon"
            tone="warning"
            muted
            full-height
          >
            <template #actions>
              <AppBadge label="TODO" tone="warning" size="sm" />
            </template>
            <div class="tw-space-y-3">
              <p class="tw-text-sm tw-text-slate-600">{{ report.todo }}</p>
              <v-btn variant="outlined" color="warning" disabled prepend-icon="mdi-lock-outline">
                Backend endpoint required
              </v-btn>
            </div>
          </AppCard>
        </div>
      </AppCard>

      <AppCard
        title="Recent System Activity"
        icon="mdi-history"
        tone="secondary"
      >
        <div v-if="loading" class="tw-py-12 tw-flex tw-justify-center">
          <v-progress-circular indeterminate color="primary" />
        </div>
        <AppEmptyState
          v-else-if="activities.length === 0"
          icon="mdi-history"
          title="No recent activity"
          description="No activity records are currently available from the dashboard feed."
        />
        <div v-else class="tw-space-y-3">
          <div
            v-for="activity in activities.slice(0, 10)"
            :key="activity.id"
            class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-px-3 tw-py-2"
          >
            <div class="tw-flex tw-items-start tw-gap-3">
              <div class="qds-icon-shell qds-tone-secondary">
                <v-icon size="18">{{ activity.icon || 'mdi-history' }}</v-icon>
              </div>
              <div>
                <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ activity.title || activity.description }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ activity.subtitle || 'System activity event' }}</p>
              </div>
            </div>
            <span class="tw-text-xs tw-text-slate-400">{{ formatRelative(activity.created_at || activity.time) }}</span>
          </div>
        </div>
      </AppCard>

      <AppModal
        v-model="reportDialog"
        :title="selectedReport?.title || 'Report details'"
        :subtitle="selectedReport?.description || ''"
        icon="mdi-file-chart-outline"
        size="md"
      >
        <template #actions>
          <v-btn variant="outlined" @click="reportDialog = false">Close</v-btn>
          <AppExportButton
            :label="`Download ${reportForm.format.toUpperCase()}`"
            :loading="generating && activeReportKey === selectedReport?.key"
            @click="selectedReport && generateReport(selectedReport)"
          />
        </template>

        <div class="tw-space-y-4">
          <AppAlert
            tone="info"
            :message="selectedReport?.notes || 'This report is generated by the backend reporting endpoint using the current filter inputs above.'"
          />
          <div class="tw-flex tw-flex-wrap tw-gap-2">
            <AppBadge
              v-for="fmt in selectedReport?.formats || []"
              :key="fmt"
              :label="fmt.toUpperCase()"
              tone="primary"
              size="sm"
              :outline="reportForm.format.toLowerCase() !== fmt"
            />
          </div>
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppExportButton from '../common/AppExportButton.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppModal from '../common/AppModal.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { dashboardAPI } from '../../utils/api'
import api from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const loading = ref(false)
const generating = ref(false)
const reportDialog = ref(false)
const selectedReport = ref(null)
const activeReportKey = ref('')
const activities = ref([])
const stats = ref({
  total_enrollees: 0,
  active_facilities: 0,
  total_referrals: 0,
  paid_claims: 0,
})

const reportForm = ref({
  from_date: '',
  to_date: '',
  format: 'pdf',
})

const formatOptions = ['pdf', 'excel', 'csv']

const availableReports = [
  { key: 'executive-summary', title: 'Executive Summary', description: 'High-level enrollee, facility, claims, and referral KPI overview.', icon: 'mdi-chart-box-outline', tone: 'primary', formats: ['pdf'], notes: 'PDF-only by backend rule.' },
  { key: 'enrollment-summary', title: 'Enrollment Summary', description: 'Grouped enrollee counts across geography, gender, type, and status.', icon: 'mdi-account-group-outline', tone: 'success', formats: ['pdf', 'excel', 'csv'] },
  { key: 'mobile-enrollment-activity', title: 'Mobile Enrollment Activity', description: 'Operational feed for mobile enrollment and officer activity.', icon: 'mdi-cellphone-arrow-down', tone: 'info', formats: ['pdf', 'excel', 'csv'] },
  { key: 'offline-sync-summary', title: 'Offline Sync Summary', description: 'Sync throughput, duplicates, failures, and processing time metrics.', icon: 'mdi-sync-alert', tone: 'warning', formats: ['pdf', 'excel', 'csv'] },
  { key: 'facility-utilization', title: 'Facility Utilization', description: 'Provider usage, referral/admission/claim counts, and claim amounts.', icon: 'mdi-hospital-box-outline', tone: 'secondary', formats: ['pdf', 'excel', 'csv'] },
  { key: 'referral-preauth', title: 'Referral Pre-Auth', description: 'Referral status breakdown and approval turnaround analytics.', icon: 'mdi-file-send-outline', tone: 'warning', formats: ['pdf', 'excel', 'csv'] },
  { key: 'admission', title: 'Admission Report', description: 'Admissions, discharge counts, and ward-day averages by facility.', icon: 'mdi-bed-outline', tone: 'info', formats: ['pdf', 'excel', 'csv'] },
  { key: 'capitation', title: 'Capitation Report', description: 'Computed capitation, paid amounts, and outstanding balances by period.', icon: 'mdi-cash-sync', tone: 'success', formats: ['pdf', 'excel', 'csv'] },
  { key: 'financial-liability', title: 'Financial Liability', description: 'Outstanding approved claims broken into aging buckets.', icon: 'mdi-scale-balance', tone: 'danger', formats: ['pdf', 'excel', 'csv'] },
  { key: 'payment', title: 'Payment Report', description: 'Claim payment batch status and total amount tracking.', icon: 'mdi-bank-transfer-out', tone: 'primary', formats: ['pdf', 'excel', 'csv'] },
  { key: 'rejected-claims', title: 'Rejected Claims', description: 'Rejected claim counts grouped by facility and rejection reason.', icon: 'mdi-close-circle-outline', tone: 'danger', formats: ['pdf', 'excel', 'csv'] },
  { key: 'audit-activity', title: 'Audit Activity', description: 'Action-level audit aggregation for governance and compliance review.', icon: 'mdi-clipboard-text-clock-outline', tone: 'secondary', formats: ['pdf', 'excel', 'csv'] },
  { key: 'user-activity', title: 'User Activity', description: 'Aggregated user actions grouped from the audit trail.', icon: 'mdi-account-clock-outline', tone: 'info', formats: ['pdf', 'excel', 'csv'] },
]

const todoReports = [
  { key: 'benefactor-utilization', title: 'Benefactor Utilization', description: 'Benefactor-level utilization and spend analysis.', icon: 'mdi-hand-heart-outline', todo: 'No dedicated backend report type exists yet for benefactor-level operational reporting.' },
  { key: 'lga-ward-coverage', title: 'LGA / Ward Coverage', description: 'Geographic enrollment coverage and facility distribution metrics.', icon: 'mdi-map-marker-radius-outline', todo: 'Needs a backend report that joins enrollee coverage, LGAs, wards, and facility counts.' },
  { key: 'renewal-churn', title: 'Renewal & Churn', description: 'Premium renewal expiry, churn, and reactivation analysis.', icon: 'mdi-refresh-circle-outline', todo: 'Coverage and renewal analytics are not yet exposed through the reporting endpoint.' },
  { key: 'approval-aging', title: 'Enrollment Approval Aging', description: 'Queue aging for pending enrollee approvals by facility, LGA, and funding source.', icon: 'mdi-timer-sand', todo: 'Needs a new reporting endpoint backed by enrollment approval timestamps and dimensions.' },
  { key: 'service-entitlement', title: 'Benefit Package Service Entitlement', description: 'Package-level allowed service utilization and exceptions.', icon: 'mdi-shield-plus-outline', todo: 'Requires backend entitlement modeling before a trustworthy report can be generated.' },
  { key: 'claims-tat', title: 'Claims Turnaround Time', description: 'Submission-to-review and review-to-payment turnaround metrics.', icon: 'mdi-timeline-clock-outline', todo: 'No dedicated turnaround-time reporting endpoint exists yet.' },
]

const activeFilterCount = computed(() => [reportForm.value.from_date, reportForm.value.to_date, reportForm.value.format].filter(Boolean).length)

async function fetchData() {
  loading.value = true
  try {
    const [overviewRes, activityRes] = await Promise.allSettled([
      dashboardAPI.getOverview(),
      dashboardAPI.getRecentActivities(),
    ])

    if (overviewRes.status === 'fulfilled') {
      const data = overviewRes.value.data?.data ?? overviewRes.value.data
      stats.value = {
        total_enrollees: data?.total_enrollees ?? data?.enrollees?.total ?? 0,
        active_facilities: data?.active_facilities ?? data?.facilities?.active ?? 0,
        total_referrals: data?.total_referrals ?? data?.referrals?.total ?? 0,
        paid_claims: data?.paid_claims ?? data?.claims?.paid ?? 0,
      }
    }

    if (activityRes.status === 'fulfilled') {
      const data = activityRes.value.data?.data ?? activityRes.value.data
      activities.value = Array.isArray(data) ? data : (data?.activities ?? data?.data ?? [])
    }
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  reportForm.value = {
    from_date: '',
    to_date: '',
    format: 'pdf',
  }
}

function previewReport(report) {
  selectedReport.value = report
  reportDialog.value = true
}

async function generateReport(report) {
  generating.value = true
  activeReportKey.value = report.key
  try {
    const response = await api.get(`/reports/${report.key}`, {
      params: {
        ...reportForm.value,
      },
      responseType: 'blob',
      showGlobalLoader: true,
      loaderTitle: 'Generating report',
      loaderSubtitle: `Preparing ${report.title.toLowerCase()}`,
    })

    const blob = new Blob([response.data], {
      type: response.headers['content-type'] || 'application/octet-stream',
    })
    const downloadUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    const extension = reportForm.value.format.toLowerCase()
    link.href = downloadUrl
    link.download = `${report.key}-${new Date().toISOString().slice(0, 10)}.${extension}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(downloadUrl)
    success(`${report.title} downloaded successfully`)
    reportDialog.value = false
  } catch (err) {
    error(err?.response?.data?.message || `Failed to generate ${report.title}`)
  } finally {
    generating.value = false
    activeReportKey.value = ''
  }
}

function formatRelative(dateValue) {
  if (!dateValue) return ''
  const diff = Date.now() - new Date(dateValue).getTime()
  const minutes = Math.floor(diff / 60000)
  if (minutes < 1) return 'just now'
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  return new Date(dateValue).toLocaleDateString('en-NG', { day: '2-digit', month: 'short' })
}

onMounted(fetchData)
</script>
