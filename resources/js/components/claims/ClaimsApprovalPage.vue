<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <AppPageHeader
        title="Claims Approval & Payment Prep"
        subtitle="Select review-ready claims, confirm approval inputs, and push them through the backend batch approval flow."
        kicker="Claims Operations"
        icon="mdi-cash-check"
      >
        <template #meta>
          <AppBadge label="Backend-confirmed actions only" tone="success" icon="mdi-database-check-outline" />
          <AppBadge :label="`${selectedClaims.length.toLocaleString()} selected`" tone="info" icon="mdi-checkbox-multiple-marked-outline" />
        </template>

        <v-btn variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadClaims">
          Refresh
        </v-btn>
      </AppPageHeader>

      <AppFilterBar :active-count="activeFilterCount" :cols="5" @clear="resetFilters">
        <v-text-field
          v-model="searchQuery"
          label="Search"
          placeholder="Claim #, UTN, enrollee"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          @keyup.enter="loadClaims"
        />
        <v-text-field
          v-model="monthFilter"
          label="Month"
          type="month"
          density="compact"
          variant="outlined"
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
        <div class="tw-flex tw-items-end">
          <v-btn color="primary" block prepend-icon="mdi-filter-check-outline" :loading="loading" @click="loadClaims">
            Apply Filters
          </v-btn>
        </div>

        <template #tags>
          <AppBadge v-if="monthFilter" :label="`Month: ${monthFilter}`" tone="secondary" />
          <AppBadge v-if="dateFrom" :label="`From: ${dateFrom}`" tone="secondary" />
          <AppBadge v-if="dateTo" :label="`To: ${dateTo}`" tone="secondary" />
        </template>
      </AppFilterBar>

      <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-4">
        <AppMetricCard title="Claims Loaded" icon="mdi-file-document-multiple-outline" tone="primary">
          <template #value>{{ claims.length.toLocaleString() }}</template>
        </AppMetricCard>
        <AppMetricCard title="Selected Claims" icon="mdi-checkbox-multiple-marked-outline" tone="success">
          <template #value>{{ selectedClaims.length.toLocaleString() }}</template>
        </AppMetricCard>
        <AppMetricCard title="Loaded Amount" icon="mdi-cash-multiple" tone="warning">
          <template #value><MoneyDisplay :value="totalAmount" /></template>
        </AppMetricCard>
        <AppMetricCard title="Selected Amount" icon="mdi-cash-check" tone="info">
          <template #value><MoneyDisplay :value="selectedAmount" /></template>
        </AppMetricCard>
      </div>

      <AppErrorState
        v-if="loadError"
        title="Unable to load approval queue"
        :message="loadError"
      >
        <v-btn color="primary" variant="flat" @click="loadClaims">Retry</v-btn>
      </AppErrorState>

      <AppBulkActions
        :count="selectedClaims.length"
        title="Bulk approval ready"
        subtitle="Only submitted or reviewing claims can be approved from this workspace."
      >
        <v-btn
          color="success"
          variant="flat"
          prepend-icon="mdi-check-all"
          :disabled="!canApproveSelection"
          @click="showApprovalDialog = true"
        >
          Approve Selected
        </v-btn>
      </AppBulkActions>

      <AppCard
        title="Approval Queue"
        subtitle="Use this page to finalize approval inputs for selected claims before payment batch work continues."
        icon="mdi-format-list-checks"
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
              <v-btn icon size="small" variant="text" color="info" title="Download slip" @click="downloadSlip(item)">
                <v-icon>mdi-download-outline</v-icon>
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              title="No claims ready for approval"
              description="Try a different search, month, or date range to find the claims you need."
              icon="mdi-file-search-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppCard
        v-if="selectedClaimForDetails"
        title="Selected Claim Details"
        :subtitle="selectedClaimForDetails.claim_number || 'Claim detail view'"
        icon="mdi-file-document-outline"
        tone="secondary"
      >
        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-4">
          <AppMetricCard title="Claim Status" icon="mdi-information-outline" tone="secondary">
            <template #value>
              <ClaimStatusBadge :status="selectedClaimForDetails.status" :label="selectedClaimForDetails.status" />
            </template>
          </AppMetricCard>
          <AppMetricCard title="Amount Claimed" icon="mdi-cash" tone="warning">
            <template #value><MoneyDisplay :value="selectedClaimForDetails.total_amount_claimed" /></template>
          </AppMetricCard>
          <AppMetricCard title="Submitted" icon="mdi-calendar-clock-outline" tone="info">
            <template #value><DateDisplay :value="selectedClaimForDetails.submitted_at || selectedClaimForDetails.created_at" format="short" /></template>
          </AppMetricCard>
          <AppMetricCard title="Facility" icon="mdi-hospital-box-outline" tone="primary">
            <template #value>{{ selectedClaimForDetails.facility?.name || 'N/A' }}</template>
          </AppMetricCard>
        </div>
      </AppCard>

      <AppModal
        v-model="showApprovalDialog"
        title="Approve Selected Claims"
        subtitle="Provide the approval data required by the backend before processing."
        icon="mdi-check-decagram-outline"
        size="lg"
        :loading="approving"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="approving" @click="showApprovalDialog = false">Cancel</v-btn>
          <v-btn color="success" variant="flat" :loading="approving" @click="submitApproval">
            Approve & Process
          </v-btn>
        </template>

        <div class="tw-space-y-5">
          <AppAlert
            tone="info"
            :message="`You are preparing approval for ${selectedClaims.length.toLocaleString()} claim(s) worth ${selectedAmountLabel}.`"
          />

          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <v-text-field
              v-model="approvalData.payment_code"
              label="Payment Code / Reference"
              variant="outlined"
              density="comfortable"
              hide-details="auto"
            />
            <v-text-field
              :model-value="selectedAmountLabel"
              label="Selected Amount"
              variant="outlined"
              density="comfortable"
              readonly
              hide-details
            />
          </div>

          <v-textarea
            v-model="approvalData.approval_comments"
            label="Approval Comments"
            variant="outlined"
            rows="4"
            hide-details="auto"
          />

          <div class="tw-grid tw-gap-3 md:tw-grid-cols-2">
            <v-checkbox
              v-model="approvalData.generate_approval_letter"
              label="Generate approval letter"
              hide-details
            />
            <v-checkbox
              v-model="approvalData.generate_payment_receipts"
              label="Generate payment receipts"
              hide-details
            />
          </div>
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
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppErrorState from '../common/AppErrorState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppMetricCard from '../common/AppMetricCard.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import ClaimStatusBadge from '../common/ClaimStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'
import { claimsAPI } from '../../utils/api'
import { useToast } from '@/js/composables/useToast'

