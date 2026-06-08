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
            <v-text-field v-model="form.payer_name" label="Full name / agent name" variant="outlined" density="compact" :error-messages="errors.payer_name" />
            <v-text-field v-model="form.payer_phone" label="Phone number" variant="outlined" density="compact" :error-messages="errors.payer_phone" />
            <v-text-field v-model="form.payer_email" label="Email address" type="email" variant="outlined" density="compact" :error-messages="errors.payer_email" />
            <v-text-field v-model.number="form.quantity" label="Number of PINs" type="number" min="1" max="500" variant="outlined" density="compact" :error-messages="errors.quantity" />

            <dl v-if="selectedPlan" class="pin-page__summary">
              <div><dt>Unit price</dt><dd><MoneyDisplay :value="selectedPlan.amount" currency="NGN" size="sm" /></dd></div>
              <div><dt>Total</dt><dd><MoneyDisplay :value="totalAmount" currency="NGN" size="sm" /></dd></div>
            </dl>

            <v-btn block color="primary" variant="flat" type="submit" :loading="submitting" :disabled="!selectedPlan">Proceed to Payment</v-btn>
            <v-btn v-if="purchaseReference" block variant="outlined" color="primary" :loading="verifying" @click="verifyPurchase">Verify Payment</v-btn>
            <v-btn v-if="purchaseReady" block variant="outlined" prepend-icon="mdi-file-pdf-box" @click="downloadDocket">Download PIN Docket PDF</v-btn>
          </v-form>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { publicEnrollmentAPI } from '../../utils/enrolleeApi'
import { useToast } from '../../composables/useToast'
import { useOrganizationSettings } from '../../composables/useOrganizationSettings'
import AppAlert from '../common/AppAlert.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'

const route = useRoute()
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
const purchaserTypes = [{ label: 'Beneficiary', value: 'beneficiary' }, { label: 'Agent', value: 'agent' }]
const form = reactive({ premium_plan_id: null, purchaser_type: 'beneficiary', payer_name: '', payer_phone: '', payer_email: '', quantity: 1 })
const plans = computed(() => (metadata.value.premium_plans || []).filter((plan) => plan.payment_required))
const selectedPlan = computed(() => plans.value.find((plan) => plan.id === form.premium_plan_id) || null)
const totalAmount = computed(() => Number(selectedPlan.value?.amount || 0) * Math.max(1, Number(form.quantity || 1)))

const clearErrors = () => Object.keys(errors).forEach((key) => delete errors[key])
const openCheckout = (url) => {
  const popup = window.open(url, 'premium-pin-checkout', 'width=520,height=760,noopener,noreferrer')
  if (!popup) window.location.href = url
}

const purchasePins = async () => {
  clearErrors()
  submitting.value = true
  try {
    const response = await publicEnrollmentAPI.purchasePins(form)
    const payload = response.data?.data || {}
    purchaseReference.value = payload.purchase?.payment_reference || ''
    docketToken.value = payload.docket_token || ''
    statusMessage.value = `Purchase ${purchaseReference.value} was created. Complete payment, then verify it to receive your PIN docket.`
    openCheckout(payload.checkout?.authorization_url)
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
</style>
