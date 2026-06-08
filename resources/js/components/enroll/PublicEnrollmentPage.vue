<template>
  <div class="enroll">
    <!-- TOP STRIP -->
    <div class="enroll__topbar">
      <div class="enroll__topbar-inner">
        <span><v-icon size="14">mdi-flag-outline</v-icon> Niger State Government</span>
        <span class="enroll__topbar-spacer" />
        <span>Hotline: {{ org.hotline }}</span>
      </div>
    </div>

    <header class="enroll__header">
      <div class="enroll__header-inner">
        <div class="enroll__brand">
          <img v-if="org.logo_url && !logoErr" :src="org.logo_url" alt="Organization logo" class="enroll__brand-mark enroll__brand-logo" @error="logoErr = true" />
          <div v-else class="enroll__brand-mark"><v-icon color="white" size="20">mdi-hospital-box</v-icon></div>
          <div>
            <div class="enroll__brand-name">{{ org.scheme_name }} Enrollment</div>
            <div class="enroll__brand-sub">{{ org.agency_name }}</div>
          </div>
        </div>
        <div class="enroll__header-actions">
          <v-btn variant="text" color="primary" @click="$router.push('/enroll/pins')">
            <v-icon start size="18">mdi-key-plus</v-icon> Buy Premium PINs
          </v-btn>
          <v-btn variant="text" @click="$router.push('/')">
            <v-icon start size="18">mdi-arrow-left</v-icon> Home
          </v-btn>
          <v-btn variant="outlined" color="primary" @click="$router.push('/enroll/login')">
            <v-icon start size="18">mdi-account-circle-outline</v-icon> Enrollee Portal
          </v-btn>
        </div>
      </div>
    </header>

    <div class="enroll__inner">
      <h1 class="enroll__title">Apply for Enrollment</h1>
      <p class="enroll__lede">Select a plan, complete your details, and submit your application for review.</p>

      <div v-if="successSummary" ref="successArea" class="enroll__success">
        <AppAlert
          tone="success"
          title="Application submitted successfully"
          :message="successSummary"
        />
      </div>

      <div v-if="submissionError" ref="errorArea" class="enroll__error">
        <AppAlert
          tone="danger"
          title="Application could not be submitted"
          :message="submissionError"
        />
      </div>

      <AppAlert
        v-if="paymentSummary"
        :tone="verifyingPayment ? 'info' : 'success'"
        title="Payment status"
        :message="paymentSummary"
      />

      <div class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-[1.2fr_0.8fr] tw-gap-4">
        <div class="tw-space-y-4">
          <!-- PLAN SELECTION -->
          <section class="enroll__panel">
            <header class="enroll__panel-head">
              <h2 class="enroll__panel-title">1. Select a Plan</h2>
              <span class="enroll__panel-note">Only plans approved for self-enrollment are listed</span>
            </header>

            <div v-if="loading" class="tw-flex tw-justify-center tw-py-10">
              <v-progress-circular indeterminate color="primary" size="40" />
            </div>

            <AppEmptyState
              v-else-if="!plans.length"
              title="No plans available"
              description="There are no premium plans currently open for self-enrollment."
              icon="mdi-shield-off-outline"
            />

            <div v-else class="enroll__plans">
              <button
                v-for="plan in plans"
                :key="plan.id"
                type="button"
                class="enroll__plan"
                :class="{ 'enroll__plan--selected': form.premium_plan_id === plan.id }"
                @click="selectPlan(plan)"
              >
                <div class="enroll__plan-radio">
                  <span v-if="form.premium_plan_id === plan.id" class="enroll__plan-radio-dot" />
                </div>
                <div class="enroll__plan-body">
                  <div class="enroll__plan-row">
                    <span class="enroll__plan-name">{{ plan.name }}</span>
                    <AppBadge :label="plan.payment_required ? 'Payment required' : 'No payment'" :tone="plan.payment_required ? 'warning' : 'success'" size="sm" />
                  </div>
                  <div class="enroll__plan-meta">
                    <span>{{ plan.programme?.name || 'NiCare programme' }}</span>
                    <span>&middot;</span>
                    <span>{{ plan.has_no_expiry ? 'No expiry' : `${plan.duration_days || 0} day(s) cover` }}</span>
                    <span>&middot;</span>
                    <span>{{ plan.waiting_period_days || 0 }} day waiting period</span>
                  </div>
                </div>
                <MoneyDisplay :value="plan.amount" currency="NGN" size="md" />
              </button>
            </div>
          </section>

          <!-- APPLICANT FORM -->
          <section class="enroll__panel">
            <header class="enroll__panel-head">
              <h2 class="enroll__panel-title">2. Your Details</h2>
              <span class="enroll__panel-note">Matched against your NIN during approval</span>
            </header>

            <v-form @submit.prevent="submitApplication">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3">
                <v-text-field v-model="form.first_name" label="First name" variant="outlined" density="compact" :error-messages="errors.first_name" />
                <v-text-field v-model="form.last_name" label="Last name" variant="outlined" density="compact" :error-messages="errors.last_name" />
                <v-text-field v-model="form.middle_name" label="Middle name" variant="outlined" density="compact" :error-messages="errors.middle_name" />
                <v-text-field v-model="form.nin" label="NIN" variant="outlined" density="compact" required :error-messages="errors.nin" />
                <v-text-field v-model="form.phone" label="Phone number" variant="outlined" density="compact" :error-messages="errors.phone" />
                <v-text-field v-model="form.email" label="Email address" variant="outlined" density="compact" :error-messages="errors.email" />
                <v-text-field v-model="form.date_of_birth" label="Date of birth" type="date" variant="outlined" density="compact" :error-messages="errors.date_of_birth" />
                <v-select v-model="form.sex" label="Sex" variant="outlined" density="compact" :items="sexOptions" item-title="label" item-value="value" :error-messages="errors.sex" />
                <v-select v-model="form.marital_status" label="Marital status" variant="outlined" density="compact" :items="maritalStatusOptions" item-title="label" item-value="value" :error-messages="errors.marital_status" />
                <v-select v-model="form.lga_id" label="LGA" variant="outlined" density="compact" :items="metadata.lgas" item-title="name" item-value="id" :error-messages="errors.lga_id" />
                <v-select v-model="form.ward_id" label="Ward" variant="outlined" density="compact" :items="metadata.wards" item-title="name" item-value="id" :error-messages="errors.ward_id" />
                <v-select v-model="form.facility_id" label="Preferred facility" variant="outlined" density="compact" :items="metadata.facilities" item-title="name" item-value="id" :error-messages="errors.facility_id" />
              </div>

              <div class="tw-mt-4 tw-grid tw-gap-3 lg:tw-grid-cols-[minmax(0,1fr)_220px]">
                <div class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                  <div class="tw-flex tw-flex-wrap tw-items-start tw-justify-between tw-gap-3">
                    <div>
                      <h3 class="tw-text-sm tw-font-semibold tw-text-slate-900">Passport photo</h3>
                      <p class="tw-mt-1 tw-text-xs tw-leading-5 tw-text-slate-500">
                        Upload a clear face photo so the approval officer can compare it with your verified NIN photo.
                      </p>
                    </div>
                    <v-btn variant="outlined" color="primary" prepend-icon="mdi-camera-plus-outline" @click="passportInput?.click()">
                      {{ passportPreview ? 'Change photo' : 'Upload photo' }}
                    </v-btn>
                  </div>

                  <input
                    ref="passportInput"
                    type="file"
                    accept="image/jpeg,image/png,image/jpg,image/gif"
                    class="tw-hidden"
                    @change="handlePassportSelected"
                  />

                  <p v-if="errors.passport?.length" class="tw-mt-3 tw-text-xs tw-font-medium tw-text-rose-700">
                    {{ errors.passport[0] }}
                  </p>
                </div>

                <div class="tw-flex tw-justify-center">
                  <div class="tw-flex tw-h-[220px] tw-w-[220px] tw-items-center tw-justify-center tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-100">
                    <img v-if="passportPreview" :src="passportPreview" alt="Passport preview" class="tw-h-full tw-w-full tw-object-cover" />
                    <div v-else class="tw-flex tw-flex-col tw-items-center tw-gap-2 tw-text-slate-400">
                      <v-icon size="40">mdi-account-box-outline</v-icon>
                      <span class="tw-text-xs tw-font-medium">No photo uploaded</span>
                    </div>
                  </div>
                </div>
              </div>

              <v-textarea
                v-model="form.address"
                label="Residential address"
                variant="outlined"
                density="compact"
                rows="2"
                class="tw-mt-3"
                :error-messages="errors.address"
              />

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3 tw-mt-3">
                <v-text-field
                  v-model="form.password"
                  label="Portal password"
                  type="password"
                  variant="outlined"
                  density="compact"
                  :error-messages="errors.password"
                  hint="At least 8 characters — used to sign in after approval"
                  persistent-hint
                />
                <v-text-field
                  v-model="form.password_confirmation"
                  label="Confirm password"
                  type="password"
                  variant="outlined"
                  density="compact"
                />
              </div>

              <div v-if="selectedPlan?.payment_required" class="tw-mt-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
                <h3 class="tw-text-sm tw-font-semibold tw-text-slate-900">How would you like to enroll?</h3>
                <v-radio-group v-model="form.enrollment_method" inline hide-details class="tw-mt-2">
                  <v-radio label="Enroll and pay online" value="online_payment" />
                  <v-radio label="Use a Premium PIN" value="premium_pin" />
                </v-radio-group>
                <v-text-field
                  v-if="form.enrollment_method === 'premium_pin'"
                  v-model="form.premium_pin"
                  label="Valid unused Premium PIN"
                  variant="outlined"
                  density="compact"
                  class="tw-mt-3"
                  :error-messages="errors.premium_pin"
                  hint="The PIN must be paid, unused, and issued for the selected plan."
                  persistent-hint
                />
              </div>

              <AppAlert
                v-if="selectedPlan?.payment_required && form.enrollment_method !== 'premium_pin'"
                class="tw-mt-3"
                tone="info"
                title="Secure online payment"
                :message="paymentInstruction"
              />

              <div class="tw-mt-4 tw-flex tw-flex-wrap tw-gap-2">
                <v-btn color="primary" variant="flat" :loading="submitting" type="submit" :disabled="!form.premium_plan_id">
                  <v-icon start size="18">mdi-send-outline</v-icon> Submit Application
                </v-btn>
                <v-btn variant="outlined" @click="resetForm">Reset</v-btn>
              </div>
            </v-form>
          </section>
        </div>

        <div class="tw-space-y-4">
          <!-- SELECTED PLAN -->
          <section class="enroll__panel">
            <header class="enroll__panel-head">
              <h2 class="enroll__panel-title">Plan Summary</h2>
            </header>

            <AppEmptyState
              v-if="!selectedPlan"
              title="No plan selected"
              description="Choose a plan to see its coverage and payment terms."
              icon="mdi-cursor-default-click-outline"
            />

            <dl v-else class="enroll__summary">
              <div><dt>Plan</dt><dd>{{ selectedPlan.name }}</dd></div>
              <div><dt>Programme</dt><dd>{{ selectedPlan.programme?.name || 'N/A' }}</dd></div>
              <div><dt>Premium</dt><dd><MoneyDisplay :value="selectedPlan.amount" currency="NGN" size="sm" /></dd></div>
              <div><dt>Coverage</dt><dd>{{ selectedPlan.has_no_expiry ? 'No expiry' : `${selectedPlan.duration_days || 0} days` }}</dd></div>
              <div><dt>Waiting period</dt><dd>{{ selectedPlan.waiting_period_days || 0 }} days</dd></div>
              <div><dt>Enrollment type</dt><dd>{{ selectedPlan.is_family_plan ? 'Family plan' : 'Principal only' }}</dd></div>
              <div><dt>Payment</dt><dd>{{ selectedPlan.payment_required ? selectedPlan.payment_gateway || activePaymentGatewayLabel : 'Not required' }}</dd></div>
            </dl>
          </section>

          <!-- FACILITY -->
          <section class="enroll__panel">
            <header class="enroll__panel-head">
              <h2 class="enroll__panel-title">Preferred Facility</h2>
            </header>

            <AppEmptyState
              v-if="!selectedFacility"
              title="No facility selected"
              description="Select an LGA, ward, and facility in the form."
              icon="mdi-map-marker-outline"
            />
            <dl v-else class="enroll__summary">
              <div><dt>Facility</dt><dd>{{ selectedFacility.name }}</dd></div>
              <div><dt>Type</dt><dd><FacilityBadge :type="selectedFacility.type" :label="selectedFacility.type" /></dd></div>
              <div><dt>LGA</dt><dd>{{ selectedFacility.lga?.name || selectedLgaName }}</dd></div>
              <div><dt>Ward</dt><dd>{{ selectedFacility.ward?.name || selectedWardName }}</dd></div>
              <div><dt>Address</dt><dd>{{ selectedFacility.address || 'Not available' }}</dd></div>
            </dl>
          </section>

          <!-- PROCESS -->
          <section class="enroll__panel">
            <header class="enroll__panel-head">
              <h2 class="enroll__panel-title">What Happens Next</h2>
            </header>
            <ol class="enroll__steps">
              <li><span>1</span>Your application and documents are received and queued for review.</li>
              <li><span>2</span>If your plan requires payment, a secure hosted payment page opens using the configured gateway.</li>
              <li><span>3</span>An enrollment officer verifies your NIN and reviews your details.</li>
              <li><span>4</span>Once approved, sign in to the enrollee portal with your chosen password.</li>
            </ol>
          </section>
        </div>
      </div>
    </div>

    <AppModal
      v-model="successDialog"
      title="Application submitted successfully"
      subtitle="Your application has been received"
      icon="mdi-check-circle-outline"
      color="success"
      size="sm"
    >
      <div class="tw-space-y-4">
        <div class="tw-flex tw-items-center tw-gap-3 tw-bg-emerald-50 tw-p-4 tw-text-emerald-950">
          <v-icon color="success" size="28">mdi-check-decagram</v-icon>
          <div>
            <div class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wide tw-text-emerald-700">Enrollee reference</div>
            <div class="tw-mt-1 tw-text-xl tw-font-extrabold">{{ submittedEnrolleeId }}</div>
          </div>
        </div>

        <p class="tw-text-sm tw-leading-6 tw-text-slate-600">{{ successSummary }}</p>

        <div v-if="successNextSteps.length">
          <h3 class="tw-text-sm tw-font-semibold tw-text-slate-900">What happens next</h3>
          <ol class="tw-mt-2 tw-space-y-2">
            <li v-for="(step, index) in successNextSteps" :key="step" class="tw-flex tw-gap-2 tw-text-sm tw-leading-5 tw-text-slate-600">
              <span class="tw-font-bold tw-text-emerald-700">{{ index + 1 }}.</span>
              <span>{{ step }}</span>
            </li>
          </ol>
        </div>
      </div>

      <template #actions>
        <v-btn color="success" variant="flat" @click="successDialog = false">Close</v-btn>
      </template>
    </AppModal>
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useToast } from '../../composables/useToast'
import { publicEnrollmentAPI } from '../../utils/enrolleeApi'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppModal from '../common/AppModal.vue'
import FacilityBadge from '../common/FacilityBadge.vue'
import MoneyDisplay from '../common/MoneyDisplay.vue'
import { useOrganizationSettings } from '../../composables/useOrganizationSettings'

