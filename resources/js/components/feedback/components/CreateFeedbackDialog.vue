<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card>
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
        <v-icon class="tw-mr-2">mdi-plus-circle</v-icon>
        Create Feedback Record
      </v-card-title>

      <v-card-text class="tw-p-6">
        <v-stepper v-model="currentStep" :items="stepperItems" class="tw-elevation-0">
          <!-- Step 1: Search Enrollee -->
          <template v-slot:item.1>
            <div class="tw-space-y-4">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-800">Search Enrollee</h3>
              
              <v-autocomplete
                v-model="selectedEnrollee"
                :items="enrollees"
                :loading="searchingEnrollees"
                :search="enrolleeSearch"
                @update:search="searchEnrollees"
                item-title="full_name"
                item-value="id"
                label="Search by name or NiCare number"
                prepend-inner-icon="mdi-account-search"
                variant="outlined"
                clearable
                no-filter
                return-object
              >
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template v-slot:title>
                      <div class="tw-font-medium">{{ item.raw.full_name }}</div>
                    </template>
                    <template v-slot:subtitle>
                      <div class="tw-text-sm tw-text-gray-600">
                        {{ item.raw.nicare_number }} • {{ item.raw.phone }}
                        <span v-if="item.raw.facility" class="tw-ml-2">
                          • {{ item.raw.facility.name }}
                        </span>
                      </div>
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>

              <div v-if="selectedEnrollee" class="tw-bg-blue-50 tw-p-4 tw-rounded-lg">
                <h4 class="tw-font-semibold tw-text-blue-800 tw-mb-2">Selected Enrollee</h4>
                <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                  <div>
                    <span class="tw-font-medium">Name:</span> {{ selectedEnrollee.full_name }}
                  </div>
                  <div>
                    <span class="tw-font-medium">NiCare Number:</span> {{ selectedEnrollee.nicare_number }}
                  </div>
                  <div>
                    <span class="tw-font-medium">Phone:</span> {{ selectedEnrollee.phone }}
                  </div>
                  <div>
                    <span class="tw-font-medium">Gender:</span> {{ selectedEnrollee.gender }}
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Step 2: Feedback Details -->
          <template v-slot:item.2>
            <div class="tw-space-y-4">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-800">Feedback Details</h3>
              
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                <v-select
                  v-model="form.feedback_type"
                  :items="typeOptions"
                  label="Feedback Type"
                  variant="outlined"
                  :rules="[v => !!v || 'Feedback type is required']"
                />
                
                <v-select
                  v-model="form.priority"
                  :items="priorityOptions"
                  label="Priority"
                  variant="outlined"
                  :rules="[v => !!v || 'Priority is required']"
                />
              </div>

              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4" v-if="form.feedback_type">
                <v-autocomplete
                  v-if="form.feedback_type === 'referral'"
                  v-model="form.referral_id"
                  :items="referrals"
                  :loading="loadingReferrals"
                  item-title="referral_code"
                  item-value="id"
                  label="Select Referral"
                  variant="outlined"
                  clearable
                />
                
                <v-autocomplete
                  v-if="form.feedback_type === 'pa_code'"
                  v-model="form.pa_code_id"
                  :items="paCodes"
                  :loading="loadingPaCodes"
                  item-title="pa_code"
                  item-value="id"
                  label="Select PA Code"
                  variant="outlined"
                  clearable
                />
              </div>

              <v-textarea
                v-model="form.feedback_comments"
                label="Feedback Comments"
                variant="outlined"
                rows="3"
                placeholder="Enter your feedback comments..."
              />

              <v-textarea
                v-model="form.officer_observations"
                label="Officer Observations"
                variant="outlined"
                rows="3"
                placeholder="Enter your observations..."
              />

              <v-textarea
                v-model="form.claims_guidance"
                label="Claims Processing Guidance"
                variant="outlined"
                rows="3"
                placeholder="Provide guidance for claims processing..."
              />
            </div>
          </template>

          <!-- Step 3: Review -->
          <template v-slot:item.3>
            <div class="tw-space-y-4">
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-800">Review Feedback</h3>
              
              <div class="tw-bg-gray-50 tw-p-4 tw-rounded-lg tw-space-y-3">
                <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                  <div>
                    <span class="tw-font-medium">Enrollee:</span> {{ selectedEnrollee?.full_name }}
                  </div>
                  <div>
                    <span class="tw-font-medium">NiCare Number:</span> {{ selectedEnrollee?.nicare_number }}
                  </div>
                  <div>
                    <span class="tw-font-medium">Feedback Type:</span> 
                    <v-chip :color="getTypeColor(form.feedback_type)" size="small" class="tw-ml-2">
                      {{ formatType(form.feedback_type) }}
                    </v-chip>
                  </div>
                  <div>
                    <span class="tw-font-medium">Priority:</span>
                    <v-chip :color="getPriorityColor(form.priority)" size="small" class="tw-ml-2">
                      {{ formatPriority(form.priority) }}
                    </v-chip>
                  </div>
                </div>

                <div v-if="form.feedback_comments" class="tw-border-t tw-pt-3">
                  <span class="tw-font-medium">Comments:</span>
                  <p class="tw-text-sm tw-text-gray-700 tw-mt-1">{{ form.feedback_comments }}</p>
                </div>

                <div v-if="form.officer_observations" class="tw-border-t tw-pt-3">
                  <span class="tw-font-medium">Observations:</span>
                  <p class="tw-text-sm tw-text-gray-700 tw-mt-1">{{ form.officer_observations }}</p>
                </div>

                <div v-if="form.claims_guidance" class="tw-border-t tw-pt-3">
                  <span class="tw-font-medium">Claims Guidance:</span>
                  <p class="tw-text-sm tw-text-gray-700 tw-mt-1">{{ form.claims_guidance }}</p>
                </div>
              </div>
            </div>
          </template>
        </v-stepper>
      </v-card-text>

      <v-card-actions class="tw-p-6 tw-bg-gray-50">
        <v-spacer />
        <v-btn
          variant="outlined"
          @click="closeDialog"
          :disabled="submitting"
        >
          Cancel
        </v-btn>
        <v-btn
          v-if="currentStep > 1"
          variant="outlined"
          @click="currentStep--"
          :disabled="submitting"
        >
          Previous
        </v-btn>
        <v-btn
          v-if="currentStep < 3"
          color="primary"
          @click="nextStep"
          :disabled="!canProceed"
        >
          Next
        </v-btn>
        <v-btn
          v-if="currentStep === 3"
          color="primary"
          @click="submitFeedback"
          :loading="submitting"
          :disabled="!isFormValid"
        >
          Create Feedback
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { feedbackAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';
import { debounce } from 'lodash-es';

const { success, error } = useToast();

// Props and emits
const props = defineProps({
  modelValue: Boolean
});

const emit = defineEmits(['update:modelValue', 'created']);

// Reactive data
const currentStep = ref(1);
const submitting = ref(false);
const searchingEnrollees = ref(false);
const loadingReferrals = ref(false);
const loadingPaCodes = ref(false);

const enrollees = ref([]);
const referrals = ref([]);
const paCodes = ref([]);
const selectedEnrollee = ref(null);
const enrolleeSearch = ref('');

const form = ref({
  feedback_type: '',
  priority: '',
  referral_id: null,
  pa_code_id: null,
  feedback_comments: '',
  officer_observations: '',
  claims_guidance: ''
});

// Computed
const dialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const stepperItems = [
  { title: 'Search Enrollee', value: 1 },
  { title: 'Feedback Details', value: 2 },
  { title: 'Review', value: 3 }
];

const typeOptions = [
  { title: 'Referral Follow-up', value: 'referral' },
  { title: 'PA Code Issue', value: 'pa_code' },
  { title: 'General Inquiry', value: 'general' },
  { title: 'Enrollee Verification', value: 'enrollee_verification' },
  { title: 'Service Delivery', value: 'service_delivery' },
  { title: 'Claims Guidance', value: 'claims_guidance' },
  { title: 'Medical History Review', value: 'medical_history' },
  { title: 'Complaint Resolution', value: 'complaint' },
  { title: 'UTN Validation Issue', value: 'utn_validation' },
  { title: 'Facility Coordination', value: 'facility_coordination' },
  { title: 'Document Verification', value: 'document_verification' },
  { title: 'Treatment Progress', value: 'treatment_progress' },
];

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' }
];

