<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Claims Review" icon="mdi-clipboard-check-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadClaims">Refresh</v-btn>
      </AppPageHeader>

      <AppFilterBar :active-count="activeFilterCount" :cols="5" @clear="resetFilters">
        <v-text-field
          v-model="searchQuery"
          label="Search"
          placeholder="Claim #, UTN, enrollee, facility"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-select
          v-model="statusFilter"
          label="Status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
        <v-text-field
          v-model="dateFrom"
          label="From Date"
          type="date"
          density="compact"
          variant="outlined"
          hide-details
        />
        <v-text-field
          v-model="dateTo"
          label="To Date"
          type="date"
          density="compact"
          variant="outlined"
          hide-details
        />
        <template #tags>
          <AppBadge v-if="statusFilter" :label="`Status: ${statusFilter}`" tone="warning" size="sm" />
          <AppBadge v-if="dateFrom" :label="`From: ${dateFrom}`" tone="secondary" size="sm" />
          <AppBadge v-if="dateTo" :label="`To: ${dateTo}`" tone="secondary" size="sm" />
        </template>
        <template #actions>
          <v-btn size="small" color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="applyFilters">Load</v-btn>
        </template>
      </AppFilterBar>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
        <AppStatCard compact label="Total Claims" :value="claims.length" icon="mdi-file-document-multiple-outline" color="primary" :loading="loading" />
        <AppStatCard compact label="Selected" :value="selectedClaims.length" icon="mdi-checkbox-multiple-marked-outline" color="info" :loading="loading" />
        <AppStatCard compact label="Submitted Amount" :value="formattedTotalAmount" icon="mdi-cash-multiple" color="warning" :loading="loading" />
        <AppStatCard compact label="Selected Amount" :value="formattedSelectedAmount" icon="mdi-cash-check" color="success" :loading="loading" />
      </div>

      <AppErrorState
        v-if="loadError"
        title="Unable to load claims"
        :message="loadError"
      >
        <v-btn color="primary" variant="flat" @click="loadClaims">Retry</v-btn>
      </AppErrorState>

      <AppBulkActions
        :count="selectedClaims.length"
        title="Bulk claim actions"
      >
        <v-btn
          color="success"
          variant="flat"
          prepend-icon="mdi-check-all"
          :disabled="!hasBulkReviewableClaims"
          @click="openApproveDialog()"
        >
          Approve Selected
        </v-btn>
        <v-btn
          color="error"
          variant="outlined"
          prepend-icon="mdi-close-circle-outline"
          :disabled="!hasBulkReviewableClaims"
          @click="openRejectDialog()"
        >
          Reject Selected
        </v-btn>
      </AppBulkActions>

      <AppCard
        title="Claims Queue"
        icon="mdi-clipboard-list-outline"
        tone="primary"
        :padded="false"
      >
        <AppDataTable
          :headers="headers"
          :items="claims"
          :loading="loading"
          item-value="id"
          show-select
          v-model:model-value="selectedClaims"
          class="tw-rounded-none tw-border-0"
        >
          <template #item.claim_number="{ item }">
            <button class="tw-text-left tw-font-semibold tw-text-cyan-700 hover:tw-underline" @click="viewClaimDetails(item.id)">
              {{ item.claim_number }}
            </button>
          </template>
          <template #item.enrollee="{ item }">
            <div class="tw-font-medium tw-text-slate-900">{{ item.enrollee?.full_name || 'N/A' }}</div>
            <div class="tw-text-xs tw-text-slate-500">{{ item.enrollee?.enrollee_id || 'No enrollee ID' }}</div>
          </template>
          <template #item.facility="{ item }">
            <div class="tw-font-medium tw-text-slate-900">{{ item.facility?.name || 'N/A' }}</div>
            <div class="tw-text-xs tw-text-slate-500">{{ item.facility?.hcp_code || 'No facility code' }}</div>
          </template>
          <template #item.total_amount_claimed="{ item }">
            <MoneyDisplay :value="item.total_amount_claimed" />
          </template>
          <template #item.status="{ item }">
            <ClaimStatusBadge :status="item.status" :label="item.status" size="sm" />
          </template>
          <template #item.submitted_at="{ item }">
            <DateDisplay :value="item.submitted_at || item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-items-center tw-justify-end tw-gap-1">
              <v-btn icon size="small" variant="text" title="View" @click="viewClaimDetails(item.id)">
                <v-icon>mdi-eye-outline</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="success"
                :disabled="!canReviewClaim(item)"
                title="Approve"
                @click="openApproveDialog(item)"
              >
                <v-icon>mdi-check-circle-outline</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                :disabled="!canReviewClaim(item)"
                title="Reject"
                @click="openRejectDialog(item)"
              >
                <v-icon>mdi-close-circle-outline</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="info" title="Download slip" @click="downloadSlip(item)">
                <v-icon>mdi-download-outline</v-icon>
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              title="No claims found"
              description="Adjust your status, search, or date filters to find matching claim submissions."
              icon="mdi-file-search-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppCard
        v-if="selectedClaimForDetails"
        title="Claim Details"
        icon="mdi-file-document-outline"
        tone="secondary"
      >
        <div class="tw-grid tw-gap-4 lg:tw-grid-cols-[minmax(0,1.3fr)_minmax(0,0.7fr)]">
          <div class="tw-space-y-4">
            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
              <AppCard title="Claim Summary" icon="mdi-information-outline" tone="primary">
                <div class="tw-space-y-2 tw-text-sm">
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Claim Number</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.claim_number || 'N/A' }}</span></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">UTN</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.utn || 'N/A' }}</span></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Status</span><ClaimStatusBadge :status="selectedClaimForDetails.status" :label="selectedClaimForDetails.status" size="sm" /></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Submitted</span><DateDisplay :value="selectedClaimForDetails.submitted_at || selectedClaimForDetails.created_at" format="short" /></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Amount Claimed</span><MoneyDisplay :value="selectedClaimForDetails.total_amount_claimed" /></div>
                </div>
              </AppCard>

              <AppCard title="Member & Facility" icon="mdi-account-heart-outline" tone="secondary">
                <div class="tw-space-y-2 tw-text-sm">
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Enrollee</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.enrollee?.full_name || 'N/A' }}</span></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Enrollee ID</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.enrollee?.enrollee_id || 'N/A' }}</span></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Facility</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.facility?.name || 'N/A' }}</span></div>
                  <div class="tw-flex tw-justify-between tw-gap-3"><span class="tw-text-slate-500">Facility Code</span><span class="tw-font-semibold tw-text-slate-900">{{ selectedClaimForDetails.facility?.hcp_code || 'N/A' }}</span></div>
                </div>
              </AppCard>
            </div>

            <AppCard
              v-if="claimAlerts.length"
              title="Validation Alerts"
              icon="mdi-alert-outline"
              tone="warning"
            >
              <div class="tw-space-y-3">
                <AppBadge
                  v-for="(alert, index) in claimAlerts"
                  :key="index"
                  :label="alert.message"
                  :tone="alertTone(alert.severity)"
                  :icon="alertIcon(alert.severity)"
                />
              </div>
            </AppCard>
          </div>

          <AppCard title="Review Guidance" icon="mdi-shield-lock-outline" tone="warning">
            <div class="tw-space-y-2 tw-text-xs tw-text-slate-600">
              <p>Draft claims cannot be approved or rejected until submitted.</p>
              <p>All actions call live backend endpoints — no local-only success states.</p>
            </div>
          </AppCard>
        </div>
      </AppCard>

      <AppConfirmDialog
        v-model="approveDialog"
        title="Approve claims"
        subtitle="This will send the selected claims through the backend approval workflow."
        :message="approveDialogMessage"
        warning="Only submitted or reviewing claims should be approved from this queue."
        confirm-text="Approve claims"
        icon="mdi-check-decagram-outline"
        tone="success"
        :loading="processing"
        @cancel="closeApproveDialog"
        @confirm="confirmApprove"
        @update:model-value="handleApproveDialogChange"
      />

      <AppModal
        v-model="rejectDialog"
        title="Reject claims"
        subtitle="A rejection reason is required for auditability."
        icon="mdi-close-circle-outline"
        size="md"
        :loading="processing"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="processing" @click="closeRejectDialog">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="processing" @click="confirmReject">
            Reject claims
          </v-btn>
        </template>

        <div class="tw-space-y-4">
          <AppAlert
            tone="warning"
            :message="rejectDialogMessage"
          />
          <v-textarea
            v-model="rejectionReason"
            label="Rejection reason"
            rows="4"
            variant="outlined"
            density="comfortable"
            hide-details="auto"
          />
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppBulkActions from '../common/AppBulkActions.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppErrorState from '../common/AppErrorState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import ClaimStatusBadge from '../common/ClaimStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'
import { claimsAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const loading = ref(false)
const processing = ref(false)
const claims = ref([])
const selectedClaims = ref([])
const selectedClaimForDetails = ref(null)
const searchQuery = ref('')
const statusFilter = ref('SUBMITTED')
const dateFrom = ref('')
const dateTo = ref('')
const loadError = ref('')

const approveDialog = ref(false)
const rejectDialog = ref(false)
const actionClaimIds = ref([])
const rejectionReason = ref('')

const statusOptions = [
  { title: 'All statuses', value: null },
  { title: 'Draft', value: 'DRAFT' },
  { title: 'Submitted', value: 'SUBMITTED' },
  { title: 'Reviewing', value: 'REVIEWING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
]

const headers = [
  { title: 'Claim Number', key: 'claim_number' },
  { title: 'UTN', key: 'utn' },
  { title: 'Enrollee', key: 'enrollee' },
  { title: 'Facility', key: 'facility' },
  { title: 'Amount Claimed', key: 'total_amount_claimed' },
  { title: 'Status', key: 'status' },
  { title: 'Submitted Date', key: 'submitted_at' },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const activeFilterCount = computed(() => [searchQuery.value, statusFilter.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const selectedClaimRows = computed(() => claims.value.filter((claim) => selectedClaims.value.includes(claim.id)))
const totalSubmittedAmount = computed(() => claims.value.reduce((sum, claim) => sum + Number(claim.total_amount_claimed || 0), 0))
const selectedAmount = computed(() => selectedClaimRows.value.reduce((sum, claim) => sum + Number(claim.total_amount_claimed || 0), 0))
const hasBulkReviewableClaims = computed(() => selectedClaimRows.value.length > 0 && selectedClaimRows.value.every(canReviewClaim))
const formatNGN = (val) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(val)
const formattedTotalAmount = computed(() => formatNGN(totalSubmittedAmount.value))
const formattedSelectedAmount = computed(() => formatNGN(selectedAmount.value))
const claimAlerts = computed(() => selectedClaimForDetails.value?.alerts || [])

const approveDialogMessage = computed(() => {
  const count = actionClaimIds.value.length
  return count === 1
    ? 'Approve this submitted claim now?'
    : `Approve ${count.toLocaleString()} selected claims now?`
})

const rejectDialogMessage = computed(() => {
  const count = actionClaimIds.value.length
  return count === 1
    ? 'Provide a clear reason for rejecting this claim.'
    : `Provide one rejection reason for ${count.toLocaleString()} selected claims.`
})

function canReviewClaim(claim) {
  return ['SUBMITTED', 'REVIEWING'].includes(String(claim?.status || '').toUpperCase())
}

const alertTone = (severity) => ({
  critical: 'danger',
  error: 'danger',
  warning: 'warning',
  info: 'info',
  success: 'success',
}[String(severity || '').toLowerCase()] || 'neutral')

const alertIcon = (severity) => ({
  critical: 'mdi-alert-circle-outline',
  error: 'mdi-alert-circle-outline',
  warning: 'mdi-alert-outline',
  info: 'mdi-information-outline',
  success: 'mdi-check-circle-outline',
}[String(severity || '').toLowerCase()] || 'mdi-circle-medium')

const loadClaims = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const params = {}
    if (statusFilter.value) params.status = statusFilter.value
    if (searchQuery.value) params.search = searchQuery.value
    if (dateFrom.value) params.date_from = dateFrom.value
    if (dateTo.value) params.date_to = dateTo.value

    const response = await claimsAPI.getAll(params)
    const payload = response.data?.data ?? response.data ?? []
    claims.value = Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : [])
    selectedClaims.value = selectedClaims.value.filter((id) => claims.value.some((claim) => claim.id === id))
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Failed to load claims'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  loadClaims()
}

const resetFilters = () => {
  searchQuery.value = ''
  statusFilter.value = 'SUBMITTED'
  dateFrom.value = ''
  dateTo.value = ''
  loadClaims()
}

const viewClaimDetails = async (claimId) => {
  try {
    const response = await claimsAPI.getFullDetails(claimId)
    selectedClaimForDetails.value = response.data?.data || response.data
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load claim details')
  }
}

const openApproveDialog = (claim = null) => {
  const ids = claim ? [claim.id] : [...selectedClaims.value]
  const targetClaims = claims.value.filter((item) => ids.includes(item.id))
  if (!targetClaims.length || !targetClaims.every(canReviewClaim)) {
    error('Only submitted or reviewing claims can be approved')
    return
  }

  actionClaimIds.value = ids
  approveDialog.value = true
}

const closeApproveDialog = () => {
  approveDialog.value = false
  actionClaimIds.value = []
}

const handleApproveDialogChange = (value) => {
  approveDialog.value = value
  if (!value) actionClaimIds.value = []
}

const confirmApprove = async () => {
  if (!actionClaimIds.value.length) return
  processing.value = true
  try {
    await claimsAPI.batchApprove({
      claim_ids: actionClaimIds.value,
      comments: 'Approved from PAS review queue',
    })
    success(actionClaimIds.value.length === 1 ? 'Claim approved successfully' : 'Claims approved successfully')
    selectedClaims.value = selectedClaims.value.filter((id) => !actionClaimIds.value.includes(id))
    closeApproveDialog()
    await loadClaims()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to approve claims')
  } finally {
    processing.value = false
  }
}

const openRejectDialog = (claim = null) => {
  const ids = claim ? [claim.id] : [...selectedClaims.value]
  const targetClaims = claims.value.filter((item) => ids.includes(item.id))
  if (!targetClaims.length || !targetClaims.every(canReviewClaim)) {
    error('Only submitted or reviewing claims can be rejected')
    return
  }

  actionClaimIds.value = ids
  rejectionReason.value = ''
  rejectDialog.value = true
}

const closeRejectDialog = () => {
  rejectDialog.value = false
  rejectionReason.value = ''
  actionClaimIds.value = []
}

const confirmReject = async () => {
  if (!rejectionReason.value.trim()) {
    error('Rejection reason is required')
    return
  }

  processing.value = true
  try {
    await claimsAPI.batchReject({
      claim_ids: actionClaimIds.value,
      reason: rejectionReason.value.trim(),
    })
    success(actionClaimIds.value.length === 1 ? 'Claim rejected successfully' : 'Claims rejected successfully')
    selectedClaims.value = selectedClaims.value.filter((id) => !actionClaimIds.value.includes(id))
    closeRejectDialog()
    await loadClaims()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to reject claims')
  } finally {
    processing.value = false
  }
}

const downloadSlip = async (claim) => {
  try {
    const response = await claimsAPI.downloadSlip(claim.id)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `claim-slip-${claim.claim_number}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    error(err.response?.data?.message || 'Failed to download claim slip')
  }
}

onMounted(loadClaims)
</script>
