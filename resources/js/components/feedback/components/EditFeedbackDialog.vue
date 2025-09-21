<template>
  <v-dialog v-model="dialog" max-width="800px" persistent>
    <v-card v-if="feedback">
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
        <v-icon class="tw-mr-2">mdi-pencil</v-icon>
        Edit Feedback - {{ feedback.feedback_code }}
      </v-card-title>

      <v-card-text class="tw-p-6">
        <div class="tw-space-y-6">
          <!-- Enrollee Info (Read-only) -->
          <v-card variant="outlined" class="tw-bg-gray-50">
            <v-card-title class="tw-text-lg">Enrollee Information</v-card-title>
            <v-card-text>
              <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                <div>
                  <span class="tw-font-medium">Name:</span> {{ feedback.enrollee?.full_name }}
                </div>
                <div>
                  <span class="tw-font-medium">NiCare Number:</span> {{ feedback.enrollee?.nicare_number }}
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Editable Fields -->
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-select
              v-model="form.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              :rules="[v => !!v || 'Status is required']"
            />
            
            <v-select
              v-model="form.priority"
              :items="priorityOptions"
              label="Priority"
              variant="outlined"
              :rules="[v => !!v || 'Priority is required']"
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

          <!-- Additional Information -->
          <v-card variant="outlined">
            <v-card-title class="tw-text-lg">Additional Information</v-card-title>
            <v-card-text>
              <div class="tw-space-y-4">
                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                    Enrollee Verification Data (JSON)
                  </label>
                  <v-textarea
                    v-model="verificationDataText"
                    variant="outlined"
                    rows="3"
                    placeholder='{"verified": true, "notes": "..."}'
                    :error-messages="verificationDataError"
                  />
                </div>

                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                    Medical History Summary (JSON)
                  </label>
                  <v-textarea
                    v-model="medicalHistoryText"
                    variant="outlined"
                    rows="3"
                    placeholder='{"conditions": [], "medications": []}'
                    :error-messages="medicalHistoryError"
                  />
                </div>

                <div>
                  <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                    Additional Information (JSON)
                  </label>
                  <v-textarea
                    v-model="additionalInfoText"
                    variant="outlined"
                    rows="3"
                    placeholder='{"notes": "...", "recommendations": []}'
                    :error-messages="additionalInfoError"
                  />
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>
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
          color="primary"
          @click="updateFeedback"
          :loading="submitting"
          :disabled="!isFormValid"
        >
          Update Feedback
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { feedbackAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const { success, error } = useToast();

// Props and emits
const props = defineProps({
  modelValue: Boolean,
  feedback: Object
});

const emit = defineEmits(['update:modelValue', 'updated']);

// Reactive data
const submitting = ref(false);
const verificationDataText = ref('');
const medicalHistoryText = ref('');
const additionalInfoText = ref('');
const verificationDataError = ref('');
const medicalHistoryError = ref('');
const additionalInfoError = ref('');

const form = ref({
  status: '',
  priority: '',
  feedback_comments: '',
  officer_observations: '',
  claims_guidance: ''
});

// Computed
const dialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const statusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'In Progress', value: 'in_progress' },
  { title: 'Completed', value: 'completed' },
  { title: 'Escalated', value: 'escalated' }
];

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' }
];

const isFormValid = computed(() => {
  return form.value.status && form.value.priority;
});

// Methods
const initializeForm = () => {
  if (props.feedback) {
    form.value = {
      status: props.feedback.status || '',
      priority: props.feedback.priority || '',
      feedback_comments: props.feedback.feedback_comments || '',
      officer_observations: props.feedback.officer_observations || '',
      claims_guidance: props.feedback.claims_guidance || ''
    };

    // Initialize JSON fields
    verificationDataText.value = props.feedback.enrollee_verification_data 
      ? JSON.stringify(props.feedback.enrollee_verification_data, null, 2) 
      : '';
    
    medicalHistoryText.value = props.feedback.medical_history_summary 
      ? JSON.stringify(props.feedback.medical_history_summary, null, 2) 
      : '';
    
    additionalInfoText.value = props.feedback.additional_information 
      ? JSON.stringify(props.feedback.additional_information, null, 2) 
      : '';
  }
};

const validateJSON = (text, errorRef) => {
  if (!text.trim()) {
    errorRef.value = '';
    return null;
  }

  try {
    return JSON.parse(text);
  } catch (e) {
    errorRef.value = 'Invalid JSON format';
    return false;
  }
};

const updateFeedback = async () => {
  if (!isFormValid.value) return;

  // Validate JSON fields
  const verificationData = validateJSON(verificationDataText.value, verificationDataError);
  const medicalHistory = validateJSON(medicalHistoryText.value, medicalHistoryError);
  const additionalInfo = validateJSON(additionalInfoText.value, additionalInfoError);

  if (verificationData === false || medicalHistory === false || additionalInfo === false) {
    error('Please fix JSON formatting errors');
    return;
  }

  try {
    submitting.value = true;
    const payload = {
      ...form.value,
      enrollee_verification_data: verificationData,
      medical_history_summary: medicalHistory,
      additional_information: additionalInfo
    };

    const response = await feedbackAPI.update(props.feedback.id, payload);
    if (response.data.success) {
      emit('updated', response.data.data);
      closeDialog();
      success('Feedback updated successfully');
    }
  } catch (err) {
    console.error('Failed to update feedback:', err);
    error('Failed to update feedback');
  } finally {
    submitting.value = false;
  }
};

const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

const resetForm = () => {
  form.value = {
    status: '',
    priority: '',
    feedback_comments: '',
    officer_observations: '',
    claims_guidance: ''
  };
  verificationDataText.value = '';
  medicalHistoryText.value = '';
  additionalInfoText.value = '';
  verificationDataError.value = '';
  medicalHistoryError.value = '';
  additionalInfoError.value = '';
};

// Watchers
watch(() => props.modelValue, (newVal) => {
  if (newVal && props.feedback) {
    initializeForm();
  } else if (!newVal) {
    resetForm();
  }
});

watch(() => props.feedback, (newFeedback) => {
  if (newFeedback && props.modelValue) {
    initializeForm();
  }
});

// Validate JSON on input
watch(verificationDataText, () => {
  if (verificationDataText.value.trim()) {
    validateJSON(verificationDataText.value, verificationDataError);
  } else {
    verificationDataError.value = '';
  }
});

watch(medicalHistoryText, () => {
  if (medicalHistoryText.value.trim()) {
    validateJSON(medicalHistoryText.value, medicalHistoryError);
  } else {
    medicalHistoryError.value = '';
  }
});

watch(additionalInfoText, () => {
  if (additionalInfoText.value.trim()) {
    validateJSON(additionalInfoText.value, additionalInfoError);
  } else {
    additionalInfoError.value = '';
  }
});
</script>