const { error } = useToast()
const { settings: org, fetchSettings } = useOrganizationSettings()
const route = useRoute()

const logoErr = ref(false)
const loading = ref(false)
const submitting = ref(false)
const verifyingPayment = ref(false)
const passportInput = ref(null)
const passportFile = ref(null)
const passportPreview = ref('')
const metadata = ref({
  insurance_programmes: [],
  premium_plans: [],
  payment_gateways: [],
  active_payment_gateway: null,
  lgas: [],
  wards: [],
  facilities: [],
})
const errors = reactive({})
const successSummary = ref('')
const successDialog = ref(false)
const submittedEnrolleeId = ref('')
const successNextSteps = ref([])
const successArea = ref(null)
const errorArea = ref(null)
const submissionError = ref('')
const paymentSummary = ref('')

const form = reactive({
  premium_plan_id: null,
  nin: '',
  first_name: '',
  last_name: '',
  middle_name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  sex: null,
  marital_status: null,
  address: '',
  lga_id: null,
  ward_id: null,
  facility_id: null,
  password: '',
  password_confirmation: '',
  payment_reference: '',
  enrollment_method: 'online_payment',
  premium_pin: '',
})

const sexOptions = [
  { label: 'Male', value: 1 },
  { label: 'Female', value: 2 },
]

