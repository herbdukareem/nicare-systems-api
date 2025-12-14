<template>
  <v-dialog v-model="isOpen" max-width="1200" scrollable @update:model-value="handleClose">
    <v-card v-if="referral">
      <v-card-title class="bg-primary text-white d-flex align-center pa-4">
        <v-icon left class="mr-2">mdi-file-document-check</v-icon>
        <span class="text-h6">Referral Details</span>
        <v-spacer></v-spacer>
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
          @click="$emit('print-slip')"
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

const props = defineProps({
  modelValue: Boolean,
  referral: Object,
});

const emit = defineEmits(['update:modelValue', 'print-slip']);

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
});

const handleClose = () => {
  isOpen.value = false;
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
</script>

<style scoped>
.detail-item {
  padding: 8px 0;
}
</style>

