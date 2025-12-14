<template>
  <AdminLayout>
    <div class="facility-pa-code-management-page">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-4">
          <v-col cols="12">
            <div class="d-flex justify-space-between align-center">
              <div>
                <h1 class="text-h4 font-weight-bold">
                  <v-icon size="32" color="primary" class="mr-2">mdi-shield-check</v-icon>
                  FU-PA Code Management
                </h1>
                <p class="text-subtitle-1 text-grey mt-2">
                  View and manage your Follow-Up PA code requests
                </p>
              </div>
              <v-btn
                color="primary"
                size="large"
                prepend-icon="mdi-plus"
                @click="openRequestDialog"
              >
                New FU-PA Request
              </v-btn>
            </div>
          </v-col>
        </v-row>

        <!-- Stats Cards -->
        <v-row class="mb-4">
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Total Requests</p>
                    <h3 class="text-h5 font-weight-bold text-primary">{{ stats.total }}</h3>
                  </div>
                  <v-icon size="40" color="primary">mdi-file-document-multiple</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Pending</p>
                    <h3 class="text-h5 font-weight-bold text-warning">{{ stats.pending }}</h3>
                  </div>
                  <v-icon size="40" color="warning">mdi-clock-alert</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Approved</p>
                    <h3 class="text-h5 font-weight-bold text-success">{{ stats.approved }}</h3>
                  </div>
                  <v-icon size="40" color="success">mdi-check-circle</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Rejected</p>
                    <h3 class="text-h5 font-weight-bold text-error">{{ stats.rejected }}</h3>
                  </div>
                  <v-icon size="40" color="error">mdi-close-circle</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Filters -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-text>
                <v-row align="center">
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model="searchQuery"
                      label="Search PA Codes"
                      placeholder="Code, UTN, Patient Name..."
                      variant="outlined"
                      density="comfortable"
                      prepend-inner-icon="mdi-magnify"
                      clearable
                      hide-details
                    />
                  </v-col>
                  <v-col cols="12" md="3">
                    <v-select
                      v-model="statusFilter"
                      label="Filter by Status"
                      :items="statusOptions"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      hide-details
                    />
                  </v-col>
                  <v-col cols="12" md="3">
                    <v-select
                      v-model="typeFilter"
                      label="Filter by Type"
                      :items="typeOptions"
                      variant="outlined"
                      density="comfortable"
                      clearable
                      hide-details
                    />
                  </v-col>
                  <v-col cols="12" md="2">
                    <v-btn
                      color="secondary"
                      variant="outlined"
                      block
                      @click="resetFilters"
                      prepend-icon="mdi-refresh"
                    >
                      Reset
                    </v-btn>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- PA Codes Table -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-grey-lighten-4">
                <v-icon start>mdi-table</v-icon>
                FU-PA Code Requests
              </v-card-title>
              <v-card-text>
                <v-data-table
                  :headers="headers"
                  :items="filteredPACodes"
                  :loading="loading"
                  class="elevation-0"
                  item-value="id"
                >
                  <template v-slot:item.code="{ item }">
                    <v-chip color="primary" size="small">
                      <v-icon start size="small">mdi-shield</v-icon>
                      {{ item.code || 'Pending' }}
                    </v-chip>
                  </template>

                  <template v-slot:item.utn="{ item }">
                    <v-chip color="indigo" size="small" variant="outlined">
                      {{ item.referral?.utn || 'N/A' }}
                    </v-chip>
                  </template>

                  <template v-slot:item.patient_name="{ item }">
                    <div>
                      <div class="font-weight-medium">{{ item.referral?.enrollee?.first_name }} {{ item.referral?.enrollee?.last_name }}</div>
                      <div class="text-caption text-grey">{{ item.referral?.enrollee?.nicare_number || item.referral?.enrollee?.enrollee_id }}</div>
                    </div>
                  </template>

                  <template v-slot:item.type="{ item }">
                    <v-chip
                      :color="item.type === 'FFS_TOP_UP' ? 'orange' : 'blue'"
                      size="small"
                    >
                      {{ item.type === 'FFS_TOP_UP' ? 'FFS Top-Up' : 'Bundle' }}
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

                  <template v-slot:item.created_at="{ item }">
                    {{ formatDate(item.created_at) }}
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-btn
                      icon
                      size="small"
                      color="primary"
                      @click="viewDetails(item)"
                      title="View Details"
                    >
                      <v-icon>mdi-eye</v-icon>
                    </v-btn>
                    <v-btn
                      icon
                      size="small"
                      color="purple"
                      @click="printPASlip(item)"
                      title="Print Slip"
                    >
                      <v-icon>mdi-printer</v-icon>
                    </v-btn>
                  </template>

                  <template v-slot:no-data>
                    <div class="text-center py-8">
                      <v-icon size="64" color="grey-lighten-2">mdi-shield-alert-outline</v-icon>
                      <p class="text-h6 text-grey mt-4">No PA code requests found</p>
                      <p class="text-body-2 text-grey">Click "New FU-PA Request" to create your first request</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- FU-PA Code Details Modal -->
      <FUPACodeDetailsModal
        v-model="detailsDialog"
        :pa-code="selectedPA"
        @print-slip="printPASlip(selectedPA)"
      />

      <!-- FU-PA Request Dialog -->
      <v-dialog v-model="showRequestDialog" max-width="1200px" persistent scrollable>
        <v-card>
          <v-card-title class="bg-primary text-white pa-4">
            <v-icon class="mr-2">mdi-plus-circle</v-icon>
            Request Follow-Up PA Code
          </v-card-title>

          <v-card-text class="pa-6" style="max-height: 70vh;">
            <v-form ref="requestForm">
              <!-- Step 1: Select Referral -->
              <v-card class="mb-4" elevation="2">
                <v-card-title class="bg-grey-lighten-4">
                  <v-chip color="primary" size="small" class="mr-2">1</v-chip>
                  Select Approved Referral
                </v-card-title>
                <v-card-text class="pa-4">
                  <v-autocomplete
                    v-model="selectedReferralId"
                    label="Search and Select Approved Referral (UTN)"
                    :items="approvedReferrals"
                    item-title="display_text"
                    item-value="id"
                    :loading="loadingReferrals"
                    @update:model-value="onReferralSelected"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-magnify"
                    :rules="[v => !!v || 'Referral is required']"
                  />

                  <!-- Claim Check Status -->
                  <v-alert v-if="claimCheckStatus === 'checking'" type="info" variant="outlined" class="mt-2">
                    <v-progress-circular indeterminate size="20" width="2" class="mr-2"></v-progress-circular>
                    Checking if claim exists...
                  </v-alert>

                  <v-alert v-if="claimCheckStatus === 'exists'" type="error" variant="outlined" class="mt-2">
                    <strong>Claim Already Submitted</strong><br>
                    A claim has already been submitted for this referral. You cannot request additional PA codes.
                  </v-alert>

                  <v-alert v-if="claimCheckStatus === 'none'" type="success" variant="outlined" class="mt-2">
                    <v-icon class="mr-2">mdi-check-circle</v-icon>
                    No claim submitted yet. You can proceed with the FU-PA request.
                  </v-alert>
                </v-card-text>
              </v-card>

              <!-- Step 2: Referral Details (shown when referral is selected and no claim exists) -->
              <v-card v-if="selectedReferral && claimCheckStatus === 'none'" class="mb-4" elevation="2">
                <v-card-title class="bg-grey-lighten-4">
                  <v-chip color="primary" size="small" class="mr-2">2</v-chip>
                  Referral & Patient Details
                </v-card-title>
                <v-card-text class="pa-4">
                  <v-row>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedReferral.referral_code"
                        label="Referral Code"
                        variant="outlined"
                        density="comfortable"
                        readonly
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedReferral.utn"
                        label="UTN"
                        variant="outlined"
                        density="comfortable"
                        readonly
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="enrolleeDetails?.full_name || selectedReferral.enrollee_full_name"
                        label="Patient Name"
                        variant="outlined"
                        density="comfortable"
                        readonly
                      />
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="enrolleeDetails?.nicare_number || selectedReferral.nicare_number"
                        label="NiCare Number"
                        variant="outlined"
                        density="comfortable"
                        readonly
                      />
                    </v-col>
                  </v-row>

                  <!-- Admission Status Alert -->
              
                  <v-alert v-if="formData.admission_id" type="success" variant="outlined" class="mt-2">
                    <v-icon class="mr-2">mdi-check-circle</v-icon>
                    Active admission found. You can proceed.
                  </v-alert>
                </v-card-text>
              </v-card>

              <!-- Step 3: Service Selection -->
              <v-card v-if="selectedReferral && claimCheckStatus === 'none' " class="mb-4" elevation="2">
                <v-card-title class="bg-grey-lighten-4">
                  <v-chip color="primary" size="small" class="mr-2">3</v-chip>
                  Service Selection
                </v-card-title>
                <v-card-text class="pa-4">
                  <v-radio-group
                    v-model="formData.service_selection_type"
                    label="Select Service Type"
                    :rules="[v => !!v || 'Service type is required']"
                  >
                    <v-radio label="Bundle Service" value="bundle"></v-radio>
                    <v-radio label="Direct Services (Case Records)" value="direct"></v-radio>
                  </v-radio-group>

                  <!-- Bundle Selection -->
                  <v-autocomplete
                    v-if="formData.service_selection_type === 'bundle'"
                    v-model="formData.service_bundle_id"
                    label="Select Service Bundle"
                    :items="serviceBundles"
                    item-title="display_name"
                    item-value="id"
                    :loading="loadingBundles"
                    variant="outlined"
                    density="comfortable"
                    :rules="[v => !!v || 'Bundle is required']"
                    class="mt-4"
                  />

                  <!-- Direct Services Selection -->
                  <v-autocomplete
                    v-if="formData.service_selection_type === 'direct'"
                    v-model="formData.case_record_ids"
                    label="Select Services (Case Records)"
                    :items="caseRecords"
                    item-title="display_name"
                    item-value="id"
                    :loading="loadingCaseRecords"
                    variant="outlined"
                    density="comfortable"
                    multiple
                    chips
                    :rules="[v => (v && v.length > 0) || 'At least one service is required']"
                    class="mt-4"
                  />
                </v-card-text>
              </v-card>

              <!-- Step 4: Clinical Justification -->
              <v-card v-if="selectedReferral && claimCheckStatus === 'none' " class="mb-4" elevation="2">
                <v-card-title class="bg-grey-lighten-4">
                  <v-chip color="primary" size="small" class="mr-2">4</v-chip>
                  Clinical Justification
                </v-card-title>
                <v-card-text class="pa-4">
                  <v-textarea
                    v-model="formData.clinical_justification"
                    label="Clinical Justification (Required)"
                    variant="outlined"
                    rows="4"
                    :rules="[v => !!v || 'Clinical justification is required']"
                    placeholder="Provide detailed clinical justification for this FU-PA request..."
                  />

                  <v-textarea
                    v-model="formData.diagnosis_update"
                    label="Diagnosis Update (Optional)"
                    variant="outlined"
                    rows="3"
                    placeholder="Any updates to the diagnosis..."
                    class="mt-4"
                  />
                </v-card-text>
              </v-card>

              <!-- Step 5: Supporting Documents -->
              <v-card v-if="selectedReferral && claimCheckStatus === 'none'" class="mb-4" elevation="2">
                <v-card-title class="bg-grey-lighten-4">
                  <v-chip color="primary" size="small" class="mr-2">5</v-chip>
                  Supporting Documents
                </v-card-title>
                <v-card-text class="pa-4">
                  <v-alert type="info" density="compact" class="mb-4">
                    Upload required supporting documents for your FU-PA code request.
                  </v-alert>

                  <v-table v-if="paDocumentRequirements.length > 0" density="compact" class="mb-4">
                    <thead>
                      <tr>
                        <th style="width: 30%">Document Type</th>
                        <th style="width: 35%">Description</th>
                        <th style="width: 10%">Required</th>
                        <th style="width: 25%">Upload</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="requirement in paDocumentRequirements" :key="requirement.id">
                        <td>
                          <strong>{{ requirement.name }}</strong>
                          <v-chip v-if="requirement.is_required" color="error" size="x-small" class="ml-2">Required</v-chip>
                        </td>
                        <td class="text-caption">{{ requirement.description }}</td>
                        <td class="text-center">
                          <v-icon v-if="requirement.is_required" color="error">mdi-asterisk</v-icon>
                          <v-icon v-else color="grey">mdi-minus</v-icon>
                        </td>
                        <td>
                          <v-file-input
                            v-model="uploadedPADocuments[requirement.document_type]"
                            :label="`Upload ${requirement.name}`"
                            variant="outlined"
                            density="compact"
                                    :accept="getAcceptedTypes(requirement.allowed_file_types)"
                            :rules="requirement.is_required ? [v => !!v || `${requirement.name} is required`] : []"
                            prepend-icon="mdi-paperclip"
                            show-size
                            clearable
                            @update:model-value="(file) => handlePADocumentUpload(requirement, file)"
                          >
                            <template v-slot:selection="{ fileNames }">
                              <v-chip size="small" color="success">
                                <v-icon start>mdi-check</v-icon>
                                {{ fileNames[0] }}
                              </v-chip>
                            </template>
                          </v-file-input>
                          <div class="text-caption text-grey mt-1">
                            Max size: {{ requirement.max_file_size_mb }}MB | Allowed: {{ requirement.allowed_file_types }}
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </v-table>

                  <v-alert v-else type="warning" density="compact">
                    No document requirements configured for PA codes.
                  </v-alert>
                </v-card-text>
              </v-card>
            </v-form>
          </v-card-text>

          <v-divider></v-divider>

          <v-card-actions class="pa-4">
            <v-btn
              variant="outlined"
              @click="closeRequestDialog"
              :disabled="submitting"
            >
              Cancel
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn
              color="primary"
              @click="handleSubmitRequest"
              :loading="submitting"
              :disabled="!canSubmitRequest || submitting"
              prepend-icon="mdi-send"
            >
              Submit Request
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../../utils/api';
import { useToast } from '../../composables/useToast';
import AdminLayout from '../layout/AdminLayout.vue';
import FUPACodeDetailsModal from '../modals/FUPACodeDetailsModal.vue';

