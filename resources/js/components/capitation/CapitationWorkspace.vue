<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-3">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ title }}</h1>
          <p class="tw-text-sm tw-text-gray-600">Generate, review, approve, export, and track capitation periods.</p>
        </div>
        <div class="tw-flex tw-gap-2">
          <v-btn variant="tonal" prepend-icon="mdi-plus-circle-outline" @click="$router.push('/capitation/generate')">Generate</v-btn>
          <v-btn variant="tonal" prepend-icon="mdi-eye-outline" @click="$router.push('/capitation/review')">Review</v-btn>
          <v-btn variant="tonal" prepend-icon="mdi-check-circle-outline" @click="$router.push('/capitation/approval')">Approval</v-btn>
          <v-btn variant="tonal" prepend-icon="mdi-receipt-text-outline" @click="$router.push('/capitation/payments')">Payments</v-btn>
        </div>
      </div>

      <div v-if="mode === 'generate'" class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-p-4 tw-shadow-sm">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-4">
          <v-text-field v-model="form.name" label="Period name" density="comfortable" variant="outlined" />
          <v-select v-model="form.capitation_month" :items="months" item-title="name" item-value="value" label="Capitation month" density="comfortable" variant="outlined" />
          <v-text-field v-model.number="form.year" label="Capitation year" type="number" density="comfortable" variant="outlined" />
          <v-text-field v-model.number="form.start_day" label="Eligibility start day" type="number" density="comfortable" variant="outlined" />
        </div>
        <div class="tw-flex tw-gap-2 tw-mt-3">
          <v-btn color="primary" :loading="saving" prepend-icon="mdi-content-save" @click="createPeriod">Create Period</v-btn>
          <v-btn variant="tonal" @click="loadPeriods">Refresh</v-btn>
        </div>
      </div>

      <div class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-shadow-sm">
        <v-data-table :headers="periodHeaders" :items="periods" :loading="loading" density="comfortable">
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
              <v-btn icon="mdi-eye" size="small" variant="text" @click="openBreakdown(item)" />
              <v-btn v-if="mode === 'generate' || mode === 'review'" icon="mdi-calculator" size="small" variant="text" :disabled="item.status" @click="compute(item)" />
              <v-btn v-if="mode === 'approval'" icon="mdi-check" size="small" variant="text" color="success" :disabled="item.status || !item.computed_at" @click="finalise(item)" />
              <v-btn v-if="mode === 'payments'" icon="mdi-cash-check" size="small" variant="text" color="success" :disabled="!item.status" @click="openPayment(item)" />
              <v-btn icon="mdi-download" size="small" variant="text" @click="exportPeriod(item)" />
            </div>
          </template>
        </v-data-table>
      </div>

      <v-dialog v-model="breakdownDialog" max-width="980">
        <v-card>
          <v-card-title class="tw-flex tw-items-center tw-justify-between">
            <span>{{ selectedPeriod?.name }} Breakdown</span>
            <v-chip size="small" color="primary">{{ breakdownTotals.enrollees }} enrollees</v-chip>
          </v-card-title>
          <v-card-text>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-3 tw-mb-4">
              <div class="tw-rounded tw-border tw-border-gray-100 tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Facilities</p>
                <p class="tw-text-xl tw-font-bold">{{ breakdown.length }}</p>
              </div>
              <div class="tw-rounded tw-border tw-border-gray-100 tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Total Enrollees</p>
                <p class="tw-text-xl tw-font-bold">{{ breakdownTotals.enrollees }}</p>
              </div>
              <div class="tw-rounded tw-border tw-border-gray-100 tw-p-3">
                <p class="tw-text-xs tw-text-gray-500">Total Amount</p>
                <p class="tw-text-xl tw-font-bold">NGN {{ breakdownTotals.amount.toLocaleString() }}</p>
              </div>
            </div>
            <v-data-table :headers="breakdownHeaders" :items="breakdown" density="compact" />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="breakdownDialog = false">Close</v-btn>
            <v-btn color="primary" prepend-icon="mdi-download" @click="exportPeriod(selectedPeriod)">Export</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <v-dialog v-model="paymentDialog" max-width="560">
        <v-card>
          <v-card-title>Confirm Capitation Payment</v-card-title>
          <v-card-text class="tw-space-y-3">
            <div class="tw-rounded tw-border tw-border-gray-200 tw-p-3 tw-text-sm">
              <div><strong>Period:</strong> {{ selectedPeriod?.name }}</div>
              <div><strong>Date range:</strong> {{ formatDate(selectedPeriod?.period_start) }} - {{ formatDate(selectedPeriod?.period_end) }}</div>
            </div>
            <v-text-field v-model="paymentForm.payment_reference" label="Payment reference" density="comfortable" variant="outlined" />
            <v-text-field v-model="paymentForm.payment_date" label="Payment date" type="date" density="comfortable" variant="outlined" />
            <v-textarea v-model="paymentForm.description" label="Description" rows="2" density="comfortable" variant="outlined" />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="paymentDialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="saving" @click="markPaid">Mark Paid</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { capitationAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const props = defineProps({ mode: { type: String, default: 'generate' } })
