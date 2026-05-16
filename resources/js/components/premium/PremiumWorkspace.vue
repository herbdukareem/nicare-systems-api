<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-4">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ pageTitle }}</h1>
          <p class="tw-text-sm tw-text-gray-600">{{ pageSubtitle }}</p>
        </div>
        <div class="tw-flex tw-gap-2 tw-flex-wrap">
          <v-btn v-for="item in quickLinks" :key="item.path" size="small" variant="tonal" :prepend-icon="item.icon" @click="$router.push(item.path)">
            {{ item.label }}
          </v-btn>
        </div>
      </div>

      <div v-if="mode === 'dashboard'" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-4">
        <div v-for="metric in metrics" :key="metric.label" class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-p-4 tw-shadow-sm">
          <div class="tw-flex tw-items-center tw-justify-between">
            <p class="tw-text-sm tw-text-gray-600">{{ metric.label }}</p>
            <v-icon :color="metric.color">{{ metric.icon }}</v-icon>
          </div>
          <p class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-mt-2">{{ metric.value }}</p>
        </div>
      </div>

      <div v-if="['plans','generate-pins','sell-pin','purchases','eligibility','payroll','benefactors'].includes(mode)" class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-p-4 tw-shadow-sm">
        <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-4">
          <v-text-field v-if="mode === 'benefactors'" v-model="benefactorForm.name" label="Benefactor / sponsor name" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'benefactors'" v-model="benefactorForm.type" :items="benefactorTypes" label="Benefactor type" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'benefactors'" v-model="benefactorForm.registration_number" label="Registration number" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'benefactors'" v-model="benefactorForm.contact_person" label="Contact person" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'benefactors'" v-model="benefactorForm.phone" label="Phone" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'benefactors'" v-model="benefactorForm.email" label="Email" density="comfortable" variant="outlined" />

          <v-text-field v-if="mode === 'payroll'" v-model="batchForm.reference_name" :label="batchReferenceLabel" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'payroll'" v-model="batchForm.benefactor_id" :items="benefactors" item-title="name" item-value="id" label="Benefactor / sponsor" density="comfortable" variant="outlined" clearable />
          <v-select v-if="mode === 'payroll'" v-model="batchForm.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding type" density="comfortable" variant="outlined" clearable />
          <v-textarea v-if="mode === 'payroll'" v-model="batchForm.rows_text" label="Beneficiary rows (one enrollee ID per line)" rows="3" density="comfortable" variant="outlined" />

          <v-text-field v-if="mode === 'plans'" v-model="planForm.name" label="Plan name" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'plans'" v-model="planForm.code" label="Code" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'plans'" v-model="planForm.insurance_programme_id" :items="metadata.programmes" item-title="name" item-value="id" label="Programme" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'plans'" v-model="planForm.benefit_package_id" :items="metadata.benefit_packages" item-title="name" item-value="id" label="Benefit package" density="comfortable" variant="outlined" clearable />
          <v-text-field v-if="mode === 'plans'" v-model.number="planForm.amount" label="Amount" type="number" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'plans'" v-model.number="planForm.consultant_fee" label="Consultant fee" type="number" density="comfortable" variant="outlined" />
          <v-switch v-if="mode === 'plans'" v-model="planForm.has_no_expiry" label="No expiry" color="primary" hide-details />
          <v-text-field v-if="mode === 'plans' && !planForm.has_no_expiry" v-model.number="planForm.duration_days" label="Duration days" type="number" density="comfortable" variant="outlined" />
          <v-alert v-if="mode === 'plans' && planForm.has_no_expiry" type="info" variant="tonal" density="compact" class="lg:tw-col-span-2">
            This plan has no expiry. Enrollee coverage_end_date will be empty and coverage remains active until status changes.
          </v-alert>
          <v-text-field v-if="mode === 'plans'" v-model.number="planForm.waiting_period_days" label="Waiting period days" type="number" density="comfortable" variant="outlined" />
          <v-switch v-if="mode === 'plans'" v-model="planForm.is_family_plan" label="Family plan" color="primary" hide-details />
          <v-text-field v-if="mode === 'plans' && planForm.is_family_plan" v-model.number="planForm.maximum_dependants" label="Maximum dependants" type="number" density="comfortable" variant="outlined" />
          <v-alert v-if="mode === 'plans' && !planForm.is_family_plan" type="info" variant="tonal" density="compact" class="lg:tw-col-span-2">
            Dependants are not allowed for this plan.
          </v-alert>
          <v-switch v-if="mode === 'plans'" v-model="planForm.payment_required" label="Requires payment before PIN generation" color="primary" hide-details />
          <v-select v-if="mode === 'plans' && planForm.payment_required" v-model="planForm.payment_gateway" :items="metadata.payment_gateways" item-title="name" item-value="code" label="Payment gateway" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'plans' && planForm.payment_required && metadata.merchants?.length" v-model="planForm.merchant_id" :items="metadata.merchants" item-title="name" item-value="id" label="Merchant" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'plans' && planForm.payment_required && metadata.merchant_service_types?.length" v-model="planForm.merchant_service_type_id" :items="filteredMerchantServiceTypes" item-title="type_name" item-value="id" label="Merchant service type" density="comfortable" variant="outlined" />
          <v-alert v-if="mode === 'plans' && !planForm.payment_required" type="info" variant="tonal" density="compact" class="lg:tw-col-span-2">
            Payment gateway fields are cleared for payment-free sponsored or government-funded plans.
          </v-alert>

          <v-select v-if="mode === 'generate-pins'" v-model="pinForm.premium_plan_id" :items="plans" item-title="name" item-value="id" label="Premium plan" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'generate-pins'" v-model.number="pinForm.quantity" label="Quantity" type="number" density="comfortable" variant="outlined" />
          <div v-if="mode === 'generate-pins' && selectedPlan" class="tw-rounded tw-border tw-border-gray-200 tw-p-3 tw-text-sm tw-text-gray-700">
            <div class="tw-font-semibold tw-text-gray-900">{{ selectedPlan.payment_required ? 'Payment required' : 'Payment not required' }}</div>
            <div>Gateway: {{ selectedPlan.payment_required ? selectedPlanGateway : 'Waived / sponsor-funded' }}</div>
            <div v-if="selectedPlan.payment_required">Amount due: NGN {{ paymentAmount }}</div>
            <div>{{ selectedPlan.has_no_expiry ? 'PIN activates no-expiry coverage.' : `PIN expires after usage using ${selectedPlan.duration_days} plan days.` }}</div>
          </div>

          <v-text-field v-if="mode === 'sell-pin'" v-model="saleForm.pin" label="PIN" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'sell-pin'" v-model="purchaseForm.premium_plan_id" :items="plans" item-title="name" item-value="id" label="Premium plan" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.payer_type" :items="payerTypes" label="Payer type" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding type" density="comfortable" variant="outlined" clearable />
          <v-select v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.benefactor_id" :items="benefactors" item-title="name" item-value="id" label="Benefactor / sponsor" density="comfortable" variant="outlined" clearable />
          <v-text-field v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.payer_name" label="Payer name" density="comfortable" variant="outlined" />
          <v-select v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.payment_method" :items="paymentMethods" label="Payment method" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'sell-pin' || mode === 'purchases'" v-model="purchaseForm.payment_reference" label="Payment reference" density="comfortable" variant="outlined" />

          <v-text-field v-if="mode === 'eligibility'" v-model="eligibilityForm.enrollee_number" label="SHIN / enrollee number" density="comfortable" variant="outlined" />
          <v-text-field v-if="mode === 'eligibility'" v-model="eligibilityForm.date" label="Care date" type="date" density="comfortable" variant="outlined" />

          <v-select v-if="mode === 'payroll'" v-model="coverageForm.premium_plan_id" :items="plans" item-title="name" item-value="id" label="Premium plan" density="comfortable" variant="outlined" />
        </div>

        <div class="tw-flex tw-gap-2 tw-mt-3">
          <v-btn color="primary" :loading="saving" @click="primaryAction">
            {{ actionLabel }}
          </v-btn>
          <v-btn v-if="mode === 'plans' && editingPlanId" variant="tonal" @click="resetPlanForm">
            Cancel Edit
          </v-btn>
          <v-btn v-if="generatedPins.length" variant="tonal" prepend-icon="mdi-printer" @click="printPins(generatedPins)">
            Print Generated PINs
          </v-btn>
          <v-btn variant="tonal" @click="loadAll">Refresh</v-btn>
        </div>
      </div>

      <v-alert v-if="eligibilityResult" :type="eligibilityResult.eligible ? 'success' : 'warning'" variant="tonal">
        {{ eligibilityResult.eligible ? 'Eligible for care' : eligibilityResult.message }}
      </v-alert>

      <div class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-shadow-sm">
        <v-data-table v-model="selectedPins" :headers="headers" :items="rows" :loading="loading" density="comfortable" item-value="id" :show-select="['inventory','generate-pins'].includes(mode)">
          <template #item.status="{ item }">
            <v-chip size="small" :color="statusColor(item.status || item.payment_status)" variant="flat">{{ item.status || item.payment_status }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-gap-1">
              <v-btn v-if="mode === 'plans'" icon="mdi-pencil" size="small" variant="text" @click="editPlan(item)" />
              <v-btn v-if="mode === 'plans'" icon="mdi-delete-outline" size="small" variant="text" color="error" @click="deletePlan(item)" />
              <v-btn v-if="['inventory','generate-pins','sell-pin','validate-pin'].includes(mode)" icon="mdi-eye" size="small" variant="text" @click="viewPin(item)" />
              <v-btn v-if="['inventory','generate-pins'].includes(mode)" icon="mdi-printer" size="small" variant="text" @click="printPins([item])" />
              <v-btn v-if="mode === 'purchases' && item.payment_status !== 'confirmed'" icon="mdi-check" size="small" variant="text" @click="confirmPurchase(item)" />
            </div>
          </template>
          <template #top>
            <div v-if="['inventory','generate-pins'].includes(mode) && selectedPins.length" class="tw-p-3 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-gap-2">
              <span class="tw-text-sm tw-text-gray-600">{{ selectedPins.length }} PINs selected</span>
              <v-btn size="small" color="primary" prepend-icon="mdi-printer" @click="printSelectedPins">Print Selected</v-btn>
            </div>
          </template>
        </v-data-table>
      </div>

      <v-dialog v-model="pinDetailsDialog" max-width="640">
        <v-card v-if="pinDetails">
          <v-card-title class="tw-flex tw-items-center tw-justify-between">
            <span>PIN Details</span>
            <v-chip size="small" :color="statusColor(pinDetails.status)" variant="flat">{{ pinDetails.status }}</v-chip>
          </v-card-title>
          <v-card-text>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3 tw-text-sm">
              <div><strong>PIN:</strong> {{ pinDetails.pin }}</div>
              <div><strong>Serial:</strong> {{ pinDetails.serial_number }}</div>
              <div><strong>Batch:</strong> {{ pinDetails.batch_code }}</div>
              <div><strong>Plan:</strong> {{ pinDetails.plan?.name }}</div>
              <div><strong>Amount:</strong> {{ pinDetails.amount }}</div>
              <div><strong>Expires:</strong> {{ pinDetails.expires_at || 'Calculated after usage' }}</div>
              <div><strong>Used by:</strong> {{ pinDetails.used_by_enrollee?.full_name || pinDetails.used_by_enrollee?.enrollee_id || 'Not used' }}</div>
              <div><strong>Payment:</strong> {{ pinDetails.purchase?.payment_reference || 'N/A' }}</div>
            </div>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="pinDetailsDialog = false">Close</v-btn>
            <v-btn color="primary" prepend-icon="mdi-printer" @click="printPins([pinDetails])">Print</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="paymentDialog" max-width="520">
        <v-card>
          <v-card-title>Complete Payment</v-card-title>
          <v-card-text class="tw-space-y-3">
            <div class="tw-rounded tw-border tw-border-gray-200 tw-p-3 tw-text-sm">
              <div><strong>Gateway:</strong> {{ selectedPlanGateway }}</div>
              <div><strong>Plan:</strong> {{ selectedPlan?.name }}</div>
              <div><strong>Quantity:</strong> {{ pinForm.quantity }}</div>
              <div><strong>Amount:</strong> NGN {{ paymentAmount }}</div>
            </div>
            <v-text-field v-model="paymentForm.reference" label="Payment reference" density="comfortable" variant="outlined" autofocus />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="paymentDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="saving" @click="confirmGatewayPayment">Confirm Payment & Generate</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { benefactorAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const props = defineProps({ mode: { type: String, default: 'dashboard' } })
const { success, error } = useToast()
const loading = ref(false)
const saving = ref(false)
const dashboard = ref({})
const rows = ref([])
const plans = ref([])
const benefactors = ref([])
const metadata = ref({ programmes: [], benefit_packages: [], funding_types: [], payment_gateways: [], merchants: [], merchant_service_types: [] })
const eligibilityResult = ref(null)
const generatedPins = ref([])
const selectedPins = ref([])
const pinDetails = ref(null)
const pinDetailsDialog = ref(false)
const paymentDialog = ref(false)
const paymentForm = ref({ reference: '' })
const editingPlanId = ref(null)

const payerTypes = ['individual', 'employer', 'government', 'donor', 'group', 'institution']
const paymentMethods = ['cash', 'bank_transfer', 'pos', 'online_payment', 'payroll_deduction', 'government_subsidy', 'donor_sponsorship']
const benefactorTypes = ['individual', 'principal_enrollee', 'employer', 'government', 'donor', 'institution', 'association', 'ngo', 'group', 'philanthropist']

const blankPlanForm = () => ({
  name: '',
  code: '',
  insurance_programme_id: null,
  benefit_package_id: null,
  amount: 0,
  consultant_fee: 0,
  payment_required: false,
  payment_gateway: null,
  merchant_id: null,
  merchant_service_type_id: null,
  has_no_expiry: false,
  duration_days: 365,
  waiting_period_days: 0,
  maximum_dependants: 0,
  is_family_plan: false,
  status: 'active',
})
const planForm = ref(blankPlanForm())
const pinForm = ref({ premium_plan_id: null, quantity: 100, payment_reference: '' })
const saleForm = ref({ pin: '' })
const purchaseForm = ref({ premium_plan_id: null, payer_type: 'individual', payer_name: '', payment_method: 'cash', payment_reference: '', quantity: 1 })
const coverageForm = ref({ enrollee_id: null, premium_plan_id: null, activation_source: 'admin' })
const eligibilityForm = ref({ enrollee_number: '', date: '' })
const benefactorForm = ref({ name: '', type: 'individual', registration_number: '', contact_person: '', phone: '', email: '', status: 1 })
const batchForm = ref({ reference_name: '', benefactor_id: null, funding_type_id: null, rows_text: '' })

const pageTitle = computed(() => ({
  dashboard: 'Premium Dashboard',
  plans: 'Premium Plans',
  'generate-pins': 'Generate Premium PINs',
  inventory: 'PIN Inventory',
  'sell-pin': 'Sell Premium PIN',
  'validate-pin': 'Validate PIN',
  purchases: 'Premium Purchases',
  benefactors: 'Benefactor / Sponsor Management',
  payroll: 'Payroll Upload',
  eligibility: 'Coverage Eligibility Lookup',
}[props.mode] || 'Premium Management'))

const pageSubtitle = computed(() => 'Manage premium transactions, benefactors, PINs, funding classification, and coverage eligibility.')
const batchReferenceLabel = computed(() => 'Employer name')
const selectedPlan = computed(() => plans.value.find((plan) => plan.id === pinForm.value.premium_plan_id))
const selectedPlanGateway = computed(() => {
  const code = selectedPlan.value?.payment_gateway
  return metadata.value.payment_gateways?.find((item) => item.code === code)?.name || code || 'Configured gateway'
})
const paymentAmount = computed(() => {
  const amount = Number(selectedPlan.value?.amount || 0)
  const quantity = Number(pinForm.value.quantity || 0)
  return (amount * quantity).toLocaleString()
})
const filteredMerchantServiceTypes = computed(() => {
  if (!planForm.value.merchant_id) return metadata.value.merchant_service_types || []
  return (metadata.value.merchant_service_types || []).filter((item) => item.merchant_id === planForm.value.merchant_id)
})
const actionLabel = computed(() => ({
  plans: editingPlanId.value ? 'Update Plan' : 'Save Plan',
  'generate-pins': 'Generate PINs',
  'sell-pin': 'Sell PIN',
  purchases: 'Create Purchase',
  eligibility: 'Lookup Eligibility',
  benefactors: 'Save Benefactor',
  payroll: 'Create Payroll Batch',
}[props.mode] || 'Save'))

const quickLinks = [
  { path: '/premium/plans', label: 'Plans', icon: 'mdi-clipboard-list' },
  { path: '/premium/pins', label: 'PINs', icon: 'mdi-key' },
  { path: '/premium/purchases', label: 'Purchases', icon: 'mdi-receipt' },
  { path: '/premium/eligibility', label: 'Eligibility', icon: 'mdi-card-search' },
]

const metrics = computed(() => [
  { label: 'Plans', value: dashboard.value.plans || 0, icon: 'mdi-clipboard-list', color: 'primary' },
  { label: 'Available PINs', value: dashboard.value.pins_available || 0, icon: 'mdi-key', color: 'green' },
  { label: 'Pending Purchases', value: dashboard.value.pending_purchases || 0, icon: 'mdi-clock', color: 'orange' },
  { label: 'Active Coverage', value: dashboard.value.active_coverage || 0, icon: 'mdi-shield-check', color: 'indigo' },
])

const headers = computed(() => {
  if (props.mode === 'plans') {
    return [
      { title: 'Name', key: 'name' },
      { title: 'Programme', key: 'programme.name' },
      { title: 'Amount', key: 'amount' },
      { title: 'Consultant', key: 'consultant_fee' },
      { title: 'Duration', key: 'duration_label' },
      { title: 'Family', key: 'family_label' },
      { title: 'Payment', key: 'payment_label' },
      { title: 'Gateway', key: 'payment_gateway' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', sortable: false },
    ]
  }
  if (['inventory', 'generate-pins', 'sell-pin', 'validate-pin'].includes(props.mode)) return [{ title: 'Serial', key: 'serial_number' }, { title: 'Batch', key: 'batch_code' }, { title: 'PIN', key: 'pin' }, { title: 'Amount', key: 'amount' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }]
  if (props.mode === 'purchases') return [{ title: 'Payer', key: 'payer_name' }, { title: 'Type', key: 'payer_type' }, { title: 'Amount', key: 'amount' }, { title: 'Status', key: 'payment_status' }, { title: '', key: 'actions', sortable: false }]
  if (props.mode === 'benefactors') return [{ title: 'Name', key: 'name' }, { title: 'Type', key: 'type' }, { title: 'Phone', key: 'phone' }, { title: 'Status', key: 'status' }]
  return [{ title: 'Enrollee', key: 'enrollee.enrollee_id' }, { title: 'Programme', key: 'programme.name' }, { title: 'Facility', key: 'facility.name' }, { title: 'Status', key: 'status' }, { title: '', key: 'actions', sortable: false }]
})

const loadAll = async () => {
  loading.value = true
  try {
    const [meta, planRes, benefactorRes] = await Promise.all([premiumAPI.metadata(), premiumAPI.plans({ per_page: 100 }), benefactorAPI.getAll()])
    metadata.value = meta.data.data
    plans.value = planRes.data.data || planRes.data
    benefactors.value = benefactorRes.data.data || []
    if (props.mode === 'dashboard') dashboard.value = (await premiumAPI.dashboard()).data.data
    else if (props.mode === 'plans') rows.value = plans.value.map(formatPlanRow)
    else if (['inventory', 'generate-pins', 'sell-pin', 'validate-pin'].includes(props.mode)) rows.value = (await premiumAPI.pins()).data.data
    else if (props.mode === 'purchases') rows.value = (await premiumAPI.purchases()).data.data
    else if (props.mode === 'benefactors') rows.value = benefactors.value
    else if (props.mode === 'payroll') rows.value = (await premiumAPI.payrollBatches()).data.data
    else rows.value = []
  } catch (err) {
    error('Failed to load premium module data')
  } finally {
    loading.value = false
  }
}

const primaryAction = async () => {
  saving.value = true
  try {
    if (props.mode === 'plans') {
      if (editingPlanId.value) await premiumAPI.updatePlan(editingPlanId.value, planPayload())
      else await premiumAPI.createPlan(planPayload())
      resetPlanForm()
    }
    else if (props.mode === 'generate-pins') {
      if (selectedPlan.value?.payment_required) {
        paymentForm.value.reference = pinForm.value.payment_reference || ''
        paymentDialog.value = true
        return
      }
      await generatePins()
      return
    }
    else if (props.mode === 'sell-pin') {
      const purchase = (await premiumAPI.createPurchase(purchaseForm.value)).data.data
      const pin = (await premiumAPI.validatePin({ pin: saleForm.value.pin })).data.data
      await premiumAPI.sellPin(pin.id, { premium_purchase_id: purchase.id })
    } else if (props.mode === 'purchases') await premiumAPI.createPurchase(purchaseForm.value)
    else if (props.mode === 'eligibility') eligibilityResult.value = (await premiumAPI.eligibility(eligibilityForm.value)).data
    else if (props.mode === 'benefactors') await benefactorAPI.create(benefactorForm.value)
    else if (props.mode === 'payroll') await premiumAPI.createPayrollBatch(batchPayload('payroll'))
    success('Action completed')
    await loadAll()
  } catch (err) {
    error(err?.response?.data?.message || 'Action failed')
  } finally {
    saving.value = false
  }
}

const generatePins = async () => {
  const response = await premiumAPI.generatePins(pinForm.value)
  generatedPins.value = response.data.data.pins || []
  rows.value = generatedPins.value
  selectedPins.value = generatedPins.value.map((pin) => pin.id)
  success(`${generatedPins.value.length} PINs generated`)
}

const confirmGatewayPayment = async () => {
  if (!paymentForm.value.reference) {
    error(`Enter the ${selectedPlanGateway.value} payment reference.`)
    return
  }

  saving.value = true
  try {
    pinForm.value.payment_reference = paymentForm.value.reference
    await generatePins()
    paymentDialog.value = false
  } catch (err) {
    error(err?.response?.data?.message || 'Payment confirmation failed')
  } finally {
    saving.value = false
  }
}

const confirmPurchase = async (item) => { await premiumAPI.confirmPurchase(item.id); await loadAll() }
const editPlan = (plan) => {
  editingPlanId.value = plan.id
  planForm.value = {
    ...blankPlanForm(),
    insurance_programme_id: plan.insurance_programme_id ?? null,
    benefit_package_id: plan.benefit_package_id ?? null,
    name: plan.name || '',
    code: plan.code || '',
    amount: Number(plan.amount || 0),
    consultant_fee: Number(plan.consultant_fee || 0),
    payment_required: Boolean(plan.payment_required),
    payment_gateway: plan.payment_gateway || null,
    merchant_id: plan.merchant_id || null,
    merchant_service_type_id: plan.merchant_service_type_id || null,
    has_no_expiry: Boolean(plan.has_no_expiry),
    duration_days: plan.has_no_expiry ? null : Number(plan.duration_days || 365),
    waiting_period_days: Number(plan.waiting_period_days || 0),
    is_family_plan: Boolean(plan.is_family_plan),
    maximum_dependants: Number(plan.maximum_dependants || 0),
    status: plan.status || 'active',
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
const resetPlanForm = () => {
  editingPlanId.value = null
  planForm.value = blankPlanForm()
}
const deletePlan = async (plan) => {
  const ok = window.confirm(`Delete premium plan "${plan.name}"? Plans already used by enrollees, purchases, or PINs will be archived instead.`)
  if (!ok) return

  saving.value = true
  try {
    const response = await premiumAPI.deletePlan(plan.id)
    success(response.data?.message || 'Premium plan removed')
    if (editingPlanId.value === plan.id) resetPlanForm()
    await loadAll()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to delete premium plan')
  } finally {
    saving.value = false
  }
}
const viewPin = async (item) => {
  pinDetails.value = (await premiumAPI.getPin(item.id)).data.data
  pinDetailsDialog.value = true
}
const printSelectedPins = () => {
  const selected = rows.value.filter((pin) => selectedPins.value.includes(pin.id))
  printPins(selected)
}
const qrUrl = (pin) => `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(pin)}`
const printPins = (pins) => {
  const cards = pins.map((pin) => `
    <section class="card">
      <div class="brand">NiCare Premium PIN</div>
      <div class="plan">${pin.plan?.name || selectedPlan.value?.name || 'Premium Plan'}</div>
      <div class="pin">${pin.pin}</div>
      <div class="meta">Serial: ${pin.serial_number}</div>
      <div class="meta">Batch: ${pin.batch_code}</div>
      <div class="meta">Amount: NGN ${pin.amount}</div>
      <div class="meta">Expiry starts from day of usage</div>
      <img class="qr" src="${qrUrl(pin.pin)}" alt="QR code" />
    </section>
  `).join('')
  const printWindow = window.open('', '_blank')
  printWindow.document.write(`
    <html><head><title>Premium PIN Docket</title>
    <style>
      body{font-family:Arial,sans-serif;margin:20px;background:#f3f4f6}
      .grid{display:grid;grid-template-columns:repeat(2, 1fr);gap:14px}
      .card{position:relative;min-height:210px;border-radius:10px;padding:16px;overflow:hidden;color:#0f172a;background:linear-gradient(135deg,#e0f2fe,#ffffff 45%,#dcfce7);border:1px dashed #0f766e;box-shadow:0 8px 20px rgba(15,23,42,.08)}
      .brand{font-size:16px;font-weight:800;color:#075985;text-transform:uppercase;letter-spacing:.04em}
      .plan{margin-top:4px;font-size:13px;font-weight:700;color:#166534}
      .pin{margin-top:18px;font-size:28px;font-weight:900;letter-spacing:3px;background:#fff;border:1px solid #bae6fd;border-radius:6px;padding:10px;display:inline-block}
      .meta{font-size:11px;margin-top:5px;color:#334155}
      .qr{position:absolute;right:14px;bottom:14px;width:92px;height:92px;background:#fff;padding:6px;border-radius:6px}
      @media print{body{background:#fff}.card{break-inside:avoid;box-shadow:none}}
    </style></head><body><div class="grid">${cards}</div></body></html>
  `)
  printWindow.document.close()
  printWindow.focus()
  printWindow.print()
}
const statusColor = (status) => status === 'active' || status === 'confirmed' ? 'success' : status === 'cancelled' || status === 'expired' ? 'error' : 'warning'
const formatPlanRow = (plan) => ({
  ...plan,
  duration_label: plan.has_no_expiry ? 'No Expiry' : `${plan.duration_days || 0} days`,
  family_label: plan.is_family_plan ? `Yes (${plan.maximum_dependants || 0})` : 'No',
  payment_label: plan.payment_required ? 'Required' : 'Not required',
})
const planPayload = () => ({
  ...planForm.value,
  duration_days: planForm.value.has_no_expiry ? null : planForm.value.duration_days,
  maximum_dependants: planForm.value.is_family_plan ? planForm.value.maximum_dependants : 0,
  payment_gateway: planForm.value.payment_required ? planForm.value.payment_gateway : null,
  merchant_id: planForm.value.payment_required ? planForm.value.merchant_id : null,
  merchant_service_type_id: planForm.value.payment_required ? planForm.value.merchant_service_type_id : null,
})
const batchPayload = (type) => {
  const ids = batchForm.value.rows_text.split('\n').map((row) => Number(row.trim())).filter(Boolean)
  const plan = plans.value.find((item) => item.id === coverageForm.value.premium_plan_id) || {}
  const rows = ids.map((id) => ({
    enrollee_id: id,
    first_name: 'Registered',
    last_name: 'Beneficiary',
    vulnerability_type: type,
    vulnerability_verified: true,
  }))
  const base = {
    benefactor_id: batchForm.value.benefactor_id,
    funding_type_id: batchForm.value.funding_type_id,
    insurance_programme_id: plan.insurance_programme_id,
    premium_plan_id: coverageForm.value.premium_plan_id,
    rows,
  }
  const start = new Date()
  const end = new Date()
  end.setFullYear(end.getFullYear() + 1)
  if (type === 'payroll') {
    return {
      ...base,
      employer_name: batchForm.value.reference_name,
      period_start: start.toISOString().slice(0, 10),
      period_end: end.toISOString().slice(0, 10),
      rows: rows.map((row) => ({
        enrollee_id: row.enrollee_id,
        first_name: row.first_name,
        last_name: row.last_name,
        staff_number: String(row.enrollee_id),
      })),
    }
  }
  return {
    ...base,
    funding_source: batchForm.value.reference_name,
    coverage_start_date: start.toISOString().slice(0, 10),
    coverage_end_date: end.toISOString().slice(0, 10),
  }
}

onMounted(loadAll)
watch(() => props.mode, loadAll)
watch(() => planForm.value.has_no_expiry, (value) => {
  if (value) planForm.value.duration_days = null
  else if (!planForm.value.duration_days) planForm.value.duration_days = 365
})
watch(() => planForm.value.is_family_plan, (value) => {
  if (!value) planForm.value.maximum_dependants = 0
})
watch(() => planForm.value.payment_required, (value) => {
  if (!value) {
    planForm.value.payment_gateway = null
    planForm.value.merchant_id = null
    planForm.value.merchant_service_type_id = null
  }
})
watch(() => planForm.value.merchant_id, () => {
  planForm.value.merchant_service_type_id = null
})
</script>