const router = useRouter();
const { success: showSuccess, error: showError, info: showInfo, warning: showWarning } = useToast();

// State
const loading = ref(false);
const paCodes = ref([]);
const searchQuery = ref('');
const statusFilter = ref(null);
const typeFilter = ref(null);
const detailsDialog = ref(false);
const selectedPA = ref(null);

// Request dialog state
const showRequestDialog = ref(false);
const loadingReferrals = ref(false);
const loadingReferralDetails = ref(false);
const loadingCaseRecords = ref(false);
const loadingBundles = ref(false);
const submitting = ref(false);
const approvedReferrals = ref([]);
const selectedReferralId = ref(null);
const selectedReferral = ref(null);
const enrolleeDetails = ref(null);
const caseRecords = ref([]);
const serviceBundles = ref([]);
const claimCheckStatus = ref(null); // 'checking', 'exists', 'none'
const existingClaim = ref(null);
const requestForm = ref(null);
const paDocumentRequirements = ref([]);
const uploadedPADocuments = ref({});
const loadingPADocuments = ref(false);

// Form data for PA request
const formData = ref({
  referral_id: null,
  enrollee_id: null,
  facility_id: null,
  admission_id: null,
  is_complication_pa: true,
  clinical_justification: '',
  diagnosis_update: '',
  service_selection_type: null,
  service_bundle_id: null,
  case_record_ids: [],
});