const maritalStatusOptions = [
  { label: 'Single', value: 1 },
  { label: 'Married', value: 2 },
  { label: 'Divorced', value: 3 },
  { label: 'Widowed', value: 4 },
]

const plans = computed(() => metadata.value.premium_plans || [])
const selectedPlan = computed(() => plans.value.find((plan) => plan.id === form.premium_plan_id) || null)
const selectedFacility = computed(() => (metadata.value.facilities || []).find((facility) => facility.id === form.facility_id) || null)
const selectedLgaName = computed(() => (metadata.value.lgas || []).find((item) => item.id === form.lga_id)?.name || 'Not selected')
const selectedWardName = computed(() => (metadata.value.wards || []).find((item) => item.id === form.ward_id)?.name || 'Not selected')
const activePaymentGatewayLabel = computed(() => {
  const code = selectedPlan.value?.payment_gateway || metadata.value.active_payment_gateway
  return (metadata.value.payment_gateways || []).find((gateway) => gateway.code === code)?.name || code || 'configured online gateway'
})
const paymentInstruction = computed(() => `After you submit this form, we will open ${activePaymentGatewayLabel.value} so you can pay securely online.`)

const setPassportPreview = (value) => {
  if (passportPreview.value && passportPreview.value.startsWith('blob:')) {
    URL.revokeObjectURL(passportPreview.value)
  }

  passportPreview.value = value || ''
}

