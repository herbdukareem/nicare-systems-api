<template>
  <v-dialog v-model="dialog" max-width="1200px" scrollable>
    <v-card v-if="feedback">
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
        <v-icon class="tw-mr-2">mdi-eye</v-icon>
        Feedback Details - {{ feedback.feedback_code }}
        <v-spacer />
        <v-chip
          :color="getStatusColor(feedback.status)"
          size="small"
          variant="flat"
        >
          {{ formatStatus(feedback.status) }}
        </v-chip>
      </v-card-title>

      <v-card-text class="tw-p-0">
        <v-tabs v-model="activeTab" class="tw-border-b">
          <v-tab value="overview">Overview</v-tab>
          <v-tab value="enrollee">Enrollee Details</v-tab>
          <v-tab value="medical">Medical History</v-tab>
          <v-tab value="feedback">Feedback</v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab" class="tw-p-6">
          <!-- Overview Tab -->
          <v-tabs-window-item value="overview">
            <div class="tw-space-y-6">
              <!-- Basic Information -->
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <v-card variant="outlined">
                  <v-card-title class="tw-text-lg">Basic Information</v-card-title>
                  <v-card-text>
                    <div class="tw-space-y-3">
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Feedback Code:</span>
                        <span>{{ feedback.feedback_code }}</span>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Type:</span>
                        <v-chip :color="getTypeColor(feedback.feedback_type)" size="small">
                          {{ formatType(feedback.feedback_type) }}
                        </v-chip>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Priority:</span>
                        <v-chip :color="getPriorityColor(feedback.priority)" size="small">
                          {{ formatPriority(feedback.priority) }}
                        </v-chip>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Status:</span>
                        <v-chip :color="getStatusColor(feedback.status)" size="small">
                          {{ formatStatus(feedback.status) }}
                        </v-chip>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Created:</span>
                        <span>{{ formatDate(feedback.created_at) }}</span>
                      </div>
                      <div v-if="feedback.completed_at" class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Completed:</span>
                        <span>{{ formatDate(feedback.completed_at) }}</span>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>

                <v-card variant="outlined">
                  <v-card-title class="tw-text-lg">Assignment</v-card-title>
                  <v-card-text>
                    <div class="tw-space-y-3">
                      <div v-if="feedback.feedback_officer">
                        <span class="tw-font-medium">Assigned Officer:</span>
                        <div class="tw-mt-1">
                          <p class="tw-font-medium">{{ feedback.feedback_officer.name }}</p>
                          <p class="tw-text-sm tw-text-gray-600">{{ feedback.feedback_officer.email }}</p>
                        </div>
                      </div>
                      <div v-else class="tw-text-gray-500">
                        Not assigned to any officer
                      </div>
                      
                      <div v-if="feedback.creator">
                        <span class="tw-font-medium">Created by:</span>
                        <p class="tw-mt-1">{{ feedback.creator.name }}</p>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>
              </div>

              <!-- Related Records -->
              <div v-if="feedback.referral || feedback.pa_code" class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <v-card v-if="feedback.referral" variant="outlined">
                  <v-card-title class="tw-text-lg">Related Referral</v-card-title>
                  <v-card-text>
                    <div class="tw-space-y-2">
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Referral Code:</span>
                        <span>{{ feedback.referral.referral_code }}</span>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Status:</span>
                        <v-chip size="small" color="blue">{{ feedback.referral.status }}</v-chip>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>

                <v-card v-if="feedback.pa_code" variant="outlined">
                  <v-card-title class="tw-text-lg">Related PA Code</v-card-title>
                  <v-card-text>
                    <div class="tw-space-y-2">
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">PA Code:</span>
                        <span>{{ feedback.pa_code.pa_code }}</span>
                      </div>
                      <div class="tw-flex tw-justify-between">
                        <span class="tw-font-medium">Status:</span>
                        <v-chip size="small" color="green">{{ feedback.pa_code.status }}</v-chip>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>
              </div>
            </div>
          </v-tabs-window-item>

          <!-- Enrollee Details Tab -->
          <v-tabs-window-item value="enrollee">
            <div v-if="comprehensiveData" class="tw-space-y-6">
              <!-- Enrollee Basic Info -->
              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Enrollee Information</v-card-title>
                <v-card-text>
                  <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                    <div>
                      <span class="tw-font-medium">Full Name:</span>
                      <p>{{ comprehensiveData.enrollee.full_name }}</p>
                    </div>
                    <div>
                      <span class="tw-font-medium">NiCare Number:</span>
                      <p>{{ comprehensiveData.enrollee.nicare_number }}</p>
                    </div>
                    <div>
                      <span class="tw-font-medium">Phone:</span>
                      <p>{{ comprehensiveData.enrollee.phone }}</p>
                    </div>
                    <div>
                      <span class="tw-font-medium">Gender:</span>
                      <p>{{ comprehensiveData.enrollee.gender }}</p>
                    </div>
                    <div>
                      <span class="tw-font-medium">Primary Facility:</span>
                      <p>{{ comprehensiveData.enrollee.facility?.name }}</p>
                    </div>
                    <div>
                      <span class="tw-font-medium">Benefactor:</span>
                      <p>{{ comprehensiveData.enrollee.benefactor?.name }}</p>
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <!-- Relations (NOK, Family) -->
              <v-card v-if="comprehensiveData.relations?.length" variant="outlined">
                <v-card-title class="tw-text-lg">Relations & Contacts</v-card-title>
                <v-card-text>
                  <div class="tw-space-y-4">
                    <div 
                      v-for="relation in comprehensiveData.relations" 
                      :key="relation.id"
                      class="tw-border tw-rounded-lg tw-p-4"
                    >
                      <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                        <h4 class="tw-font-semibold">{{ relation.full_name }}</h4>
                        <div class="tw-flex tw-space-x-2">
                          <v-chip v-if="relation.is_next_of_kin" size="small" color="red">NOK</v-chip>
                          <v-chip v-if="relation.is_primary_contact" size="small" color="blue">Primary</v-chip>
                          <v-chip v-if="relation.is_emergency_contact" size="small" color="orange">Emergency</v-chip>
                        </div>
                      </div>
                      <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                        <div>
                          <span class="tw-font-medium">Relation:</span> {{ relation.relation_type }}
                        </div>
                        <div>
                          <span class="tw-font-medium">Phone:</span> {{ relation.phone_number }}
                        </div>
                        <div v-if="relation.address" class="tw-col-span-2">
                          <span class="tw-font-medium">Address:</span> {{ relation.address }}
                        </div>
                      </div>
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <!-- Medical Summary -->
              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Medical Summary</v-card-title>
                <v-card-text>
                  <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4 tw-text-center">
                    <div class="tw-bg-blue-50 tw-p-3 tw-rounded">
                      <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ comprehensiveData.medical_summary.total_referrals }}</p>
                      <p class="tw-text-sm tw-text-gray-600">Total Referrals</p>
                    </div>
                    <div class="tw-bg-green-50 tw-p-3 tw-rounded">
                      <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ comprehensiveData.medical_summary.total_pa_codes }}</p>
                      <p class="tw-text-sm tw-text-gray-600">PA Codes</p>
                    </div>
                    <div class="tw-bg-purple-50 tw-p-3 tw-rounded">
                      <p class="tw-text-2xl tw-font-bold tw-text-purple-600">{{ comprehensiveData.medical_summary.total_encounters }}</p>
                      <p class="tw-text-sm tw-text-gray-600">Encounters</p>
                    </div>
                    <div class="tw-bg-orange-50 tw-p-3 tw-rounded">
                      <p class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ comprehensiveData.medical_summary.active_referrals }}</p>
                      <p class="tw-text-sm tw-text-gray-600">Active Referrals</p>
                    </div>
                  </div>
                  
                  <div v-if="comprehensiveData.medical_summary.last_diagnosis" class="tw-mt-4 tw-p-3 tw-bg-gray-50 tw-rounded">
                    <span class="tw-font-medium">Last Diagnosis:</span>
                    <p class="tw-mt-1">{{ comprehensiveData.medical_summary.last_diagnosis }}</p>
                  </div>
                </v-card-text>
              </v-card>
            </div>
            <div v-else class="tw-text-center tw-py-8">
              <v-btn @click="loadComprehensiveData" :loading="loadingComprehensive">
                Load Comprehensive Data
              </v-btn>
            </div>
          </v-tabs-window-item>

          <!-- Medical History Tab -->
          <v-tabs-window-item value="medical">
            <div v-if="comprehensiveData" class="tw-space-y-6">
              <!-- Recent Encounters -->
              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Recent Primary Encounters</v-card-title>
                <v-card-text>
                  <div v-if="comprehensiveData.primary_encounters?.length" class="tw-space-y-3">
                    <div 
                      v-for="encounter in comprehensiveData.primary_encounters" 
                      :key="encounter.id"
                      class="tw-border tw-rounded-lg tw-p-4"
                    >
                      <div class="tw-flex tw-justify-between tw-items-start tw-mb-2">
                        <div>
                          <h4 class="tw-font-semibold">{{ encounter.encounter_code }}</h4>
                          <p class="tw-text-sm tw-text-gray-600">{{ formatDate(encounter.encounter_date) }}</p>
                        </div>
                        <v-chip size="small" :color="encounter.status === 'completed' ? 'green' : 'blue'">
                          {{ encounter.status }}
                        </v-chip>
                      </div>
                      <div v-if="encounter.diagnosis" class="tw-mb-2">
                        <span class="tw-font-medium">Diagnosis:</span>
                        <p class="tw-text-sm">{{ encounter.diagnosis }}</p>
                      </div>
                      <div v-if="encounter.facility" class="tw-text-sm tw-text-gray-600">
                        <span class="tw-font-medium">Facility:</span> {{ encounter.facility.name }}
                      </div>
                    </div>
                  </div>
                  <div v-else class="tw-text-center tw-text-gray-500 tw-py-4">
                    No primary encounters found
                  </div>
                </v-card-text>
              </v-card>

              <!-- Referral History -->
              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Referral History</v-card-title>
                <v-card-text>
                  <div v-if="comprehensiveData.referral_history?.length" class="tw-space-y-3">
                    <div 
                      v-for="referral in comprehensiveData.referral_history" 
                      :key="referral.id"
                      class="tw-border tw-rounded-lg tw-p-4"
                    >
                      <div class="tw-flex tw-justify-between tw-items-start tw-mb-2">
                        <div>
                          <h4 class="tw-font-semibold">{{ referral.referral_code }}</h4>
                          <p class="tw-text-sm tw-text-gray-600">{{ formatDate(referral.created_at) }}</p>
                        </div>
                        <v-chip size="small" color="blue">{{ referral.status }}</v-chip>
                      </div>
                      <div class="tw-grid tw-grid-cols-2 tw-gap-4 tw-text-sm">
                        <div>
                          <span class="tw-font-medium">From:</span> {{ referral.from_facility?.name }}
                        </div>
                        <div>
                          <span class="tw-font-medium">To:</span> {{ referral.to_facility?.name }}
                        </div>
                      </div>
                    </div>
                  </div>
                  <div v-else class="tw-text-center tw-text-gray-500 tw-py-4">
                    No referral history found
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-tabs-window-item>

          <!-- Feedback Tab -->
          <v-tabs-window-item value="feedback">
            <div class="tw-space-y-6">
              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Feedback Comments</v-card-title>
                <v-card-text>
                  <p v-if="feedback.feedback_comments" class="tw-whitespace-pre-wrap">{{ feedback.feedback_comments }}</p>
                  <p v-else class="tw-text-gray-500 tw-italic">No feedback comments provided</p>
                </v-card-text>
              </v-card>

              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Officer Observations</v-card-title>
                <v-card-text>
                  <p v-if="feedback.officer_observations" class="tw-whitespace-pre-wrap">{{ feedback.officer_observations }}</p>
                  <p v-else class="tw-text-gray-500 tw-italic">No officer observations provided</p>
                </v-card-text>
              </v-card>

              <v-card variant="outlined">
                <v-card-title class="tw-text-lg">Claims Processing Guidance</v-card-title>
                <v-card-text>
                  <p v-if="feedback.claims_guidance" class="tw-whitespace-pre-wrap">{{ feedback.claims_guidance }}</p>
                  <p v-else class="tw-text-gray-500 tw-italic">No claims guidance provided</p>
                </v-card-text>
              </v-card>
            </div>
          </v-tabs-window-item>
        </v-tabs-window>
      </v-card-text>

      <v-card-actions class="tw-p-6 tw-bg-gray-50">
        <v-spacer />
        <v-btn variant="outlined" @click="dialog = false">
          Close
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { feedbackAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const { error } = useToast();

// Props and emits
const props = defineProps({
  modelValue: Boolean,
  feedback: Object
});

const emit = defineEmits(['update:modelValue']);

// Reactive data
const activeTab = ref('overview');
const comprehensiveData = ref(null);
const loadingComprehensive = ref(false);

// Computed
const dialog = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

// Methods
const loadComprehensiveData = async () => {
  if (!props.feedback?.enrollee?.id) return;

  try {
    loadingComprehensive.value = true;
    const response = await feedbackAPI.getEnrolleeComprehensiveData(props.feedback.enrollee.id);
    if (response.data.success) {
      comprehensiveData.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load comprehensive data:', err);
    error('Failed to load enrollee comprehensive data');
  } finally {
    loadingComprehensive.value = false;
  }
};

// Utility methods
const getStatusColor = (status) => {
  const colors = {
    pending: 'orange',
    in_progress: 'blue',
    completed: 'green',
    escalated: 'red'
  };
  return colors[status] || 'grey';
};

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

const formatStatus = (status) => {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatType = (type) => {
  return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatPriority = (priority) => {
  return priority.charAt(0).toUpperCase() + priority.slice(1);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

// Watchers
watch(() => props.modelValue, (newVal) => {
  if (newVal && props.feedback) {
    activeTab.value = 'overview';
    comprehensiveData.value = null;
  }
});
</script>