// Filter options
const statusOptions = [
  { title: 'Pending', value: 'PENDING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
];

const typeOptions = [
  { title: 'FFS Top-Up', value: 'FFS_TOP_UP' },
  { title: 'Bundle', value: 'BUNDLE' },
];

// Table headers
const headers = [
  { title: 'PA Code', value: 'code', key: 'code' },
  { title: 'UTN', value: 'utn', key: 'utn' },
  { title: 'Patient Name', value: 'patient_name', key: 'patient_name' },
  { title: 'Type', value: 'type', key: 'type' },
  { title: 'Status', value: 'status', key: 'status' },
  { title: 'Requested Date', value: 'created_at', key: 'created_at' },
  { title: 'Actions', value: 'actions', key: 'actions', sortable: false },
];

// Computed stats
const stats = computed(() => {
  const total = paCodes.value.length;
  const pending = paCodes.value.filter(pa => pa.status === 'PENDING').length;
  const approved = paCodes.value.filter(pa => pa.status === 'APPROVED').length;
  const rejected = paCodes.value.filter(pa => pa.status === 'REJECTED').length;

  return { total, pending, approved, rejected };
});

// Filtered PA codes
const filteredPACodes = computed(() => {
  return paCodes.value.filter(pa => {
    const matchesSearch = !searchQuery.value ||
      pa.code?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      pa.referral?.utn?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      pa.referral?.enrollee?.first_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      pa.referral?.enrollee?.last_name?.toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchesStatus = !statusFilter.value || pa.status === statusFilter.value;
    const matchesType = !typeFilter.value || pa.type === typeFilter.value;

    return matchesSearch && matchesStatus && matchesType;
  });
});

// Fetch PA codes
const fetchPACodes = async () => {
  loading.value = true;
  try {
    const response = await api.get('/pas/pa-codes', {
      params: {
        facility_requested: true, // Only get PA codes requested by this facility
      }
    });
    paCodes.value = response.data?.data || response.data || [];
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch PA codes');
    console.error(err);
  } finally {
    loading.value = false;
  }
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  typeFilter.value = null;
};

// View details
const viewDetails = (pa) => {
  selectedPA.value = pa;
  detailsDialog.value = true;
};

// Open request dialog - show inline request form
const openRequestDialog = () => {
  showRequestDialog.value = true;
  fetchApprovedReferralsForFacility();
  fetchCaseRecords();
  fetchServiceBundles();
  fetchPADocumentRequirements();
};

// Get status color
const getStatusColor = (status) => {
  const colors = {
    PENDING: 'warning',
    APPROVED: 'success',
    REJECTED: 'error',
  };
  return colors[status] || 'grey';
};

// Get status icon
const getStatusIcon = (status) => {
  const icons = {
    PENDING: 'mdi-clock-alert',
    APPROVED: 'mdi-check-circle',
    REJECTED: 'mdi-close-circle',
  };
  return icons[status] || 'mdi-help-circle';
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

// Print PA slip
const printPASlip = (pa) => {
  const printWindow = window.open('', '_blank', 'width=400,height=700,scrollbars=yes');
  const printContent = generatePrintContent(pa);

  printWindow.document.write(printContent);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
  }, 250);
};