const clearErrors = () => {
  Object.keys(errors).forEach((key) => {
    delete errors[key]
  })
}

const fetchMetadata = async () => {
  loading.value = true

  try {
    const response = await publicEnrollmentAPI.metadata({
      insurance_programme_id: selectedPlan.value?.insurance_programme_id || undefined,
      lga_id: form.lga_id || undefined,
      ward_id: form.ward_id || undefined,
    })

    metadata.value = response.data.data || metadata.value
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to load public enrollment metadata.')
  } finally {
    loading.value = false
  }
}

const selectPlan = (plan) => {
  if (form.premium_plan_id !== plan.id) {
    form.premium_pin = ''
  }
  form.premium_plan_id = plan.id
  if (!plan.payment_required) {
    form.enrollment_method = 'online_payment'
  }
  successSummary.value = ''
  submissionError.value = ''
}

const resetForm = () => {
  clearErrors()
  successSummary.value = ''
  submissionError.value = ''
  submittedEnrolleeId.value = ''
  successNextSteps.value = []
  paymentSummary.value = ''
  clearApplicationFields()
}

const clearApplicationFields = () => {
  passportFile.value = null
  setPassportPreview('')
  Object.assign(form, {
    premium_plan_id: null,
    nin: '',
    first_name: '',
    last_name: '',
    middle_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    sex: null,
    marital_status: null,
    address: '',
    lga_id: null,
    ward_id: null,
    facility_id: null,
    password: '',
    password_confirmation: '',
    payment_reference: '',
    enrollment_method: 'online_payment',
    premium_pin: '',
  })

  if (passportInput.value) {
    passportInput.value.value = ''
  }
}

