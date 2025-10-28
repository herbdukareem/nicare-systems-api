<template>
  <div class="tw-space-y-4">
    <!-- Service Selection -->
    <v-select
      v-model="selectedServiceIds"
      :items="availableServices"
      item-title="tariff_item"
      item-value="id"
      label="Select Services for Claim *"
      variant="outlined"
      multiple
      chips
      closable-chips
      :loading="loading"
      :rules="[rules.required]"
      :disabled="disabled"
      @update:modelValue="onServicesChanged"
    >
      <template #item="{ props, item }">
        <v-list-item v-bind="props">
          <v-list-item-title>{{ item.raw.tariff_item }}</v-list-item-title>
          <v-list-item-subtitle>
            ₦{{ formatPrice(item.raw.price || 0) }}
          </v-list-item-subtitle>
        </v-list-item>
      </template>

      <template #chip="{ props, item }">
        <v-chip v-bind="props" closable :text="getServiceLabel(item)" />
      </template>

      <template #no-data>
        <v-list-item>
          <v-list-item-title class="tw-text-gray-500">
            {{ loading ? 'Loading services...' : 'No services available for this case' }}
          </v-list-item-title>
        </v-list-item>
      </template>
    </v-select>

    <!-- Service Summary Card -->
    <v-card v-if="selectedServiceIds.length > 0" class="tw-bg-blue-50 tw-border tw-border-blue-200">
      <v-card-text>
        <div class="tw-space-y-3">
          <div class="tw-flex tw-justify-between tw-items-center">
            <span class="tw-font-semibold tw-text-gray-700">Services Selected:</span>
            <v-chip color="primary" :text="`${selectedServiceIds.length}`" />
          </div>

          <!-- Service List -->
          <div class="tw-space-y-2">
            <div
              v-for="serviceId in selectedServiceIds"
              :key="serviceId"
              class="tw-flex tw-justify-between tw-items-center tw-p-2 tw-bg-white tw-rounded tw-border tw-border-gray-200"
            >
              <span class="tw-text-sm tw-text-gray-700">
                {{ getServiceName(serviceId) }}
              </span>
              <span class="tw-font-semibold tw-text-blue-600">
                ₦{{ formatPrice(getServicePrice(serviceId)) }}
              </span>
            </div>
          </div>

          <!-- Total -->
          <v-divider />
          <div class="tw-flex tw-justify-between tw-items-center">
            <span class="tw-font-bold tw-text-gray-800">Total Amount:</span>
            <span class="tw-text-xl tw-font-bold tw-text-blue-600">
              ₦{{ formatPrice(totalAmount) }}
            </span>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Info Alert -->
    <v-alert
      v-if="!disabled && availableServices.length === 0"
      type="info"
      variant="tonal"
      class="tw-mb-4"
    >
      <v-alert-title>No Services Available</v-alert-title>
      <p class="tw-text-sm tw-mt-2">
        No services are defined for this case. If you need to claim for additional services, 
        you may need to create a new PA code for those services and get it approved first.
      </p>
    </v-alert>

    <!-- Warning Alert for Undefined Services -->
    <v-alert
      v-if="showUndefinedServicesWarning"
      type="warning"
      variant="tonal"
      class="tw-mb-4"
    >
      <v-alert-title>Additional Services Required</v-alert-title>
      <p class="tw-text-sm tw-mt-2">
        To claim for services not listed above, you must:
        1. Create a new PA code for those services
        2. Get the PA code approved
        3. Then submit the claim with those services
      </p>
    </v-alert>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { formatPrice } from '@/js/utils/formatters'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  referralId: {
    type: [Number, String],
    default: null
  },
  paCodeId: {
    type: [Number, String],
    default: null
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'services-loaded'])

// State
const selectedServiceIds = ref([])
const availableServices = ref([])
const loading = ref(false)
const showUndefinedServicesWarning = ref(false)

// Validation rules
const rules = {
  required: v => (Array.isArray(v) && v.length > 0) || 'Please select at least one service'
}

// Computed
const totalAmount = computed(() => {
  return availableServices.value
    .filter(s => selectedServiceIds.value.includes(s.id))
    .reduce((total, service) => total + (parseFloat(service.price) || 0), 0)
})

// Methods
const fetchServices = async () => {
  if (!props.referralId && !props.paCodeId) {
    availableServices.value = []
    return
  }

  loading.value = true
  try {
    const params = new URLSearchParams()
    if (props.referralId) {
      params.append('referral_id', props.referralId)
    }
    if (props.paCodeId) {
      params.append('pa_code_id', props.paCodeId)
    }

    const response = await fetch(`/api/v1/claims/services/for-referral-or-pacode?${params}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    })

    const data = await response.json()
    availableServices.value = data.data || []
    emit('services-loaded', availableServices.value)

    if (availableServices.value.length === 0) {
      showUndefinedServicesWarning.value = true
    }
  } catch (err) {
    console.error('Failed to fetch services:', err)
    availableServices.value = []
  } finally {
    loading.value = false
  }
}

const onServicesChanged = () => {
  emit('update:modelValue', selectedServiceIds.value)
}

const getServiceName = (serviceId) => {
  const service = availableServices.value.find(s => s.id === serviceId)
  return service ? service.tariff_item : `Service #${serviceId}`
}

const getServicePrice = (serviceId) => {
  const service = availableServices.value.find(s => s.id === serviceId)
  return service ? service.price : 0
}

const getServiceLabel = (item) => {
  return `${item.raw.tariff_item} (₦${formatPrice(item.raw.price || 0)})`
}

// Watchers
watch(() => props.modelValue, (newVal) => {
  selectedServiceIds.value = newVal
}, { immediate: true })

watch(() => props.referralId, () => {
  selectedServiceIds.value = []
  fetchServices()
}, { immediate: true })

watch(() => props.paCodeId, () => {
  selectedServiceIds.value = []
  fetchServices()
}, { immediate: true })
</script>

