<template>
  <div class="pin-page">
    <header class="pin-page__header">
      <div>
        <div class="pin-page__eyebrow">{{ org.scheme_name }}</div>
        <h1>Purchase Premium PINs</h1>
        <p>Beneficiaries and agents can buy any quantity and download one printable PDF docket after payment.</p>
      </div>
      <div class="tw-flex tw-flex-wrap tw-gap-2">
        <v-btn variant="outlined" color="primary" @click="$router.push('/enroll/start')">Start Enrollment</v-btn>
        <v-btn variant="text" @click="$router.push('/')">Home</v-btn>
      </div>
    </header>

    <main class="pin-page__main">
      <AppAlert v-if="statusMessage" :tone="purchaseReady ? 'success' : 'info'" title="Purchase status" :message="statusMessage" />

      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-[1fr_360px] tw-gap-4">
        <section class="pin-page__panel">
          <h2>Choose a public premium plan</h2>
          <p class="pin-page__note">Only active plans approved for self-enrollment are listed.</p>

          <div v-if="loading" class="tw-flex tw-justify-center tw-py-12">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <AppEmptyState v-else-if="!plans.length" title="No plans available" description="There are no paid plans currently open for public PIN purchase." icon="mdi-key-off-outline" />
          <div v-else class="tw-mt-4 tw-space-y-2">
            <button v-for="plan in plans" :key="plan.id" class="pin-page__plan" :class="{ 'pin-page__plan--selected': form.premium_plan_id === plan.id }" @click="form.premium_plan_id = plan.id">
              <div>
                <strong>{{ plan.name }}</strong>
                <span>{{ plan.programme?.name || 'NiCare programme' }} · {{ plan.duration_label }}</span>
              </div>
              <MoneyDisplay :value="plan.amount" currency="NGN" size="sm" />
            </button>
          </div>
        </section>

        <section class="pin-page__panel">
          <h2>Purchaser details</h2>
          <v-form class="tw-mt-4 tw-space-y-2" @submit.prevent="purchasePins">
            <v-select v-model="form.purchaser_type" label="Purchasing as" :items="purchaserTypes" item-title="label" item-value="value" variant="outlined" density="compact" />
            <v-radio-group v-if="selectedPlanSupportsBankTransfer" v-model="form.payment_method" inline hide-details>
              <v-radio label="Pay online now" value="online_payment" />
              <v-radio label="Bank transfer" value="bank_transfer" />
            </v-radio-group>
            <v-text-field v-model="form.payer_name" label="Full name / agent name" variant="outlined" density="compact" :error-messages="errors.payer_name" />
            <v-text-field v-model="form.payer_phone" label="Phone number" variant="outlined" density="compact" :error-messages="errors.payer_phone" />
            <v-text-field v-model="form.payer_email" label="Email address" type="email" variant="outlined" density="compact" :error-messages="errors.payer_email" />
            <v-text-field v-model.number="form.quantity" label="Number of PINs" type="number" min="1" max="500" variant="outlined" density="compact" :error-messages="errors.quantity" />

            <dl v-if="selectedPlan" class="pin-page__summary">
              <div><dt>Unit price</dt><dd><MoneyDisplay :value="selectedPlan.amount" currency="NGN" size="sm" /></dd></div>
              <div><dt>Total</dt><dd><MoneyDisplay :value="totalAmount" currency="NGN" size="sm" /></dd></div>
            </dl>

            <div v-if="form.payment_method === 'bank_transfer' && selectedTransferAccount" class="pin-page__transfer-card">
              <div class="pin-page__transfer-title">Dedicated bank account for this plan</div>
              <div><strong>Bank:</strong> {{ selectedTransferAccount.bank_name }}</div>
              <div><strong>Account name:</strong> {{ selectedTransferAccount.account_name }}</div>
              <div><strong>Account number:</strong> {{ selectedTransferAccount.account_number }}</div>
              <div v-if="selectedTransferAccount.instructions" class="pin-page__transfer-note">{{ selectedTransferAccount.instructions }}</div>
              <div class="pin-page__transfer-note">We will generate a payment reference after you submit this request. Use that reference as your transfer narration.</div>
            </div>

            <div v-if="paymentCollection" class="pin-page__transfer-card pin-page__transfer-card--active">
              <div class="pin-page__transfer-title">Transfer instructions</div>
              <div><strong>Reference:</strong> {{ purchaseReference }}</div>
              <div><strong>Bank:</strong> {{ paymentCollection.bank_name }}</div>
              <div><strong>Account name:</strong> {{ paymentCollection.account_name }}</div>
              <div><strong>Account number:</strong> {{ paymentCollection.account_number }}</div>
              <div v-if="paymentCollection.instructions" class="pin-page__transfer-note">{{ paymentCollection.instructions }}</div>
              <div v-if="paymentCollection.narration_hint" class="pin-page__transfer-note">{{ paymentCollection.narration_hint }}</div>
            </div>

            <v-btn block color="primary" variant="flat" type="submit" :loading="submitting" :disabled="!selectedPlan">{{ form.payment_method === 'bank_transfer' ? 'Create Transfer Request' : 'Proceed to Payment' }}</v-btn>
            <v-btn v-if="purchaseReference" block variant="outlined" color="primary" :loading="verifying" @click="verifyPurchase">{{ paymentCollection ? 'Check Transfer Status' : 'Verify Payment' }}</v-btn>
            <v-btn v-if="purchaseReady" block variant="outlined" prepend-icon="mdi-file-pdf-box" @click="downloadDocket">Download PIN Docket PDF</v-btn>
          </v-form>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { publicEnrollmentAPI } from '../../utils/enrolleeApi'
