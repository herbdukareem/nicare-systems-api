<template>
  <AdminLayout>
    <div class="do-assigned-referrals-page">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-6">
          <v-col cols="12">
            <div class="page-header">
              <div class="header-content">
                <div class="header-icon">
                  <v-icon size="40" color="primary">mdi-file-document-multiple</v-icon>
                </div>
                <div class="header-text">
                  <h1 class="text-h4 font-weight-bold mb-1">Assigned Facilities Referrals</h1>
                  <p class="text-subtitle-1 text-grey">View and manage referrals for your assigned facilities</p>
                </div>
              </div>
              <div class="header-stats">
                <v-chip color="orange" text-color="white" class="mr-2">
                  <v-icon left small>mdi-clock-outline</v-icon>
                  {{ pendingCount }} Pending
                </v-chip>
                <v-chip color="green" text-color="white" class="mr-2">
                  <v-icon left small>mdi-check-circle</v-icon>
                  {{ approvedCount }} Approved
                </v-chip>
                <v-chip color="red" text-color="white">
                  <v-icon left small>mdi-close-circle</v-icon>
                  {{ rejectedCount }} Rejected
                </v-chip>
              </div>
            </div>
          </v-col>
        </v-row>

        <!-- Filter Card -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-card class="filter-card" elevation="2">
              <v-card-text>
                <v-row align="center">
                  <v-col cols="12" md="3">
                    <v-text-field
                      v-model="searchQuery"
                      label="Search Referrals"
                      placeholder="Code, UTN, Patient Name..."
                      variant="outlined"
                      density="comfortable"
                      prepend-inner-icon="mdi-magnify"
                      clearable
                      hide-details
                    />
                  </v-col>
                  <v-col cols="12" md="2">
                    <v-select
                      v-model="directionFilter"
                      label="Direction"
                      :items="directionOptions"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      hide-details
                      @update:model-value="fetchReferrals"
                    />
                  </v-col>
                  <v-col cols="12" md="2">
                    <v-select
                      v-model="statusFilter"
                      label="Status"
                      :items="statusOptions"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      hide-details
                      @update:model-value="fetchReferrals"
                    />
                  </v-col>
                  <v-col cols="12" md="2">
                    <v-select
                      v-model="severityFilter"
                      label="Severity"
                      :items="severityOptions"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      hide-details
                      @update:model-value="fetchReferrals"
                    />
                  </v-col>
                  <v-col cols="12" md="3" class="text-right">
                    <v-btn
                      color="grey-lighten-1"
                      variant="outlined"
                      @click="resetFilters"
                      class="mr-2"
                    >
                      <v-icon left>mdi-refresh</v-icon>
                      Reset
                    </v-btn>
                    <v-btn
                      color="primary"
                      @click="fetchReferrals"
                      :loading="loading"
                    >
                      <v-icon left>mdi-magnify</v-icon>
                      Search
                    </v-btn>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Referrals Table -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-grey-lighten-4">
                <v-icon start>mdi-table</v-icon>
                Referrals ({{ filteredReferrals.length }})
              </v-card-title>
              <v-card-text>
                <v-data-table
                  :headers="headers"
                  :items="filteredReferrals"
                  :loading="loading"
                  class="elevation-0"
                  item-value="id"
                >
                  <template v-slot:item.referral_code="{ item }">
                    <v-chip color="primary" size="small">
                      {{ item.referral_code }}
                    </v-chip>
                  </template>

                  <template v-slot:item.utn="{ item }">
                    <v-chip color="indigo" size="small" variant="outlined">
                      {{ item.utn || 'N/A' }}
                    </v-chip>
                  </template>

                  <template v-slot:item.patient_name="{ item }">
                    <div>
                      <div class="font-weight-medium">{{ item.enrollee?.first_name }} {{ item.enrollee?.last_name }}</div>
                      <div class="text-caption text-grey">{{ item.enrollee?.nicare_number || item.enrollee?.enrollee_id }}</div>
                    </div>
                  </template>

                  <template v-slot:item.direction="{ item }">
                    <v-chip
                      :color="getDirectionColor(item)"
                      size="small"
                    >
                      <v-icon start size="small">{{ getDirectionIcon(item) }}</v-icon>
                      {{ getDirectionText(item) }}
                    </v-chip>
                  </template>

                  <template v-slot:item.status="{ item }">
                    <v-chip
                      :color="getStatusColor(item.status)"
                      size="small"
                    >
                      <v-icon start size="small">{{ getStatusIcon(item.status) }}</v-icon>
                      {{ item.status }}
                    </v-chip>
                  </template>

                  <template v-slot:item.severity_level="{ item }">
                    <v-chip
                      :color="getSeverityColor(item.severity_level)"
                      size="small"
                    >
                      {{ item.severity_level }}
                    </v-chip>
                  </template>

                  <template v-slot:item.request_date="{ item }">
                    {{ formatDate(item.request_date || item.created_at) }}
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-tooltip text="View Details" location="top">
                      <template v-slot:activator="{ props }">
                        <v-btn
                          v-bind="props"
                          icon
                          size="small"
                          variant="text"
                          color="primary"
                          @click="viewDetails(item)"
                        >
                          <v-icon>mdi-eye</v-icon>
                        </v-btn>
                      </template>
                    </v-tooltip>

                    <v-tooltip text="Print Slip" location="top">
                      <template v-slot:activator="{ props }">
                        <v-btn
                          v-bind="props"
                          icon
                          size="small"
                          variant="text"
                          color="purple"
                          @click="printReferralSlip(item)"
                        >
                          <v-icon>mdi-printer</v-icon>
                        </v-btn>
                      </template>
                    </v-tooltip>
                  </template>

                  <template v-slot:no-data>
                    <div class="text-center py-8">
                      <v-icon size="64" color="grey-lighten-2">mdi-file-document-alert-outline</v-icon>
                      <p class="text-h6 text-grey mt-4">No referrals found</p>
                      <p class="text-body-2 text-grey">Referrals for your assigned facilities will appear here</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Referral Details Dialog -->
      <v-dialog v-model="detailsDialog" max-width="1200" scrollable>
        <v-card>
          <v-card-title class="bg-primary text-white d-flex align-center pa-4">
            <v-icon left class="mr-2">mdi-file-document-check</v-icon>
            <span class="text-h6">Referral Details</span>
            <v-spacer></v-spacer>
            <v-btn icon variant="text" @click="detailsDialog = false" color="white">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </v-card-title>

          <v-card-text class="pa-0">
            <v-container fluid v-if="selectedReferral">
              <!-- Header Info -->
              <v-row class="bg-grey-lighten-4 pa-4">
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Referral Code</div>
                    <div class="text-h6 font-weight-bold">{{ selectedReferral.referral_code }}</div>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">UTN</div>
                    <v-chip color="indigo" variant="flat" class="mt-1">{{ selectedReferral.utn }}</v-chip>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Status</div>
                    <v-chip :color="getStatusColor(selectedReferral.status)" variant="flat" class="mt-1">
                      <v-icon left size="small">{{ getStatusIcon(selectedReferral.status) }}</v-icon>
                      {{ selectedReferral.status }}
                    </v-chip>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Severity Level</div>
                    <v-chip :color="getSeverityColor(selectedReferral.severity_level)" variant="flat" class="mt-1">
                      {{ selectedReferral.severity_level }}
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
                      <v-list-item-subtitle>{{ selectedReferral.enrollee?.first_name }} {{ selectedReferral.enrollee?.last_name }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-card-account-details</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Enrollee ID</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedReferral.enrollee?.enrollee_id }}</v-list-item-subtitle>
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
                      <v-list-item-subtitle>{{ selectedReferral.enrollee?.phone_number || 'N/A' }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-email</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Email</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedReferral.enrollee?.email || 'N/A' }}</v-list-item-subtitle>
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
                      <div class="font-weight-bold text-body-1 mb-1">{{ selectedReferral.referring_facility?.name }}</div>
                      <div class="text-caption text-grey">{{ selectedReferral.referring_facility?.type }} Facility</div>
                      <div class="text-caption text-grey mt-1">{{ selectedReferral.referring_facility?.address }}</div>
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
                      <div class="font-weight-bold text-body-1 mb-1">{{ selectedReferral.receiving_facility?.name }}</div>
                      <div class="text-caption text-grey">{{ selectedReferral.receiving_facility?.type }} Facility</div>
                      <div class="text-caption text-grey mt-1">{{ selectedReferral.receiving_facility?.address }}</div>
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
                    <v-card-text>{{ selectedReferral.presenting_complains || 'N/A' }}</v-card-text>
                  </v-card>
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Preliminary Diagnosis</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.preliminary_diagnosis || 'N/A' }}</v-card-text>
                  </v-card>
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Examination Findings</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.examination_findings || 'N/A' }}</v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6">
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Reasons for Referral</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.reasons_for_referral || 'N/A' }}</v-card-text>
                  </v-card>
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Treatments Given</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.treatments_given || 'N/A' }}</v-card-text>
                  </v-card>
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Investigations Done</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.investigations_done || 'N/A' }}</v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Service Selection -->
              <v-row class="pa-4" v-if="selectedReferral.service_selection_type">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-medical-bag</v-icon>
                    Service Selection
                  </h3>
                </v-col>
                <v-col cols="12">
                  <v-alert
                    :type="selectedReferral.service_selection_type === 'bundle' ? 'info' : 'success'"
                    variant="tonal"
                    density="compact"
                  >
                    <div class="d-flex align-center">
                      <v-icon left>{{ selectedReferral.service_selection_type === 'bundle' ? 'mdi-package-variant' : 'mdi-medical-bag' }}</v-icon>
                      <div>
                        <div class="font-weight-bold">
                          {{ selectedReferral.service_selection_type === 'bundle' ? 'Bundle Service Selected' : 'Direct Service Selected' }}
                        </div>
                        <div v-if="selectedReferral.service_bundle" class="mt-2">
                          <div class="text-subtitle-2">{{ selectedReferral.service_bundle.description || selectedReferral.service_bundle.name }}</div>
                          <div class="text-caption">Code: {{ selectedReferral.service_bundle.code }} | Price: ₦{{ Number(selectedReferral.service_bundle.fixed_price).toLocaleString() }}</div>
                          <div class="text-caption" v-if="selectedReferral.service_bundle.diagnosis_icd10">ICD-10: {{ selectedReferral.service_bundle.diagnosis_icd10 }}</div>
                        </div>
                        <div v-if="selectedReferral.case_record" class="mt-2">
                          <div class="text-subtitle-2">{{ selectedReferral.case_record.case_name }}</div>
                          <div class="text-caption">NiCare Code: {{ selectedReferral.case_record.nicare_code }}</div>
                        </div>
                      </div>
                    </div>
                  </v-alert>
                </v-col>
              </v-row>

              <v-divider v-if="selectedReferral.service_selection_type"></v-divider>

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
                      <v-list-item-subtitle>{{ selectedReferral.referring_person_name || 'N/A' }}</v-list-item-subtitle>
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
                      <v-list-item-subtitle>{{ selectedReferral.referring_person_specialisation || 'N/A' }}</v-list-item-subtitle>
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
                      <v-list-item-subtitle>{{ selectedReferral.referring_person_cadre || 'N/A' }}</v-list-item-subtitle>
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
                    <v-card-text>{{ selectedReferral.medical_history || 'N/A' }}</v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="6">
                  <v-card variant="outlined" class="mb-3">
                    <v-card-subtitle class="font-weight-bold">Medication History</v-card-subtitle>
                    <v-card-text>{{ selectedReferral.medication_history || 'N/A' }}</v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Timestamps -->
              <v-row class="pa-4 bg-grey-lighten-5">
                <v-col cols="12" md="4">
                  <div class="text-caption text-grey">Request Date</div>
                  <div class="font-weight-medium">{{ formatDate(selectedReferral.request_date) }}</div>
                </v-col>
                <v-col cols="12" md="4">
                  <div class="text-caption text-grey">Created At</div>
                  <div class="font-weight-medium">{{ formatDate(selectedReferral.created_at) }}</div>
                </v-col>
                <v-col cols="12" md="4">
                  <div class="text-caption text-grey">Last Updated</div>
                  <div class="font-weight-medium">{{ formatDate(selectedReferral.updated_at) }}</div>
                </v-col>
              </v-row>
            </v-container>
          </v-card-text>

          <v-divider></v-divider>
          <v-card-actions class="pa-4">
            <v-btn
              color="purple"
              variant="elevated"
              @click="printReferralSlip(selectedReferral)"
            >
              <v-icon left>mdi-printer</v-icon>
              Print Slip
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn variant="outlined" @click="detailsDialog = false">Close</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { doDashboardAPI } from '../../utils/api';
import api from '../../utils/api';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../stores/auth';
import AdminLayout from '../layout/AdminLayout.vue';