// Generate print content
const generatePrintContent = (pa) => {
  const currentDate = new Date().toLocaleString('en-NG');

  return `
    <!DOCTYPE html>
    <html>
    <head>
      <title>PA Code Slip - ${pa.code || 'Pending'}</title>
      <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
          font-family: 'Arial', sans-serif;
          padding: 10px;
          font-size: 11px;
          line-height: 1.4;
        }
        .header {
          text-align: center;
          border-bottom: 2px solid #000;
          padding-bottom: 8px;
          margin-bottom: 10px;
        }
        .logo { font-size: 16px; font-weight: bold; color: #2c3e50; }
        .title { font-size: 13px; font-weight: bold; margin: 5px 0; }
        .section { margin-bottom: 10px; }
        .section-title {
          background: #34495e;
          color: white;
          padding: 4px 6px;
          font-weight: bold;
          font-size: 10px;
          margin-bottom: 5px;
        }
        .row { display: flex; margin-bottom: 4px; }
        .label { font-weight: bold; width: 120px; }
        .value { flex: 1; }
        .footer {
          border-top: 1px solid #ccc;
          padding-top: 8px;
          margin-top: 10px;
          text-align: center;
          font-size: 9px;
          color: #666;
        }
        .status-badge {
          display: inline-block;
          padding: 2px 8px;
          border-radius: 3px;
          font-weight: bold;
          font-size: 10px;
        }
        .status-pending { background: #ff9800; color: white; }
        .status-approved { background: #4caf50; color: white; }
        .status-rejected { background: #f44336; color: white; }
        @media print {
          body { margin: 0; padding: 5px; }
          .no-print { display: none; }
        }
      </style>
    </head>
    <body>
      <div class="header">
        <div class="logo">NGSCHA - NiCare</div>
        <div class="title">FOLLOW-UP PA CODE SLIP</div>
      </div>

      <div class="section">
        <div class="section-title">PA Code Information</div>
        <div class="row">
          <span class="label">PA Code:</span>
          <span class="value"><strong>${pa.code || 'PENDING APPROVAL'}</strong></span>
        </div>
        <div class="row">
          <span class="label">UTN:</span>
          <span class="value">${pa.referral?.utn || 'N/A'}</span>
        </div>
        <div class="row">
          <span class="label">Type:</span>
          <span class="value">${pa.type === 'FFS_TOP_UP' ? 'FFS Top-Up' : 'Bundle'}</span>
        </div>
        <div class="row">
          <span class="label">Status:</span>
          <span class="value">
            <span class="status-badge status-${pa.status?.toLowerCase()}">${pa.status}</span>
          </span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="row">
          <span class="label">Patient Name:</span>
          <span class="value">${pa.referral?.enrollee?.first_name || ''} ${pa.referral?.enrollee?.last_name || ''}</span>
        </div>
        <div class="row">
          <span class="label">Enrollee ID:</span>
          <span class="value">${pa.referral?.enrollee?.nicare_number || pa.referral?.enrollee?.enrollee_id || 'N/A'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Service Details</div>
        <div class="row">
          <span class="label">Description:</span>
          <span class="value">${pa.service_description || 'N/A'}</span>
        </div>
      </div>

      <div class="section">
        <div class="section-title">Dates</div>
        <div class="row">
          <span class="label">Requested:</span>
          <span class="value">${formatDate(pa.created_at)}</span>
        </div>
        ${pa.approved_at ? `
        <div class="row">
          <span class="label">Approved:</span>
          <span class="value">${formatDate(pa.approved_at)}</span>
        </div>
        ` : ''}
      </div>

      ${pa.rejection_reason ? `
      <div class="section">
        <div class="section-title">Rejection Reason</div>
        <div style="padding: 5px; background: #ffebee; border-left: 3px solid #f44336;">
          ${pa.rejection_reason}
        </div>
      </div>
      ` : ''}

      <div class="footer">
        <div>Printed: ${currentDate}</div>
        <div>Niger State Contributory Health Agency</div>
        <div>www.ngscha.ng.gov.ng</div>
      </div>
    </body>
    </html>
  `;
};

