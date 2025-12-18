<template>
  <v-dialog v-model="isOpen" max-width="1200" scrollable @update:model-value="handleClose">
    <v-card v-if="referral">
      <v-card-title class="bg-primary text-white d-flex align-center pa-4">
        <v-icon left class="mr-2">mdi-file-document-check</v-icon>
        <span class="text-h6">Referral Details</span>
        <v-spacer></v-spacer>
        <v-btn
          v-if="canCreateFeedback"
          color="white"
          variant="outlined"
          prepend-icon="mdi-comment-plus"
          @click="openCreateFeedback"
          class="mr-2"
        >
          Create Feedback
        </v-btn>
        <v-btn icon variant="text" @click="handleClose" color="white">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-card-text class="pa-0">
        <v-container fluid>
          <!-- Header Info -->
          <v-row class="bg-grey-lighten-4 pa-4">
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Referral Code</div>
                <div class="text-h6 font-weight-bold">{{ referral.referral_code }}</div>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">UTN</div>
                <v-chip color="indigo" variant="flat" class="mt-1">{{ referral.utn }}</v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Status</div>
                <v-chip :color="getStatusColor(referral.status)" variant="flat" class="mt-1">
                  <v-icon left size="small">{{ getStatusIcon(referral.status) }}</v-icon>
                  {{ referral.status }}
                </v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="detail-item">
                <div class="text-caption text-grey">Severity Level</div>
                <v-chip :color="getSeverityColor(referral.severity_level)" variant="flat" class="mt-1">
                  {{ referral.severity_level }}
                </v-chip>
              </div>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Patient Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-account</v-icon>
                Patient Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-account-circle</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Full Name</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.enrollee?.first_name }} {{ referral.enrollee?.last_name }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-card-account-details</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Enrollee ID</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.enrollee?.enrollee_id }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-phone</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Phone Number</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.enrollee?.phone_number || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-email</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Email</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.enrollee?.email || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Facility Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-hospital-building</v-icon>
                Facility Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title class="bg-blue-lighten-5 text-subtitle-1">
                  <v-icon left color="blue">mdi-hospital-marker</v-icon>
                  Referring Facility
                </v-card-title>
                <v-card-text>
                  <div class="font-weight-bold text-body-1 mb-1">{{ referral.referring_facility?.name }}</div>
                  <div class="text-caption text-grey">{{ referral.referring_facility?.type }} Facility</div>
                  <div class="text-caption text-grey mt-1">{{ referral.referring_facility?.address }}</div>
                </v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title class="bg-green-lighten-5 text-subtitle-1">
                  <v-icon left color="green">mdi-hospital</v-icon>
                  Receiving Facility
                </v-card-title>
                <v-card-text>
                  <div class="font-weight-bold text-body-1 mb-1">{{ referral.receiving_facility?.name }}</div>
                  <div class="text-caption text-grey">{{ referral.receiving_facility?.type }} Facility</div>
                  <div class="text-caption text-grey mt-1">{{ referral.receiving_facility?.address }}</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Clinical Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-stethoscope</v-icon>
                Clinical Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Presenting Complaints</v-card-subtitle>
                <v-card-text>{{ referral.presenting_complains || 'N/A' }}</v-card-text>
              </v-card>
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Preliminary Diagnosis</v-card-subtitle>
                <v-card-text>{{ referral.preliminary_diagnosis || 'N/A' }}</v-card-text>
              </v-card>
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Examination Findings</v-card-subtitle>
                <v-card-text>{{ referral.examination_findings || 'N/A' }}</v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Reasons for Referral</v-card-subtitle>
                <v-card-text>{{ referral.reasons_for_referral || 'N/A' }}</v-card-text>
              </v-card>
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Treatments Given</v-card-subtitle>
                <v-card-text>{{ referral.treatments_given || 'N/A' }}</v-card-text>
              </v-card>
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Investigations Done</v-card-subtitle>
                <v-card-text>{{ referral.investigations_done || 'N/A' }}</v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Service Selection -->
          <v-row class="pa-4" v-if="referral.service_selection_type">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-medical-bag</v-icon>
                Service Selection
              </h3>
            </v-col>
            <v-col cols="12">
              <v-alert
                :type="referral.service_selection_type === 'bundle' ? 'info' : 'success'"
                variant="tonal"
                density="compact"
              >
                <div class="d-flex align-center">
                  <v-icon left>{{ referral.service_selection_type === 'bundle' ? 'mdi-package-variant' : 'mdi-medical-bag' }}</v-icon>
                  <div>
                    <div class="font-weight-bold">
                      {{ referral.service_selection_type === 'bundle' ? 'Bundle Service Selected' : 'Direct Service Selected' }}
                    </div>
                    <div v-if="referral.service_bundle" class="mt-2">
                      <div class="text-subtitle-2">{{ referral.service_bundle.description || referral.service_bundle.name }}</div>
                      <div class="text-caption">Code: {{ referral.service_bundle.code }} | Price: ₦{{ Number(referral.service_bundle.fixed_price).toLocaleString() }}</div>
                      <div class="text-caption" v-if="referral.service_bundle.diagnosis_icd10">ICD-10: {{ referral.service_bundle.diagnosis_icd10 }}</div>
                    </div>
                    <div v-if="referral.service_selection_type === 'direct'" class="mt-2">
                      <div v-if="referral.case_records?.length">
                        <div class="text-subtitle-2 mb-1">Selected Services</div>
                        <div class="d-flex flex-wrap">
                          <v-chip
                            v-for="service in referral.case_records"
                            :key="service.id"
                            class="mr-1 mb-1"
                            variant="outlined"
                            size="small"
                          >
                            {{ service.case_name || service.service_description }} ({{ service.nicare_code }})
                          </v-chip>
                        </div>
                      </div>
                      <div v-else-if="referral.case_record_ids?.length" class="text-caption">
                        {{ referral.case_record_ids.length }} service(s) selected
                      </div>
                      <div v-else class="text-caption">No direct services found</div>
                    </div>
                  </div>
                </div>
              </v-alert>
            </v-col>
          </v-row>

          <v-divider v-if="referral.service_selection_type"></v-divider>

          <!-- Referring Person Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-doctor</v-icon>
                Referring Person
              </h3>
            </v-col>
            <v-col cols="12" md="4">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-account-tie</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Name</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.referring_person_name || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="4">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-briefcase</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Specialisation</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.referring_person_specialisation || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="4">
              <v-list density="compact">
                <v-list-item>
                  <template v-slot:prepend>
                    <v-icon color="grey">mdi-badge-account</v-icon>
                  </template>
                  <v-list-item-title class="font-weight-medium">Cadre</v-list-item-title>
                  <v-list-item-subtitle>{{ referral.referring_person_cadre || 'N/A' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Additional Information -->
          <v-row class="pa-4">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-information</v-icon>
                Additional Information
              </h3>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Medical History</v-card-subtitle>
                <v-card-text>{{ referral.medical_history || 'N/A' }}</v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" md="6">
              <v-card variant="outlined" class="mb-3">
                <v-card-subtitle class="font-weight-bold">Medication History</v-card-subtitle>
                <v-card-text>{{ referral.medication_history || 'N/A' }}</v-card-text>
              </v-card>
            </v-col>
          </v-row>

          <v-divider></v-divider>

          <!-- Feedback Records -->
          <v-row class="pa-4" v-if="referral.feedback_records && referral.feedback_records.length > 0">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-comment-text-multiple</v-icon>
                Feedback Records
                <v-chip size="small" class="ml-2" color="primary">{{ referral.feedback_records.length }}</v-chip>
              </h3>
            </v-col>
            <v-col cols="12">
              <v-timeline side="end" density="compact">
                <v-timeline-item
                  v-for="feedback in referral.feedback_records"
                  :key="feedback.id"
                  dot-color="primary"
                  size="small"
                >
                  <template v-slot:opposite>
                    <div class="text-caption font-weight-bold">
                      <v-icon size="small" color="grey">mdi-calendar</v-icon>
                      {{ formatDate(feedback.feedback_date || feedback.created_at) }}
                    </div>
                  </template>
                  <v-card variant="outlined">
                    <v-card-title class="text-subtitle-1 d-flex align-center">
                      <span class="text-caption text-grey">{{ feedback.feedback_code }}</span>
                      <v-spacer></v-spacer>
                      <v-chip v-if="feedback.is_system_generated" size="x-small" variant="outlined">
                        <v-icon start size="x-small">mdi-robot</v-icon>
                        System
                      </v-chip>
                    </v-card-title>
                    <v-card-subtitle class="pb-0">
                      <div class="d-flex align-center text-caption mb-1">
                        <v-icon size="small" class="mr-1">mdi-clock-outline</v-icon>
                        <span class="font-weight-bold">Submitted:</span>
                        <span class="ml-1">{{ formatDate(feedback.feedback_date || feedback.created_at) }}</span>
                      </div>
                      <div v-if="feedback.creator" class="d-flex align-center text-caption">
                        <v-icon size="small" class="mr-1">mdi-account</v-icon>
                        <span class="font-weight-bold">Created by:</span>
                        <span class="ml-1">{{ feedback.creator.name }}</span>
                      </div>
                    </v-card-subtitle>
                    <v-card-text>
                      <div v-if="feedback.feedback_type" class="mb-2">
                        <v-chip size="small" variant="tonal" color="blue">
                          <v-icon start size="small">mdi-tag</v-icon>
                          {{ feedback.feedback_type }}
                        </v-chip>
                        <v-chip v-if="feedback.event_type" size="small" variant="tonal" color="purple" class="ml-1">
                          <v-icon start size="small">mdi-calendar-clock</v-icon>
                          {{ feedback.event_type }}
                        </v-chip>
                      </div>
                      <div v-if="feedback.feedback_comments" class="mb-2">
                        <div class="text-caption font-weight-bold text-grey-darken-1">Comments:</div>
                        <div class="text-body-2">{{ feedback.feedback_comments }}</div>
                      </div>
                      <div v-if="feedback.officer_observations" class="mb-2">
                        <div class="text-caption font-weight-bold text-grey-darken-1">Officer Observations:</div>
                        <div class="text-body-2">{{ feedback.officer_observations }}</div>
                      </div>
                      <div v-if="feedback.referral_status_before || feedback.referral_status_after" class="mt-2">
                        <v-chip size="x-small" variant="outlined" class="mr-1">
                          Before: {{ feedback.referral_status_before || 'N/A' }}
                        </v-chip>
                        <v-icon size="small">mdi-arrow-right</v-icon>
                        <v-chip size="x-small" variant="outlined" class="ml-1">
                          After: {{ feedback.referral_status_after || 'N/A' }}
                        </v-chip>
                      </div>
                    </v-card-text>
                  </v-card>
                </v-timeline-item>
              </v-timeline>
            </v-col>
          </v-row>

          <v-divider v-if="referral.feedback_records && referral.feedback_records.length > 0"></v-divider>

          <!-- Documents -->
          <v-row class="pa-4" v-if="referral.documents && referral.documents.length > 0">
            <v-col cols="12">
              <h3 class="text-h6 mb-3">
                <v-icon color="primary" class="mr-2">mdi-file-document-multiple</v-icon>
                Uploaded Documents
                <v-chip size="small" class="ml-2" color="primary">{{ referral.documents.length }}</v-chip>
              </h3>
            </v-col>
            <v-col cols="12">
              <v-row>
                <v-col
                  v-for="doc in referral.documents"
                  :key="doc.id"
                  cols="12"
                  md="6"
                  lg="4"
                >
                  <v-card variant="outlined" class="h-100">
                    <v-card-title class="text-subtitle-2 d-flex align-center bg-grey-lighten-4">
                      <v-icon :color="getFileTypeColor(doc.file_type)" class="mr-2">
                        {{ getFileTypeIcon(doc.file_type) }}
                      </v-icon>
                      <span class="text-truncate">{{ doc.file_name }}</span>
                    </v-card-title>
                    <v-card-text>
                      <div class="mb-2">
                        <div class="text-caption text-grey">Document Type</div>
                        <v-chip size="small" variant="tonal" color="indigo">{{ doc.document_type }}</v-chip>
                      </div>
                      <div v-if="doc.document_requirement" class="mb-2">
                        <div class="text-caption text-grey">Requirement</div>
                        <div class="text-body-2">{{ doc.document_requirement.name }}</div>
                      </div>
                      <div class="mb-2">
                        <div class="text-caption text-grey">File Size</div>
                        <div class="text-body-2">{{ doc.file_size_human }}</div>
                      </div>
                      <div v-if="doc.uploader" class="mb-2">
                        <div class="text-caption text-grey">Uploaded By</div>
                        <div class="text-body-2">{{ doc.uploader.name }}</div>
                      </div>
                      <div class="mb-2">
                        <div class="text-caption text-grey">Upload Date</div>
                        <div class="text-body-2">{{ formatDate(doc.created_at) }}</div>
                      </div>
                      <div v-if="doc.is_required" class="d-flex align-center">
                        <v-chip
                          size="small"
                          variant="outlined"
                          color="error"
                        >
                          Required
                        </v-chip>
                      </div>
                    </v-card-text>
                    <v-card-actions>
                      <v-btn
                        :href="doc.url"
                        target="_blank"
                        size="small"
                        variant="tonal"
                        color="primary"
                        prepend-icon="mdi-download"
                      >
                        Download
                      </v-btn>
                      <v-btn
                        :href="doc.url"
                        target="_blank"
                        size="small"
                        variant="text"
                        color="primary"
                        prepend-icon="mdi-eye"
                      >
                        View
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </v-col>
              </v-row>
            </v-col>
          </v-row>

          <v-divider v-if="referral.documents && referral.documents.length > 0"></v-divider>

          <!-- Timestamps -->
          <v-row class="pa-4 bg-grey-lighten-5">
            <v-col cols="12" md="4">
              <div class="text-caption text-grey">Request Date</div>
              <div class="font-weight-medium">{{ formatDate(referral.request_date) }}</div>
            </v-col>
            <v-col cols="12" md="4">
              <div class="text-caption text-grey">Created At</div>
              <div class="font-weight-medium">{{ formatDate(referral.created_at) }}</div>
            </v-col>
            <v-col cols="12" md="4">
              <div class="text-caption text-grey">Last Updated</div>
              <div class="font-weight-medium">{{ formatDate(referral.updated_at) }}</div>
            </v-col>
          </v-row>
        </v-container>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-btn
          color="purple"
          variant="elevated"
          @click="emit('print-slip')"
          prepend-icon="mdi-printer"
        >
          Print Slip
        </v-btn>
        <v-spacer></v-spacer>
        <slot name="actions"></slot>
        <v-btn variant="outlined" @click="handleClose">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '../../stores/auth';
import { useRouter } from 'vue-router';

const authStore = useAuthStore();
const router = useRouter();

const props = defineProps({
  modelValue: Boolean,
  referral: Object,
});

const emit = defineEmits(['update:modelValue', 'print-slip']);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

// Check if user has permission to create feedback
const canCreateFeedback = computed(() => {
  return authStore.hasPermission('feedback.create');
});

const handleClose = () => {
  isOpen.value = false;
};

const openCreateFeedback = () => {
  // Close the modal
  handleClose();

  // Navigate to feedback creation page with the referral pre-selected
  router.push({
    path: '/feedback/create',
    query: { referral_id: props.referral?.id }
  });
};

const getStatusColor = (status) => {
  const colors = {
    PENDING: 'orange',
    APPROVED: 'green',
    REJECTED: 'red',
    UTN_VALIDATED: 'blue',
  };
  return colors[status] || 'grey';
};

const getStatusIcon = (status) => {
  const icons = {
    PENDING: 'mdi-clock-outline',
    APPROVED: 'mdi-check-circle',
    REJECTED: 'mdi-close-circle',
    UTN_VALIDATED: 'mdi-check-decagram',
  };
  return icons[status] || 'mdi-information';
};

const getSeverityColor = (severity) => {
  const colors = {
    CRITICAL: 'red',
    HIGH: 'orange',
    MEDIUM: 'amber',
    LOW: 'green',
  };
  return colors[severity] || 'grey';
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getFileTypeIcon = (fileType) => {
  const icons = {
    pdf: 'mdi-file-pdf-box',
    doc: 'mdi-file-word',
    docx: 'mdi-file-word',
    xls: 'mdi-file-excel',
    xlsx: 'mdi-file-excel',
    jpg: 'mdi-file-image',
    jpeg: 'mdi-file-image',
    png: 'mdi-file-image',
    gif: 'mdi-file-image',
    txt: 'mdi-file-document',
    zip: 'mdi-folder-zip',
    rar: 'mdi-folder-zip',
  };
  return icons[fileType?.toLowerCase()] || 'mdi-file';
};

const getFileTypeColor = (fileType) => {
  const colors = {
    pdf: 'red',
    doc: 'blue',
    docx: 'blue',
    xls: 'green',
    xlsx: 'green',
    jpg: 'purple',
    jpeg: 'purple',
    png: 'purple',
    gif: 'purple',
    txt: 'grey',
    zip: 'orange',
    rar: 'orange',
  };
  return colors[fileType?.toLowerCase()] || 'grey';
};
</script>

<style scoped>
.detail-item {
  padding: 8px 0;
}
</style>

