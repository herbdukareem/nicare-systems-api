<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Create Referral / PA Code</h1>
          <p class="tw-text-gray-600 tw-mt-1">Generate referrals for transfers or PA codes for service authorization</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="grey" 
            variant="outlined" 
            prepend-icon="mdi-arrow-left"
            @click="$router.push('/pas')"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Back to PAS
          </v-btn>
        </div>
      </div>

      <!-- Progress Stepper -->
      <v-card class="tw-shadow-sm">
        <v-card-text>
          <v-stepper 
            v-model="currentStep" 
            :items="stepperItems"
            hide-actions
            class="tw-shadow-none"
          >
            <template v-slot:item.1>
              <div class="tw-p-4">
                <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Facility</h3>
                <FacilitySelector 
                  v-model="selectedFacility"
                  @update:modelValue="onFacilitySelected"
                  :loading="loadingFacilities"
                />
              </div>
            </template>

            <!-- Step 2: Request Type Selection (for all flows) -->
            <template v-slot:item.2>
              <div class="tw-p-4">
                <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Request Type</h3>
                <RequestTypeSelector
                  v-model="requestType"
                  @update:modelValue="onRequestTypeSelected"
                />
              </div>
            </template>

            <!-- Step 3: Dynamic content based on request type -->
            <template v-slot:item.3>
              <div class="tw-p-4">
                <!-- Enrollee Selection (for referral and PA code requests) -->
                <div v-if="requestType === 'referral' || requestType === 'pa_code'">
                  <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Enrollee</h3>
                  <EnrolleeSelector
                    v-model="selectedEnrollee"
                    :facility="selectedFacility"
                    @update:modelValue="onEnrolleeSelected"
                    :loading="loadingEnrollees"
                  />
                </div>

                <!-- Pending Referral Selection (for modify referral) -->
                <div v-else-if="requestType === 'modify_referral'">
                  <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Pending Referral</h3>
                  <PendingReferralSelector
                    v-model="selectedReferral"
                    :facility="selectedFacility"
                    :loading="loadingPendingReferrals"
                    @update:modelValue="onReferralSelected"
                  />
                </div>
              </div>
            </template>

            <!-- Step 4: Dynamic content based on request type -->
            <template v-slot:item.4>
              <div class="tw-p-4">
                <!-- Approved Referral Selection (for PA Code requests) -->
                <div v-if="requestType === 'pa_code'">
                  <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select Approved Referral</h3>
                  <ApprovedReferralSelector
                    v-model="selectedApprovedReferral"
                    :enrollee="selectedEnrollee"
                  />
                </div>

                <!-- Enrollee Profile (for referral requests only) -->
                <div v-else-if="requestType === 'referral'">
                  <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Enrollee Profile</h3>
                  <EnrolleeProfile
                    v-if="selectedEnrollee"
                    :enrollee="selectedEnrollee"
                    :facility="selectedFacility"
                  />
                </div>

                <!-- New Service Selection (for modify referral) -->
                <div v-else-if="requestType === 'modify_referral'">
                  <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Select New Service</h3>
                  <SimpleServiceSelector
                    v-model="newService"
                    :current-referral="selectedReferral"
                    :modification-reason-value="modificationReason"
                    @update:modelValue="onServiceSelected"
                    @update:modificationReason="(value) => modificationReason = value"
                  />
                </div>
              </div>
            </template>

            <!-- Services Selection -->
            <template v-slot:[servicesSlotName]>
              <div class="tw-p-4">
                <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Services</h3>
                <ServiceSelector
                  v-if="requestType"
                  v-model="selectedServices"
                  :request-type="requestType"
                  :facility="selectedFacility"
                  class="tw-mt-6"
                />
              </div>
            </template>

            <!-- Review & Submit -->
            <template v-slot:[reviewSlotName]>
              <div class="tw-p-4">
                <h3 class="tw-text-lg tw-font-semibold tw-mb-4">Review & Submit</h3>
                <RequestReview
                  :facility="selectedFacility"
                  :enrollee="selectedEnrollee"
                  :request-type="requestType"
                  :services="selectedServices"
                  :selected-approved-referral="selectedApprovedReferral"
                  :additional-data="additionalData"
                  :selected-referral="selectedReferral"
                  :new-service="newService"
                  :modification-reason="modificationReason"
                  @submit="submitRequest"
                  :loading="submitting"
                />
              </div>
            </template>
          </v-stepper>
        </v-card-text>
      </v-card>

      <!-- Navigation Buttons -->
      <div class="tw-flex tw-justify-between tw-items-center">
        <v-btn
          v-if="currentStep > 1"
          variant="outlined"
          prepend-icon="mdi-arrow-left"
          @click="previousStep"
          :disabled="submitting"
        >
          Previous
        </v-btn>
        <div></div>
        <v-btn
          v-if="currentStep < maxSteps"
          color="primary"
          append-icon="mdi-arrow-right"
          @click="nextStep"
          :disabled="!canProceedToNext || submitting"
        >
          Next
        </v-btn>
      </div>
    </div>

    <!-- Success Dialog -->
    <v-dialog v-model="showSuccessDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="tw-text-center tw-py-6">
          <v-icon color="success" size="64" class="tw-mb-4">mdi-check-circle</v-icon>
          <h2 class="tw-text-xl tw-font-bold">{{ requestType === 'referral' ? 'Referral' : 'PA Code' }} Created Successfully!</h2>
        </v-card-title>
        <v-card-text class="tw-text-center">
          <div v-if="createdRequest">
            <p class="tw-text-lg tw-mb-4">
              {{ requestType === 'referral' ? 'Referral Code:' : 'PA Code:' }}
              <strong class="tw-text-primary">{{ createdRequest?.referral?.referral_code ?? createdRequest?.referral_code }}</strong>
            </p>
            <p class="tw-text-gray-600">
              {{ requestType === 'referral' ? 'The referral has been created and is pending approval.' : 'The PA code is now active and ready for use.' }}
            </p>
          </div>
        </v-card-text>
        <v-card-actions class="tw-justify-center tw-pb-6">
          <v-btn
            color="primary"
            variant="outlined"
            @click="viewRequest"
            class="tw-mr-3"
          >
            View {{ requestType === 'referral' ? 'Referral' : 'PA Code' }}
          </v-btn>
          <v-btn
            color="primary"
            @click="createAnother"
          >
            Create Another
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import FacilitySelector from './components/FacilitySelector.vue';
import EnrolleeSelector from './components/EnrolleeSelector.vue';
import EnrolleeProfile from './components/EnrolleeProfile.vue';
import RequestTypeSelector from './components/RequestTypeSelector.vue';
import ServiceSelector from './components/ServiceSelector.vue';
import RequestReview from './components/RequestReview.vue';
import ApprovedReferralSelector from './components/ApprovedReferralSelector.vue';
import PendingReferralSelector from './components/PendingReferralSelector.vue';
import SimpleServiceSelector from './components/SimpleServiceSelector.vue';
import { useToast } from '../../composables/useToast';
import { pasAPI } from '../../utils/api.js';