// ========== FU-PA REQUEST FUNCTIONS ==========

// Fetch approved referrals for this facility only
const fetchApprovedReferralsForFacility = async () => {
 
  loadingReferrals.value = true;
  try {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    const facilityId = user.facility_id;
    


    const response = await api.get('/referrals', {
      params: {
        status: 'APPROVED',
        receiving_facility_id: facilityId // Filter by facility
      }
    });
    const referrals = response.data.data || response.data;

    // Add display text for autocomplete
    approvedReferrals.value = referrals.map(ref => ({
      ...ref,
      display_text: `${ref.utn} - ${ref.enrollee?.full_name || 'N/A'}`
    }));
  } catch (err) {
    showError('Failed to load approved referrals');
    console.error(err);
  } finally {
    loadingReferrals.value = false;
  }
};

// Fetch case records (services)
const fetchCaseRecords = async () => {
  loadingCaseRecords.value = true;
  try {
    const response = await api.get('/cases', {
      params: { status: 'active' }
    });
    const records = response.data.data || response.data;
    caseRecords.value = records.map(record => ({
      ...record,
      display_name: `${record.case_name} (${record.nicare_code})`,
      service_description: `${record.case_name} - ${record.nicare_code}`
    }));
  } catch (err) {
    showError('Failed to load services');
    console.error(err);
  } finally {
    loadingCaseRecords.value = false;
  }
};

