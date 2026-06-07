<template>
  <AdminLayout>
    <div class="qds-page-shell">
      <AppPageHeader title="Claims Dashboard" icon="mdi-file-document-multiple-outline" icon-color="secondary">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadStatistics">Refresh</v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4 xl:tw-grid-cols-7">
        <AppStatCard compact label="Total Claims" :value="overview.total_claims || 0" icon="mdi-file-document-multiple" color="secondary" :loading="loading" />
        <AppStatCard compact label="Awaiting Review" :value="queue.awaiting_review || 0" icon="mdi-clock-outline" color="warning" :loading="loading" />
        <AppStatCard compact label="Approved This Month" :value="month.approved || 0" icon="mdi-check-decagram" color="success" :loading="loading" />
        <AppStatCard compact label="Total Referrals" :value="overview.total_referrals || 0" icon="mdi-hospital-box-outline" color="info" :loading="loading" />
        <AppStatCard compact label="My Queue" :value="queue.my_queue || 0" icon="mdi-account-switch-outline" color="primary" :loading="loading" />
        <AppStatCard compact label="Approval Rate" :value="`${month.approval_rate_percent || 0}%`" icon="mdi-chart-arc" color="success" :loading="loading" />
        <AppStatCard compact label="Avg Review Time" :value="`${today.avg_review_time_minutes || 0} min`" icon="mdi-timer-outline" color="info" :loading="loading" />
      </div>

      <div v-if="loadError">
        <AppErrorState title="Claims dashboard unavailable" :message="loadError">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-refresh" @click="loadStatistics">Retry</v-btn>
        </AppErrorState>
      </div>

      <div class="tw-grid tw-grid-cols-1 tw-gap-4 xl:tw-grid-cols-2">
        <AppCard title="Claims Queue" icon="mdi-format-list-checks" tone="secondary">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 sm:tw-grid-cols-2">
            <div class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Currently Reviewing</p>
              <p class="tw-mt-1.5 tw-text-xl tw-font-semibold tw-text-slate-950">{{ queue.currently_reviewing || 0 }}</p>
            </div>
            <div class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Claims Over 7 Days</p>
              <p class="tw-mt-1.5 tw-text-xl tw-font-semibold tw-text-slate-950">{{ turnaround.claims_over_7_days || 0 }}</p>
            </div>
          </div>
          <div class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-file-search-outline" to="/claims/review">Open Review Queue</v-btn>
            <v-btn color="primary" variant="outlined" prepend-icon="mdi-cash-multiple" to="/claims/payment-batches">Payment Batches</v-btn>
          </div>
        </AppCard>

        <AppCard title="Recent Adjudications" icon="mdi-history" tone="info">
          <div v-if="recentAdjudications.length" class="tw-space-y-2">
            <div
              v-for="item in recentAdjudications.slice(0, 6)"
              :key="item.id"
              class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2"
            >
              <div class="tw-min-w-0">
                <p class="tw-font-medium tw-text-slate-900">Claim #{{ item.claim_number || item.id }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ item.facility?.name || 'Facility unavailable' }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ item.enrollee?.first_name }} {{ item.enrollee?.last_name }}</p>
              </div>
              <div class="tw-flex tw-flex-col tw-items-end tw-gap-2">
                <ClaimStatusBadge :status="item.status" size="sm" />
                <DateDisplay :value="item.updated_at" format="short" />
              </div>
            </div>
          </div>
          <AppEmptyState
            v-else
            icon="mdi-history"
            title="No adjudications yet"
            description="As claims are approved or rejected, they will appear here for quick operational review."
          />
        </AppCard>
      </div>

      <AppCard title="Facility Workload" icon="mdi-hospital-building" tone="warning">
        <div v-if="byFacility.length" class="tw-overflow-x-auto">
          <table class="tw-min-w-full tw-text-sm">
            <thead class="tw-bg-slate-50">
              <tr class="tw-text-left tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">
                <th class="tw-px-4 tw-py-3">Facility</th>
                <th class="tw-px-4 tw-py-3">Total</th>
                <th class="tw-px-4 tw-py-3">Submitted</th>
                <th class="tw-px-4 tw-py-3">Approved</th>
                <th class="tw-px-4 tw-py-3">Rejected</th>
                <th class="tw-px-4 tw-py-3">Pending</th>
              </tr>
            </thead>
            <tbody class="tw-divide-y tw-divide-slate-200">
              <tr v-for="facility in byFacility" :key="facility.facility_name">
                <td class="tw-px-4 tw-py-3 tw-font-medium tw-text-slate-900">{{ facility.facility_name }}</td>
                <td class="tw-px-4 tw-py-3">{{ facility.total }}</td>
                <td class="tw-px-4 tw-py-3">{{ facility.submitted }}</td>
                <td class="tw-px-4 tw-py-3">{{ facility.approved }}</td>
                <td class="tw-px-4 tw-py-3">{{ facility.rejected }}</td>
                <td class="tw-px-4 tw-py-3">{{ facility.pending }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <AppEmptyState
          v-else
          icon="mdi-file-chart-outline"
          title="No facility workload data"
          description="Facility-level claim summaries will appear here once submissions start flowing."
        />
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppCard from '../common/AppCard.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppErrorState from '../common/AppErrorState.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import ClaimStatusBadge from '../common/ClaimStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import api from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error } = useToast()

const loading = ref(false)
const loadError = ref('')
const overview = ref({})
const queue = ref({})
const today = ref({})
const month = ref({})
const turnaround = ref({})
const byFacility = ref([])
const recentAdjudications = ref([])

const loadStatistics = async () => {
  loading.value = true
  loadError.value = ''

  try {
    const response = await api.get('/dashboard/claims')
    const payload = response.data?.data ?? response.data ?? {}

    overview.value = payload.overview || {}
    queue.value = payload.queue || {}
    today.value = payload.today || {}
    month.value = payload.this_month || {}
    turnaround.value = payload.turnaround || {}
    byFacility.value = payload.by_facility || []
    recentAdjudications.value = payload.recent_adjudications || []
  } catch (err) {
    loadError.value = err?.response?.data?.message || 'Unable to load claims dashboard.'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

loadStatistics()
</script>
