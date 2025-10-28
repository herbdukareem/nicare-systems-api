<template>
  <div class="referral-pa-wizard">
    <v-stepper v-model="currentStep" :items="stepperItems" class="tw-shadow-none">
      <!-- Step 1: Facility Selection -->
      <template #item.1>
        <v-card flat>
          <v-card-title class="tw-text-lg tw-font-semibold tw-text-gray-800">
            <v-icon class="tw-mr-2">mdi-hospital-marker</v-icon>
            Select Receiving Facility
          </v-card-title>
          <v-card-text class="tw-pt-4">
            <div class="tw-space-y-4">
              <v-select
                v-model="selectedFacilityId"
                :items="facilities"
                item-title="name"
                item-value="id"
                label="Receiving Facility *"
                variant="outlined"
                density="comfortable"
                :loading="facilitiesLoading"
                :rules="[rules.required]"
                @update:model-value="handleFacilitySelection"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                    <v-list-item-subtitle>{{ item.raw.hcp_code }} - {{ item.raw.level_of_care }}</v-list-item-subtitle>
                  </v-list-item>
                </template>
                <template #selection="{ item }">
                  <span>{{ item.raw.name }}</span>
                </template>
              </v-select>
              
              <!-- Selected Facility Details -->
              <v-card v-if="selectedFacility" variant="outlined" class="tw-bg-blue-50">
                <v-card-text>
                  <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Facility Name</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedFacility.name }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">NiCare Code</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedFacility.hcp_code }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Level of Care</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedFacility.level_of_care }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Address</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedFacility.address || 'N/A' }}</p>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
        </v-card>
      </template>

      <!-- Step 2: Request Type Selection -->
      <template #item.2>
        <v-card flat>
          <v-card-title class="tw-text-lg tw-font-semibold tw-text-gray-800">
            <v-icon class="tw-mr-2">mdi-clipboard-list</v-icon>
            Select Request Type
          </v-card-title>
          <v-card-text class="tw-pt-4">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-6">
              <!-- Referral Request -->
              <v-card 
                :class="[
                  'tw-cursor-pointer tw-transition-all tw-duration-200 tw-border-2',
                  requestType === 'referral' ? 'tw-border-blue-500 tw-bg-blue-50' : 'tw-border-gray-200 hover:tw-border-gray-300'
                ]"
                @click="requestType = 'referral'"
              >
                <v-card-text class="tw-text-center tw-py-8">
                  <v-icon size="48" :color="requestType === 'referral' ? 'blue' : 'grey'">
                    mdi-account-arrow-right
                  </v-icon>
                  <h3 class="tw-text-lg tw-font-medium tw-mt-4 tw-mb-2">Referral Request</h3>
                  <p class="tw-text-sm tw-text-gray-600 tw-mb-4">Transfer patient to another facility for specialized care or higher level of treatment</p>
                  <div class="tw-space-y-1 tw-text-xs tw-text-left">
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Provider-to-provider transfers</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Requires approval workflow</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Clinical justification required</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Generates referral code</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <!-- PA Code Request -->
              <v-card 
                :class="[
                  'tw-cursor-pointer tw-transition-all tw-duration-200 tw-border-2',
                  requestType === 'pa_code' ? 'tw-border-green-500 tw-bg-green-50' : 'tw-border-gray-200 hover:tw-border-gray-300'
                ]"
                @click="requestType = 'pa_code'"
              >
                <v-card-text class="tw-text-center tw-py-8">
                  <v-icon size="48" :color="requestType === 'pa_code' ? 'green' : 'grey'">
                    mdi-qrcode
                  </v-icon>
                  <h3 class="tw-text-lg tw-font-medium tw-mt-4 tw-mb-2">PA Code Request</h3>
                  <p class="tw-text-sm tw-text-gray-600 tw-mb-4">Authorization for specialized services, procedures, or medications to be provided to enrollee</p>
                  <div class="tw-space-y-1 tw-text-xs tw-text-left">
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Service authorization</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Drug/consumable authorization</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Immediate activation</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Generates PA code</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <!-- Modify Referral -->
              <v-card 
                :class="[
                  'tw-cursor-pointer tw-transition-all tw-duration-200 tw-border-2',
                  requestType === 'modify_referral' ? 'tw-border-orange-500 tw-bg-orange-50' : 'tw-border-gray-200 hover:tw-border-gray-300'
                ]"
                @click="requestType = 'modify_referral'"
              >
                <v-card-text class="tw-text-center tw-py-8">
                  <v-icon size="48" :color="requestType === 'modify_referral' ? 'orange' : 'grey'">
                    mdi-pencil-circle
                  </v-icon>
                  <h3 class="tw-text-lg tw-font-medium tw-mt-4 tw-mb-2">Modify Referral</h3>
                  <p class="tw-text-sm tw-text-gray-600 tw-mb-4">Change the referral service for an existing pending referral while maintaining the original referral code</p>
                  <div class="tw-space-y-1 tw-text-xs tw-text-left">
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Select from pending referrals</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Change referral service</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Maintains original code</span>
                    </div>
                    <div class="tw-flex tw-items-center">
                      <v-icon size="16" color="green" class="tw-mr-2">mdi-check</v-icon>
                      <span>Tracks service history</span>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
        </v-card>
      </template>

      <!-- Step 3: Pending Referrals (for PA Code and Modify Referral) -->
      <template #item.3 v-if="requestType === 'pa_code' || requestType === 'modify_referral'">
        <v-card flat>
          <v-card-title class="tw-text-lg tw-font-semibold tw-text-gray-800">
            <v-icon class="tw-mr-2">mdi-clipboard-text</v-icon>
            Select Pending Referral
          </v-card-title>
          <v-card-text class="tw-pt-4">
            <div v-if="pendingReferralsLoading" class="tw-text-center tw-py-8">
              <v-progress-circular indeterminate color="primary"></v-progress-circular>
              <p class="tw-mt-4 tw-text-gray-600">Loading pending referrals...</p>
            </div>
            
            <div v-else-if="pendingReferrals.length === 0" class="tw-text-center tw-py-8">
              <v-icon size="64" color="grey">mdi-clipboard-off</v-icon>
              <p class="tw-mt-4 tw-text-gray-600">No pending referrals found for this facility</p>
            </div>
            
            <div v-else class="tw-space-y-4">
              <v-card 
                v-for="referral in pendingReferrals" 
                :key="referral.id"
                :class="[
                  'tw-cursor-pointer tw-transition-all tw-duration-200 tw-border-2',
                  selectedReferralId === referral.id ? 'tw-border-blue-500 tw-bg-blue-50' : 'tw-border-gray-200 hover:tw-border-gray-300'
                ]"
                @click="selectedReferralId = referral.id"
              >
                <v-card-text>
                  <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Referral Code</p>
                      <p class="tw-text-base tw-font-mono tw-text-gray-900">{{ referral.referral_code }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Patient</p>
                      <p class="tw-text-base tw-text-gray-900">{{ referral.patient_name }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Date Created</p>
                      <p class="tw-text-base tw-text-gray-900">{{ formatDate(referral.created_at) }}</p>
                    </div>
                  </div>
                  <div class="tw-mt-3">
                    <p class="tw-text-sm tw-font-medium tw-text-gray-600">Current Service</p>
                    <p class="tw-text-base tw-text-gray-900">{{ referral.current_service || 'Not specified' }}</p>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
        </v-card>
      </template>

      <!-- Step 3: Full Referral Form (for Referral Request) -->
      <template #item.3 v-if="requestType === 'referral'">
        <ReferralRequestForm
          ref="referralFormRef"
          :selected-facility="selectedFacility"
          @submit="handleReferralFormSubmit"
        />
      </template>

      <!-- Step 4: Review (for PA Code Request) -->
      <template #item.4 v-if="requestType === 'pa_code'">
        <v-card flat>
          <v-card-title class="tw-text-lg tw-font-semibold tw-text-gray-800">
            <v-icon class="tw-mr-2">mdi-clipboard-check</v-icon>
            Review PA Code Request
          </v-card-title>
          <v-card-text class="tw-pt-4">
            <div v-if="selectedReferral" class="tw-space-y-4">
              <v-alert type="info" variant="tonal">
                <v-alert-title>Selected Referral</v-alert-title>
                <p>You are about to generate a PA Code for referral <strong>{{ selectedReferral.referral_code }}</strong> for patient <strong>{{ selectedReferral.patient_name }}</strong>.</p>
              </v-alert>

              <v-card variant="outlined">
                <v-card-text>
                  <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Referral Code</p>
                      <p class="tw-text-base tw-font-mono tw-text-gray-900">{{ selectedReferral.referral_code }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Patient Name</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedReferral.patient_name }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Current Service</p>
                      <p class="tw-text-base tw-text-gray-900">{{ selectedReferral.current_service }}</p>
                    </div>
                    <div>
                      <p class="tw-text-sm tw-font-medium tw-text-gray-600">Date Created</p>
                      <p class="tw-text-base tw-text-gray-900">{{ formatDate(selectedReferral.created_at) }}</p>
                    </div>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
        </v-card>
      </template>

      <!-- Step 4: Service Selection (for Modify Referral) -->
      <template #item.4 v-if="requestType === 'modify_referral'">
        <v-card flat>
          <v-card-title class="tw-text-lg tw-font-semibold tw-text-gray-800">
            <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
            Select New Service
          </v-card-title>
          <v-card-text class="tw-pt-4">
            <div class="tw-space-y-4">
              <v-alert type="warning" variant="tonal">
                <v-alert-title>Modifying Referral Service</v-alert-title>
                <p>You are changing the service for referral <strong>{{ selectedReferral?.referral_code }}</strong>. The original referral code will be maintained.</p>
              </v-alert>

              <v-select
                v-model="newServiceId"
                :items="availableServices"
                item-title="service_description"
                item-value="id"
                label="New Referral Service *"
                variant="outlined"
                density="comfortable"
                :loading="servicesLoading"
                :rules="[rules.required]"
              >
                <template #item="{ props, item }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ item.raw.service_description }}</v-list-item-title>
                    <v-list-item-subtitle>{{ item.raw.nicare_code }} - ₦{{ item.raw.price }}</v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-select>

              <v-textarea
                v-model="modificationReason"
                label="Reason for Modification *"
                variant="outlined"
                rows="3"
                :rules="[rules.required]"
                placeholder="Please provide a reason for changing the referral service..."
              />
            </div>
          </v-card-text>
        </v-card>
      </template>

      <!-- Navigation Buttons -->
      <template #actions>
        <div class="tw-flex tw-justify-between tw-w-full tw-px-6 tw-py-4">
          <v-btn
            v-if="currentStep > 1"
            variant="outlined"
            @click="previousStep"
            :disabled="loading"
          >
            <v-icon class="tw-mr-2">mdi-arrow-left</v-icon>
            Previous
          </v-btn>
          <div v-else></div>

          <div class="tw-flex tw-space-x-3">
            <v-btn
              variant="text"
              @click="$emit('cancel')"
              :disabled="loading"
            >
              Cancel
            </v-btn>
            
            <v-btn
              v-if="!isLastStep"
              color="primary"
              @click="nextStep"
              :disabled="!canProceedToNextStep || loading"
            >
              Next
              <v-icon class="tw-ml-2">mdi-arrow-right</v-icon>
            </v-btn>
            
            <v-btn
              v-else
              color="primary"
              @click="submitRequest"
              :loading="loading"
              :disabled="!canSubmit"
            >
              {{ getSubmitButtonText() }}
            </v-btn>
          </div>
        </div>
      </template>
    </v-stepper>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { facilityAPI, pasAPI, caseAPI } from '../../utils/api.js';
import { useToast } from '../../composables/useToast';
import ReferralRequestForm from './ReferralRequestForm.vue';

const emit = defineEmits(['submit', 'cancel']);
const { success, error } = useToast();

// Reactive data
const currentStep = ref(1);
const selectedFacilityId = ref(null);
const selectedFacility = ref(null);
const requestType = ref('');
const selectedReferralId = ref(null);
const newServiceId = ref(null);
const modificationReason = ref('');
const loading = ref(false);
const referralFormRef = ref(null);

// Data arrays
const facilities = ref([]);
const facilitiesLoading = ref(false);
const pendingReferrals = ref([]);
const pendingReferralsLoading = ref(false);
const availableServices = ref([]);
const servicesLoading = ref(false);

// Validation rules
const rules = {
  required: (value) => !!value || 'This field is required'
};

// Computed properties
const stepperItems = computed(() => {
  const items = [
    { title: 'Facility', value: 1 },
    { title: 'Request Type', value: 2 }
  ];
  
  if (requestType.value === 'pa_code' || requestType.value === 'modify_referral') {
    items.push({ title: 'Select Referral', value: 3 });
  }
  
  if (requestType.value === 'modify_referral') {
    items.push({ title: 'New Service', value: 4 });
  } else if (requestType.value === 'referral') {
    items.push({ title: 'Details', value: 3 });
  } else if (requestType.value === 'pa_code') {
    items.push({ title: 'Review', value: 4 });
  }
  
  return items;
});

const isLastStep = computed(() => {
  return currentStep.value === stepperItems.value.length;
});

const canProceedToNextStep = computed(() => {
  switch (currentStep.value) {
    case 1:
      return !!selectedFacilityId.value;
    case 2:
      return !!requestType.value;
    case 3:
      if (requestType.value === 'pa_code' || requestType.value === 'modify_referral') {
        return !!selectedReferralId.value;
      }
      return true;
    case 4:
      if (requestType.value === 'modify_referral') {
        return !!newServiceId.value && !!modificationReason.value;
      }
      return true;
    default:
      return true;
  }
});

const canSubmit = computed(() => {
  return canProceedToNextStep.value;
});

const selectedReferral = computed(() => {
  return pendingReferrals.value.find(r => r.id === selectedReferralId.value);
});

// Methods
const loadFacilities = async () => {
  try {
    facilitiesLoading.value = true;
    const response = await facilityAPI.getAll({
      status: 1,
      per_page: 1000
    });
    
    if (response.data.success) {
      facilities.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load facilities:', err);
    error('Failed to load facilities');
  } finally {
    facilitiesLoading.value = false;
  }
};

const handleFacilitySelection = (facilityId) => {
  selectedFacility.value = facilities.value.find(f => f.id === facilityId);
};

const loadPendingReferrals = async () => {
  if (!selectedFacilityId.value) return;

  try {
    pendingReferralsLoading.value = true;
    const response = await pasAPI.getPendingReferralsByFacility(selectedFacilityId.value);

    if (response.data.success) {
      pendingReferrals.value = response.data.data || [];
    } else {
      // If API doesn't exist yet, use mock data
      pendingReferrals.value = [
        {
          id: 1,
          referral_code: 'REF-2024-001',
          patient_name: 'John Doe',
          current_service: 'General Consultation',
          created_at: '2024-01-15T10:30:00Z'
        },
        {
          id: 2,
          referral_code: 'REF-2024-002',
          patient_name: 'Jane Smith',
          current_service: 'Cardiology Consultation',
          created_at: '2024-01-14T14:20:00Z'
        }
      ];
    }
  } catch (err) {
    console.error('Failed to load pending referrals:', err);
    // Use mock data as fallback
    pendingReferrals.value = [
      {
        id: 1,
        referral_code: 'REF-2024-001',
        patient_name: 'John Doe',
        current_service: 'General Consultation',
        created_at: '2024-01-15T10:30:00Z'
      },
      {
        id: 2,
        referral_code: 'REF-2024-002',
        patient_name: 'Jane Smith',
        current_service: 'Cardiology Consultation',
        created_at: '2024-01-14T14:20:00Z'
      }
    ];
  } finally {
    pendingReferralsLoading.value = false;
  }
};

const loadAvailableServices = async () => {
  try {
    servicesLoading.value = true;
    const response = await caseAPI.getAll({
      status: 1, // Only active cases
      per_page: 1000
    });

    if (response.data.success) {
      availableServices.value = response.data.data.data || response.data.data || [];
    } else {
      // If API doesn't exist yet, use mock data
      availableServices.value = [
        {
          id: 1,
          nicare_code: 'NGSCHS/Card/S/001',
          case_description: 'Cardiology Consultation',
          price: 5000
        },
        {
          id: 2,
          nicare_code: 'NGSCHS/Orth/S/001',
          case_description: 'Orthopedic Consultation',
          price: 4500
        }
      ];
    }
  } catch (err) {
    console.error('Failed to load services:', err);
    // Use mock data as fallback
    availableServices.value = [
      {
        id: 1,
        nicare_code: 'NGSCHS/Card/S/001',
        service_description: 'Cardiology Consultation',
        price: 5000
      },
      {
        id: 2,
        nicare_code: 'NGSCHS/Orth/S/001',
        service_description: 'Orthopedic Consultation',
        price: 4500
      }
    ];
  } finally {
    servicesLoading.value = false;
  }
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString();
};

const nextStep = () => {
  if (canProceedToNextStep.value) {
    currentStep.value++;
    
    // Load data for specific steps
    if (currentStep.value === 3 && (requestType.value === 'pa_code' || requestType.value === 'modify_referral')) {
      loadPendingReferrals();
    }
    
    if (currentStep.value === 4 && requestType.value === 'modify_referral') {
      loadAvailableServices();
    }
  }
};

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

const getSubmitButtonText = () => {
  switch (requestType.value) {
    case 'referral':
      return 'Create Referral';
    case 'pa_code':
      return 'Generate PA Code';
    case 'modify_referral':
      return 'Save Modification';
    default:
      return 'Submit';
  }
};

const handleReferralFormSubmit = (formData) => {
  // For referral requests, the form handles its own submission
  // We just need to pass the data up with the request type
  const requestData = {
    ...formData,
    facility_id: selectedFacilityId.value,
    request_type: requestType.value
  };

  emit('submit', requestData);
};

const submitRequest = async () => {
  try {
    loading.value = true;

    if (requestType.value === 'referral') {
      // For referral requests, trigger the form submission
      if (referralFormRef.value) {
        referralFormRef.value.submitForm();
      }
      return;
    }

    if (requestType.value === 'pa_code') {
      // Generate PA Code from selected referral
      const requestData = {
        facility_id: selectedFacilityId.value,
        request_type: requestType.value,
        referral_id: selectedReferralId.value,
        referral: selectedReferral.value
      };

      emit('submit', requestData);
      success('PA Code request submitted successfully');
      return;
    }

    if (requestType.value === 'modify_referral') {
      // Modify referral service
      const response = await pasAPI.modifyReferral(selectedReferralId.value, {
        new_service_id: newServiceId.value,
        modification_reason: modificationReason.value
      });

      if (response.data.success) {
        success('Referral service modified successfully');
        emit('submit', {
          facility_id: selectedFacilityId.value,
          request_type: requestType.value,
          referral_id: selectedReferralId.value,
          new_service_id: newServiceId.value,
          modification_reason: modificationReason.value
        });
      } else {
        error(response.data.message || 'Failed to modify referral');
      }
      return;
    }

  } catch (err) {
    console.error('Failed to submit request:', err);
    error('Failed to submit request');
  } finally {
    loading.value = false;
  }
};

// Watchers
watch(requestType, (newType) => {
  // Reset selections when request type changes
  selectedReferralId.value = null;
  newServiceId.value = null;
  modificationReason.value = '';
});

// Lifecycle
onMounted(() => {
  loadFacilities();
});
</script>

<style scoped>
:deep(.v-stepper) {
  box-shadow: none;
}

:deep(.v-stepper-header) {
  box-shadow: none;
  border-bottom: 1px solid #e5e7eb;
}
</style>