// Fetch service bundles
const fetchServiceBundles = async () => {
  loadingBundles.value = true;
  try {
    const response = await api.get('/service-bundles', {
      params: { status: 'active' }
    });
    serviceBundles.value = (response.data.data || response.data).map(bundle => ({
      ...bundle,
      display_name: `${bundle.description || bundle.name} - ₦${Number(bundle.fixed_price).toLocaleString()}`
    }));
  } catch (err) {
    showError('Failed to load service bundles');
  } finally {
    loadingBundles.value = false;
  }
};

// Fetch PA code document requirements
const fetchPADocumentRequirements = async () => {
  loadingPADocuments.value = true;
  try {
    const response = await api.get('/document-requirements', {
      params: { request_type: 'pa_code', status: 1 }
    });
    paDocumentRequirements.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load document requirements');
    console.error(err);
  } finally {
    loadingPADocuments.value = false;
  }
};

// Handle PA document upload
const getAcceptedTypes = (allowed) => {
  if (!allowed) return '*';
  const parts = Array.isArray(allowed) ? allowed : allowed.split(',').map(t => t.trim()).filter(Boolean);
  return parts.map(t => (t.startsWith('.') ? t : `.${t}`)).join(',');
};

const handlePADocumentUpload = (requirement, files) => {
  if (!files || files.length === 0) {
    uploadedPADocuments.value[requirement.document_type] = null;
    return;
  }

  const uploadedFile = files[0];

  // Validate file size
  const maxSizeBytes = requirement.max_file_size_mb * 1024 * 1024;
  if (uploadedFile.size > maxSizeBytes) {
    showError(`File size exceeds ${requirement.max_file_size_mb}MB limit`);
    uploadedPADocuments.value[requirement.document_type] = null;
    return;
  }

  // Validate file type
  const allowedRaw = requirement.allowed_file_types || '';
  const allowedTypes = Array.isArray(allowedRaw)
    ? allowedRaw
    : allowedRaw.split(',').map(t => t.trim()).filter(Boolean);
  const fileExtension = uploadedFile.name?.split('.').pop().toLowerCase();

  if (allowedTypes.length && (!fileExtension || !allowedTypes.includes(fileExtension))) {
    showError(`File type .${fileExtension || 'unknown'} is not allowed. Allowed types: ${allowedTypes.join(', ')}`);
    uploadedPADocuments.value[requirement.document_type] = null;
    return;
  }

  // File is valid, store it
  uploadedPADocuments.value[requirement.document_type] = uploadedFile;
};