import { useToast } from '../../composables/useToast'
import { useOrganizationSettings } from '../../composables/useOrganizationSettings'
import AppAlert from '../common/AppAlert.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'
import { openHostedCheckout } from '../../utils/hostedCheckout'

const route = useRoute()
const router = useRouter()
const { error } = useToast()
const { settings: org, fetchSettings } = useOrganizationSettings()
const loading = ref(false)
const submitting = ref(false)
const verifying = ref(false)
const metadata = ref({ premium_plans: [] })
const errors = reactive({})
const purchaseReference = ref('')
const docketToken = ref('')
const purchaseReady = ref(false)
const statusMessage = ref('')
const paymentCollection = ref(null)
const purchaserTypes = [{ label: 'Beneficiary', value: 'beneficiary' }, { label: 'Agent', value: 'agent' }]
const form = reactive({ premium_plan_id: null, purchaser_type: 'beneficiary', payment_method: 'online_payment', payer_name: '', payer_phone: '', payer_email: '', quantity: 1 })
const plans = computed(() => (metadata.value.premium_plans || []).filter((plan) => plan.payment_required))
const selectedPlan = computed(() => plans.value.find((plan) => plan.id === form.premium_plan_id) || null)
const selectedPlanSupportsBankTransfer = computed(() => Boolean(selectedPlan.value?.bank_transfer_available))
const selectedTransferAccount = computed(() => selectedPlan.value?.bank_transfer_account || null)
const totalAmount = computed(() => Number(selectedPlan.value?.amount || 0) * Math.max(1, Number(form.quantity || 1)))

const clearErrors = () => Object.keys(errors).forEach((key) => delete errors[key])
const purchasePins = async () => {
  clearErrors()
  paymentCollection.value = null
  submitting.value = true
  try {
    const response = await publicEnrollmentAPI.purchasePins(form)
    const payload = response.data?.data || {}
    purchaseReference.value = payload.purchase?.payment_reference || ''
    docketToken.value = payload.docket_token || ''
    paymentCollection.value = payload.payment_collection || null
    statusMessage.value = paymentCollection.value
      ? `Purchase ${purchaseReference.value} was created. Transfer the exact amount to the dedicated plan account, then check status to receive your PIN docket after confirmation.`
      : `Purchase ${purchaseReference.value} was created. Complete payment, then verify it to receive your PIN docket.`

    await router.replace({
      query: {
        ...route.query,
        payment_reference: purchaseReference.value,
        docket_token: docketToken.value,
      },
    })

    if (payload.checkout) {
      openHostedCheckout(payload.checkout, 'premium-pin-checkout')
    }
  } catch (err) {
    if (err.response?.status === 422) Object.assign(errors, err.response?.data?.errors || {})
    error(err?.response?.data?.message || 'Unable to initialize Premium PIN purchase.')
  } finally {
    submitting.value = false
  }
}

