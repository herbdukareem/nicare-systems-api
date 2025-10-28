<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Submit Claim</h1>
          <p class="tw-text-gray-600 tw-mt-1">Submit claims for referrals or PA codes with approved services</p>
        </div>
      </div>

      <!-- Main Form Card -->
      <v-card class="tw-elevation-1">
        <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-file-document-plus</v-icon>
          Claim Details
        </v-card-title>

        <v-card-text class="tw-p-6">
          <v-form ref="formRef" v-model="formValid" @submit.prevent="submitClaim">
            <v-row>
              <!-- Step 1: Select Referral or PA Code -->
              <v-col cols="12">
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">
                  Step 1: Select Referral or PA Code
                </h3>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedReferralId"
                  :items="referrals"
                  item-title="referral_code"
                  item-value="id"
                  label="Select Referral *"
                  variant="outlined"
                  :rules="[rules.referralOrPACode]"
                  @update:modelValue="onReferralSelected"
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props">
                      <v-list-item-title>{{ item.raw.referral_code }}</v-list-item-title>
                      <v-list-item-subtitle>
                        {{ item.raw.enrollee_full_name }} - {{ item.raw.nicare_number }}
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedPACodeId"
                  :items="paCodes"
                  item-title="pa_code"
                  item-value="id"
                  label="Or Select PA Code *"
                  variant="outlined"
                  :rules="[rules.referralOrPACode]"
                  @update:modelValue="onPACodeSelected"
                >
                  <template #item="{ props, item }">
                    <v-list-item v-bind="props">
                      <v-list-item-title>{{ item.raw.pa_code }}</v-list-item-title>
                      <v-list-item-subtitle>
                        {{ item.raw.enrollee_name }} - {{ item.raw.nicare_number }}
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>

              <!-- Step 2: Select Services -->
              <v-col cols="12">
                <v-divider class="tw-my-4" />
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-800 tw-mb-4">
                  Step 2: Select Services (Only services defined for this case)
                </h3>
              </v-col>

              <v-col cols="12">
                <ClaimServiceSelector
                  v-model="selectedServices"
                  :referral-id="selectedReferralId"
                  :pa-code-id="selectedPACodeId"
                  :disabled="!selectedReferralId && !selectedPACodeId"
                  @services-loaded="onServicesLoaded"
                />
              </v-col>

              <!-- Action Buttons -->
              <v-col cols="12" class="tw-flex tw-gap-3 tw-justify-end">
                <v-btn
                  variant="outlined"
                  @click="resetForm"
                >
                  Clear
                </v-btn>
                <v-btn
                  color="primary"
                  :loading="submitting"
                  :disabled="!formValid || selectedServices.length === 0"
                  @click="submitClaim"
                >
                  Submit Claim
                </v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import { formatPrice } from '@/js/utils/formatters'

const router = useRouter()
const { success, error } = useToast()

// Form state
const formRef = ref(null)
const formValid = ref(false)
const submitting = ref(false)
const loadingServices = ref(false)

// Data
const referrals = ref([])
const paCodes = ref([])
const availableServices = ref([])

// Selected values
const selectedReferralId = ref(null)
const selectedPACodeId = ref(null)
const selectedServices = ref([])

// Validation rules
const rules = {
  required: v => !!v || 'This field is required',
  referralOrPACode: v => {
    if (selectedReferralId.value || selectedPACodeId.value) return true
    return 'Please select either a referral or PA code'
  }
}

// Computed
const totalAmount = computed(() => {
  return availableServices.value
    .filter(s => selectedServices.value.includes(s.id))
    .reduce((total, service) => total + (parseFloat(service.price) || 0), 0)
})

// Methods
const fetchReferrals = async () => {
  try {
    const response = await fetch('/api/v1/referrals?status=approved', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    })
    const data = await response.json()
    referrals.value = data.data || []
  } catch (err) {
    console.error('Failed to fetch referrals:', err)
  }
}

const fetchPACodes = async () => {
  try {
    const response = await fetch('/api/v1/pa-codes?status=active', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    })
    const data = await response.json()
    paCodes.value = data.data || []
  } catch (err) {
    console.error('Failed to fetch PA codes:', err)
  }
}

const onReferralSelected = async () => {
  selectedPACodeId.value = null
  selectedServices.value = []
  await fetchServices()
}

const onPACodeSelected = async () => {
  selectedReferralId.value = null
  selectedServices.value = []
  await fetchServices()
}

const fetchServices = async () => {
  if (!selectedReferralId.value && !selectedPACodeId.value) {
    availableServices.value = []
    return
  }

  loadingServices.value = true
  try {
    const params = new URLSearchParams()
    if (selectedReferralId.value) {
      params.append('referral_id', selectedReferralId.value)
    }
    if (selectedPACodeId.value) {
      params.append('pa_code_id', selectedPACodeId.value)
    }

    const response = await fetch(`/api/v1/claims/services/for-referral-or-pacode?${params}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    })
    const data = await response.json()
    availableServices.value = data.data || []

    if (availableServices.value.length === 0) {
      error({
        summary: 'No Services',
        detail: 'No services are defined for this case. You may need to create a new PA code for additional services.',
        life: 5000
      })
    }
  } catch (err) {
    console.error('Failed to fetch services:', err)
    error({
      summary: 'Error',
      detail: 'Failed to load services',
      life: 5000
    })
  } finally {
    loadingServices.value = false
  }
}

const onServicesSelected = () => {
  // Services selected
}

const getServiceLabel = (item) => {
  return `${item.raw.tariff_item} (₦${formatPrice(item.raw.price || 0)})`
}

const submitClaim = async () => {
  if (!formRef.value?.validate()) return

  submitting.value = true
  try {
    const claimData = {
      referral_id: selectedReferralId.value,
      pa_code_id: selectedPACodeId.value,
      services: selectedServices.value
    }

    const response = await fetch('/api/v1/claims', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(claimData)
    })

    const data = await response.json()

    if (data.success) {
      success({
        summary: 'Success',
        detail: 'Claim submitted successfully',
        life: 3000
      })
      setTimeout(() => {
        router.push('/claims/submissions')
      }, 1500)
    } else {
      error({
        summary: 'Error',
        detail: data.message || 'Failed to submit claim',
        life: 5000
      })
    }
  } catch (err) {
    console.error('Failed to submit claim:', err)
    error({
      summary: 'Error',
      detail: 'Failed to submit claim',
      life: 5000
    })
  } finally {
    submitting.value = false
  }
}

const resetForm = () => {
  selectedReferralId.value = null
  selectedPACodeId.value = null
  selectedServices.value = []
  availableServices.value = []
  formRef.value?.resetValidation()
}

// Lifecycle
onMounted(() => {
  fetchReferrals()
  fetchPACodes()
})
</script>

