<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col lg:tw-flex-row lg:tw-items-center lg:tw-justify-between tw-gap-3">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900">Demo Enrollee Enrollment</h1>
          <p class="tw-text-sm tw-text-gray-600">Capture biodata, select programme coverage, validate funding, and approve with real NiCARE APIs.</p>
        </div>
        <v-btn variant="tonal" prepend-icon="mdi-auto-fix" @click="fillSampleData">Fill Sample Data</v-btn>
      </div>

      <div class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-p-4 tw-shadow-sm">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3">
          <div v-for="(item, index) in flowSteps" :key="item" class="tw-flex tw-gap-3 tw-rounded tw-border tw-border-gray-100 tw-p-3">
            <div class="tw-flex tw-h-8 tw-w-8 tw-items-center tw-justify-center tw-rounded-full tw-bg-emerald-50 tw-text-sm tw-font-bold tw-text-emerald-700">{{ index + 1 }}</div>
            <p class="tw-text-sm tw-text-gray-700">{{ item }}</p>
          </div>
        </div>
      </div>

      <v-stepper v-model="step" class="tw-bg-white tw-border tw-border-gray-100 tw-rounded-lg tw-shadow-sm" alt-labels>
        <v-stepper-header>
          <v-stepper-item v-for="item in stepItems" :key="item.value" :value="item.value" :title="item.title" />
        </v-stepper-header>

        <v-stepper-window>
          <v-stepper-window-item :value="1">
            <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4 tw-p-4">
              <v-text-field v-model="form.first_name" label="First name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.last_name" label="Last name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.middle_name" label="Middle name" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.nin" label="NIN" variant="outlined" density="comfortable" />
              <v-select v-model="form.sex" :items="sexOptions" label="Sex" variant="outlined" density="comfortable" />
              <v-select v-model="form.marital_status" :items="maritalOptions" label="Marital status" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.date_of_birth" label="Date of birth" type="date" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.phone" label="Phone" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.email" label="Email" variant="outlined" density="comfortable" />
              <v-textarea v-model="form.address" label="Address" rows="2" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.village" label="Village" variant="outlined" density="comfortable" />
              <v-text-field v-model="form.occupation" label="Occupation" variant="outlined" density="comfortable" />
              <v-switch v-model="form.pregnant" label="Pregnant" color="primary" hide-details />
              <v-text-field v-model="form.disability" label="Disability" variant="outlined" density="comfortable" />
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="2">
            <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4 tw-p-4">
              <v-select v-model="form.insurance_programme_id" :items="metadata.programmes" item-title="name" item-value="id" label="Insurance programme" variant="outlined" density="comfortable" />
              <v-select v-model="form.enrollee_category_id" :items="filteredCategories" item-title="name" item-value="id" label="Enrollee category" variant="outlined" density="comfortable" clearable />
              <v-select v-model="form.premium_plan_id" :items="filteredPlans" item-title="name" item-value="id" label="Premium plan" variant="outlined" density="comfortable" />
              <v-select v-model="form.benefit_package_id" :items="metadata.benefit_packages" item-title="name" item-value="id" label="Benefit package" variant="outlined" density="comfortable" clearable />

              <div v-if="selectedPlan" class="tw-rounded tw-border tw-border-emerald-100 tw-bg-emerald-50 tw-p-4 tw-text-sm tw-text-gray-800 md:tw-col-span-2 lg:tw-col-span-3">
                <div class="tw-font-bold tw-text-gray-900">{{ selectedPlan.name }}</div>
                <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-2 tw-mt-2">
                  <span>Amount: {{ money(selectedPlan.amount) }}</span>
                  <span>Duration: {{ selectedPlan.has_no_expiry ? 'No Expiry' : `${selectedPlan.duration_days} days` }}</span>
                  <span>Waiting: {{ selectedPlan.waiting_period_days || 0 }} days</span>
                  <span>Consultant fee: {{ money(selectedPlan.consultant_fee) }}</span>
                  <span>Family: {{ selectedPlan.is_family_plan ? `Yes, ${selectedPlan.maximum_dependants || 0} dependants` : 'No' }}</span>
                  <span>Payment: {{ selectedPlan.payment_required ? 'Required' : 'Not required' }}</span>
                  <span>Gateway: {{ selectedPlan.payment_gateway || 'N/A' }}</span>
                </div>
              </div>
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="3">
            <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4 tw-p-4">
              <v-select v-model="form.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding type" variant="outlined" density="comfortable" clearable />
              <v-select v-if="showSponsorFields" v-model="form.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" variant="outlined" density="comfortable" clearable />
              <v-select v-if="showSponsorFields" v-model="form.enrollment_phase_id" :items="filteredEnrollmentPhases" item-title="name" item-value="id" label="Enrollment phase" variant="outlined" density="comfortable" clearable />
              <v-select v-if="showVulnerableGroup" v-model="form.vulnerable_group_id" :items="metadata.vulnerable_groups" item-title="name" item-value="id" label="Vulnerable group" variant="outlined" density="comfortable" clearable />
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="4">
            <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4 tw-p-4">
              <v-select v-model="form.lga_id" :items="metadata.lgas" item-title="name" item-value="id" label="LGA" variant="outlined" density="comfortable" />
              <v-select v-model="form.ward_id" :items="filteredWards" item-title="name" item-value="id" label="Ward" variant="outlined" density="comfortable" />
              <v-select v-model="form.facility_id" :items="filteredFacilities" item-title="name" item-value="id" label="Facility" variant="outlined" density="comfortable" />
              <div v-if="selectedFacility" class="tw-rounded tw-border tw-border-gray-100 tw-p-4 tw-text-sm md:tw-col-span-3">
                <strong>{{ selectedFacility.name }}</strong>
                <span class="tw-ml-2 tw-text-gray-600">{{ selectedFacility.code || selectedFacility.facility_code || '' }}</span>
                <div class="tw-text-gray-600">{{ selectedFacility.ownership || selectedFacility.type || 'Facility details available after selection.' }}</div>
              </div>
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="5">
            <section class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4 tw-p-4">
              <v-select v-model="form.relationship_to_principal" :items="relationshipOptions" label="Relationship to principal" variant="outlined" density="comfortable" />
              <v-autocomplete
                v-if="form.relationship_to_principal !== 1"
                v-model="form.principal_enrollee_id"
                v-model:search="principalSearch"
                :items="principalOptions"
                item-title="label"
                item-value="id"
                label="Principal enrollee"
                variant="outlined"
                density="comfortable"
                clearable
              />
              <v-alert v-if="form.relationship_to_principal !== 1 && selectedPlan && !selectedPlan.is_family_plan" type="error" variant="tonal" density="comfortable" class="md:tw-col-span-2">
                The selected premium plan is not a family plan. Dependant enrollment is blocked.
              </v-alert>
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="6">
            <section class="tw-space-y-4 tw-p-4">
              <v-alert v-if="!selectedPlan?.payment_required" type="success" variant="tonal">Payment not required for this plan.</v-alert>
              <div v-else class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-alert type="warning" variant="tonal" class="md:tw-col-span-2">
                  This plan requires payment through {{ selectedPlan.payment_gateway || 'the configured gateway' }} before approval.
                </v-alert>
                <v-text-field v-model="payment.pin" label="Premium PIN" variant="outlined" density="comfortable" />
                <v-btn color="primary" variant="tonal" :loading="validatingPin" @click="validatePin">Validate PIN</v-btn>
                <v-alert v-if="payment.validatedPin" type="success" variant="tonal" class="md:tw-col-span-2">
                  PIN validated. It will be applied after enrollee creation.
                </v-alert>
              </div>
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="7">
            <section class="tw-space-y-4 tw-p-4">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-3 tw-text-sm">
                <SummaryItem label="Name" :value="`${form.first_name} ${form.last_name}`" />
                <SummaryItem label="Programme" :value="selectedProgramme?.name" />
                <SummaryItem label="Category" :value="selectedCategory?.name" />
                <SummaryItem label="Premium Plan" :value="selectedPlan?.name" />
                <SummaryItem label="Coverage" :value="selectedPlan?.has_no_expiry ? 'No Expiry' : `${selectedPlan?.duration_days || 0} days`" />
                <SummaryItem label="Funding" :value="selectedFundingType?.name" />
                <SummaryItem label="Benefactor" :value="selectedBenefactor?.name || 'N/A'" />
                <SummaryItem label="Facility" :value="selectedFacility?.name" />
                <SummaryItem label="Relationship" :value="relationshipOptions.find((item) => item.value === form.relationship_to_principal)?.title" />
                <SummaryItem label="Payment" :value="selectedPlan?.payment_required ? 'Required' : 'Not required'" />
              </div>
              <v-btn color="primary" :loading="submitting" @click="submitEnrollment">Create Pending Enrollee</v-btn>
            </section>
          </v-stepper-window-item>

          <v-stepper-window-item :value="8">
            <section class="tw-space-y-4 tw-p-4">
              <v-alert v-if="createdEnrollee" type="success" variant="tonal">
                Enrollee {{ createdEnrollee.enrollee_id }} created. Status: {{ createdEnrollee.status_label || createdEnrollee.status }}.
              </v-alert>
              <v-btn v-if="createdEnrollee && Number(createdEnrollee.status) === 0" color="success" :loading="approving" @click="approveEnrollment">Approve Enrollee</v-btn>
              <div v-if="approvedEnrollee" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-3 tw-text-sm">
                <SummaryItem label="Status" value="Active" />
                <SummaryItem label="Approval date" :value="date(approvedEnrollee.approval_date)" />
                <SummaryItem label="Coverage start" :value="date(approvedEnrollee.coverage_start_date)" />
                <SummaryItem label="Coverage end" :value="approvedEnrollee.coverage_end_date ? date(approvedEnrollee.coverage_end_date) : 'No Expiry'" />
              </div>
            </section>
          </v-stepper-window-item>
        </v-stepper-window>

        <v-divider />
        <div class="tw-flex tw-justify-between tw-p-4">
          <v-btn variant="tonal" :disabled="step === 1" @click="step--">Back</v-btn>
          <v-btn v-if="step < 8" color="primary" @click="step++">Next</v-btn>
        </div>
      </v-stepper>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const SummaryItem = {
  props: { label: String, value: [String, Number, Boolean] },
  template: `<div class="tw-rounded tw-border tw-border-gray-100 tw-p-3"><div class="tw-text-xs tw-font-semibold tw-uppercase tw-text-gray-500">{{ label }}</div><div class="tw-mt-1 tw-text-sm tw-font-semibold tw-text-gray-900">{{ value || 'N/A' }}</div></div>`,
}

