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
          <p class="tw-text-xs tw-text-gray-500">Create the monthly capitation period first. Facility generation is done separately by period and funding type.</p>
        </div>
        <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-4">
          <v-text-field v-model="form.name" label="Period name" density="comfortable" variant="outlined" />
          <v-select v-model="form.capitation_month" :items="months" item-title="name" item-value="value" label="Capitation month" density="comfortable" variant="outlined" />
          <v-text-field v-model.number="form.year" label="Capitation year" type="number" density="comfortable" variant="outlined" />
          <v-text-field v-model.number="form.start_day" label="Eligibility start day" type="number" density="comfortable" variant="outlined" />
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
        <div class="tw-grid tw-grid-cols-1 tw-gap-4 md:tw-grid-cols-2">
          <v-select v-model="generationForm.period_id" :items="periodOptions" item-title="label" item-value="id" label="Capitation period" density="comfortable" variant="outlined" :disabled="facilitiesLoaded" />
          <v-select v-model="generationForm.funding_type_id" :items="fundingTypes" item-title="name" item-value="id" label="Funding type" density="comfortable" variant="outlined" :disabled="facilitiesLoaded" />
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

          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-5">
            <div class="tw-border tw-border-gray-100 tw-bg-white tw-p-3">
              <p class="tw-text-xs tw-text-gray-500">Funding Type</p>
              <p class="tw-font-bold">{{ selectedGenerationFundingType?.name || 'N/A' }}</p>
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
            <template #item.facility="{ item }">{{ item.facility?.name || 'N/A' }}</template>
            <template #item.funding_type="{ item }">{{ item.funding_type?.name || 'N/A' }}</template>
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
          <v-chip size="small" :color="item.status ? 'success' : item.computed_at ? 'info' : 'warning'" variant="flat">
            {{ item.status ? 'Finalised' : item.computed_at ? 'Computed' : 'Draft' }}
          </v-chip>
        </template>
        <template #item.period="{ item }">
          {{ formatDate(item.period_start) }} - {{ formatDate(item.period_end) }}
        </template>
        <template #item.actions="{ item }">
          <div class="tw-flex tw-gap-1">
            <v-btn icon="mdi-eye" size="small" variant="text" color="primary" title="View breakdown" @click="openBreakdown(item)" />
            <v-btn icon="mdi-printer" size="small" variant="text" color="teal" title="Print invoice" @click="printPeriodQuickInvoice(item)" />
            <v-btn v-if="canExport" icon="mdi-download" size="small" variant="text" title="Export CSV" @click="exportPeriod(item)" />
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
          <v-btn color="primary" variant="flat" prepend-icon="mdi-download" @click="exportPeriod(selectedPeriod)">Export CSV</v-btn>
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
                  <td class="tw-px-4 tw-py-3 tw-text-slate-600">{{ item.funding_type?.name || 'N/A' }}</td>
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
              <p class="tw-font-bold">{{ selectedPeriod?.funding_type?.name || selectedPeriod?.funding_type?.label || 'N/A' }}</p>
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
              <span class="tw-text-slate-500 tw-w-28 tw-flex-shrink-0">Date range:</span>
              <span class="tw-font-semibold tw-text-slate-800">{{ formatDate(selectedPeriod?.period_start) }} – {{ formatDate(selectedPeriod?.period_end) }}</span>
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
import { capitationAPI, fundingTypeAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const props = defineProps({ mode: { type: String, default: 'generate' } })
const { success, error } = useToast()
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
  start_day: 1,
})
const generationForm = ref({ period_id: null, funding_type_id: null })
const workflowForm = ref({ period_id: null, funding_type_id: null })