// Check if claim exists for referral
const checkClaimExists = async (referralId) => {
  claimCheckStatus.value = 'checking';
  existingClaim.value = null;

  try {
    const response = await api.get('/claims-automation/claims', {
      params: { referral_id: referralId }
    });

    const claims = response.data.data?.data || response.data.data || response.data;

    if (claims && Array.isArray(claims) && claims.length > 0) {
      claimCheckStatus.value = 'exists';
      existingClaim.value = claims[0];
      showError('A claim has already been submitted for this referral');
    } else if (claims && !Array.isArray(claims) && claims.id) {
      claimCheckStatus.value = 'exists';
      existingClaim.value = claims;
      showError('A claim has already been submitted for this referral');
    } else {
      claimCheckStatus.value = 'none';
    }
  } catch (err) {
    if (err.response?.status === 404 || err.response?.data?.data?.length === 0) {
      claimCheckStatus.value = 'none';
    } else {
      showError('Failed to check claim status');
      console.error(err);
      claimCheckStatus.value = null;
    }
  }
};

// Load enrollee details
const loadEnrolleeDetails = async (enrolleeId) => {
  try {
    const response = await api.get(`/enrollees/${enrolleeId}`);
    enrolleeDetails.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load enrollee details:', err);
  }
};

// Handle referral selection
const onReferralSelected = async (referralId) => {
  if (!referralId) {
    selectedReferral.value = null;
    enrolleeDetails.value = null;
    claimCheckStatus.value = null;
    existingClaim.value = null;
    return;
  }

  await loadReferralDetails();
};

// Load referral details
const loadReferralDetails = async () => {
  if (!selectedReferralId.value) return;

  loadingReferralDetails.value = true;
  selectedReferral.value = null;
  enrolleeDetails.value = null;
  claimCheckStatus.value = null;
  existingClaim.value = null;

  try {
    const response = await api.get(`/referrals/${selectedReferralId.value}`);
    const referral = response.data.data || response.data;

    selectedReferral.value = referral;
    formData.value.referral_id = referral.id;
    formData.value.enrollee_id = referral.enrollee_id;
    formData.value.facility_id = referral.receiving_facility_id;

    // Load enrollee details
    if (referral.enrollee_id) {
      await loadEnrolleeDetails(referral.enrollee_id);
    }

    // Fetch active admission for this referral
    try {
      const admissionResponse = await api.get('/claims-automation/admissions', {
        params: {
          referral_id: referral.id,
          status: 'active'
        }
      });

      const admissions = admissionResponse.data.data?.data || admissionResponse.data.data || admissionResponse.data;

      if (admissions && Array.isArray(admissions) && admissions.length > 0) {
        formData.value.admission_id = admissions[0].id;
      } else if (admissions && !Array.isArray(admissions) && admissions.id) {
        formData.value.admission_id = admissions.id;
      } 
    } catch (admissionErr) {
      console.error('Failed to fetch admission:', admissionErr);
      formData.value.admission_id = null;
      showError('Could not verify active admission. Patient must be admitted before requesting FU-PA code.');
    }

    // Check if claim exists
    await checkClaimExists(referral.id);

  } catch (err) {
    showError('Failed to load referral details');
    console.error(err);
  } finally {
    loadingReferralDetails.value = false;
  }
};