const router = useRouter();
const { success, error } = useToast();

// Reactive data
const currentStep = ref(1);
const loadingFacilities = ref(false);
const loadingEnrollees = ref(false);
const submitting = ref(false);
const showSuccessDialog = ref(false);

// Form data
const selectedFacility = ref(null);
const selectedEnrollee = ref(null);
const requestType = ref(''); // 'referral', 'pa_code', or 'modify_referral'
const selectedServices = ref([]);
const selectedApprovedReferral = ref(null);
const additionalData = ref({});
const createdRequest = ref(null);

// Modify referral specific data
const pendingReferrals = ref([]);
const selectedReferral = ref(null);
const newService = ref(null);
const modificationReason = ref('');
const loadingPendingReferrals = ref(false);

// Stepper configuration
const stepperItems = computed(() => {
  if (requestType.value === 'pa_code') {
    return [
      { title: 'Facility', value: 1, icon: 'mdi-hospital-building' },
      { title: 'Request Type', value: 2, icon: 'mdi-clipboard-list' },
      { title: 'Enrollee', value: 3, icon: 'mdi-account-search' },
      { title: 'Referral', value: 4, icon: 'mdi-file-document-check' },
      { title: 'Services', value: 5, icon: 'mdi-medical-bag' },
      { title: 'Review', value: 6, icon: 'mdi-check-circle' }
    ];
  } else if (requestType.value === 'modify_referral') {
    return [
      { title: 'Facility', value: 1, icon: 'mdi-hospital-building' },
      { title: 'Request Type', value: 2, icon: 'mdi-clipboard-list' },
      { title: 'Select Referral', value: 3, icon: 'mdi-file-document-check' },
      { title: 'New Service', value: 4, icon: 'mdi-medical-bag' },
      { title: 'Review', value: 5, icon: 'mdi-check-circle' }
    ];
  } else if (requestType.value === 'referral') {
    return [
      { title: 'Facility', value: 1, icon: 'mdi-hospital-building' },
      { title: 'Request Type', value: 2, icon: 'mdi-clipboard-list' },
      { title: 'Enrollee', value: 3, icon: 'mdi-account-search' },
      { title: 'Profile', value: 4, icon: 'mdi-account-details' },
      { title: 'Services', value: 5, icon: 'mdi-medical-bag' },
      { title: 'Review', value: 6, icon: 'mdi-check-circle' }
    ];
  } else {
    // Default flow when no request type is selected yet
    return [
      { title: 'Facility', value: 1, icon: 'mdi-hospital-building' },
      { title: 'Request Type', value: 2, icon: 'mdi-clipboard-list' },
      { title: 'Details', value: 3, icon: 'mdi-file-document' },
      { title: 'Services', value: 4, icon: 'mdi-medical-bag' },
      { title: 'Review', value: 5, icon: 'mdi-check-circle' }
    ];
  }
});

