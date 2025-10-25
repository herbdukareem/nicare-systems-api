<template>
  <div class="tw-max-w-6xl tw-mx-auto tw-p-6">
    <v-card class="tw-shadow-lg">
      <v-card-title class="tw-bg-orange-600 tw-text-white tw-py-4">
        <div class="tw-flex tw-items-center tw-space-x-3">
          <v-icon color="white" size="28">mdi-file-edit</v-icon>
          <span class="tw-text-xl tw-font-semibold">Modify Referral Service</span>
        </div>
      </v-card-title>

      <v-card-text>
        <v-stepper 
          v-model="currentStep" 
          :items="stepperItems"
          hide-actions
          class="tw-shadow-none"
        >
          <!-- Step 1: Facility Selection -->
          <template v-slot:item.1>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Facility</h3>
              <FacilitySelector v-model="selectedFacility" />
            </div>
          </template>

          <!-- Step 2: Pending Referral Selection -->
          <template v-slot:item.2>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Pending Referral</h3>
              <PendingReferralSelector
                v-model="selectedReferral"
                :facility="selectedFacility"
              />
            </div>
          </template>

          <!-- Step 3: New Service Selection -->
          <template v-slot:item.3>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select New Service</h3>
              <SimpleServiceSelector
                v-model="newService"
                :current-referral="selectedReferral"
                :modification-reason-value="modificationReason"
                @update:modelValue="onServiceSelected"
                @update:modificationReason="(value) => modificationReason = value"
              />
            </div>
          </template>

          <!-- Step 4: Review and Submit -->
          <template v-slot:item.4>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Review Modification</h3>
              <ModifyReferralReview
                :selected-facility="selectedFacility"
                :selected-referral="selectedReferral"
                :new-service="newService"
                :modification-reason="modificationReason"
                @submit="handleModifySubmit"
              />
            </div>
          </template>
        </v-stepper>
      </v-card-text>

      <!-- Navigation Actions -->
      <v-card-actions class="tw-p-6 tw-bg-gray-50">
        <v-btn
          v-if="currentStep > 1"
          variant="outlined"
          @click="previousStep"
          :disabled="loading"
        >
          <v-icon left>mdi-chevron-left</v-icon>
          Previous
        </v-btn>
        
        <v-spacer />
        
        <v-btn
          v-if="currentStep < stepperItems.length"
          color="orange"
          @click="nextStep"
          :disabled="!canProceedToNext || loading"
        >
          Next
          <v-icon right>mdi-chevron-right</v-icon>
        </v-btn>

        <v-btn
          v-if="currentStep === stepperItems.length"
          color="orange"
          @click="modifyReferral"
          :loading="loading"
          :disabled="!canSubmit"
        >
          <v-icon left>mdi-check</v-icon>
          Modify Referral
        </v-btn>
      </v-card-actions>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import FacilitySelector from './components/FacilitySelector.vue';
import PendingReferralSelector from './components/PendingReferralSelector.vue';
import SimpleServiceSelector from './components/SimpleServiceSelector.vue';
import ModifyReferralReview from './components/ModifyReferralReview.vue';
import { pasAPI } from '../../utils/api.js';

const router = useRouter();
const { success, error } = useToast();

// Stepper configuration
const currentStep = ref(1);
const stepperItems = [
  { title: 'Facility', value: 1 },
  { title: 'Referral', value: 2 },
  { title: 'Service', value: 3 },
  { title: 'Review', value: 4 }
];

// Form data
const selectedFacility = ref(null);
const selectedReferral = ref(null);
const newService = ref(null);
const modificationReason = ref('');
const loading = ref(false);

// Computed properties for navigation
const canProceedToNext = computed(() => {
  switch (currentStep.value) {
    case 1: return !!selectedFacility.value;
    case 2: return !!selectedReferral.value;
    case 3: return !!newService.value && !!modificationReason.value;
    default: return true;
  }
});

const canSubmit = computed(() => {
  return selectedFacility.value && 
         selectedReferral.value && 
         newService.value &&
         modificationReason.value;
});

// Navigation methods
const nextStep = () => {
  if (currentStep.value < stepperItems.length) {
    currentStep.value++;
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

// Service selection handler
const onServiceSelected = (service) => {
  newService.value = service;
};

// Modify referral submission
const handleModifySubmit = async (requestData) => {
  loading.value = true;
  try {
    const response = await pasAPI.modifyReferral(selectedReferral.value.id, requestData);
    
    if (response.data.success) {
      success('Referral service modified successfully!');
      router.push('/pas/referrals');
    } else {
      error(response.data.message || 'Failed to modify referral');
    }
  } catch (err) {
    console.error('Modify referral error:', err);
    error(err.response?.data?.message || 'Failed to modify referral');
  } finally {
    loading.value = false;
  }
};

const modifyReferral = () => {
  // This will trigger the modification in ModifyReferralReview
  // The actual submission is handled by handleModifySubmit
};

// Reset selections when going back to previous steps
watch(currentStep, (newStep, oldStep) => {
  if (newStep < oldStep) {
    if (newStep < 2) selectedReferral.value = null;
    if (newStep < 3) {
      newService.value = null;
      modificationReason.value = '';
    }
  }
});
</script>