// Handle form submission
const handleSubmitRequest = async () => {
  // Validate form
  const { valid } = await requestForm.value?.validate();
  if (!valid) {
    showError('Please fill in all required fields');
    return;
  }

  if (!formData.value.clinical_justification) {
    showError('Clinical justification is required');
    return;
  }

  // Validate required documents
  const requiredDocs = paDocumentRequirements.value.filter(req => req.is_required);
  for (const req of requiredDocs) {
    if (!uploadedPADocuments.value[req.document_type]) {
      showError(`Required document "${req.name}" is missing`);
      return;
    }
  }

  submitting.value = true;

  try {
    // Create FormData for file uploads
    const formDataPayload = new FormData();

    // Add all form fields
    formDataPayload.append('referral_id', formData.value.referral_id);
    formDataPayload.append('enrollee_id', formData.value.enrollee_id);
    formDataPayload.append('facility_id', formData.value.facility_id);
    if (formData.value.admission_id) {
      formDataPayload.append('admission_id', formData.value.admission_id);
    }
    formDataPayload.append('is_complication_pa', true);
    formDataPayload.append('justification', formData.value.clinical_justification);
    if (formData.value.diagnosis_update) {
      formDataPayload.append('diagnosis_update', formData.value.diagnosis_update);
    }
    if (formData.value.service_selection_type) {
      formDataPayload.append('service_selection_type', formData.value.service_selection_type);
    }
    if (formData.value.service_bundle_id) {
      formDataPayload.append('service_bundle_id', formData.value.service_bundle_id);
    }
    if (formData.value.case_record_ids && formData.value.case_record_ids.length > 0) {
      formDataPayload.append('case_record_ids', JSON.stringify(formData.value.case_record_ids));
    }
    formDataPayload.append('requested_items', JSON.stringify([]));

    // Add uploaded documents
    Object.keys(uploadedPADocuments.value).forEach(docType => {
      const file = uploadedPADocuments.value[docType];
      if (file) {
        formDataPayload.append(`documents[${docType}]`, file);
      }
    });

    const response = await api.post('/pas/pa-codes', formDataPayload, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    const createdPACode = response.data.data || response.data;

    showSuccess(`FU-PA Code request submitted successfully! Code: ${createdPACode.code}`);

    // Close dialog and refresh list
    closeRequestDialog();
    await fetchPACodes();

  } catch (err) {
    const message = err.response?.data?.message || err.message || 'Failed to submit FU-PA Code request';
    showError(message);
    console.error(err);
  } finally {
    submitting.value = false;
  }
};

// Close request dialog and reset form
const closeRequestDialog = () => {
  showRequestDialog.value = false;
  selectedReferralId.value = null;
  selectedReferral.value = null;
  enrolleeDetails.value = null;
  claimCheckStatus.value = null;
  existingClaim.value = null;
  uploadedPADocuments.value = {};
  formData.value = {
    referral_id: null,
    enrollee_id: null,
    facility_id: null,
    admission_id: null,
    is_complication_pa: true,
    clinical_justification: '',
    diagnosis_update: '',
    service_selection_type: null,
    service_bundle_id: null,
    case_record_ids: [],
  };

  if (requestForm.value) {
    requestForm.value.reset();
  }
};

// Computed: Can submit form
const canSubmitRequest = computed(() => {
  if (!selectedReferral.value || claimCheckStatus.value !== 'none') {
    return false;
  }

  

  if (!formData.value.service_selection_type) {
    return false;
  }

  if (formData.value.service_selection_type === 'bundle' && !formData.value.service_bundle_id) {
    return false;
  }

  if (formData.value.service_selection_type === 'direct' && (!formData.value.case_record_ids || formData.value.case_record_ids.length === 0)) {
    return false;
  }

  return true;
});

// Lifecycle
onMounted(() => {
  fetchPACodes();
});
</script>

<style scoped>
.stat-card {
  transition: transform 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
}
</style>
