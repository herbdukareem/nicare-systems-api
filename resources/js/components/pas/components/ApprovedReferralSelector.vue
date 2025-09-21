<template>
  <div class="tw-space-y-4">
    <div class="tw-flex tw-items-center tw-justify-between">
      <h4 class="tw-font-semibold tw-text-gray-900">Select Approved Referral</h4>
      <v-btn
        color="primary"
        variant="outlined"
        size="small"
        prepend-icon="mdi-refresh"
        @click="loadApprovedReferrals"
        :loading="loading"
      >
        Refresh
      </v-btn>
    </div>

    <v-alert
      v-if="!loading && approvedReferrals.length === 0"
      type="info"
      variant="tonal"
      class="tw-mb-4"
    >
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-information</v-icon>
        <div>
          <strong>No approved referrals found</strong>
          <p class="tw-text-sm tw-mt-1">
            PA codes can only be generated for approved referrals. Please ensure the enrollee has approved referrals.
          </p>
        </div>
      </div>
    </v-alert>

    <div v-if="loading" class="tw-text-center tw-py-8">
      <v-progress-circular indeterminate color="primary" />
      <p class="tw-text-gray-600 tw-mt-2">Loading approved referrals...</p>
    </div>

    <div v-else-if="approvedReferrals.length > 0" class="tw-space-y-3">
      <div
        v-for="referral in approvedReferrals"
        :key="referral.id"
        :class="[
          'tw-cursor-pointer tw-transition-all tw-duration-300 tw-border tw-rounded-lg tw-p-4',
          selectedReferral?.id === referral.id 
            ? 'tw-border-green-500 tw-bg-green-50 tw-ring-2 tw-ring-green-200' 
            : 'tw-border-gray-200 hover:tw-border-gray-300 hover:tw-bg-gray-50'
        ]"
        @click="selectReferral(referral)"
      >
        <div class="tw-flex tw-items-start tw-justify-between">
          <div class="tw-flex-1">
            <div class="tw-flex tw-items-center tw-space-x-3 tw-mb-2">
              <v-chip
                color="success"
                size="small"
                variant="flat"
                prepend-icon="mdi-check-circle"
              >
                Approved
              </v-chip>
              <span class="tw-font-semibold tw-text-primary">{{ referral.referral_code }}</span>
            </div>
            
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4 tw-text-sm">
              <div>
                <p class="tw-text-gray-600">From:</p>
                <p class="tw-font-medium">{{ referral.referring_facility_name }}</p>
                <p class="tw-text-xs tw-text-gray-500">{{ referral.referring_nicare_code }}</p>
              </div>
              <div>
                <p class="tw-text-gray-600">To:</p>
                <p class="tw-font-medium">{{ referral.receiving_facility_name }}</p>
                <p class="tw-text-xs tw-text-gray-500">{{ referral.receiving_nicare_code }}</p>
              </div>
            </div>

            <div class="tw-mt-3 tw-text-sm">
              <p class="tw-text-gray-600">Reason:</p>
              <p class="tw-text-gray-800">{{ referral.reasons_for_referral || '—' }}</p>
            </div>

            <div class="tw-flex tw-items-center tw-justify-between tw-mt-3 tw-text-xs tw-text-gray-500">
              <span>Approved: {{ formatDate(referral.approved_at) }}</span>
              <span>Severity: {{ (referral.severity_level || '').toUpperCase() }}</span>
            </div>
          </div>

          <div class="tw-ml-4">
            <v-radio-group
              :model-value="selectedReferral?.id"
              hide-details
              @update:model-value="selectReferralById"
            >
              <v-radio
                :value="referral.id"
                color="green"
                @click.stop="selectReferral(referral)"
              />
            </v-radio-group>
          </div>
        </div>
      </div>
    </div>

    <!-- Selected Referral Summary -->
    <v-card v-if="selectedReferral" class="tw-bg-green-50 tw-border tw-border-green-200">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
          <h5 class="tw-font-semibold tw-text-green-900">Selected Referral</h5>
          <v-btn
            color="green"
            variant="outlined"
            size="small"
            @click="clearSelection"
          >
            Clear
          </v-btn>
        </div>
        <div class="tw-text-sm tw-text-green-800">
          <p><strong>Code:</strong> {{ selectedReferral.referral_code }}</p>
          <p><strong>Patient:</strong> {{ selectedReferral.enrollee_full_name }}</p>
          <p><strong>Diagnosis:</strong> {{ selectedReferral.preliminary_diagnosis || '—' }}</p>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { pasAPI } from '../../../utils/api.js'
import { useToast } from '../../../composables/useToast'

const props = defineProps({
  enrollee: {
    type: Object,
    required: true
  },
  modelValue: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:modelValue'])

const { error } = useToast()

// Reactive data
const loading = ref(false)
const approvedReferrals = ref([])
const selectedReferral = ref(props.modelValue)

// Methods
const loadApprovedReferrals = async () => {
  if (!props.enrollee?.enrollee_id) return
  
  try {
    loading.value = true
    const response = await pasAPI.getReferrals({
      search: props.enrollee.enrollee_id,
      status: 'approved',
      limit: 10,
      sort: 'approved_at',
      order: 'desc'
    })
    
    if (response.data.success) {
      // Handle paginated response structure
      let referrals = [];
      const responseData = response.data.data;

      if (Array.isArray(responseData)) {
        // Direct array response
        referrals = responseData;
      } else if (responseData && Array.isArray(responseData.data)) {
        // Paginated response - extract the data array
        referrals = responseData.data;
      } else if (responseData && responseData.length !== undefined) {
        // Fallback for other array-like structures
        referrals = Array.from(responseData);
      }

      console.log('Raw referrals data:', referrals);

      // Filter to only show approved referrals for this enrollee
      approvedReferrals.value = referrals
        .filter(referral =>
          referral.status === 'approved' &&
          referral.nicare_number === props.enrollee.enrollee_id
        )
        .slice(0, 2) // Show only last 2 approved referrals
        .map(referral => ({
          ...referral,
          display_text: `${referral.referral_code} - ${referral.preliminary_diagnosis || 'No diagnosis'}`
        }));

      console.log('Filtered approved referrals:', approvedReferrals.value);
    }
  } catch (err) {
    console.error('Error loading approved referrals:', err)
    error('Failed to load approved referrals')
    // Set empty array on error
    approvedReferrals.value = []
  } finally {
    loading.value = false
  }
}

const selectReferral = (referral) => {
  selectedReferral.value = referral
  emit('update:modelValue', referral)
}

const selectReferralById = (id) => {
  const referral = approvedReferrals.value.find(r => r.id === id)
  if (referral) {
    selectReferral(referral)
  }
}

const clearSelection = () => {
  selectedReferral.value = null
  emit('update:modelValue', null)
}

const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  if (isNaN(date)) return '—'
  return date.toLocaleString('en-NG', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Watchers
watch(() => props.enrollee, () => {
  clearSelection()
  if (props.enrollee?.enrollee_id) {
    loadApprovedReferrals()
  }
}, { immediate: true })

watch(() => props.modelValue, (newValue) => {
  selectedReferral.value = newValue
})

// Lifecycle
onMounted(() => {
  if (props.enrollee?.enrollee_id) {
    loadApprovedReferrals()
  }
})
</script>