const months = [
  { name: 'January', value: 1 }, { name: 'February', value: 2 }, { name: 'March', value: 3 },
  { name: 'April', value: 4 }, { name: 'May', value: 5 }, { name: 'June', value: 6 },
  { name: 'July', value: 7 }, { name: 'August', value: 8 }, { name: 'September', value: 9 },
  { name: 'October', value: 10 }, { name: 'November', value: 11 }, { name: 'December', value: 12 },
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

const periodOptions = computed(() => periods.value.map((p) => ({
  ...p,
  label: `#${p.id} - ${p.name} (${formatDate(p.period_start)} - ${formatDate(p.period_end)}) - ${p.capitation_details_count || 0} generated`,
})))

const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const STATUS_COL_HEADERS = {
  generate: 'Generation Status',
  review:   'Reviewal Status',
  approval: 'Approval Status',
  payments: 'Payment Status',
}

const periodHeaders = [
  { title: 'Name', key: 'name' },
  { title: 'Funding Type', key: 'funding_type.name' },
  { title: 'Period', key: 'period' },
  { title: 'Rate', key: 'capitation_rate' },
  { title: 'Generated Facilities', key: 'capitation_details_count' },
  { title: 'Status', key: 'status' },
  { title: 'Computed', key: 'computed_at' },
  { title: 'Finalised', key: 'finalised_at' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]
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
    const response = await capitationAPI.periods({ per_page: 100 })
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
  if (!generationForm.value.period_id || !generationForm.value.funding_type_id) {
    error('Select a capitation period and funding type before loading facilities')
    return
  }
  eligibleLoading.value = true
  try {
    generationPeriod.value = periods.value.find((item) => Number(item.id) === Number(generationForm.value.period_id)) || null
    if (!generationPeriod.value) return
    const response = await capitationAPI.eligibleProviders(generationPeriod.value.id, { funding_type_id: generationForm.value.funding_type_id })
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
  generationForm.value = { period_id: null, funding_type_id: null }
  facilitiesLoaded.value = false
}

const openProviderSelection = async (period) => {
  selectedPeriod.value = period
  providerDialog.value = true
  eligibleLoading.value = true
  selectedProviderIds.value = []
  try {
    eligibleProviders.value = (await capitationAPI.eligibleProviders(period.id, { funding_type_id: period.funding_type_id })).data.data || []
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
      facility_ids: selectedProviderIdValues.value,
    })
    success('Capitation generated for selected facilities')
    const response = await capitationAPI.eligibleProviders(generationPeriod.value.id, { funding_type_id: generationForm.value.funding_type_id })
    eligibleProviders.value = response.data?.data || []
    selectedProviderIds.value = []
    await loadPeriods()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to generate capitation')
  } finally {
    saving.value = false
  }
}

const loadWorkflowDetails = async () => {
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
    if (workflowDetails.value.length === 0) error('No capitation details found for this stage and period')
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
    await loadWorkflowDetails()
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
      await loadWorkflowDetails()
      await loadPeriods()
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

// ── Invoice printing ─────────────────────────────────────────────────────────

const printInvoice = (period, items, mode, dataSource = 'breakdown') => {
  const now = new Date()
  const rand = () => Math.random().toString(36).substring(2).toUpperCase()
  const paymentCode = `IN${rand().slice(0, 8)}${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}NGD${rand().slice(0, 12)}`

  const totalAmount = items.reduce((sum, item) => sum + Number(item.total_amount || item.amount || 0), 0)
  const dateGenerated = now.toLocaleString()
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
      <td style="text-align:center">${item.facility?.account_number || item.account_number || '—'}</td>
      <td style="text-align:left">${item.facility?.account_name || item.account_name || '—'}</td>
      <td style="text-align:center">${item.facility?.bank_name || item.facility?.bank || item.bank_name || '—'}</td>
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
    <tr><th style="width:40px">SN</th><th>Capitated Month</th><th>Capitation</th><th>Capitation Amount</th></tr>
  </thead>
  <tbody>
    <tr>
      <td style="text-align:center">1</td>
      <td>${captMonth}</td>
      <td>${period?.name || '—'}</td>
      <td style="text-align:right">${totalAmount.toLocaleString()}</td>
    </tr>
    <tr class="total-row">
      <td colspan="3" style="text-align:center">Total</td>
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
</script>