const verifyPurchase = async () => {
  if (!purchaseReference.value || !docketToken.value) return
  verifying.value = true
  try {
    const response = await publicEnrollmentAPI.verifyPinPurchase(purchaseReference.value, docketToken.value)
    const payload = response.data?.data || {}
    purchaseReady.value = payload.purchase?.payment_status === 'confirmed'
    paymentCollection.value = payload.purchase?.payer_details?.bank_transfer_account || paymentCollection.value
    statusMessage.value = purchaseReady.value
      ? `Payment confirmed. ${payload.pins?.length || 0} Premium PIN(s) are ready for download.`
      : `Payment is still ${payload.verification?.status || 'pending'}.`
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to verify Premium PIN purchase.')
  } finally {
    verifying.value = false
  }
}

const downloadDocket = async () => {
  try {
    const response = await publicEnrollmentAPI.downloadPinDocket(purchaseReference.value, docketToken.value)
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `premium-pin-docket-${purchaseReference.value}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to download Premium PIN docket.')
  }
}

onMounted(async () => {
  fetchSettings()
  loading.value = true
  try {
    metadata.value = (await publicEnrollmentAPI.metadata()).data?.data || metadata.value
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load public premium plans.')
  } finally {
    loading.value = false
  }

  purchaseReference.value = String(route.query.payment_reference || '')
  docketToken.value = String(route.query.docket_token || '')
  if (purchaseReference.value && docketToken.value) await verifyPurchase()
})

watch(() => form.premium_plan_id, () => {
  if (form.payment_method === 'bank_transfer' && !selectedPlanSupportsBankTransfer.value) {
    form.payment_method = 'online_payment'
  }
})
</script>

<style scoped>
.pin-page { min-height: 100vh; background: linear-gradient(145deg, #eef8f7 0%, #f8fafc 42%, #e8f1f8 100%); color: #102a43; }
.pin-page__header { max-width: 1120px; margin: 0 auto; padding: 28px 24px 20px; display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; flex-wrap: wrap; }
.pin-page__header h1 { font-size: 28px; font-weight: 800; }
.pin-page__header p, .pin-page__note { color: #526777; font-size: 13px; }
.pin-page__eyebrow { color: #0f766e; font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
.pin-page__main { max-width: 1120px; margin: 0 auto; padding: 0 24px 56px; }
.pin-page__panel { background: white; border: 1px solid #d6e2e8; padding: 18px; box-shadow: 0 16px 40px rgba(15, 45, 62, .06); }
.pin-page__panel h2 { font-size: 15px; font-weight: 750; }
.pin-page__plan { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 14px; text-align: left; padding: 13px; border: 1px solid #d6e2e8; background: white; }
.pin-page__plan--selected { border-color: #0f766e; background: #eef8f7; }
.pin-page__plan strong, .pin-page__plan span { display: block; }
.pin-page__plan span { margin-top: 4px; color: #526777; font-size: 12px; }
.pin-page__summary { margin: 8px 0 14px; padding: 10px; background: #f4f8fa; }
.pin-page__summary div { display: flex; justify-content: space-between; gap: 12px; padding: 4px 0; font-size: 12px; }
.pin-page__transfer-card { border: 1px solid #bfdbfe; background: #eff6ff; padding: 12px; font-size: 13px; color: #0f172a; }
.pin-page__transfer-card--active { border-color: #86efac; background: #f0fdf4; }
.pin-page__transfer-title { font-size: 13px; font-weight: 800; margin-bottom: 8px; }
.pin-page__transfer-note { margin-top: 6px; color: #334155; font-size: 12px; line-height: 1.5; }
</style>