const { success, error } = useToast()

const loading = ref(false)
const approving = ref(false)
const loadError = ref('')
const claims = ref([])
const selectedClaims = ref([])
const selectedClaimForDetails = ref(null)
const searchQuery = ref('')
const monthFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const showApprovalDialog = ref(false)

const approvalData = ref({
  payment_code: '',
  approval_comments: '',
  generate_approval_letter: true,
  generate_payment_receipts: true,
})

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

const activeFilterCount = computed(() => [searchQuery.value, monthFilter.value, dateFrom.value, dateTo.value].filter(Boolean).length)
const totalAmount = computed(() => claims.value.reduce((sum, claim) => sum + Number(claim.total_amount_claimed || 0), 0))
const selectedAmount = computed(() => claims.value
  .filter((claim) => selectedClaims.value.includes(claim.id))
  .reduce((sum, claim) => sum + Number(claim.total_amount_claimed || 0), 0))
const selectedAmountLabel = computed(() => new Intl.NumberFormat('en-NG', {
  style: 'currency',
  currency: 'NGN',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
}).format(selectedAmount.value))
const canApproveSelection = computed(() => {
  if (!selectedClaims.value.length) return false
  const selectedRows = claims.value.filter((claim) => selectedClaims.value.includes(claim.id))
  return selectedRows.every((claim) => ['SUBMITTED', 'REVIEWING'].includes(String(claim.status || '').toUpperCase()))
})

const loadClaims = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const params = {}
    if (searchQuery.value) params.search = searchQuery.value
    if (monthFilter.value) params.month = monthFilter.value
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

const viewClaimDetails = async (claimId) => {
  try {
    const response = await claimsAPI.getFullDetails(claimId)
    selectedClaimForDetails.value = response.data?.data || response.data
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load claim details')
  }
}

const submitApproval = async () => {
  if (!approvalData.value.payment_code.trim()) {
    error('Payment code is required')
    return
  }
  if (!canApproveSelection.value) {
    error('Only submitted or reviewing claims can be approved')
    return
  }

  approving.value = true
  try {
    await claimsAPI.batchApprove({
      claim_ids: selectedClaims.value,
      approval_comments: approvalData.value.approval_comments,
      payment_code: approvalData.value.payment_code.trim(),
      generate_approval_letter: approvalData.value.generate_approval_letter,
      generate_payment_receipts: approvalData.value.generate_payment_receipts,
    })

    success(`${selectedClaims.value.length} claim(s) approved successfully`)
    showApprovalDialog.value = false
    selectedClaims.value = []
    approvalData.value = {
      payment_code: '',
      approval_comments: '',
      generate_approval_letter: true,
      generate_payment_receipts: true,
    }
    await loadClaims()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to approve claims')
  } finally {
    approving.value = false
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

const resetFilters = () => {
  searchQuery.value = ''
  monthFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  loadClaims()
}

onMounted(loadClaims)
</script>