const scrollToSuccess = async () => {
  await nextTick()
  successArea.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const scrollToError = async () => {
  await nextTick()
  errorArea.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const openCheckoutWindow = (authorizationUrl) => {
  if (!authorizationUrl) return

  const popup = window.open(authorizationUrl, 'premium-checkout', 'width=520,height=760,noopener,noreferrer')

  if (!popup) {
    window.location.href = authorizationUrl
  }
}

const verifyReturnedPayment = async (reference) => {
  if (!reference) return

  verifyingPayment.value = true
  paymentSummary.value = ''

  try {
    const response = await publicEnrollmentAPI.verifyPayment(reference)
    const payload = response.data?.data || {}
    const purchase = payload.purchase
    const verification = payload.verification || {}

    if (verification.paid) {
      paymentSummary.value = `Payment ${purchase?.payment_reference || reference} was verified successfully through ${verification.provider || activePaymentGatewayLabel.value}. Your enrollment application remains pending approval and NIN verification.`
    } else {
      paymentSummary.value = `Payment ${purchase?.payment_reference || reference} is still ${verification.status || 'pending'}. Complete the checkout and return to this page if you have not finished payment.`
    }
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to verify your payment status right now.')
  } finally {
    verifyingPayment.value = false
  }
}

const submitApplication = async () => {
  clearErrors()
  successSummary.value = ''
  submissionError.value = ''
  paymentSummary.value = ''
  submitting.value = true

  try {
    const requestData = new FormData()

    Object.entries(form).forEach(([key, value]) => {
      if (value === null || value === '') {
        return
      }

      requestData.append(key, value)
    })

    if (passportFile.value) {
      requestData.append('passport', passportFile.value)
    }

    const response = await publicEnrollmentAPI.createApplication(requestData)
    const responseData = response.data.data
    const enrolleeId = responseData?.enrollee?.enrollee_id
    const paymentRef = responseData?.purchase?.payment_reference
    const checkout = responseData?.payment_checkout

    successSummary.value = responseData?.requires_payment
      ? `Application ${enrolleeId} submitted. We are opening ${activePaymentGatewayLabel.value} for payment reference ${paymentRef}. Approval continues after payment confirmation and NIN verification.`
      : responseData?.enrollment_method === 'premium_pin'
        ? `Application ${enrolleeId} submitted. Your Premium PIN has been accepted, and the application is awaiting approval and NIN verification.`
      : `Application ${enrolleeId} submitted and is awaiting approval and NIN verification.`

    submittedEnrolleeId.value = enrolleeId || ''
    successNextSteps.value = responseData?.next_steps || []
    successDialog.value = true
    clearApplicationFields()
    scrollToSuccess()

    if (responseData?.requires_payment && checkout?.authorization_url) {
      openCheckoutWindow(checkout.authorization_url)
    }
  } catch (err) {
    const message = err?.response?.data?.message || 'Unable to submit your application right now.'

    if (err.response?.status === 422) {
      const serverErrors = err.response?.data?.errors || {}
      Object.entries(serverErrors).forEach(([key, value]) => {
        errors[key] = value
      })
    }

    submissionError.value = message
    error(message)
    scrollToError()
  } finally {
    submitting.value = false
  }
}

const handlePassportSelected = (event) => {
  const file = event.target.files?.[0]
  clearErrors()

  if (!file) {
    passportFile.value = null
    setPassportPreview('')
    return
  }

  if (!file.type.startsWith('image/')) {
    errors.passport = ['Please select a valid image file.']
    passportFile.value = null
    setPassportPreview('')
    return
  }

  if (file.size > 2 * 1024 * 1024) {
    errors.passport = ['Passport photo must be 2MB or smaller.']
    passportFile.value = null
    setPassportPreview('')
    return
  }

  passportFile.value = file
  setPassportPreview(URL.createObjectURL(file))
}

watch(() => form.lga_id, async () => {
  form.ward_id = null
  form.facility_id = null
  await fetchMetadata()
})

watch(() => form.ward_id, async () => {
  form.facility_id = null
  await fetchMetadata()
})

onMounted(() => {
  fetchSettings()
  fetchMetadata()

  const returnedReference = route.query.payment_reference || route.query.reference || route.query.trxref
  if (returnedReference) {
    verifyReturnedPayment(String(returnedReference))
  }
})
</script>

<style scoped>
.enroll {
  min-height: 100vh;
  background: var(--qds-color-bg);
  font-family: var(--qds-font-sans);
}

.enroll__topbar {
  background: #0b1f33;
  color: rgba(255, 255, 255, 0.78);
  font-size: 12px;
}
.enroll__topbar-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 6px 24px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.enroll__topbar-inner > span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}
.enroll__topbar-spacer { flex: 1; }

.enroll__header {
  background: var(--qds-color-surface);
  border-bottom: 1px solid var(--qds-color-border);
}
.enroll__header-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 12px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.enroll__brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.enroll__brand-mark {
  height: 38px;
  width: 38px;
  display: grid;
  place-items: center;
  background: var(--qds-color-primary);
}
.enroll__brand-logo {
  object-fit: contain;
  background: var(--qds-color-surface-muted);
  border: 1px solid var(--qds-color-border);
  padding: 4px;
}
.enroll__brand-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
  line-height: 1.2;
}
.enroll__brand-sub {
  font-size: 11px;
  color: var(--qds-color-text-secondary);
}
.enroll__header-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.enroll__inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 24px 24px 56px;
}
.enroll__title {
  font-size: 22px;
  font-weight: 800;
  color: var(--qds-color-text);
  margin-bottom: 4px;
}
.enroll__lede {
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  margin-bottom: 16px;
}