const { success, error } = useToast()
const loading = ref(false)
const saving = ref(false)
const periods = ref([])
const breakdown = ref([])
const selectedPeriod = ref(null)
const breakdownDialog = ref(false)
const paymentDialog = ref(false)
const form = ref({
  name: '',
  capitation_month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  start_day: 1,
})

const months = [
  { name: 'January', value: 1 },
  { name: 'February', value: 2 },
  { name: 'March', value: 3 },
  { name: 'April', value: 4 },
  { name: 'May', value: 5 },
  { name: 'June', value: 6 },
  { name: 'July', value: 7 },
  { name: 'August', value: 8 },
  { name: 'September', value: 9 },
  { name: 'October', value: 10 },
  { name: 'November', value: 11 },
  { name: 'December', value: 12 },
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

const periodHeaders = [
  { title: 'Name', key: 'name' },
  { title: 'Period', key: 'period' },
  { title: 'Rate', key: 'capitation_rate' },
  { title: 'Status', key: 'status' },
  { title: 'Computed', key: 'computed_at' },
  { title: 'Finalised', key: 'finalised_at' },
  { title: '', key: 'actions', sortable: false },
]

const breakdownHeaders = [
  { title: 'Facility', key: 'facility.name' },
  { title: 'Funding Type', key: 'funding_type_id' },
  { title: 'Benefactor', key: 'benefactor_id' },
  { title: 'Enrollees', key: 'total_enrollees' },
  { title: 'Rate', key: 'capitation_rate' },
  { title: 'Amount', key: 'total_amount' },
]

const breakdownTotals = computed(() => ({
  enrollees: breakdown.value.reduce((sum, row) => sum + Number(row.total_enrollees || 0), 0),
  amount: breakdown.value.reduce((sum, row) => sum + Number(row.total_amount || 0), 0),
}))

const loadPeriods = async () => {
  loading.value = true
  try {
    const response = await capitationAPI.periods({ per_page: 100 })
    const payload = response.data.data
    periods.value = payload.data || payload || []
  } catch (err) {
    error('Failed to load capitation periods')
  } finally {
    loading.value = false
  }
}

const createPeriod = async () => {
  saving.value = true
  try {
    await capitationAPI.createPeriod(form.value)
    success('Capitation period created')
    await loadPeriods()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to create capitation period')
  } finally {
    saving.value = false
  }
}

const compute = async (period) => {
  try {
    await capitationAPI.compute(period.id)
    success('Capitation computed')
    await loadPeriods()
    await openBreakdown(period)
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to compute capitation')
  }
}

const finalise = async (period) => {
  try {
    await capitationAPI.finalise(period.id)
    success('Capitation finalised')
    await loadPeriods()
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to finalise capitation')
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
  breakdown.value = (await capitationAPI.breakdown(period.id)).data.data || []
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

const formatDate = (value) => value ? new Date(value).toLocaleDateString() : 'N/A'

onMounted(loadPeriods)
watch(() => props.mode, loadPeriods)
</script>