const { success, error } = useToast()
const step = ref(1)
const metadata = ref({
  programmes: [],
  categories: [],
  benefit_packages: [],
  premium_plans: [],
  funding_types: [],
  benefactors: [],
  enrollment_phases: [],
  vulnerable_groups: [],
  lgas: [],
  wards: [],
  facilities: [],
})
const principalSearch = ref('')
const principalOptions = ref([])
const validatingPin = ref(false)
const submitting = ref(false)
const approving = ref(false)
const createdEnrollee = ref(null)
const approvedEnrollee = ref(null)
const payment = ref({ pin: '', validatedPin: null })

const form = ref({
  first_name: '',
  last_name: '',
  middle_name: '',
  nin: '',
  sex: 1,
  marital_status: 1,
  date_of_birth: '',
  phone: '',
  email: '',
  address: '',
  village: '',
  pregnant: false,
  disability: '',
  occupation: '',
  insurance_programme_id: null,
  enrollee_category_id: null,
  premium_plan_id: null,
  benefit_package_id: null,
  funding_type_id: null,
  benefactor_id: null,
  enrollment_phase_id: null,
  vulnerable_group_id: null,
  lga_id: null,
  ward_id: null,
  facility_id: null,
  relationship_to_principal: 1,
  principal_enrollee_id: null,
})