// Computed properties
const maxSteps = computed(() => {
  if (requestType.value === 'pa_code') return 6;
  if (requestType.value === 'modify_referral') return 5;
  if (requestType.value === 'referral') return 6;
  return 5; // Default when no request type selected
});

const servicesSlotName = computed(() => {
  if (requestType.value === 'pa_code') return 'item.5';
  if (requestType.value === 'modify_referral') return 'item.4';
  if (requestType.value === 'referral') return 'item.5';
  return 'item.4'; // Default
});

const reviewSlotName = computed(() => {
  if (requestType.value === 'pa_code') return 'item.6';
  if (requestType.value === 'modify_referral') return 'item.5';
  if (requestType.value === 'referral') return 'item.6';
  return 'item.5'; // Default
});

const canProceedToNext = computed(() => {
  switch (currentStep.value) {
    case 1:
      return !!selectedFacility.value;
    case 2:
      return !!requestType.value;
    case 3:
      if (requestType.value === 'pa_code') {
        return !!selectedEnrollee.value;
      } else if (requestType.value === 'modify_referral') {
        return !!selectedReferral.value;
      } else if (requestType.value === 'referral') {
        return !!selectedEnrollee.value;
      }
      return false;
    case 4:
      if (requestType.value === 'pa_code') {
        return !!selectedApprovedReferral.value;
      } else if (requestType.value === 'modify_referral') {
        return !!newService.value;
      } else if (requestType.value === 'referral') {
        return true; // Profile step
      }
      return false;
    case 5:
      if (requestType.value === 'pa_code') {
        return selectedServices.value.length > 0;
      } else if (requestType.value === 'modify_referral') {
        return false; // Review step (no next step)
      } else if (requestType.value === 'referral') {
        return selectedServices.value.length > 0;
      }
      return false;
    case 6:
      if (requestType.value === 'pa_code') {
        return false; // Review step (no next step)
      } else if (requestType.value === 'referral') {
        return false; // Review step (no next step)
      }
      return false;
    default:
      return false;
  }
});

