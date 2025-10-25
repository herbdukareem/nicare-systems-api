<template>
  <div class="tw-max-w-6xl tw-mx-auto tw-p-6">
    <v-card class="tw-shadow-lg">
      <v-card-title class="tw-bg-blue-600 tw-text-white tw-py-4">
        <div class="tw-flex tw-items-center tw-space-x-3">
          <v-icon color="white" size="28">mdi-file-document-plus</v-icon>
          <span class="tw-text-xl tw-font-semibold">Create New Referral Request</span>
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
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Referring Facility</h3>
              <FacilitySelector v-model="selectedFacility" />
            </div>
          </template>

          <!-- Step 2: Enrollee Selection -->
          <template v-slot:item.2>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Enrollee</h3>
              <EnrolleeSelector 
                v-model="selectedEnrollee"
                :facility="selectedFacility"
              />
            </div>
          </template>

          <!-- Step 3: Enrollee Profile -->
          <template v-slot:item.3>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Enrollee Profile</h3>
              <EnrolleeProfile
                v-if="selectedEnrollee"
                :enrollee="selectedEnrollee"
                :facility="selectedFacility"
              />
            </div>
          </template>

          <!-- Step 4: Services Selection -->
          <template v-slot:item.4>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Services</h3>
              <ServiceSelector 
                v-model="selectedServices"
                :facility="selectedFacility"
                :enrollee="selectedEnrollee"
              />
            </div>
          </template>

          <!-- Step 5: Referral Details -->
          <template v-slot:item.5>
            <div class="tw-p-4">
              <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Referral Details</h3>
              <ReferralRequestForm
                ref="referralFormRef"
                :selected-facility="selectedFacility"
                :selected-enrollee="selectedEnrollee"
                :selected-services="selectedServices"
                @submit="handleReferralSubmit"
                @cancel="handleCancel"
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
          color="blue"
          @click="nextStep"
          :disabled="!canProceedToNext || loading"
        >
          Next
          <v-icon right>mdi-chevron-right</v-icon>
        </v-btn>

        <v-btn
          v-if="currentStep === stepperItems.length"
          color="green"
          @click="submitReferral"
          :loading="loading"
          :disabled="!canSubmit"
        >
          <v-icon left>mdi-check</v-icon>
          Submit Referral
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Success Dialog -->
    <v-dialog v-model="showSuccessDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="tw-bg-green-50 tw-text-green-800 tw-text-center">
          <v-icon color="green" size="32" class="tw-mr-2">mdi-check-circle</v-icon>
          Referral Submitted Successfully!
        </v-card-title>
        <v-card-text class="tw-p-6 tw-text-center">
          <div class="tw-space-y-4">
            <div class="tw-p-4 tw-bg-blue-50 tw-rounded-lg">
              <h3 class="tw-font-semibold tw-text-blue-800 tw-mb-2">Referral Code</h3>
              <p class="tw-text-2xl tw-font-bold tw-text-blue-900">{{ submittedReferral?.referral_code || submittedReferral?.referralCode || 'Generated' }}</p>
            </div>
            <div class="tw-p-4 tw-bg-amber-50 tw-rounded-lg">
              <h3 class="tw-font-semibold tw-text-amber-800 tw-mb-2">Next Steps</h3>
              <p class="tw-text-sm tw-text-amber-900">
                Your referral is pending approval. Once approved, you can generate a PA Code with UTN number.
              </p>
            </div>
            <p class="tw-text-gray-600">
              Your referral request has been submitted successfully. Please save these numbers for your records.
            </p>
          </div>
        </v-card-text>
        <v-card-actions class="tw-p-6 tw-justify-center tw-space-x-4">
          <v-btn
            color="blue"
            variant="outlined"
            @click="viewReferralDetails"
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
import FacilitySelector from './components/FacilitySelector.vue';
import EnrolleeSelector from './components/EnrolleeSelector.vue';
import EnrolleeProfile from './components/EnrolleeProfile.vue';
import ServiceSelector from './components/ServiceSelector.vue';
import ReferralRequestForm from './ReferralRequestForm.vue';
import { pasAPI } from '../../utils/api.js';

const router = useRouter();
const { success, error } = useToast();

// Stepper configuration
const currentStep = ref(1);
const stepperItems = [
  { title: 'Facility', value: 1 },
  { title: 'Enrollee', value: 2 },
  { title: 'Profile', value: 3 },
  { title: 'Services', value: 4 },
  { title: 'Details', value: 5 }
];

// Form data
const selectedFacility = ref(null);
const selectedEnrollee = ref(null);
const selectedServices = ref([]);
const loading = ref(false);
const showSuccessDialog = ref(false);
const submittedReferral = ref(null);
const referralFormRef = ref(null);

// Computed properties for navigation
const canProceedToNext = computed(() => {
  switch (currentStep.value) {
    case 1: return !!selectedFacility.value;
    case 2: return !!selectedEnrollee.value;
    case 3: return !!selectedEnrollee.value;
    case 4: return selectedServices.value.length > 0;
    default: return true;
  }
});

const canSubmit = computed(() => {
  return selectedFacility.value && 
         selectedEnrollee.value && 
         selectedServices.value.length > 0;
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

// Form submission
const handleReferralSubmit = async (submissionData) => {
  loading.value = true;
  try {
    if (submissionData.showSuccessDialog) {
      // Show success dialog with referral code and UTN
      showSuccessDialog.value = true;
      submittedReferral.value = submissionData;
    } else {
      success('Referral request submitted successfully!');
      router.push('/pas');
    }
  } catch (err) {
    console.error('Referral submission error:', err);
    error('Failed to submit referral request');
  } finally {
    loading.value = false;
  }
};

const submitReferral = () => {
  // Trigger the form submission in ReferralRequestForm
  if (referralFormRef.value) {
    referralFormRef.value.submitForm();
  }
};

const handleCancel = () => {
  router.push('/pas');
};

const viewReferralDetails = () => {
  if (submittedReferral.value?.referral_code || submittedReferral.value?.referralCode) {
    const referralCode = submittedReferral.value.referral_code || submittedReferral.value.referralCode;
    showSuccessDialog.value = false;
    router.push(`/pas/referrals/${referralCode}`);
  } else {
    console.error('No referral code found in submitted data:', submittedReferral.value);
  }
};

const goToDashboard = () => {
  showSuccessDialog.value = false;
  router.push('/pas');
};

// Reset selections when going back to previous steps
watch(currentStep, (newStep, oldStep) => {
  if (newStep < oldStep) {
    if (newStep < 2) selectedEnrollee.value = null;
    if (newStep < 4) selectedServices.value = [];
  }
});
</script>
