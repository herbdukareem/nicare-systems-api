<template>
  <div class="tw-max-w-6xl tw-mx-auto tw-p-6">
    <v-card class="tw-shadow-lg">
      <v-card-title class="tw-bg-green-600 tw-text-white tw-py-4">
        <div class="tw-flex tw-items-center tw-space-x-3">
          <v-icon color="white" size="28">mdi-qrcode</v-icon>
          <span class="tw-text-xl tw-font-semibold">Generate PA Code</span>
        </div>
      </v-card-title>

      <v-card-text>
        <v-stepper 
          v-model="currentStep" 
          :items="stepperItems"
          hide-actions
          class="tw-shadow-none"
        >
          <!-- Step 1: Secondary Facility Selection -->
          <template v-slot:item.1>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Secondary Facility</h3>
              <SecondaryFacilitySelector v-model="selectedFacility" />
            </div>
          </template>

          <!-- Step 2: Approved Referral Selection -->
          <template v-slot:item.2>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Approved Referral</h3>
              <ApprovedReferralSelector
                v-model="selectedApprovedReferral"
                :facility="selectedFacility"
              />
            </div>
          </template>

          <!-- Step 3: Services Selection (Optional) -->
          <template v-slot:item.3>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Services (Optional)</h3>
              <p class="tw-text-gray-600 tw-mb-4">
                Select additional services or leave empty to use referral services
              </p>
              <ServiceSelector
                v-model="selectedServices"
                :facility="selectedFacility"
                :optional="true"
              />
            </div>
          </template>

          <!-- Step 4: Review and Generate -->
          <template v-slot:item.4>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Review PA Code Request</h3>
              <PACodeReview
                :selected-facility="selectedFacility"
                :selected-approved-referral="selectedApprovedReferral"
                :selected-services="selectedServices"
                @submit="handlePACodeSubmit"
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
          color="green"
          @click="nextStep"
          :disabled="!canProceedToNext || loading"
        >
          Next
          <v-icon right>mdi-chevron-right</v-icon>
        </v-btn>

        <v-btn
          v-if="currentStep === stepperItems.length"
          color="green"
          @click="generatePACode"
          :loading="loading"
          :disabled="!canSubmit"
        >
          <v-icon left>mdi-qrcode</v-icon>
          Generate PA Code
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Success Dialog -->
    <v-dialog v-model="showSuccessDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="tw-bg-green-50 tw-text-green-800 tw-text-center">
          <v-icon color="green" size="32" class="tw-mr-2">mdi-check-circle</v-icon>
          PA Code Generated Successfully!
        </v-card-title>
        <v-card-text class="tw-p-6 tw-text-center">
          <div class="tw-space-y-4">
            <div class="tw-p-4 tw-bg-green-50 tw-rounded-lg">
              <h3 class="tw-font-semibold tw-text-green-800 tw-mb-2">PA Code</h3>
              <p class="tw-text-2xl tw-font-bold tw-text-green-900">{{ generatedPACode?.pa_code || 'Generated' }}</p>
            </div>
            <div class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
              <h3 class="tw-font-semibold tw-text-blue-800 tw-mb-2">Valid Until</h3>
              <p class="tw-text-lg tw-font-semibold tw-text-blue-900">{{ generatedPACode?.valid_until || 'Check details' }}</p>
            </div>
            <p class="tw-text-gray-600">
              Your PA code has been generated successfully. Please save this code for your records.
            </p>
          </div>
        </v-card-text>
        <v-card-actions class="tw-p-6 tw-justify-center tw-space-x-4">
          <v-btn
            color="blue"
            variant="outlined"
            @click="viewPACodeDetails"
          >
            <v-icon left>mdi-eye</v-icon>
            View Details
          </v-btn>
          <v-btn
            color="green"
            @click="goToDashboard"
          >
            <v-icon left>mdi-home</v-icon>
            Go to Dashboard
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import SecondaryFacilitySelector from './components/SecondaryFacilitySelector.vue';
import ApprovedReferralSelector from './components/ApprovedReferralSelector.vue';
import ServiceSelector from './components/ServiceSelector.vue';
import PACodeReview from './components/PACodeReview.vue';
import { pasAPI } from '../../utils/api.js';

const router = useRouter();
const { success, error } = useToast();

// Stepper configuration
const currentStep = ref(1);
const stepperItems = [
  { title: 'Facility', value: 1 },
  { title: 'Referral', value: 2 },
  { title: 'Services', value: 3 },
  { title: 'Review', value: 4 }
];

// Form data
const selectedFacility = ref(null);
const selectedApprovedReferral = ref(null);
const selectedServices = ref([]);
const loading = ref(false);
const showSuccessDialog = ref(false);
const generatedPACode = ref(null);

// Computed properties for navigation
const canProceedToNext = computed(() => {
  switch (currentStep.value) {
    case 1: return !!selectedFacility.value;
    case 2: return !!selectedApprovedReferral.value;
    case 3: return true; // Services are optional
    default: return true;
  }
});

const canSubmit = computed(() => {
  return selectedFacility.value &&
         selectedApprovedReferral.value;
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

// PA Code generation
const handlePACodeSubmit = async (requestData) => {
  loading.value = true;
  try {
    const referralId = selectedApprovedReferral.value.id;
    const response = await pasAPI.generatePACodeFromReferral(referralId, requestData);

    if (response.data.success) {
      generatedPACode.value = response.data.data;
      showSuccessDialog.value = true;
      success('PA code generated successfully!');
    } else {
      error(response.data.message || 'Failed to generate PA code');
    }
  } catch (err) {
    console.error('PA Code generation error:', err);
    error(err.response?.data?.message || 'Failed to generate PA code');
  } finally {
    loading.value = false;
  }
};

const generatePACode = () => {
  // This will trigger the PA code generation in PACodeReview
  // The actual submission is handled by handlePACodeSubmit
};

const viewPACodeDetails = () => {
  // Navigate to specific PA Code details page
  showSuccessDialog.value = false;
  if (generatedPACode.value?.id) {
    router.push(`/pas/pa-codes/${generatedPACode.value.id}`);
  } else {
    // Fallback to PA Codes tab if no specific ID
    router.push('/pas?tab=pa-codes');
  }
};

const goToDashboard = () => {
  showSuccessDialog.value = false;
  router.push('/pas');
};

// Reset selections when going back to previous steps
watch(currentStep, (newStep, oldStep) => {
  if (newStep < oldStep) {
    if (newStep < 2) selectedApprovedReferral.value = null;
    if (newStep < 3) selectedServices.value = [];
  }
});
</script>