// Methods
const nextStep = () => {
  if (canProceedToNext.value && currentStep.value < maxSteps.value) {
    currentStep.value++;
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

const onFacilitySelected = (facility) => {
  selectedFacility.value = facility;
  // Reset dependent selections
  selectedEnrollee.value = null;
  requestType.value = '';
  selectedServices.value = [];
  selectedApprovedReferral.value = null;
  selectedReferral.value = null;
  newService.value = null;
  modificationReason.value = '';

  // Automatically advance to next step after selecting facility
  nextTick(() => {
    if (canProceedToNext.value && currentStep.value < maxSteps.value) {
      currentStep.value++;
    }
  });
};

const onEnrolleeSelected = (enrollee) => {
  selectedEnrollee.value = enrollee;
  // Reset dependent selections
  requestType.value = '';
  selectedServices.value = [];
  selectedApprovedReferral.value = null;
};

const onRequestTypeSelected = (type) => {
 
  requestType.value = type;
  selectedServices.value = [];
  selectedApprovedReferral.value = null;
  selectedReferral.value = null;
  newService.value = null;
  modificationReason.value = '';

  // Reset enrollee selection for non-referral requests
  if (type !== 'referral') {
    selectedEnrollee.value = null;
  }

  // Automatically advance to next step after selecting request type
  nextTick(() => {
    if (canProceedToNext.value && currentStep.value < maxSteps.value) {
      currentStep.value++;
    }
  });
};

const onReferralSelected = (referral) => {
  selectedReferral.value = referral;
};

const onServiceSelected = (service) => {
  newService.value = service;
};

const submitRequest = async (formData) => {
  try {
    submitting.value = true;

    console.log('Submit request data:', formData);
    console.log('Request type:', requestType.value);
    console.log('Selected approved referral:', selectedApprovedReferral.value);

    // Create FormData for file uploads
    const submitData = new FormData();

    // Add all form fields
    Object.keys(formData).forEach(key => {
      if (formData[key] !== null && formData[key] !== undefined) {
        if (key === 'services') {
          // Handle services array
          submitData.append(key, JSON.stringify(formData[key]));
        } else if (key === 'enrollee_id_card' || key === 'referral_letter') {
          // Handle file uploads - v-file-input returns array, get first file
          const file = Array.isArray(formData[key]) ? formData[key][0] : formData[key];
          if (file instanceof File) {
            submitData.append(key, file);
          }
        } else {
          submitData.append(key, formData[key]);
        }
      }
    });

    let response;
    if (requestType.value === 'referral') {
      response = await pasAPI.createReferral(submitData);
    } else if (requestType.value === 'modify_referral') {
      // For modify referral, use the modify API
      const modifyData = {
        new_service_id: newService.value?.id,
        modification_reason: modificationReason.value || 'Service modification requested'
      };
      response = await pasAPI.modifyReferral(selectedReferral.value.id, modifyData);
    } else {
      // For PA codes, use the direct PA code generation from referral
      // Extract referral_id from original formData before using FormData
      const referralId = formData.referral_id;
      if (!referralId) {
        throw new Error('Referral ID is required for PA code generation');
      }
      response = await pasAPI.generatePACodeFromReferral(referralId, submitData);
    }

    if (response.data.success) {
      createdRequest.value = response.data.data;
      showSuccessDialog.value = true;
      const successMessage = requestType.value === 'referral' ? 'Referral' :
                           requestType.value === 'modify_referral' ? 'Referral modification' : 'PA Code';
      success(`${successMessage} ${requestType.value === 'modify_referral' ? 'completed' : 'created'} successfully!`);
    }
  } catch (err) {
    console.error('Failed to create request:', err);
    if (err.response?.data?.errors) {
      // Show validation errors
      const errors = Object.values(err.response.data.errors).flat();
      error(`Validation failed: ${errors.join(', ')}`);
    } else {
      error(`Failed to create ${requestType.value === 'referral' ? 'referral' : 'PA code'}`);
    }
  } finally {
    submitting.value = false;
  }
};

const viewRequest = () => {
  showSuccessDialog.value = false;
  if (requestType.value === 'referral') {
    console.log(createdRequest.value)
    router.push(`/pas/referrals/${createdRequest.value.referral?.referral_code }`);
  } else {
    router.push(`/pas/pa-codes/${createdRequest.value.id}`);
  }
};

const createAnother = () => {
  showSuccessDialog.value = false;
  // Reset form
  currentStep.value = 1;
  selectedFacility.value = null;
  selectedEnrollee.value = null;
  requestType.value = '';
  selectedServices.value = [];
  selectedApprovedReferral.value = null;
  additionalData.value = {};
  createdRequest.value = null;
};

onMounted(() => {
  // Initialize component
});
</script>