.enroll__success {
  margin-bottom: 16px;
  scroll-margin-top: 20px;
  border: 1px solid #6ee7b7;
  background: #d1fae5;
  box-shadow: 0 10px 28px rgba(5, 150, 105, 0.12);
}

.enroll__success :deep(.qds-alert) {
  border: 0;
  background: transparent;
  color: #064e3b;
}

.enroll__error {
  margin-bottom: 16px;
  scroll-margin-top: 20px;
  border: 1px solid #fda4af;
  background: #fff1f2;
  box-shadow: 0 10px 28px rgba(190, 18, 60, 0.1);
}

.enroll__error :deep(.qds-alert) {
  border: 0;
  background: transparent;
  color: #881337;
}

.enroll__panel {
  background: var(--qds-color-surface);
  border: 1px solid var(--qds-color-border);
  padding: 16px;
}
.enroll__panel-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}
.enroll__panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
}
.enroll__panel-note {
  font-size: 11px;
  color: var(--qds-color-text-muted);
}

/* PLAN LIST */
.enroll__plans {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.enroll__plan {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  text-align: left;
  background: var(--qds-color-surface);
  border: 1px solid var(--qds-color-border);
  padding: 12px;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.enroll__plan:hover {
  border-color: var(--qds-color-primary);
}
.enroll__plan--selected {
  border-color: var(--qds-color-primary);
  background: rgba(11, 107, 121, 0.05);
}
.enroll__plan-radio {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  border: 1px solid var(--qds-color-text-muted);
  display: grid;
  place-items: center;
}
.enroll__plan--selected .enroll__plan-radio {
  border-color: var(--qds-color-primary);
}
.enroll__plan-radio-dot {
  width: 10px;
  height: 10px;
  background: var(--qds-color-primary);
}
.enroll__plan-body {
  flex: 1;
  min-width: 0;
}
.enroll__plan-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}
.enroll__plan-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--qds-color-text);
}
.enroll__plan-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 12px;
  color: var(--qds-color-text-secondary);
}

/* SUMMARY DEFINITION LISTS */
.enroll__summary {
  display: flex;
  flex-direction: column;
  gap: 0;
  margin: 0;
}
.enroll__summary > div {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid var(--qds-color-border);
  font-size: 13px;
}
.enroll__summary > div:last-child {
  border-bottom: none;
}
.enroll__summary dt {
  color: var(--qds-color-text-secondary);
  flex-shrink: 0;
}
.enroll__summary dd {
  margin: 0;
  font-weight: 600;
  color: var(--qds-color-text);
  text-align: right;
}

/* PROCESS STEPS */
.enroll__steps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.enroll__steps li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 13px;
  color: var(--qds-color-text-secondary);
  line-height: 1.5;
}
.enroll__steps li span {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  font-size: 11px;
  font-weight: 800;
  color: var(--qds-color-primary);
  border: 1px solid var(--qds-color-primary);
}
</style>