const stepItems = [
  { value: 1, title: 'Biodata' },
  { value: 2, title: 'Programme' },
  { value: 3, title: 'Funding' },
  { value: 4, title: 'Facility' },
  { value: 5, title: 'Family' },
  { value: 6, title: 'Payment' },
  { value: 7, title: 'Review' },
  { value: 8, title: 'Approval' },
]
const flowSteps = [
  'Admin sets up LGAs, wards and facilities.',
  'Admin sets up programmes, categories, benefit packages, premium plans, funding types, benefactors and phases.',
  'Officer captures enrollee biodata.',
  'Officer selects programme, category and premium plan.',
  'Officer selects funding source.',
  'Officer assigns facility, LGA and ward.',
  'Officer links principal/dependant if applicable.',
  'System validates payment, PIN, sponsorship or payroll.',
  'System checks duplicates.',
  'Enrollee is created as pending.',
  'Admin approves enrollee.',
  'System calculates coverage_start_date.',
  'System sets coverage_end_date or leaves it null for no-expiry plans.',
  'Enrollee becomes active and eligible for care/capitation.',
]
const sexOptions = [{ title: 'Male', value: 1 }, { title: 'Female', value: 2 }]
const maritalOptions = [{ title: 'Single', value: 1 }, { title: 'Married', value: 2 }, { title: 'Divorced', value: 3 }, { title: 'Widowed', value: 4 }]
const relationshipOptions = [{ title: 'Principal', value: 1 }, { title: 'Spouse', value: 2 }, { title: 'Child', value: 3 }, { title: 'Other', value: 4 }]