const { success: showSuccess, error: showError } = useToast();
const authStore = useAuthStore();

// State
const loading = ref(false);
const referrals = ref([]);
const searchQuery = ref('');
const directionFilter = ref(null);
const statusFilter = ref(null);
const severityFilter = ref(null);
const detailsDialog = ref(false);
const selectedReferral = ref(null);

// Filter options
const directionOptions = [
  { title: 'Referred (Outgoing)', value: 'referred' },
  { title: 'Received (Incoming)', value: 'received' },
];

const statusOptions = [
  { title: 'Pending', value: 'PENDING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
];

const severityOptions = [
  { title: 'Emergency', value: 'EMERGENCY' },
  { title: 'Urgent', value: 'URGENT' },
  { title: 'Routine', value: 'ROUTINE' },
];

// Table headers
const headers = [
  { title: 'Referral Code', value: 'referral_code', key: 'referral_code' },
  { title: 'UTN', value: 'utn', key: 'utn' },
  { title: 'Patient Name', value: 'patient_name', key: 'patient_name' },
  { title: 'Direction', value: 'direction', key: 'direction' },
  { title: 'Status', value: 'status', key: 'status' },
  { title: 'Severity', value: 'severity_level', key: 'severity_level' },
  { title: 'Request Date', value: 'request_date', key: 'request_date' },
  { title: 'Actions', value: 'actions', key: 'actions', sortable: false },
];

// Computed counts
const pendingCount = computed(() => referrals.value.filter(r => r.status === 'PENDING').length);
const approvedCount = computed(() => referrals.value.filter(r => r.status === 'APPROVED').length);
const rejectedCount = computed(() => referrals.value.filter(r => r.status === 'REJECTED').length);

// Filtered referrals
const filteredReferrals = computed(() => {
  return referrals.value.filter(referral => {
    const matchesSearch = !searchQuery.value ||
      referral.referral_code?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      referral.utn?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      referral.enrollee?.first_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      referral.enrollee?.last_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      referral.enrollee?.nicare_number?.toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchesStatus = !statusFilter.value || referral.status === statusFilter.value;
    const matchesSeverity = !severityFilter.value || referral.severity_level === severityFilter.value;

    // Direction filter
    let matchesDirection = true;
    if (directionFilter.value === 'referred') {
      // Referrals where one of my assigned facilities is the referring facility
      matchesDirection = authStore.user?.assigned_facilities?.some(
        f => f.id === referral.referring_facility_id
      );
    } else if (directionFilter.value === 'received') {
      // Referrals where one of my assigned facilities is the receiving facility
      matchesDirection = authStore.user?.assigned_facilities?.some(
        f => f.id === referral.receiving_facility_id
      );
    }

    return matchesSearch && matchesStatus && matchesSeverity && matchesDirection;
  });
});

// Fetch referrals
const fetchReferrals = async () => {
  loading.value = true;
  try {
    const response = await doDashboardAPI.getReferrals({
      utn_validated: true,
      per_page: 100, // Get more records to avoid pagination issues
    });

    // Handle paginated response
    const data = response.data?.data;
    if (data && Array.isArray(data.data)) {
      // Paginated response
      referrals.value = data.data;
    } else if (Array.isArray(data)) {
      // Direct array response
      referrals.value = data;
    } else {
      referrals.value = [];
    }
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch referrals');
    console.error(err);
  } finally {
    loading.value = false;
  }
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  directionFilter.value = null;
  statusFilter.value = null;
  severityFilter.value = null;
};

// View details
const viewDetails = async (referral) => {
  loading.value = true;
  try {
    // Fetch full referral details with relationships
    const response = await api.get(`/referrals/${referral.id}`);
    selectedReferral.value = response.data.data || response.data;
    detailsDialog.value = true;
  } catch (err) {
    showError('Failed to load referral details');
  } finally {
    loading.value = false;
  }
};

// Get direction text
const getDirectionText = (referral) => {
  const assignedFacilityIds = authStore.user?.assigned_facilities?.map(f => f.id) || [];

  if (assignedFacilityIds.includes(referral.referring_facility_id)) {
    return 'Referred';
  } else if (assignedFacilityIds.includes(referral.receiving_facility_id)) {
    return 'Received';
  }
  return 'N/A';
};

// Get direction color
const getDirectionColor = (referral) => {
  const direction = getDirectionText(referral);
  return direction === 'Referred' ? 'blue' : direction === 'Received' ? 'green' : 'grey';
};

// Get direction icon
const getDirectionIcon = (referral) => {
  const direction = getDirectionText(referral);
  return direction === 'Referred' ? 'mdi-arrow-right' : direction === 'Received' ? 'mdi-arrow-left' : 'mdi-help';
};

// Get status color
const getStatusColor = (status) => {
  const colors = {
    PENDING: 'orange',
    APPROVED: 'green',
    REJECTED: 'red',
  };
  return colors[status] || 'grey';
};

// Get status icon
const getStatusIcon = (status) => {
  const icons = {
    PENDING: 'mdi-clock-outline',
    APPROVED: 'mdi-check-circle',
    REJECTED: 'mdi-close-circle',
  };
  return icons[status] || 'mdi-help-circle';
};

// Get severity color
const getSeverityColor = (severity) => {
  const colors = {
    EMERGENCY: 'red',
    URGENT: 'orange',
    ROUTINE: 'blue',
  };
  return colors[severity] || 'grey';
};

// Format date
const formatDate = (date) => {
  if (!date) return 'N/A';
  try {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch (e) {
    return date;
  }
};

// Print referral slip
const printReferralSlip = (referral) => {
  const printWindow = window.open('', '_blank', 'width=400,height=700,scrollbars=yes');
  const printContent = generatePrintContent(referral);

  printWindow.document.write(printContent);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
  }, 250);
};