const canProceed = computed(() => {
  if (currentStep.value === 1) {
    return selectedEnrollee.value;
  }
  if (currentStep.value === 2) {
    return form.value.feedback_type && form.value.priority;
  }
  return true;
});

const isFormValid = computed(() => {
  return selectedEnrollee.value && 
         form.value.feedback_type && 
         form.value.priority;
});

// Methods
const searchEnrollees = debounce(async (search) => {
  if (!search || search.length < 2) {
    enrollees.value = [];
    return;
  }

  try {
    searchingEnrollees.value = true;
    const response = await feedbackAPI.searchEnrollees({ search });
    if (response.data.success) {
      enrollees.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to search enrollees:', err);
  } finally {
    searchingEnrollees.value = false;
  }
}, 300);

const nextStep = () => {
  if (canProceed.value) {
    currentStep.value++;
  }
};

const submitFeedback = async () => {
  if (!isFormValid.value) return;

  try {
    submitting.value = true;
    const payload = {
      enrollee_id: selectedEnrollee.value.id,
      ...form.value
    };

    const response = await feedbackAPI.create(payload);
    if (response.data.success) {
      success('Feedback created successfully');
      emit('created', response.data.data);
      closeDialog();
    } else {
      error(response.data.message || 'Failed to create feedback');
    }
  } catch (err) {
    console.error('Failed to create feedback:', err);
    const errorMessage = err.response?.data?.message || err.response?.data?.error || 'Failed to create feedback. Please try again.';
    error(errorMessage);
  } finally {
    submitting.value = false;
  }
};

const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

const resetForm = () => {
  currentStep.value = 1;
  selectedEnrollee.value = null;
  enrolleeSearch.value = '';
  enrollees.value = [];
  referrals.value = [];
  paCodes.value = [];
  form.value = {
    feedback_type: '',
    priority: '',
    referral_id: null,
    pa_code_id: null,
    feedback_comments: '',
    officer_observations: '',
    claims_guidance: ''
  };
};

// Utility methods
const getTypeColor = (type) => {
  const colors = {
    referral: 'blue',
    pa_code: 'green',
    general: 'purple'
  };
  return colors[type] || 'grey';
};

const getPriorityColor = (priority) => {
  const colors = {
    low: 'green',
    medium: 'orange',
    high: 'red',
    urgent: 'purple'
  };
  return colors[priority] || 'grey';
};

const formatType = (type) => {
  return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatPriority = (priority) => {
  return priority.charAt(0).toUpperCase() + priority.slice(1);
};

// Watchers
watch(() => props.modelValue, (newVal) => {
  if (!newVal) {
    resetForm();
  }
});
</script>
