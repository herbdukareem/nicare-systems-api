<template>
  <v-dialog v-model="dialog" max-width="600px" persistent>
    <v-card v-if="feedback">
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
        <v-icon class="tw-mr-2">mdi-account-plus</v-icon>
        Assign Feedback - {{ feedback.feedback_code }}
      </v-card-title>

      <v-card-text class="tw-p-6">
        <div class="tw-space-y-6">
          <!-- Feedback Info -->
          <v-card variant="outlined" class="tw-bg-gray-50">
            <v-card-title class="tw-text-lg">Feedback Information</v-card-title>
            <v-card-text>
              <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                <div>
                  <span class="tw-font-medium">Feedback Code:</span> {{ feedback.feedback_code }}
                </div>
                <div>
                  <span class="tw-font-medium">Enrollee:</span> {{ feedback.enrollee?.full_name }}
                </div>
                <div>
                  <span class="tw-font-medium">Type:</span>
                  <v-chip :color="getTypeColor(feedback.feedback_type)" size="small" class="tw-ml-2">
                    {{ formatType(feedback.feedback_type) }}
                  </v-chip>
                </div>
                <div>
                  <span class="tw-font-medium">Priority:</span>
                  <v-chip :color="getPriorityColor(feedback.priority)" size="small" class="tw-ml-2">
                    {{ formatPriority(feedback.priority) }}
                  </v-chip>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Current Assignment -->
          <div v-if="feedback.feedback_officer" class="tw-bg-orange-50 tw-p-4 tw-rounded-lg">
            <h4 class="tw-font-semibold tw-text-orange-800 tw-mb-2">Currently Assigned To:</h4>
            <div class="tw-text-sm">
              <p class="tw-font-medium">{{ feedback.feedback_officer.name }}</p>
              <p class="tw-text-gray-600">{{ feedback.feedback_officer.email }}</p>
            </div>
          </div>

          <!-- Officer Selection -->
          <div>
            <v-select
              v-model="selectedOfficer"
              :items="officers"
              :loading="loadingOfficers"
              item-title="name"
              item-value="id"
              label="Select Feedback Officer"
              variant="outlined"
              :rules="[v => !!v || 'Please select an officer']"
              return-object
            >
              <template v-slot:item="{ props, item }">
                <v-list-item v-bind="props">
                  <template v-slot:title>
                    <div class="tw-font-medium">{{ item.raw.name }}</div>
                  </template>
                  <template v-slot:subtitle>
                    <div class="tw-text-sm tw-text-gray-600">{{ item.raw.email }}</div>
                  </template>
                </v-list-item>
              </template>
            </v-select>
          </div>

          <!-- Selected Officer Info -->
          <div v-if="selectedOfficer" class="tw-bg-blue-50 tw-p-4 tw-rounded-lg">
            <h4 class="tw-font-semibold tw-text-blue-800 tw-mb-2">Assigning To:</h4>
            <div class="tw-text-sm">
              <p class="tw-font-medium">{{ selectedOfficer.name }}</p>
              <p class="tw-text-gray-600">{{ selectedOfficer.email }}</p>
            </div>
          </div>

          <!-- Assignment Note -->
          <v-alert
            type="info"
            variant="tonal"
            class="tw-text-sm"
          >
            <template v-slot:title>Assignment Note</template>
            Assigning this feedback will automatically change its status to "In Progress" and notify the selected officer.
          </v-alert>
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
          @click="assignFeedback"
          :loading="submitting"
          :disabled="!selectedOfficer"
        >
          Assign Feedback
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { feedbackAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const { success, error } = useToast();

// Props and emits
const props = defineProps({
  modelValue: Boolean,
  feedback: Object
});

const emit = defineEmits(['update:modelValue', 'assigned']);

// Reactive data
const submitting = ref(false);
const loadingOfficers = ref(false);
const officers = ref([]);
const selectedOfficer = ref(null);

// Computed
const dialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

// Methods
const loadOfficers = async () => {
  try {
    loadingOfficers.value = true;
    const response = await feedbackAPI.getFeedbackOfficers();
    if (response.data.success) {
      officers.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load officers:', err);
    error('Failed to load feedback officers');
  } finally {
    loadingOfficers.value = false;
  }
};

const assignFeedback = async () => {
  if (!selectedOfficer.value) return;

  try {
    submitting.value = true;
    const response = await feedbackAPI.assignToOfficer(props.feedback.id, {
      feedback_officer_id: selectedOfficer.value.id
    });
    
    if (response.data.success) {
      emit('assigned', response.data.data);
      closeDialog();
      success(`Feedback assigned to ${selectedOfficer.value.name}`);
    }
  } catch (err) {
    console.error('Failed to assign feedback:', err);
    error('Failed to assign feedback');
  } finally {
    submitting.value = false;
  }
};

const closeDialog = () => {
  dialog.value = false;
  selectedOfficer.value = null;
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
  if (newVal) {
    loadOfficers();
  } else {
    selectedOfficer.value = null;
  }
});

// Lifecycle
onMounted(() => {
  if (props.modelValue) {
    loadOfficers();
  }
});
</script>
