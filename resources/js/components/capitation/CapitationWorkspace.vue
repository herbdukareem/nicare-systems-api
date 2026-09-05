<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader :title="title" icon="mdi-cash-sync">
        <v-btn
          v-for="action in visibleWorkflowActions"
          :key="action.path"
          size="small"
          variant="outlined"
          :prepend-icon="action.icon"
          @click="$router.push(action.path)"
        >
          {{ action.name }}
        </v-btn>
      </AppPageHeader>

      
      <!-- Generate: create period -->
      <div v-if="mode === 'generate'" class="qds-card qds-card-padding tw-space-y-4">
        <div>
          <h2 class="tw-text-sm tw-font-semibold tw-text-gray-900">Capitation Period</h2>
          <p class="tw-text-xs tw-text-gray-500">Create the monthly capitation period first. Eligibility is always cut off on the 20th of the selected month and year, while facility generation is done separately by period and funding type.</p>
        </div>
        <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-4">
          <v-text-field v-model="form.name" label="Period name" density="comfortable" variant="outlined" />
          <v-select v-model="form.capitation_month" :items="months" item-title="name" item-value="value" label="Capitation month" density="comfortable" variant="outlined" />
          <v-text-field v-model.number="form.year" label="Capitation year" type="number" density="comfortable" variant="outlined" />
        </div>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save" @click="createPeriod">Create Period</v-btn>
          <v-btn variant="tonal" prepend-icon="mdi-refresh" @click="loadPeriods">Refresh Periods</v-btn>
        </div>
      </div>

      <!-- Generate: facility capitation -->
      <div v-if="mode === 'generate'" class="qds-card qds-card-padding tw-space-y-4">
        <div>
          <h2 class="tw-text-sm tw-font-semibold tw-text-gray-900">Generate Facility Capitation</h2>
          <p class="tw-text-xs tw-text-gray-500">Select the period and funding type, load facilities, review enrollee counts and totals, then generate only the selected facilities.</p>
        </div>
        <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-3">
          <v-select v-model="generationForm.period_id" :items="periodOptions" item-title="label" item-value="id" label="Capitation period" density="comfortable" variant="outlined" :disabled="facilitiesLoaded" />
          <v-select v-model="generationForm.funding_type_id" :items="fundingTypes" item-title="name" item-value="id" label="Funding type" density="comfortable" variant="outlined" :disabled="facilitiesLoaded" />
          <v-select v-model="generationForm.duplicate_nin_policy" :items="duplicateNinPolicyOptions" item-title="label" item-value="value" label="Duplicate NIN policy" density="comfortable" variant="outlined" :disabled="facilitiesLoaded" />
        </div>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" :loading="eligibleLoading" prepend-icon="mdi-hospital-building" @click="loadFacilitiesForGeneration">Load Facilities</v-btn>
          <v-btn variant="tonal" prepend-icon="mdi-refresh" @click="resetGenerationFlow">Reset</v-btn>
        </div>

        <div v-if="facilitiesLoaded" class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3 tw-space-y-3">
          <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
            <div>
              <p class="tw-text-sm tw-text-gray-500">Loaded facilities for</p>
              <h3 class="tw-text-lg tw-font-bold tw-text-gray-900">{{ generationPeriod?.name }}</h3>
            </div>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn size="small" variant="tonal" @click="toggleAllProviders">
                {{ selectedProviderCount === selectableProviders.length ? 'Clear Selection' : 'Select All Ungenerated' }}
              </v-btn>
              <v-btn color="primary" :loading="saving" :disabled="selectedProviderCount === 0" prepend-icon="mdi-calculator" @click="generateLoadedFacilities">
                Generate Selected Facilities
              </v-btn>
            </div>
          </div>

          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-6">
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Funding Type</p>
              <p class="tw-font-bold">{{ selectedGenerationFundingType?.name || 'N/A' }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Duplicate NIN Policy</p>
              <p class="tw-font-bold">{{ duplicateNinPolicyLabel(generationForm.duplicate_nin_policy) }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Facilities Loaded</p>
              <p class="tw-font-bold">{{ eligibleProviders.length }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Eligible Enrollees</p>
              <p class="tw-font-bold">{{ eligibleProviderTotals.enrollees.toLocaleString() }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Rate</p>
              <p class="tw-font-bold">NGN {{ Number(selectedGenerationFundingType?.capitation_rate || 0).toLocaleString() }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Selected Amount</p>
              <p class="tw-font-bold">NGN {{ selectedProviderTotal.amount.toLocaleString() }}</p>
            </div>
          </div>

          <AppDataTable
            v-model="selectedProviderIds"
            :headers="providerHeaders"
            :items="eligibleProviders"
            :loading="eligibleLoading"
            item-value="facility_id"
            item-selectable="selectable"
            show-select
            density="compact"
          >
            <template #item.is_generated="{ item }">
              <v-chip size="small" :color="item.is_generated ? 'info' : 'warning'" variant="flat">
                {{ item.is_generated ? 'Generated' : 'Not Generated' }}
              </v-chip>
            </template>
            <template #item.total_amount="{ item }">
              NGN {{ Number(item.total_amount || 0).toLocaleString() }}
            </template>
          </AppDataTable>
        </div>
      </div>

      <!-- Workflow panel (review / approval / payments) -->
      <div v-if="workflowMode" class="qds-card qds-card-padding tw-space-y-4">
        <div>
          <h2 class="tw-text-sm tw-font-semibold tw-text-gray-900">{{ workflowTitle }}</h2>
          <p class="tw-text-xs tw-text-gray-500">{{ workflowDescription }}</p>
        </div>
        <div v-if="mode === 'approval'" class="tw-flex tw-items-start tw-gap-2 tw-border tw-border-cyan-200 tw-bg-cyan-50 tw-p-3 tw-text-sm tw-text-cyan-900">
          <v-icon size="18" class="tw-mt-0.5">mdi-information-outline</v-icon>
          <p>Select a funding type to view approval and payment figures for that funding type only. The period list below updates to match your selection; click <strong>Load Details</strong> to inspect the reviewed records awaiting approval.</p>
        </div>
        <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-3">
          <v-select v-model="workflowForm.period_id" :items="periodOptions" item-title="label" item-value="id" label="Capitation period" density="comfortable" variant="outlined" />
          <v-select v-model="workflowForm.funding_type_id" :items="fundingTypes" item-title="name" item-value="id" label="Funding type" density="comfortable" variant="outlined" clearable />
          <div class="tw-flex tw-items-start tw-gap-2">
            <v-btn color="primary" :loading="workflowLoading" prepend-icon="mdi-format-list-bulleted" @click="loadWorkflowDetails">Load Details</v-btn>
          </div>
        </div>

        <div v-if="workflowDetailsLoaded" class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3 tw-space-y-3">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-4">
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Facilities</p>
              <p class="tw-font-bold">{{ workflowDetails.length }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Enrollees</p>
              <p class="tw-font-bold">{{ workflowTotals.enrollees.toLocaleString() }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Amount</p>
              <p class="tw-font-bold">NGN {{ workflowTotals.amount.toLocaleString() }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Selected</p>
              <p class="tw-font-bold">{{ selectedDetailIds.length }}</p>
            </div>
          </div>

          <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-end tw-gap-2">
            <v-btn variant="outlined" color="teal" prepend-icon="mdi-printer" :disabled="workflowDetails.length === 0" @click="printWorkflowInvoice">
              Print Invoice
            </v-btn>
            <v-btn color="primary" variant="flat" :loading="saving" :disabled="selectedDetailIds.length === 0" @click="runWorkflowAction">
              {{ workflowActionLabel }}
            </v-btn>
          </div>

          <AppDataTable
            v-model="selectedDetailIds"
            :headers="workflowHeaders"
            :items="workflowDetails"
            :loading="workflowLoading"
            item-value="id"
            show-select
            density="compact"
          >
            <template #no-data>
              <AppEmptyState
                :icon="workflowEmptyState.icon"
                :title="workflowEmptyState.title"
                :description="workflowEmptyState.description"
              />
            </template>
            <template #item.facility="{ item }">{{ item.facility?.name || 'N/A' }}</template>
            <template #item.funding_type="{ item }">{{ periodFundingTypeName(item) }}</template>
            <template #item.total_amount="{ item }">NGN {{ Number(item.total_amount || item.amount || 0).toLocaleString() }}</template>
            <template #item.stage="{ item }">
              <v-chip size="small" :color="detailStatusColor(item)" variant="flat">{{ detailStatusLabel(item) }}</v-chip>
            </template>
          </AppDataTable>
        </div>
      </div>

      <!-- Period list -->
      <AppDataTable
        :headers="periodHeaders"
        :items="periods"
        :loading="loading"
        :items-per-page="25"
        searchable
        search-placeholder="Search periods..."
        density="comfortable"
      >
        <template #item.status="{ item }">
          <v-chip size="small" :color="periodStatusColor(item)" variant="flat">
            {{ periodStatusLabel(item) }}
          </v-chip>
        </template>
        <template #item.approval_status="{ item }">
          <v-chip size="small" :color="approvalStatusColor(item)" variant="flat">
            {{ approvalStatusLabel(item) }}
          </v-chip>
        </template>
        <template #item.payment_status="{ item }">
          <v-chip size="small" :color="paymentStatusColor(item)" variant="flat">
            {{ paymentStatusLabel(item) }}
          </v-chip>
        </template>
        <template #item.approved_value="{ item }">NGN {{ formatAmount(item.approved_value) }}</template>
        <template #item.paid_value="{ item }">NGN {{ formatAmount(item.paid_value) }}</template>
        <template #item.actions="{ item }">
          <div class="tw-flex tw-gap-1">
            <v-btn icon="mdi-eye" size="small" variant="text" color="primary" title="View breakdown" @click="openBreakdown(item)" />
            <v-btn icon="mdi-printer" size="small" variant="text" color="teal" title="Print invoice" @click="printPeriodQuickInvoice(item)" />
            <v-btn v-if="canExportRemita(item)" icon="mdi-file-excel" size="small" variant="text" title="Export Remita payment format" @click="exportRemitaPeriod(item)" />
          </div>
        </template>
      </AppDataTable>

      <!-- ── Breakdown dialog ── -->
      <AppModal
        v-model="breakdownDialog"
        :title="`${selectedPeriod?.name || ''} ${breakdownStageLabel} Breakdown`"
        icon="mdi-chart-bar"
        size="lg"
      >
        <template #actions>
          <div class="tw-mr-auto tw-flex tw-items-center tw-gap-2">
            <v-chip size="small" color="primary" variant="flat">{{ breakdownTotals.enrollees }} enrollees</v-chip>
            <span class="tw-text-xs tw-text-slate-400">{{ filteredBreakdown.length }} facilities</span>
          </div>
          <v-btn variant="outlined" @click="breakdownDialog = false">Close</v-btn>
          <v-btn color="teal" variant="flat" prepend-icon="mdi-printer" @click="printBreakdownInvoice">Print Invoice</v-btn>
          <v-btn v-if="canExport" color="success" variant="flat" prepend-icon="mdi-file-excel" :disabled="filteredBreakdown.length === 0" @click="exportBreakdownExcel">Export Excel</v-btn>
          <v-btn v-if="canExportRemita(selectedPeriod)" color="primary" variant="flat" prepend-icon="mdi-file-excel" @click="exportRemitaPeriod(selectedPeriod)">Export Remita Format</v-btn>
        </template>

        <div class="tw-space-y-4">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <v-select
              v-model="breakdownFundingTypeId"
              :items="breakdownFundingTypes"
              item-title="name"
              item-value="id"
              label="Funding type"
              density="comfortable"
              variant="outlined"
              clearable
            />
            <v-text-field
              v-model="breakdownSearch"
              label="Search facility"
              prepend-inner-icon="mdi-magnify"
              density="comfortable"
              variant="outlined"
              clearable
            />
          </div>

          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-3">
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Facilities</p>
              <p class="tw-text-xl tw-font-bold">{{ filteredBreakdown.length }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Total Enrollees</p>
              <p class="tw-text-xl tw-font-bold">{{ breakdownTotals.enrollees }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Total Amount</p>
              <p class="tw-text-xl tw-font-bold">NGN {{ breakdownTotals.amount.toLocaleString() }}</p>
            </div>
          </div>

          <div class="tw-overflow-x-auto tw-border tw-border-gray-100">
            <table class="tw-min-w-full tw-text-sm">
              <thead class="tw-bg-slate-100 tw-text-left tw-text-xs tw-font-semibold tw-text-slate-600 tw-uppercase tw-tracking-wide">
                <tr>
                  <th class="tw-px-4 tw-py-3">Facility</th>
                  <th class="tw-px-4 tw-py-3">Funding Type</th>
                  <th class="tw-px-4 tw-py-3">Enrollees</th>
                  <th class="tw-px-4 tw-py-3">Rate</th>
                  <th class="tw-px-4 tw-py-3">Amount</th>
                </tr>
              </thead>
              <tbody class="tw-divide-y tw-divide-gray-100">
                <tr
                  v-for="(item, index) in filteredBreakdown"
                  :key="item.id || `${item.facility_id}-${index}`"
                  class="tw-transition-colors hover:tw-bg-slate-50"
                >
                  <td class="tw-px-4 tw-py-3 tw-font-medium tw-text-slate-800">{{ item.facility?.name || 'N/A' }}</td>
                  <td class="tw-px-4 tw-py-3 tw-text-slate-600">{{ periodFundingTypeName(item) }}</td>
                  <td class="tw-px-4 tw-py-3">{{ Number(item.total_enrollees || item.total_enrolled || 0).toLocaleString() }}</td>
                  <td class="tw-px-4 tw-py-3">{{ Number(item.capitation_rate || item.rate || 0).toLocaleString() }}</td>
                  <td class="tw-px-4 tw-py-3 tw-font-semibold tw-text-slate-900">NGN {{ Number(item.total_amount || item.amount || 0).toLocaleString() }}</td>
                </tr>
                <tr v-if="filteredBreakdown.length === 0">
                  <td colspan="5" class="tw-px-4 tw-py-10 tw-text-center tw-text-slate-400">No facilities found for this filter.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </AppModal>

      <!-- ── Provider selection dialog ── -->
      <AppModal
        v-model="providerDialog"
        title="Generate Selected Providers"
        icon="mdi-calculator"
        size="xl"
        :loading="saving"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="providerDialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            :disabled="selectedProviderCount === 0"
            prepend-icon="mdi-calculator"
            @click="generateSelectedProviders"
          >
            Generate Selected
          </v-btn>
        </template>

        <div class="tw-space-y-4">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-4">
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Funding Type</p>
              <p class="tw-font-bold">{{ periodFundingTypeName(selectedPeriod) }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Capitation Rate</p>
              <p class="tw-font-bold">NGN {{ Number(selectedPeriod?.capitation_rate || 0).toLocaleString() }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Eligible Providers</p>
              <p class="tw-font-bold">{{ eligibleProviders.length }}</p>
            </div>
            <div class="tw-border tw-border-gray-100 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Selected Amount</p>
              <p class="tw-font-bold tw-text-cyan-700">NGN {{ selectedProviderTotal.amount.toLocaleString() }}</p>
            </div>
          </div>

          <div class="tw-flex tw-justify-end">
            <v-btn size="small" variant="tonal" @click="toggleAllProviders">
              {{ selectedProviderCount === selectableProviders.length ? 'Clear Selection' : 'Select All Ungenerated' }}
            </v-btn>
          </div>

          <AppDataTable
            v-model="selectedProviderIds"
            :headers="providerHeaders"
            :items="eligibleProviders"
            :loading="eligibleLoading"
            item-value="facility_id"
            item-selectable="selectable"
            show-select
            density="compact"
            :items-per-page="50"
          >
            <template #item.is_generated="{ item }">
              <v-chip size="small" :color="item.is_generated ? 'info' : 'warning'" variant="flat">
                {{ item.is_generated ? 'Generated' : 'Not Generated' }}
              </v-chip>
            </template>
            <template #item.total_amount="{ item }">
              NGN {{ Number(item.total_amount || 0).toLocaleString() }}
            </template>
          </AppDataTable>
        </div>
      </AppModal>

      <!-- ── Payment confirmation dialog ── -->
      <AppModal
        v-model="paymentDialog"
        title="Confirm Capitation Payment"
        subtitle="Enter payment reference and date to finalise"
        icon="mdi-cash-check"
        size="sm"
        color="success"
        :loading="saving"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="paymentDialog = false">Cancel</v-btn>
          <v-btn color="success" variant="flat" :loading="saving" prepend-icon="mdi-check" @click="markPaid">Mark as Paid</v-btn>
        </template>

        <div class="tw-space-y-4">
          <div class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3 tw-text-sm tw-space-y-1">
            <div class="tw-flex tw-gap-2">
              <span class="tw-text-slate-500 tw-w-28 tw-flex-shrink-0">Period:</span>
              <span class="tw-font-semibold tw-text-slate-800">{{ selectedPeriod?.name }}</span>
            </div>
            <div class="tw-flex tw-gap-2">
              <span class="tw-text-slate-500 tw-w-28 tw-flex-shrink-0">Cutoff date:</span>
              <span class="tw-font-semibold tw-text-slate-800">{{ formatDate(selectedPeriod?.period_start) }}</span>
            </div>
          </div>
          <v-text-field v-model="paymentForm.payment_reference" label="Payment reference" density="comfortable" variant="outlined" />
          <v-text-field v-model="paymentForm.payment_date" label="Payment date" type="date" density="comfortable" variant="outlined" />
          <v-textarea v-model="paymentForm.description" label="Description" rows="2" density="comfortable" variant="outlined" />
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import { capitationAPI, fundingTypeAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({ mode: { type: String, default: 'generate' } })
const { success, error, info } = useToast()
const authStore = useAuthStore()
const loading = ref(false)
const saving = ref(false)
const periods = ref([])
const breakdown = ref([])
const breakdownSearch = ref('')
const breakdownFundingTypeId = ref(null)
const fundingTypes = ref([])
const eligibleProviders = ref([])
const selectedProviderIds = ref([])
const workflowDetails = ref([])
const selectedDetailIds = ref([])
const selectedPeriod = ref(null)
const generationPeriod = ref(null)
const breakdownDialog = ref(false)
const paymentDialog = ref(false)
const providerDialog = ref(false)
const eligibleLoading = ref(false)
const workflowLoading = ref(false)
const facilitiesLoaded = ref(false)
const workflowDetailsLoaded = ref(false)
const form = ref({
  name: '',
  capitation_month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
})
const generationForm = ref({ period_id: null, funding_type_id: null, duplicate_nin_policy: 'exclude' })
const workflowForm = ref({ period_id: null, funding_type_id: null })

const months = [
  { name: 'January', value: 1 }, { name: 'February', value: 2 }, { name: 'March', value: 3 },
  { name: 'April', value: 4 }, { name: 'May', value: 5 }, { name: 'June', value: 6 },
  { name: 'July', value: 7 }, { name: 'August', value: 8 }, { name: 'September', value: 9 },
  { name: 'October', value: 10 }, { name: 'November', value: 11 }, { name: 'December', value: 12 },
]
const duplicateNinPolicyOptions = [
  { label: 'Exclude duplicate NINs', value: 'exclude' },
  { label: 'Allow duplicate NINs', value: 'include' },
]
const paymentForm = ref({
  payment_reference: '',
  payment_date: new Date().toISOString().slice(0, 10),
  description: '',
})

const title = computed(() => ({
  generate: 'Generate Capitation',
  review: 'Review Capitation',
  approval: 'Capitation Approval',
  payments: 'Capitation Payments',
})[props.mode] || 'Capitation')

const workflowActions = [
  { name: 'Generate', path: '/capitation/generate', icon: 'mdi-plus-circle-outline', permissions: ['capitation.create', 'capitation.compute'] },
  { name: 'Review', path: '/capitation/review', icon: 'mdi-eye-outline', permissions: ['capitation.review'] },
  { name: 'Approval', path: '/capitation/approval', icon: 'mdi-check-circle-outline', permissions: ['capitation.approve', 'capitation.finalise'] },
  { name: 'Payments', path: '/capitation/payments', icon: 'mdi-receipt-text-outline', permissions: ['capitation.pay'] },
]
const hasAnyPermission = (permissions) => permissions.some((p) => authStore.hasPermission(p))
const visibleWorkflowActions = computed(() => workflowActions.filter((a) => hasAnyPermission(a.permissions)))
const canExport = computed(() => authStore.hasPermission('capitation.export'))
const canExportRemita = (period) => {
  const total = Number(period?.capitation_details_count || 0)
  const paid = Number(period?.paid_count || 0)

  return props.mode === 'payments' && canExport.value && total > 0 && paid === total
}

const workflowMode = computed(() => ['review', 'approval', 'payments'].includes(props.mode))
const workflowStage = computed(() => ({ review: 'review', approval: 'approval', payments: 'payment' })[props.mode] || 'generated')
const workflowTitle = computed(() => ({ review: 'Review Generated Facility Capitations', approval: 'Approve Reviewed Facility Capitations', payments: 'Pay Approved Facility Capitations' })[props.mode] || '')
const workflowDescription = computed(() => ({
  review: 'Select a period to load only generated facility capitation details that are waiting for review.',
  approval: 'Select a period to load only reviewed facility capitation details that are waiting for approval.',
  payments: 'Select a period to load only approved facility capitation details that are waiting for payment.',
})[props.mode] || '')
const workflowActionLabel = computed(() => ({ review: 'Review Selected', approval: 'Approve Selected', payments: 'Pay Selected' })[props.mode] || 'Process Selected')
const breakdownStage = computed(() => ({ review: 'reviewed', approval: 'approved', payments: 'paid' })[props.mode] || 'generated')
const breakdownStageLabel = computed(() => ({ generated: 'Generated', reviewed: 'Reviewed', approved: 'Approved', paid: 'Paid' })[breakdownStage.value] || 'Generated')

const duplicateNinPolicyLabel = (value) => {
  const normalized = String(value || 'exclude')
  return duplicateNinPolicyOptions.find((item) => item.value === normalized)?.label || 'Exclude duplicate NINs'
}

const periodFundingTypeName = (period) => {
  if (!period) return 'N/A'
  if (period.funding_type?.name) return period.funding_type.name
  if (period.funding_type?.label) return period.funding_type.label
  if (period.funding_type_summary) return period.funding_type_summary
  if (Array.isArray(period.funding_types) && period.funding_types.length) {
    return period.funding_types.map((item) => item?.name).filter(Boolean).join(', ')
  }
  return 'N/A'
}

const periodOptions = computed(() => periods.value.map((p) => ({
  ...p,
  label: `#${p.id} - ${p.name} (Cutoff: ${formatDate(p.period_start)}) - ${p.capitation_details_count || 0} generated`,
})))

const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const STATUS_COL_HEADERS = {
  generate: 'Generation Status',
  review:   'Reviewal Status',
  approval: 'Approval Status',
  payments: 'Payment Status',
}

const periodHeaders = computed(() => {
  const headers = [
    { title: 'Name', key: 'name' },
    { title: 'Capitation Year', key: 'year' },
    { title: 'Generated Facilities', key: 'capitation_details_count' },
  ]

  if (props.mode === 'approval') {
    headers.push(
      { title: 'Approval Status', key: 'approval_status' },
      { title: 'Approved No.', key: 'approved_count' },
      { title: 'Approved Value', key: 'approved_value' },
      { title: 'Payment Status', key: 'payment_status' },
      { title: 'Paid No.', key: 'paid_count' },
      { title: 'Paid Value', key: 'paid_value' },
    )
  } else {
    headers.push({ title: 'Status', key: 'status' })
  }

  headers.push({ title: '', key: 'actions', sortable: false, align: 'end' })

  return headers
})
const providerHeaders = [
  { title: 'Provider', key: 'facility_name' },
  { title: 'HCP Code', key: 'hcp_code' },
  { title: 'LGA', key: 'lga' },
  { title: 'Enrollees', key: 'total_enrollees' },
  { title: 'Rate', key: 'capitation_rate' },
  { title: 'Amount', key: 'total_amount' },
  { title: 'Generation Status', key: 'is_generated' },
]
const workflowHeaders = computed(() => [
  { title: 'Facility', key: 'facility' },
  { title: 'Funding Type', key: 'funding_type' },
  { title: 'Enrollees', key: 'total_enrollees' },
  { title: 'Rate', key: 'capitation_rate' },
  { title: 'Amount', key: 'total_amount' },
  { title: STATUS_COL_HEADERS[props.mode] || 'Stage', key: 'stage' },
])

const breakdownFundingTypes = computed(() => {
  const map = new Map()
  breakdown.value.forEach((row) => {
    if (row.funding_type?.id) map.set(Number(row.funding_type.id), { id: Number(row.funding_type.id), name: row.funding_type.name })
  })
  return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name))
})
const filteredBreakdown = computed(() => {
  const search = breakdownSearch.value?.toLowerCase().trim()
  return breakdown.value.filter((row) => {
    const matchesFundingType = !breakdownFundingTypeId.value || Number(row.funding_type?.id) === Number(breakdownFundingTypeId.value)
    const matchesSearch = !search || (row.facility?.name || '').toLowerCase().includes(search)
    return matchesFundingType && matchesSearch
  })
})
const breakdownTotals = computed(() => ({
  enrollees: filteredBreakdown.value.reduce((sum, row) => sum + Number(row.total_enrollees || row.total_enrolled || 0), 0),
  amount: filteredBreakdown.value.reduce((sum, row) => sum + Number(row.total_amount || row.amount || 0), 0),
}))

const selectableProviders = computed(() => eligibleProviders.value.filter((item) => !item.is_generated))
const selectedProviderIdValues = computed(() =>
  selectedProviderIds.value
    .map((item) => Number(typeof item === 'object' ? item?.facility_id : item))
    .filter((id) => Number.isInteger(id) && selectableProviders.value.some((p) => Number(p.facility_id) === id)),
)
const selectedProviderCount = computed(() => selectedProviderIdValues.value.length)
const selectedGenerationFundingType = computed(() => fundingTypes.value.find((item) => Number(item.id) === Number(generationForm.value.funding_type_id)) || null)
const selectedWorkflowPeriod = computed(() => periods.value.find((item) => Number(item.id) === Number(workflowForm.value.period_id)) || null)
const selectedWorkflowFundingType = computed(() => fundingTypes.value.find((item) => Number(item.id) === Number(workflowForm.value.funding_type_id)) || null)
const eligibleProviderTotals = computed(() => ({
  enrollees: eligibleProviders.value.reduce((sum, row) => sum + Number(row.total_enrollees || 0), 0),
  amount: eligibleProviders.value.reduce((sum, row) => sum + Number(row.total_amount || 0), 0),
}))
const selectedProviderTotal = computed(() => {
  const selected = eligibleProviders.value.filter((item) => selectedProviderIdValues.value.includes(Number(item.facility_id)))
  return {
    enrollees: selected.reduce((sum, row) => sum + Number(row.total_enrollees || 0), 0),
    amount: selected.reduce((sum, row) => sum + Number(row.total_amount || 0), 0),
  }
})
const workflowTotals = computed(() => ({
  enrollees: workflowDetails.value.reduce((sum, row) => sum + Number(row.total_enrollees || row.total_enrolled || 0), 0),
  amount: workflowDetails.value.reduce((sum, row) => sum + Number(row.total_amount || row.amount || 0), 0),
}))
const workflowEmptyState = computed(() => {
  const fundingTypeName = selectedWorkflowFundingType.value?.name
  const filterContext = fundingTypeName ? ` for ${fundingTypeName}` : ''
  const periodContext = fundingTypeName ? `${fundingTypeName} in this period` : 'this period'

  if (!workflowDetailsLoaded.value || workflowDetails.value.length > 0) {
    return {
      icon: 'mdi-table-off',
      title: 'No capitation details found',
      description: 'No capitation details matched the selected filters.',
    }
  }

  const period = selectedWorkflowPeriod.value
  if (!period) {
    return {
      icon: 'mdi-table-off',
      title: 'No capitation details found',
      description: 'No capitation details matched the selected filters.',
    }
  }

  const detailCount = Number(period.capitation_details_count || 0)
  const pendingReviewCount = Number(period.pending_review_count || 0)
  const reviewedCount = Number(period.reviewed_count || 0)
  const pendingApprovalCount = Number(period.pending_approval_count || 0)
  const approvedCount = Number(period.approved_count || 0)
  const pendingPaymentCount = Number(period.pending_payment_count || 0)
  const paidCount = Number(period.paid_count || 0)

  if (detailCount === 0) {
    return {
      icon: 'mdi-file-document-outline',
      title: 'No generated capitation yet',
      description: `This period does not have any generated facility capitation${filterContext}. Generate capitation first before continuing.`,
    }
  }

  if (props.mode === 'review') {
    if (reviewedCount >= detailCount && pendingReviewCount === 0) {
      return {
        icon: 'mdi-check-decagram-outline',
        title: 'All generated details already reviewed',
        description: `There are no generated facility capitation rows waiting for review in ${periodContext}.`,
      }
    }

    return {
      icon: 'mdi-filter-off-outline',
      title: 'No review queue for this filter',
      description: `No generated facility capitation rows are waiting for review in ${periodContext}.`,
    }
  }

  if (props.mode === 'approval') {
    if (approvedCount >= detailCount && pendingApprovalCount === 0) {
      return {
        icon: 'mdi-check-decagram-outline',
        title: 'All reviewed details already approved',
        description: `There are no reviewed facility capitation rows waiting for approval in ${periodContext}.`,
      }
    }

    if (reviewedCount === 0) {
      return {
        icon: 'mdi-timer-sand',
        title: 'Nothing is ready for approval yet',
        description: `This period does not have any reviewed facility capitation rows ready for approval${filterContext}.`,
      }
    }

    return {
      icon: 'mdi-filter-off-outline',
      title: 'No approval queue for this filter',
      description: `No reviewed facility capitation rows are waiting for approval in ${periodContext}.`,
    }
  }

  if (props.mode === 'payments') {
    if (paidCount >= detailCount && pendingPaymentCount === 0) {
      return {
        icon: 'mdi-check-decagram-outline',
        title: 'All capitation details already paid',
        description: `There are no approved unpaid facility capitation rows left in the payment queue for ${periodContext}.`,
      }
    }

    if (approvedCount === 0) {
      return {
        icon: 'mdi-timer-sand',
        title: 'Nothing is ready for payment yet',
        description: `This period does not have any approved facility capitation rows ready for payment${filterContext}.`,
      }
    }

    return {
      icon: 'mdi-filter-off-outline',
      title: 'No payment queue for this filter',
      description: `No approved unpaid facility capitation rows are waiting for payment in ${periodContext}.`,
    }
  }

  return {
    icon: 'mdi-table-off',
    title: 'No capitation details found',
    description: 'No capitation details matched the selected filters.',
  }
})

const periodStatusLabel = (item) => {
  const detailCount = Number(item.capitation_details_count || 0)
  const pendingReviewCount = Number(item.pending_review_count || 0)
  const reviewedCount = Number(item.reviewed_count || 0)
  const pendingApprovalCount = Number(item.pending_approval_count || 0)
  const approvedCount = Number(item.approved_count || 0)
  const pendingPaymentCount = Number(item.pending_payment_count || 0)
  const paidCount = Number(item.paid_count || 0)

  if (props.mode === 'review') {
    if (detailCount === 0) return 'Not Generated'
    if (pendingReviewCount > 0) return 'Pending Review'
    if (reviewedCount > 0) return 'Reviewed'
    return 'Generated'
  }

  if (props.mode === 'approval') {
    return approvalStatusLabel(item)
  }

  if (props.mode === 'payments') {
    if (detailCount === 0) return 'Not Generated'
    if (pendingPaymentCount > 0) return 'Pending Payment'
    if (paidCount > 0) return 'Paid'
    if (approvedCount > 0) return 'Awaiting Payment'
    if (reviewedCount > 0) return 'Awaiting Approval'
    return 'Awaiting Review'
  }

  if (item.status) return 'Finalised'
  if (detailCount > 0) return 'Generated'
  if (item.computed_at) return 'Computed'
  return 'Draft'
}

const approvalStatusLabel = (item) => {
  const detailCount = Number(item.capitation_details_count || 0)
  const pendingReviewCount = Number(item.pending_review_count || 0)
  const pendingApprovalCount = Number(item.pending_approval_count || 0)
  const approvedCount = Number(item.approved_count || 0)

  if (detailCount === 0) return 'Not Generated'
  if (pendingReviewCount > 0) return 'Pending Review'
  if (pendingApprovalCount > 0) return 'Pending Approval'
  if (approvedCount >= detailCount) return 'Approved'
  if (approvedCount > 0) return 'Partially Approved'
  return 'Awaiting Review'
}

const approvalStatusColor = (item) => {
  const label = approvalStatusLabel(item)

  if (label === 'Approved') return 'success'
  if (['Pending Review', 'Pending Approval'].includes(label)) return 'warning'
  if (['Partially Approved', 'Awaiting Review'].includes(label)) return 'info'
  return 'default'
}

const paymentStatusLabel = (item) => {
  const detailCount = Number(item.capitation_details_count || 0)
  const approvedCount = Number(item.approved_count || 0)
  const pendingPaymentCount = Number(item.pending_payment_count || 0)
  const paidCount = Number(item.paid_count || 0)

  if (detailCount === 0) return 'Not Generated'
  if (paidCount >= detailCount) return 'Paid'
  if (paidCount > 0) return 'Partially Paid'
  if (pendingPaymentCount > 0) return 'Pending Payment'
  if (approvedCount > 0) return 'Awaiting Payment'
  return 'Awaiting Approval'
}

const paymentStatusColor = (item) => {
  const label = paymentStatusLabel(item)

  if (label === 'Paid') return 'success'
  if (label === 'Partially Paid') return 'info'
  if (label === 'Pending Payment') return 'warning'
  return 'default'
}

const formatAmount = (value) => Number(value || 0).toLocaleString(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

const periodStatusColor = (item) => {
  const label = periodStatusLabel(item)

  if (['Finalised', 'Approved', 'Paid', 'Reviewed'].includes(label)) return 'success'
  if (['Generated', 'Computed', 'Awaiting Approval', 'Awaiting Payment'].includes(label)) return 'info'
  if (['Pending Review', 'Pending Approval', 'Pending Payment'].includes(label)) return 'warning'
  return 'default'
}

const buildPeriodParams = () => {
  const params = { per_page: 100 }

  if (workflowMode.value && workflowForm.value.funding_type_id) {
    params.funding_type_id = workflowForm.value.funding_type_id
  }

  return params
}

const loadFundingTypes = async () => {
  try {
    const response = await fundingTypeAPI.getAll({ per_page: 500 })
    const payload = response.data?.data
    fundingTypes.value = payload?.data || payload || []
  } catch {
    error('Failed to load funding types')
  }
}

const loadPeriods = async () => {
  loading.value = true
  try {
    const response = await capitationAPI.periods(buildPeriodParams())
    const payload = response.data.data
    periods.value = payload.data || payload || []
  } catch {
    error('Failed to load capitation periods')
  } finally {
    loading.value = false
  }
}

const createPeriod = async (showMessage = true) => {
  saving.value = true
  try {
    const response = await capitationAPI.createPeriod(form.value)
    if (showMessage) success('Capitation period created')
    await loadPeriods()
    return response.data?.data
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to create capitation period')
    return null
  } finally {
    saving.value = false
  }
}

const loadFacilitiesForGeneration = async () => {
  if (!generationForm.value.period_id || !generationForm.value.funding_type_id || !generationForm.value.duplicate_nin_policy) {
    error('Select a capitation period, funding type, and duplicate NIN policy before loading facilities')
    return
  }
  eligibleLoading.value = true
  try {
    generationPeriod.value = periods.value.find((item) => Number(item.id) === Number(generationForm.value.period_id)) || null
    if (!generationPeriod.value) return
    const response = await capitationAPI.eligibleProviders(generationPeriod.value.id, {
      funding_type_id: generationForm.value.funding_type_id,
      duplicate_nin_policy: generationForm.value.duplicate_nin_policy,
    })
    eligibleProviders.value = response.data?.data || []
    selectedProviderIds.value = []
    facilitiesLoaded.value = true
    if (eligibleProviders.value.length === 0) {
      error('No eligible facilities found for the selected funding type and period')
    } else {
      success('Facilities loaded for capitation generation')
    }
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to load facilities')
  } finally {
    eligibleLoading.value = false
  }
}

const resetGenerationFlow = () => {
  generationPeriod.value = null
  selectedPeriod.value = null
  eligibleProviders.value = []
  selectedProviderIds.value = []
  generationForm.value = { period_id: null, funding_type_id: null, duplicate_nin_policy: 'exclude' }
  facilitiesLoaded.value = false
}

const openProviderSelection = async (period) => {
  selectedPeriod.value = period
  providerDialog.value = true
  eligibleLoading.value = true
  selectedProviderIds.value = []
  try {
    eligibleProviders.value = (await capitationAPI.eligibleProviders(period.id, {
      funding_type_id: period.funding_type_id,
      duplicate_nin_policy: generationForm.value.duplicate_nin_policy || 'exclude',
    })).data.data || []
    selectedProviderIds.value = []
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to load eligible providers')
  } finally {
    eligibleLoading.value = false
  }
}

const toggleAllProviders = () => {
  if (selectedProviderCount.value === selectableProviders.value.length) {
    selectedProviderIds.value = []
  } else {
    selectedProviderIds.value = selectableProviders.value.map((item) => item.facility_id)
  }
}

const generateSelectedProviders = async () => {
  if (!selectedPeriod.value) return
  saving.value = true
  try {
    await capitationAPI.compute(selectedPeriod.value.id, {
      funding_type_id: selectedPeriod.value.funding_type_id,
      duplicate_nin_policy: generationForm.value.duplicate_nin_policy || 'exclude',
      facility_ids: selectedProviderIdValues.value,
    })
    success('Capitation computed')
    providerDialog.value = false
    await loadPeriods()
    await openBreakdown(selectedPeriod.value)
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to compute capitation')
  } finally {
    saving.value = false
  }
}

const generateLoadedFacilities = async () => {
  if (!generationPeriod.value) return
  saving.value = true
  try {
    await capitationAPI.compute(generationPeriod.value.id, {
      funding_type_id: generationForm.value.funding_type_id,
      duplicate_nin_policy: generationForm.value.duplicate_nin_policy,
      facility_ids: selectedProviderIdValues.value,
    })
    success('Capitation generated for selected facilities')
    const response = await capitationAPI.eligibleProviders(generationPeriod.value.id, {
      funding_type_id: generationForm.value.funding_type_id,
      duplicate_nin_policy: generationForm.value.duplicate_nin_policy,
    })
    eligibleProviders.value = response.data?.data || []
    selectedProviderIds.value = []
    await loadPeriods()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to generate capitation')
  } finally {
    saving.value = false
  }
}

const loadWorkflowDetails = async ({ notifyWhenEmpty = false } = {}) => {
  if (!workflowForm.value.period_id) { error('Select a capitation period first'); return }
  workflowLoading.value = true
  selectedDetailIds.value = []
  try {
    const response = await capitationAPI.details(workflowForm.value.period_id, {
      stage: workflowStage.value,
      funding_type_id: workflowForm.value.funding_type_id || undefined,
    })
    workflowDetails.value = response.data?.data || []
    selectedDetailIds.value = workflowDetails.value.map((item) => item.id)
    workflowDetailsLoaded.value = true
    if (notifyWhenEmpty && workflowDetails.value.length === 0) {
      info('No capitation details found for this stage and period')
    }
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to load capitation details')
  } finally {
    workflowLoading.value = false
  }
}

const runWorkflowAction = async () => {
  if (!workflowForm.value.period_id || selectedDetailIds.value.length === 0) return
  if (props.mode === 'payments') {
    selectedPeriod.value = periods.value.find((item) => Number(item.id) === Number(workflowForm.value.period_id)) || null
    paymentForm.value = {
      payment_reference: '',
      payment_date: new Date().toISOString().slice(0, 10),
      description: `Capitation payment for ${selectedPeriod.value?.name || 'selected period'}`,
    }
    paymentDialog.value = true
    return
  }
  saving.value = true
  try {
    if (props.mode === 'review') {
      await capitationAPI.reviewDetails(workflowForm.value.period_id, { detail_ids: selectedDetailIds.value })
      success('Selected capitation details reviewed')
    } else if (props.mode === 'approval') {
      await capitationAPI.approveDetails(workflowForm.value.period_id, { detail_ids: selectedDetailIds.value })
      success('Selected capitation details approved')
    }
    await Promise.all([
      loadWorkflowDetails(),
      loadPeriods(),
    ])
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to process selected capitation details')
  } finally {
    saving.value = false
  }
}

const openPayment = (period) => {
  selectedPeriod.value = period
  paymentForm.value = {
    payment_reference: '',
    payment_date: new Date().toISOString().slice(0, 10),
    description: `Capitation payment for ${period.name}`,
  }
  paymentDialog.value = true
}

const markPaid = async () => {
  if (props.mode === 'payments' && workflowForm.value.period_id) {
    saving.value = true
    try {
      await capitationAPI.payDetails(workflowForm.value.period_id, { ...paymentForm.value, detail_ids: selectedDetailIds.value })
      success('Selected capitation details paid')
      paymentDialog.value = false
      await Promise.all([
        loadWorkflowDetails(),
        loadPeriods(),
      ])
    } catch (err) {
      error(err?.response?.data?.message || 'Failed to pay selected capitation details')
    } finally {
      saving.value = false
    }
    return
  }
  if (!selectedPeriod.value) return
  saving.value = true
  try {
    await capitationAPI.pay(selectedPeriod.value.id, paymentForm.value)
    success('Capitation payment confirmed')
    paymentDialog.value = false
    await loadPeriods()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to confirm capitation payment')
  } finally {
    saving.value = false
  }
}

const openBreakdown = async (period) => {
  selectedPeriod.value = period
  breakdown.value = (await capitationAPI.breakdown(period.id, { stage: breakdownStage.value })).data.data || []
  breakdownSearch.value = ''
  breakdownFundingTypeId.value = breakdownFundingTypes.value.length === 1 ? breakdownFundingTypes.value[0].id : null
  breakdownDialog.value = true
}

const exportPeriod = async (period) => {
  if (!period) return
  const response = await capitationAPI.export(period.id)
  const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `capitation_${period.id}.csv`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

const exportBreakdownExcel = async () => {
  if (!selectedPeriod.value || filteredBreakdown.value.length === 0) return

  try {
    const response = await capitationAPI.exportBreakdownExcel(selectedPeriod.value.id, {
      stage: breakdownStage.value,
      funding_type_id: breakdownFundingTypeId.value || undefined,
    })
    const fundingType = breakdownFundingTypes.value.find((item) => Number(item.id) === Number(breakdownFundingTypeId.value))
    const suffix = fundingType?.name || 'all_funding_types'
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `capitation_payment_details_${selectedPeriod.value.id}_${String(suffix).replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '')}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to export capitation payment details')
  }
}

const exportRemitaPeriod = async (period) => {
  if (!period) return
  try {
    const response = await capitationAPI.exportRemita(period.id, {
      funding_type_id: props.mode === 'payments' ? workflowForm.value.funding_type_id || undefined : undefined,
    })
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.ms-excel' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `NiCare_Cap_Payment_Remita_Format_${period.id}.xls`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to export Remita payment format')
  }
}

// ── Invoice printing ─────────────────────────────────────────────────────────

const printInvoice = (period, items, mode, dataSource = 'breakdown') => {
  const now = new Date()
  const rand = () => Math.random().toString(36).substring(2).toUpperCase()
  const paymentCode = `IN${rand().slice(0, 8)}${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}NGD${rand().slice(0, 12)}`

  const totalAmount = items.reduce((sum, item) => sum + Number(item.total_amount || item.amount || 0), 0)
  const dateGenerated = now.toLocaleString()
  const fundingTypeNames = Array.from(new Set(items.map((item) => periodFundingTypeName(item)).filter((name) => name && name !== 'N/A')))
  const fundingTypeLabel = fundingTypeNames.length === 1 ? fundingTypeNames[0] : (fundingTypeNames.length ? 'Multiple funding types' : 'N/A')
  const statusColHeader = STATUS_COL_HEADERS[mode] || 'Status'
  const captMonth = period?.capitation_month ? (MONTH_NAMES[period.capitation_month - 1] || '—') : '—'

  const overallStatus = { generate: 'Generated', review: 'Under Review', approval: 'Approved', payments: 'Paid' }[mode] || 'Processed'
  const overallStatusStyle = {
    generate: 'color:#d97706;font-weight:bold',
    review:   'color:#1a56db;font-weight:bold',
    approval: 'color:#7e3af2;font-weight:bold',
    payments: 'color:green;font-weight:bold',
  }[mode] || 'font-weight:bold'

  const getItemStatus = (item) => {
    if (dataSource === 'workflow') {
      if (mode === 'payments') return item.paid_at ? 'Paid' : 'Pending Payment'
      if (mode === 'approval') return item.approved_at ? 'Approved' : 'Pending Approval'
      if (mode === 'review') return item.reviewed_at ? 'Reviewed' : 'Pending Review'
      return item.is_generated ? 'Generated' : 'Pending'
    }
    return { generate: 'Generated', review: 'Reviewed', approval: 'Approved', payments: 'Paid' }[mode] || 'Processed'
  }

  const itemStatusStyle = (item) => {
    const s = getItemStatus(item)
    if (s === 'Paid') return 'color:green'
    if (s === 'Approved') return 'color:#7e3af2'
    if (s === 'Reviewed') return 'color:#1a56db'
    if (s === 'Generated') return 'color:#d97706'
    return 'color:#888'
  }

  const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(paymentCode)}`

  const tableRows = items.map((item, i) => `
    <tr>
      <td style="text-align:center">${i + 1}</td>
      <td style="text-align:left;font-weight:600">${item.facility?.name || item.facility_name || 'N/A'}</td>
      <td style="text-align:center">${Number(item.total_enrollees || item.total_enrolled || 0).toLocaleString()}</td>
      <td style="text-align:center">₦${Number(item.total_amount || item.amount || 0).toLocaleString()}</td>
      <td style="text-align:center">${item.facility?.account_detail?.account_number || item.facility?.account_number || item.account_number || '—'}</td>
      <td style="text-align:left">${item.facility?.account_detail?.account_name || item.facility?.account_name || item.account_name || '—'}</td>
      <td style="text-align:center">${item.facility?.account_detail?.bank?.name || item.facility?.bank_name || item.facility?.bank || item.bank_name || '—'}</td>
      <td style="text-align:center;${itemStatusStyle(item)}">${getItemStatus(item)}</td>
    </tr>`).join('')

  const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Capitation Invoice – ${period?.name || ''}</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;font-size:11px;padding:24px 30px;color:#111;background:#fff}
  .page-header{display:flex;justify-content:flex-end;margin-bottom:6px}
  .qr-img{display:block;width:120px;height:120px}
  .meta-box{border:1.5px solid #333;padding:0;min-width:340px;margin-top:10px}
  .meta-row{display:flex;padding:5px 10px;border-bottom:1px solid #ccc;font-size:11px}
  .meta-row:last-child{border-bottom:none}
  .meta-label{font-weight:bold;min-width:145px;flex-shrink:0}
  .section-title{font-size:14px;font-weight:bold;margin:22px 0 8px}
  table{width:100%;border-collapse:collapse;margin-bottom:16px}
  th{font-weight:bold;text-align:center;padding:7px 8px;border:1.5px solid #222;font-size:11px;background:#fff}
  td{padding:5px 8px;border:1px solid #ccc;font-size:11px;vertical-align:middle}
  tr:nth-child(even){background:#fafafa}
  .total-row td{font-weight:bold;border-top:2px solid #333;background:#f5f5f5}
  @media print{body{padding:10px 15px}@page{margin:1.2cm;size:A4 landscape}button{display:none}}
</style>
</head>
<body>
<div class="page-header">
  <div>
    <img class="qr-img" src="${qrUrl}" alt="QR" onerror="this.style.visibility='hidden'"/>
    <div class="meta-box" style="margin-top:10px">
      <div class="meta-row"><span class="meta-label">Payment Code:</span><span style="word-break:break-all">${paymentCode}</span></div>
      <div class="meta-row"><span class="meta-label">Total Capitation:</span><span>₦${totalAmount.toLocaleString()}</span></div>
      <div class="meta-row"><span class="meta-label">Date Generated</span><span>${dateGenerated}</span></div>
      <div class="meta-row"><span class="meta-label">Status:</span><span style="${overallStatusStyle}">${overallStatus}</span></div>
    </div>
  </div>
</div>

<p class="section-title">Capitation Payment Details</p>
<table style="width:70%">
  <thead>
    <tr><th style="width:40px">SN</th><th>Capitated Month</th><th>Capitation</th><th>Funding Type</th><th>Capitation Amount</th></tr>
  </thead>
  <tbody>
    <tr>
      <td style="text-align:center">1</td>
      <td>${captMonth}</td>
      <td>${period?.name || '—'}</td>
      <td>${fundingTypeLabel}</td>
      <td style="text-align:right">${totalAmount.toLocaleString()}</td>
    </tr>
    <tr class="total-row">
      <td colspan="4" style="text-align:center">Total</td>
      <td style="text-align:right">₦${totalAmount.toLocaleString()}</td>
    </tr>
  </tbody>
</table>

<table>
  <thead>
    <tr>
      <th style="width:30px">#</th>
      <th>Provider</th>
      <th>Total Enrollees</th>
      <th>Total Cap</th>
      <th>Account No</th>
      <th>Account Name</th>
      <th>Bank</th>
      <th>${statusColHeader}</th>
    </tr>
  </thead>
  <tbody>${tableRows}</tbody>
</table>
</body>
</html>`

  const win = window.open('', '_blank', 'width=1100,height=750')
  if (!win) { error('Please allow pop-ups to print the invoice.'); return }
  win.document.write(html)
  win.document.close()
  win.focus()
  setTimeout(() => win.print(), 900)
}

const printBreakdownInvoice = () => printInvoice(selectedPeriod.value, filteredBreakdown.value, props.mode, 'breakdown')

const printWorkflowInvoice = () => {
  const period = periods.value.find((p) => Number(p.id) === Number(workflowForm.value.period_id)) || null
  printInvoice(period, workflowDetails.value, props.mode, 'workflow')
}

const printPeriodQuickInvoice = async (period) => {
  try {
    const response = await capitationAPI.breakdown(period.id, { stage: breakdownStage.value })
    printInvoice(period, response.data?.data || [], props.mode, 'breakdown')
  } catch {
    error('Failed to load period data for invoice')
  }
}

// ── Formatting ────────────────────────────────────────────────────────────────

const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : 'N/A')
const detailStatusLabel = (item) => {
  if (item.paid_at) return 'Paid'
  if (item.approved_at) return 'Approved'
  if (item.reviewed_at) return 'Reviewed'
  return 'Generated'
}
const detailStatusColor = (item) => {
  if (item.paid_at) return 'success'
  if (item.approved_at) return 'primary'
  if (item.reviewed_at) return 'info'
  return 'warning'
}

onMounted(async () => {
  await loadFundingTypes()
  await loadPeriods()
})
watch(() => props.mode, async () => {
  workflowDetails.value = []
  selectedDetailIds.value = []
  workflowDetailsLoaded.value = false
  await loadPeriods()
})
watch(() => workflowForm.value.funding_type_id, async (value, previous) => {
  if (!workflowMode.value || value === previous) return

  workflowDetails.value = []
  selectedDetailIds.value = []
  workflowDetailsLoaded.value = false
  await loadPeriods()
})
</script>
