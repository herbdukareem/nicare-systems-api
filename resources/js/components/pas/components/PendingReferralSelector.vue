<template>
  <div class="tw-space-y-6">
    <!-- Loading State -->
    <div v-if="loading" class="tw-text-center tw-py-8">
      <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
      <p class="tw-mt-4 tw-text-gray-600">Loading pending referrals...</p>
    </div>

    <!-- No Referrals State -->
    <div v-else-if="!loading && pendingReferrals.length === 0" class="tw-text-center tw-py-8">
      <v-icon size="64" color="grey-lighten-1">mdi-file-document-outline</v-icon>
      <h3 class="tw-text-lg tw-font-semibold tw-mt-4 tw-text-gray-900">No Pending Referrals</h3>
      <p class="tw-text-gray-600 tw-mt-2">There are no pending referrals for the selected facility.</p>
    </div>

    <!-- Referrals List -->
    <div v-else class="tw-space-y-4">
      <div class="tw-mb-4">
        <h4 class="tw-text-md tw-font-semibold tw-text-gray-900">
          {{ pendingReferrals.length }} Pending Referral{{ pendingReferrals.length !== 1 ? 's' : '' }} Found
        </h4>
        <p class="tw-text-sm tw-text-gray-600">Select a referral to modify its service</p>
      </div>

      <div class="tw-grid tw-grid-cols-1 tw-gap-4">
        <v-card
          v-for="referral in pendingReferrals"
          :key="referral.id"
          :class="[
            'tw-cursor-pointer tw-transition-all tw-duration-300',
            selectedReferral?.id === referral.id 
              ? 'tw-ring-2 tw-ring-primary tw-bg-blue-50' 
              : 'hover:tw-shadow-md hover:tw-bg-gray-50'
          ]"
          @click="selectReferral(referral)"
          elevation="2"
        >
          <v-card-text class="tw-p-4">
            <div class="tw-flex tw-justify-between tw-items-start">
              <div class="tw-flex-1">
                <div class="tw-flex tw-items-center tw-mb-2">
                  <v-chip
                    :color="getSeverityColor(referral.severity_level)"
                    size="small"
                    class="tw-mr-2"
                  >
                    {{ referral.severity_level || 'Routine' }}
                  </v-chip>
                  <span class="tw-text-sm tw-text-gray-500">
                    {{ formatDate(referral.created_at) }}
                  </span>
                </div>
                
                <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-1">
                  {{ referral.patient_name }}
                </h4>
                
                <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                  <strong>NiCare:</strong> {{ referral.nicare_number }}
                </p>
                
                <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                  <strong>Referral Code:</strong> {{ referral.referral_code }}
                </p>
                
                <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                  <strong>Current Service:</strong> {{ referral.current_service }}
                </p>
                
                <div v-if="referral.presenting_complaints" class="tw-mt-3">
                  <p class="tw-text-sm tw-font-medium tw-text-gray-700">Presenting Complaints:</p>
                  <p class="tw-text-sm tw-text-gray-600">{{ referral.presenting_complaints }}</p>
                </div>
                
                <div v-if="referral.preliminary_diagnosis" class="tw-mt-2">
                  <p class="tw-text-sm tw-font-medium tw-text-gray-700">Preliminary Diagnosis:</p>
                  <p class="tw-text-sm tw-text-gray-600">{{ referral.preliminary_diagnosis }}</p>
                </div>
              </div>
              
              <div class="tw-ml-4">
                <v-btn
                  v-if="selectedReferral?.id === referral.id"
                  color="primary"
                  variant="flat"
                  size="small"
                >
                  <v-icon class="tw-mr-1">mdi-check</v-icon>
                  Selected
                </v-btn>
                <v-btn
                  v-else
                  color="primary"
                  variant="outlined"
                  size="small"
                >
                  Select
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>
    </div>

    <!-- Selected Referral Summary -->
    <v-card v-if="selectedReferral" class="tw-border-l-4 tw-border-primary">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-mb-3">
          <v-icon color="primary" size="24" class="tw-mr-2">mdi-check-circle</v-icon>
          <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900">Selected Referral</h4>
        </div>
        
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Patient:</p>
            <p class="tw-text-sm tw-text-gray-900">{{ selectedReferral.patient_name }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Referral Code:</p>
            <p class="tw-text-sm tw-text-gray-900">{{ selectedReferral.referral_code }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Current Service:</p>
            <p class="tw-text-sm tw-text-gray-900">{{ selectedReferral.current_service }}</p>
          </div>
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Severity:</p>
            <v-chip :color="getSeverityColor(selectedReferral.severity_level)" size="small">
              {{ selectedReferral.severity_level || 'Routine' }}
            </v-chip>
          </div>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { pasAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const { success, error } = useToast();

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  facility: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

// Reactive data
const pendingReferrals = ref([]);
const loadingReferrals = ref(false);

// Computed
const selectedReferral = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

// Methods
const selectReferral = (referral) => {
  selectedReferral.value = referral;
  emit('update:modelValue', referral);
};

const getSeverityColor = (severity) => {
  switch (severity?.toLowerCase()) {
    case 'emergency': return 'red';
    case 'urgent': return 'orange';
    case 'routine': return 'green';
    default: return 'grey';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const loadPendingReferrals = async () => {
  if (!props.facility?.id) return;
  
  try {
    loadingReferrals.value = true;
    const response = await pasAPI.getPendingReferralsByFacility(props.facility.id);
    
    if (response.data.success) {
      pendingReferrals.value = response.data.data || [];
    } else {
      error('Failed to load pending referrals');
      pendingReferrals.value = [];
    }
  } catch (err) {
    console.error('Error loading pending referrals:', err);
    error('Failed to load pending referrals');
    pendingReferrals.value = [];
  } finally {
    loadingReferrals.value = false;
  }
};

// Watch for facility changes
watch(() => props.facility, (newFacility) => {
  if (newFacility?.id) {
    loadPendingReferrals();
  }
}, { immediate: true });

onMounted(() => {
  if (props.facility?.id) {
    loadPendingReferrals();
  }
});
</script>