// Format date for print slip
const formatDateShort = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

// Generate print content
const generatePrintContent = (ref) => {
  const currentDate = new Date().toLocaleString('en-NG');

  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>Referral Slip - ${ref.referral_code}</title>
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }

        body {
          font-family: 'Courier New', monospace;
          font-size: 12px;
          line-height: 1.4;
          color: #000;
          background: #fff;
          width: 80mm;
          margin: 0 auto;
          padding: 10px;
        }

        .header {
          text-align: center;
          border-bottom: 2px solid #000;
          padding-bottom: 8px;
          margin-bottom: 12px;
        }

        .logo {
          font-weight: bold;
          font-size: 16px;
          margin-bottom: 4px;
        }

        .subtitle {
          font-size: 10px;
          margin-bottom: 2px;
        }

        .section {
          margin-bottom: 12px;
          border-bottom: 1px dashed #666;
          padding-bottom: 8px;
        }

        .section:last-child {
          border-bottom: none;
        }

        .section-title {
          font-weight: bold;
          font-size: 11px;
          margin-bottom: 4px;
          text-transform: uppercase;
        }

        .row {
          display: flex;
          justify-content: space-between;
          margin-bottom: 2px;
          font-size: 10px;
        }

        .label {
          font-weight: bold;
          width: 40%;
        }

        .value {
          width: 58%;
          text-align: right;
        }

        .full-row {
          margin-bottom: 4px;
          font-size: 10px;
        }

        .full-row .label {
          font-weight: bold;
          display: block;
          margin-bottom: 2px;
        }

        .full-row .value {
          display: block;
          text-align: left;
          font-size: 9px;
          padding-left: 8px;
        }

        .status {
          text-align: center;
          font-weight: bold;
          font-size: 14px;
          padding: 4px;
          border: 2px solid #000;
          margin: 8px 0;
        }

        .status.pending { background: #fff3cd; }
        .status.approved { background: #d1edff; }
        .status.denied { background: #f8d7da; }
        .status.rejected { background: #f8d7da; }

        .footer {
          text-align: center;
          font-size: 9px;
          margin-top: 12px;
          padding-top: 8px;
          border-top: 1px solid #666;
        }

        .qr-placeholder {
          text-align: center;
          border: 1px solid #666;
          padding: 8px;
          margin: 8px 0;
          font-size: 10px;
        }

        @media print {
          body { margin: 0; padding: 5px; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo">NGSCHA</div>
        <div class="subtitle">Niger State Contributory Health Agency</div>
        <div class="subtitle">REFERRAL SLIP</div>
      </div>

      <div class="section">
        <div class="row">
          <span class="label">REF CODE:</span>
          <span class="value">${ref.referral_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">UTN:</span>
          <span class="value">${ref.utn || '—'}</span>
        </div>
        <div class="row">
          <span class="label">DATE:</span>
          <span class="value">${formatDateShort(ref.request_date || ref.created_at)}</span>
        </div>
      </div>

      <div class="status ${(ref.status || '').toLowerCase()}">
        STATUS: ${(ref.status || 'PENDING').toUpperCase()}
      </div>

      <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${ref.enrollee?.first_name || ''} ${ref.enrollee?.last_name || ''}</span>
        </div>
        <div class="row">
          <span class="label">Enrollee ID:</span>
          <span class="value">${ref.enrollee?.enrollee_id || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Gender:</span>
          <span class="value">${ref.enrollee?.gender || '—'}</span>
        </div>
        <div class="row">
          <span class="label">DOB:</span>
          <span class="value">${formatDateShort(ref.enrollee?.date_of_birth)}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.enrollee?.phone_number || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">From (Referring)</div>
        <div class="row">
          <span class="label">Facility:</span>
          <span class="value">${ref.referring_facility?.name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${ref.referring_facility?.facility_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.referring_facility?.phone || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">To (Receiving)</div>
        <div class="row">
          <span class="label">Facility:</span>
          <span class="value">${ref.receiving_facility?.name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Code:</span>
          <span class="value">${ref.receiving_facility?.facility_code || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.receiving_facility?.phone || '—'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Clinical Details</div>
        <div class="row">
          <span class="label">Severity:</span>
          <span class="value">${(ref.severity_level || '—').toUpperCase()}</span>
        </div>
        ${ref.reasons_for_referral ? `
          <div class="full-row">
            <span class="label">Reason for Referral:</span>
            <span class="value">${ref.reasons_for_referral}</span>
          </div>
        ` : ''}
        ${ref.preliminary_diagnosis ? `
          <div class="full-row">
            <span class="label">Diagnosis:</span>
            <span class="value">${ref.preliminary_diagnosis}</span>
          </div>
        ` : ''}
        ${ref.presenting_complains ? `
          <div class="full-row">
            <span class="label">Complaints:</span>
            <span class="value">${ref.presenting_complains}</span>
          </div>
        ` : ''}
        ${ref.examination_findings ? `
          <div class="full-row">
            <span class="label">Findings:</span>
            <span class="value">${ref.examination_findings}</span>
          </div>
        ` : ''}
        ${ref.treatments_given ? `
          <div class="full-row">
            <span class="label">Treatments:</span>
            <span class="value">${ref.treatments_given}</span>
          </div>
        ` : ''}
      </div>

      <div class="section">
        <div class="section-title">Referring Personnel</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${ref.referring_person_name || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Specialization:</span>
          <span class="value">${ref.referring_person_specialisation || '—'}</span>
        </div>
        <div class="row">
          <span class="label">Cadre:</span>
          <span class="value">${ref.referring_person_cadre || '—'}</span>
        </div>
      </div>

      ${ref.contact_person_name ? `
      <div class="section">
        <div class="section-title">Contact Person</div>
        <div class="row">
          <span class="label">Name:</span>
          <span class="value">${ref.contact_person_name}</span>
        </div>
        <div class="row">
          <span class="label">Phone:</span>
          <span class="value">${ref.contact_person_phone || '—'}</span>
        </div>
      </div>
      ` : ''}

      <div class="qr-placeholder">
        QR CODE: ${ref.referral_code}
        <br>Scan for verification
      </div>

      <div class="footer">
        <div>Printed: ${currentDate}</div>
        <div>Niger State Contributory Health Agency</div>
        <div>www.ngscha.ng.gov.ng</div>
      </div>
    </body>
    </html>
  `;
};

// Lifecycle
onMounted(() => {
  fetchReferrals();
});
</script>

<style scoped>
.do-assigned-referrals-page {
  min-height: 100vh;
  background-color: #f5f5f5;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px;
  border-radius: 12px;
  color: var(--primary);
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  background: rgba(255, 255, 255, 0.2);
  padding: 12px;
  border-radius: 12px;
}

.header-text h1 {
  color: var(--primary);
}

.header-text p {
  color: rgba(255, 255, 255, 0.9);
}

.header-stats {
  display: flex;
  gap: 8px;
}

.filter-card {
  border-radius: 12px;
}

.data-table-card {
  border-radius: 12px;
  overflow: hidden;
}

.modern-table {
  background: white;
}

.patient-info,
.facility-info,
.date-info {
  line-height: 1.4;
}

.action-buttons {
  display: flex;
  gap: 4px;
}

.info-item {
  margin-bottom: 12px;
}

.info-label {
  font-size: 0.75rem;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  font-size: 0.95rem;
  color: #000;
  font-weight: 500;
}
</style>