const selectedProgramme = computed(() => metadata.value.programmes.find((item) => item.id === form.value.insurance_programme_id))
const selectedCategory = computed(() => metadata.value.categories.find((item) => item.id === form.value.enrollee_category_id))
const selectedPlan = computed(() => metadata.value.premium_plans.find((item) => item.id === form.value.premium_plan_id))
const selectedFundingType = computed(() => metadata.value.funding_types.find((item) => item.id === form.value.funding_type_id))
const selectedBenefactor = computed(() => metadata.value.benefactors.find((item) => item.id === form.value.benefactor_id))
const selectedFacility = computed(() => metadata.value.facilities.find((item) => item.id === form.value.facility_id))
const filteredCategories = computed(() => metadata.value.categories.filter((item) => !form.value.insurance_programme_id || item.insurance_programme_id === form.value.insurance_programme_id))
const filteredPlans = computed(() => metadata.value.premium_plans.filter((item) => !form.value.insurance_programme_id || item.insurance_programme_id === form.value.insurance_programme_id))
const filteredWards = computed(() => metadata.value.wards.filter((item) => !form.value.lga_id || item.lga_id === form.value.lga_id))
const filteredFacilities = computed(() => metadata.value.facilities.filter((item) => {
  if (form.value.lga_id && item.lga_id !== form.value.lga_id) return false
  if (form.value.ward_id && item.ward_id !== form.value.ward_id) return false
  return true
}))
const filteredEnrollmentPhases = computed(() => metadata.value.enrollment_phases.filter((item) => !form.value.benefactor_id || item.benefactor_id === form.value.benefactor_id))
const showSponsorFields = computed(() => {
  const name = (selectedFundingType.value?.name || selectedFundingType.value?.code || '').toLowerCase()
  return name && !['self', 'premium', 'self-funded', 'self funded'].includes(name)
})
const showVulnerableGroup = computed(() => {
  const text = `${selectedProgramme.value?.name || ''} ${selectedCategory.value?.name || ''}`.toLowerCase()
  return text.includes('vulnerable') || text.includes('bhcpf')
})

const loadMetadata = async () => {
  metadata.value = (await premiumAPI.metadata()).data.data
}
const fillSampleData = () => {
  form.value = {
    ...form.value,
    first_name: 'Demo',
    last_name: `Enrollee${Math.floor(Math.random() * 1000)}`,
    middle_name: 'NiCARE',
    nin: `${Math.floor(10000000000 + Math.random() * 89999999999)}`,
    sex: 1,
    marital_status: 1,
    date_of_birth: '1992-05-10',
    phone: `080${Math.floor(10000000 + Math.random() * 89999999)}`,
    email: `demo${Date.now()}@nicare.test`,
    address: 'Demo address',
    village: 'Demo village',
    occupation: 'Trader',
  }
}
const validatePin = async () => {
  validatingPin.value = true
  try {
    payment.value.validatedPin = (await premiumAPI.validatePin({ pin: payment.value.pin })).data.data
    success('PIN validated')
  } catch (err) {
    error(err?.response?.data?.message || 'PIN validation failed')
  } finally {
    validatingPin.value = false
  }
}
const submitEnrollment = async () => {
  if (selectedPlan.value?.payment_required && !payment.value.validatedPin) {
    error('Validate a Premium PIN or create a paid invoice before approval.')
  }
  submitting.value = true
  try {
    const response = await enrolleeAPI.create(form.value)
    createdEnrollee.value = response.data.data
    if (payment.value.validatedPin) {
      const pinResponse = await premiumAPI.usePin(payment.value.validatedPin.id, {
        enrollee_id: createdEnrollee.value.id,
        facility_id: form.value.facility_id,
      })
      approvedEnrollee.value = pinResponse.data.data
      createdEnrollee.value = approvedEnrollee.value
    }
    success('Enrollee created')
    step.value = 8
  } catch (err) {
    error(err?.response?.data?.message || 'Enrollment failed')
  } finally {
    submitting.value = false
  }
}
const approveEnrollment = async () => {
  approving.value = true
  try {
    approvedEnrollee.value = (await enrolleeAPI.approve(createdEnrollee.value.id)).data.data
    success('Enrollee approved')
  } catch (err) {
    error(err?.response?.data?.message || 'Approval failed')
  } finally {
    approving.value = false
  }
}
const money = (amount) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(Number(amount || 0))
const date = (value) => value ? new Date(value).toLocaleDateString() : 'N/A'

watch(() => form.value.insurance_programme_id, () => {
  form.value.enrollee_category_id = null
  form.value.premium_plan_id = null
})
watch(() => form.value.lga_id, () => {
  form.value.ward_id = null
  form.value.facility_id = null
})
watch(() => form.value.ward_id, () => {
  form.value.facility_id = null
})
watch(principalSearch, async (value) => {
  if (!value || value.length < 2) return
  const response = await enrolleeAPI.getAll({ search: value, per_page: 10 })
  const records = response.data.data?.data || response.data.data || []
  principalOptions.value = records.map((item) => ({ ...item, label: `${item.enrollee_id} - ${item.full_name || `${item.first_name} ${item.last_name}`}` }))
})

onMounted(loadMetadata)
</script>
