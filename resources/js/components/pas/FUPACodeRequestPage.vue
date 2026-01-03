<template>
  <AdminLayout>
    <div class="fu-pa-code-request-page">
      <!-- Page Header -->
      <div class="page-header mb-6">
        <v-container fluid>
          <v-row align="center">
            <v-col cols="12">
              <div class="d-flex align-center">
                <v-icon size="40" color="primary" class="mr-4">mdi-file-document-plus-outline</v-icon>
                <div>
                  <h1 class="text-h4 font-weight-bold mb-1">Request Follow-Up PA Code</h1>
                  <p class="text-subtitle-1 text-medium-emphasis mb-0">
                    Request additional FFS services not covered in the bundle
                  </p>
                </div>
              </div>
            </v-col>
          </v-row>
        </v-container>
      </div>

      <v-container fluid>
        <div v-if="!createdPACode">
        <!-- Step 1: Select Approved Referral -->
        <v-card class="mb-6" elevation="1">
          <v-card-title class="bg-grey-lighten-4 d-flex align-center py-4">
            <v-chip color="primary" variant="flat" class="mr-3">1</v-chip>
            <span class="text-h6">Select Approved Referral</span>
          </v-card-title>

          <v-card-text class="pa-6">
           

            <v-row>
       
              <v-col cols="12" md="8">
                <v-autocomplete
                  v-model="selectedReferralId"
                  label="Search and Select Approved Referral"
                  :items="approvedReferrals"
                  item-title="display_text"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  :loading="loadingReferrals"
                  :disabled="loadingReferralDetails"
                  prepend-inner-icon="mdi-magnify"
                  placeholder="Type UTN, patient name, or NiCare number..."
                  hint="Select a referral to view details and check claim status"
                  persistent-hint
                  clearable
                  @update:model-value="onReferralSelected"
                >
                  <template v-slot:item="{ item, props }">
                    <v-list-item v-bind="props" class="px-4 py-3">
                      <template v-slot:prepend>
                        <v-avatar color="primary" size="40">
                          <v-icon color="white">mdi-account</v-icon>
                        </v-avatar>
                      </template>
                      <v-list-item-subtitle class="mt-1">
                        <v-icon size="14" class="mr-1">mdi-account</v-icon>
                        {{ item.raw.enrollee.full_name }}
                        <span class="mx-2">•</span>
                        <v-icon size="14" class="mr-1">mdi-card-account-details</v-icon>
                        {{ item.raw.enrollee.enrollee_id }}
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>

                  <template v-slot:no-data>
                    <v-list-item>
                      <v-list-item-title class="text-center text-medium-emphasis">
                        <v-icon class="mr-2">mdi-alert-circle-outline</v-icon>
                        No approved referrals found
                      </v-list-item-title>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="4" class="d-flex align-center">
                <v-btn
                  v-if="selectedReferralId"
                  color="primary"
                  variant="outlined"
                  block
                  size="large"
                  :loading="loadingReferralDetails"
                  @click="loadReferralDetails"
                >
                  <v-icon start>mdi-refresh</v-icon>
                  Reload Details
                </v-btn>
              </v-col>
            </v-row>

            <!-- Claim Status Check -->
            <v-alert
              v-if="claimCheckStatus === 'checking'"
              type="info"
              variant="outlined"
              class="mt-4"
            >
              <v-progress-circular indeterminate size="20" width="2" class="mr-3"></v-progress-circular>
              Checking if claim exists for this referral...
            </v-alert>

            <v-alert
              v-if="claimCheckStatus === 'exists'"
              type="error"
              variant="outlined"
              class="mt-4"
            >
              <div class="d-flex align-start">
                <v-icon class="mr-3" size="24" color="error">mdi-alert-circle</v-icon>
                <div>
                  <div class="font-weight-bold mb-2">Claim Already Submitted</div>
                  <p class="mb-2">A claim has already been submitted for this referral. You cannot request additional PA codes.</p>
                  <div class="mt-3">
                    <strong>Claim Number:</strong> {{ existingClaim?.claim_number || 'N/A' }}<br>
                    <strong>Status:</strong> <v-chip size="small" :color="getClaimStatusColor(existingClaim?.status)" variant="outlined" class="ml-2">{{ existingClaim?.status }}</v-chip>
                  </div>
                </div>
              </div>
            </v-alert>

            <v-alert
              v-if="claimCheckStatus === 'none'"
              type="success"
              variant="outlined"
              class="mt-4"
            >
              <div class="d-flex align-center">
                <v-icon class="mr-3" color="success">mdi-check-circle</v-icon>
                <span class="font-weight-medium">No claim submitted yet. You can proceed with the FU-PA request.</span>
              </div>
            </v-alert>
          </v-card-text>
        </v-card>

        <!-- Step 2: Referral & Enrollee Details -->
        <v-card
          v-if="selectedReferral && claimCheckStatus === 'none'"
          class="mb-6"
          elevation="1"
        >
          <v-card-title class="bg-grey-lighten-4 d-flex align-center py-4">
            <v-chip color="primary" variant="flat" class="mr-3">2</v-chip>
            <span class="text-h6">Referral & Patient Details</span>
          </v-card-title>

          <v-card-text class="pa-6">
            <!-- Referral Information -->
            <div class="mb-6">
              <h3 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center text-primary">
                <v-icon class="mr-2" size="20">mdi-file-document-outline</v-icon>
                Referral Information
              </h3>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="selectedReferral.referral_code"
                    label="Referral Code"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-barcode"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="selectedReferral.utn"
                    label="UTN (Unique Transaction Number)"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-key-variant"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="selectedReferral.severity_level"
                    label="Severity Level"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-alert-circle-outline"
                  />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedReferral.referring_facility?.name"
                    label="Referring Facility"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-hospital-building"
                  />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedReferral.receiving_facility?.name"
                    label="Receiving Facility"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-hospital-marker"
                  />
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    :model-value="selectedReferral.preliminary_diagnosis"
                    label="Preliminary Diagnosis"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    rows="2"
                    prepend-inner-icon="mdi-stethoscope"
                  />
                </v-col>
              </v-row>
            </div>

            <v-divider class="my-6"></v-divider>

            <!-- Enrollee Information -->
            <div>
              <h3 class="text-subtitle-1 font-weight-bold mb-4 d-flex align-center text-primary">
                <v-icon class="mr-2" size="20">mdi-account-circle</v-icon>
                Patient (Enrollee) Information
              </h3>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.full_name || selectedReferral.enrollee_full_name"
                    label="Full Name"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-account"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.nicare_number || selectedReferral.nicare_number"
                    label="NiCare Number"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-card-account-details"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.phone_number || 'N/A'"
                    label="Phone Number"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-phone"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.gender || 'N/A'"
                    label="Gender"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-gender-male-female"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.date_of_birth || 'N/A'"
                    label="Date of Birth"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-calendar"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="enrolleeDetails?.enrollee_type || 'N/A'"
                    label="Enrollee Type"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-account-group"
                  />
                </v-col>
              </v-row>
            </div>
          </v-card-text>
        </v-card>

        <!-- Step 3: Service Selection (Optional) -->
        <v-card
          v-if="selectedReferral && claimCheckStatus === 'none'"
          class="mb-6"
          elevation="1"
        >
          <v-card-title class="bg-grey-lighten-4 d-flex align-center py-4">
            <v-chip color="primary" variant="flat" class="mr-3">3</v-chip>
            <span class="text-h6">Service Selection (Optional)</span>
          </v-card-title>

          <v-card-text class="pa-6">
            <v-alert type="info" density="compact" class="mb-4">
              Select a service bundle  or multiple Fee-For-Service (FFS) for this FU-PA Code request.
            </v-alert>

            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.service_selection_type"
                  label="Service Selection Type *"
                  :items="serviceSelectionTypes"
                  item-title="text"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  @update:model-value="onServiceTypeChange"
                  :rules="[v => !!v || 'Service selection type is required']"
                />
              </v-col>
            </v-row>

            <!-- Bundle Service Selection (Single) -->
            <v-row v-if="formData.service_selection_type === 'bundle'">
              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.service_bundle_id"
                  label="Select Service Bundle *"
                  :items="serviceBundles"
                  item-title="display_name"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  :loading="loadingBundles"
                  :rules="[v => !!v || 'Service bundle is required']"
                >
                  <template v-slot:item="{ item, props }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-icon color="primary">mdi-package-variant</v-icon>
                      </template>
                      <v-list-item-subtitle class="mt-1">
                        <div class="text-caption">
                          <v-icon size="12">mdi-barcode</v-icon> {{ item.raw.code }} |
                          <v-icon size="12">mdi-currency-ngn</v-icon> ₦{{ Number(item.raw.fixed_price).toLocaleString() }}
                          <span v-if="item.raw.diagnosis_icd10"> | <v-icon size="12">mdi-medical-bag</v-icon> {{ item.raw.diagnosis_icd10 }}</span>
                        </div>
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <!-- FFS Service Selection (Multiple) -->
            <v-row v-if="formData.service_selection_type === 'direct'">
              <!-- Detail Type Filter -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="detailTypeFilter"
                  label="Filter by Service Type (Optional)"
                  :items="detailTypeOptions"
                  item-title="text"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  hint="Filter FFS services by type"
                  persistent-hint
                  @update:model-value="onDetailTypeFilterChange"
                >
                  <template v-slot:prepend-inner>
                    <v-icon>mdi-filter</v-icon>
                  </template>
                </v-select>
              </v-col>

              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.case_record_ids"
                  label="Select FFS Services * (Multiple allowed)"
                  :items="filteredCaseRecords"
                  item-title="display_name"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  :loading="loadingCaseRecords"
                  multiple
                  chips
                  closable-chips
                  :rules="[v => (v && v.length > 0) || 'At least one service is required']"
                >
                  <template v-slot:item="{ item, props }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-icon :color="getCaseRecordColor(item.raw.detail_type)">{{ getCaseRecordIcon(item.raw.detail_type) }}</v-icon>
                      </template>
                      <v-list-item-subtitle class="mt-1">
                        <div class="text-caption">
                          <v-icon size="12">mdi-barcode</v-icon> {{ item.raw.nicare_code }} |
                          <v-chip size="x-small" :color="getCaseRecordColor(item.raw.detail_type)" variant="flat">{{ getCaseTypeLabel(item.raw.detail_type) }}</v-chip>
                        </div>
                      </v-list-item-subtitle>
                    </v-list-item>
                  </template>
                  <template v-slot:chip="{ item, props }">
                    <v-chip
                      v-bind="props"
                      :color="getCaseRecordColor(item.raw.detail_type)"
                      closable
                    >
                      <v-icon start :icon="getCaseRecordIcon(item.raw.detail_type)"></v-icon>
                      {{ item.raw.case_name }}
                    </v-chip>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Step 4: Clinical Justification & Diagnosis Update -->
        <v-card
          v-if="selectedReferral && claimCheckStatus === 'none'"
          class="mb-6"
          elevation="1"
        >
          <v-card-title class="bg-grey-lighten-4 d-flex align-center py-4">
            <v-chip color="primary" variant="flat" class="mr-3">4</v-chip>
            <span class="text-h6">Clinical Justification & Diagnosis Update</span>
          </v-card-title>

          <v-card-text class="pa-6">
            <v-form ref="requestForm">
              <v-row>
                <v-col cols="12">
                  <v-textarea
                    v-model="formData.clinical_justification"
                    label="Clinical Justification *"
                    variant="outlined"
                    density="comfortable"
                    rows="4"
                    :rules="[v => !!v || 'Clinical justification is required']"
                    placeholder="Explain why these FFS services are medically necessary and not covered in the bundle..."
                    hint="Provide detailed clinical reasoning for the requested services"
                    persistent-hint
                    counter
                    prepend-inner-icon="mdi-text-box-outline"
                  />
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="formData.diagnosis_update"
                    label="Diagnosis Update (Optional)"
                    variant="outlined"
                    density="comfortable"
                    rows="3"
                    placeholder="Update or add to the preliminary diagnosis if needed..."
                    hint="Provide any updates to the diagnosis based on current clinical findings"
                    persistent-hint
                    prepend-inner-icon="mdi-stethoscope"
                  />
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>
        </v-card>

        <!-- Submit Button -->
        <v-card
          v-if="selectedReferral && claimCheckStatus === 'none'"
          class="mb-6"
          elevation="0"
        >
          <v-card-text class="pa-6">
            <v-row>
              <v-col cols="12" md="8" offset-md="2">
                <v-btn
                  color="primary"
                  variant="flat"
                  size="large"
                  block
                  @click="handleSubmission"
                  :loading="submitting"
                  :disabled="!canSubmit"
                  class="py-6"
                >
                  <v-icon start>mdi-send</v-icon>
                  Submit FU-PA Code Request
                </v-btn>

                <div class="text-center mt-4 text-caption text-medium-emphasis">
                  <v-icon size="14" class="mr-1">mdi-information</v-icon>
                  Your request will be reviewed by the authorization team
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        </div>

        <!-- Success Message -->
        <v-card
          v-if="createdPACode"
          class="mb-6"
          elevation="2"
        >
          <v-card-text class="pa-8">
            <div class="text-center">
              <v-icon size="64" color="success" class="mb-4">mdi-check-circle</v-icon>
              <h2 class="text-h5 font-weight-bold mb-4">
                Request Submitted Successfully!
              </h2>

              <v-divider class="my-4"></v-divider>

              <div>
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">PA Code</div>
                  <div class="text-h5 font-weight-bold text-primary">{{ createdPACode.code }}</div>
                </div>

                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Status</div>
                  <v-chip color="warning" size="default" variant="outlined">
                    <v-icon start size="18">mdi-clock-outline</v-icon>
                    {{ createdPACode.status }}
                  </v-chip>
                </div>

                <div class="mt-6">
                  <p class="text-body-2 text-medium-emphasis">
                    Your FU-PA Code request has been submitted for approval.
                    You will be notified once it's reviewed by the authorization team.
                  </p>
                </div>
              </div>

              <v-divider class="my-4"></v-divider>

              <v-btn
                color="primary"
                variant="outlined"
                size="large"
                @click="resetForm"
                class="mt-2"
              >
                <v-icon start>mdi-plus</v-icon>
                Submit Another Request
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import api from '@/js/utils/api';
import { useToast } from '@/js/composables/useToast';

const { success: showSuccess, error: showError, info: showInfo, warning: showWarning } = useToast();

// Reactive state
const submitting = ref(false);
const createdPACode = ref(null);
const requestForm = ref(null);

const approvedReferrals = ref([]);
const selectedReferralId = ref(null);
const selectedReferral = ref(null);
const enrolleeDetails = ref(null);
const caseRecords = ref([]);
const loadingReferrals = ref(false);
const loadingReferralDetails = ref(false);
const loadingServices = ref(false);

const claimCheckStatus = ref(null); // 'checking', 'exists', 'none'
const existingClaim = ref(null);

const serviceBundles = ref([]);
const loadingBundles = ref(false);
const loadingCaseRecords = ref(false);

const serviceSelectionTypes = [
  { value: 'bundle', text: 'Bundle Service (Package)' },
  { value: 'direct', text: 'Fee-For-Service (FFS)' },
];

// Service type filter
const detailTypeFilter = ref(null);
const detailTypeOptions = [
  { value: 'Procedure', text: 'Procedure' },
  { value: 'Investigation', text: 'Investigation' },
  { value: 'Consumable', text: 'Consumable' },
  { value: 'Medication', text: 'Medication' },
  { value: 'Accommodation', text: 'Accommodation' },
];

const formData = ref({
  referral_id: null,
  enrollee_id: null,
  facility_id: null,
  admission_id: null, // Active admission ID
  is_complication_pa: true,
  clinical_justification: '',
  diagnosis_update: '',
  service_selection_type: null,
  service_bundle_id: null,
  case_record_ids: [], // Array for multiple direct services
});

// Computed properties
const filteredCaseRecords = computed(() => {
  if (!detailTypeFilter.value) {
    return caseRecords.value;
  }
  return caseRecords.value.filter(record => record.detail_type === detailTypeFilter.value);
});

const canSubmit = computed(() => {
  if (!selectedReferral.value || claimCheckStatus.value !== 'none' || !formData.value.clinical_justification || submitting.value) {
    return false;
  }

 

  // Must have service selection type
  if (!formData.value.service_selection_type) {
    return false;
  }

  // If bundle, must have bundle selected
  if (formData.value.service_selection_type === 'bundle' && !formData.value.service_bundle_id) {
    return false;
  }

  // If direct, must have at least one service selected
  if (formData.value.service_selection_type === 'direct' && (!formData.value.case_record_ids || formData.value.case_record_ids.length === 0)) {
    return false;
  }

  return true;
});

// Helper functions
const getClaimStatusColor = (status) => {
  const colors = {
    'DRAFT': 'grey',
    'SUBMITTED': 'info',
    'APPROVED': 'success',
    'REJECTED': 'error',
    'PENDING': 'warning'
  };
  return colors[status] || 'grey';
};

// Fetch approved referrals
const fetchApprovedReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await api.get('/referrals', {
      params: { status: 'APPROVED' }
    });
    const referrals = response.data.data || response.data;

    // Add display text for autocomplete
    approvedReferrals.value = referrals.map(ref => ({
      ...ref,
      display_text: `${ref.utn}`
    }));
  } catch (err) {
    showError('Failed to load approved referrals');
    console.error(err);
  } finally {
    loadingReferrals.value = false;
  }
};

// Fetch case records (services) - only services that require PA
const fetchCaseRecords = async () => {
  loadingServices.value = true;
  loadingCaseRecords.value = true;
  try {
    const response = await api.get('/cases', {
      params: {
        // pa_required: true, // Only fetch services that require PA
        status: 'active'
      }
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
    loadingServices.value = false;
    loadingCaseRecords.value = false;
  }
};

// Fetch service bundles (case records where is_bundle = true)
const fetchServiceBundles = async () => {
  loadingBundles.value = true;
  try {
    const response = await api.get('/cases', {
      params: {
        is_bundle: true,
        status: true
      }
    });
    serviceBundles.value = (response.data.data || response.data).map(bundle => ({
      ...bundle,
      display_name: `${bundle.service_description || bundle.case_name} - ₦${Number(bundle.bundle_price || bundle.price).toLocaleString()}`,
      description: bundle.service_description,
      name: bundle.case_name,
      fixed_price: bundle.bundle_price || bundle.price
    }));
  } catch (err) {
    showError('Failed to load service bundles');
  } finally {
    loadingBundles.value = false;
  }
};

// Handle service type change
const onServiceTypeChange = () => {
  formData.value.service_bundle_id = null;
  formData.value.case_record_ids = [];
};

// Handle detail type filter change
const onDetailTypeFilterChange = () => {
  // Clear selected services when filter changes
  formData.value.case_record_ids = [];
};

// Get case record icon
const getCaseRecordIcon = (detailType) => {
  const iconMap = {
    'App\\Models\\DrugDetail': 'mdi-pill',
    'App\\Models\\LaboratoryDetail': 'mdi-flask',
    'App\\Models\\ProfessionalServiceDetail': 'mdi-stethoscope',
    'App\\Models\\RadiologyDetail': 'mdi-radioactive',
    'App\\Models\\ConsultationDetail': 'mdi-doctor',
    'App\\Models\\ConsumableDetail': 'mdi-package-variant-closed',
  };
  return iconMap[detailType] || 'mdi-medical-bag';
};

// Get case record color
const getCaseRecordColor = (detailType) => {
  const colorMap = {
    'App\\Models\\DrugDetail': 'blue',
    'App\\Models\\LaboratoryDetail': 'purple',
    'App\\Models\\ProfessionalServiceDetail': 'green',
    'App\\Models\\RadiologyDetail': 'orange',
    'App\\Models\\ConsultationDetail': 'teal',
    'App\\Models\\ConsumableDetail': 'brown',
  };
  return colorMap[detailType] || 'grey';
};

// Get case type label
const getCaseTypeLabel = (detailType) => {
  const labelMap = {
    'App\\Models\\DrugDetail': 'Drug',
    'App\\Models\\LaboratoryDetail': 'Laboratory',
    'App\\Models\\ProfessionalServiceDetail': 'Professional Service',
    'App\\Models\\RadiologyDetail': 'Radiology',
    'App\\Models\\ConsultationDetail': 'Consultation',
    'App\\Models\\ConsumableDetail': 'Consumable',
  };
  return labelMap[detailType] || 'Service';
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
      // Single claim object returned
      claimCheckStatus.value = 'exists';
      existingClaim.value = claims;
      showError('A claim has already been submitted for this referral');
    } else {
      claimCheckStatus.value = 'none';
      showInfo('No claim found. You can proceed with the request.');
    }
  } catch (err) {
    // If 404 or no claims found, that's good
    if (err.response?.status === 404 || err.response?.data?.data?.length === 0) {
      claimCheckStatus.value = 'none';
      showInfo('No claim found. You can proceed with the request.');
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
    // Don't show error to user, just log it
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
    // Get referral details
    const response = await api.get(`/referrals/${selectedReferralId.value}`);
    console.log(response);
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
      showWarning('Could not verify active admission. Patient must be admitted before requesting FU-PA code.');
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
const handleSubmission = async () => {
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

  submitting.value = true;
  createdPACode.value = null;

  try {
    const payload = {
      referral_id: formData.value.referral_id,
      enrollee_id: formData.value.enrollee_id,
      facility_id: formData.value.facility_id,
      admission_id: formData.value.admission_id, // Active admission ID
      is_complication_pa: true,
      justification: formData.value.clinical_justification,
      diagnosis_update: formData.value.diagnosis_update,
      service_selection_type: formData.value.service_selection_type,
      service_bundle_id: formData.value.service_bundle_id,
      case_record_ids: formData.value.case_record_ids, // Array of direct service IDs
      requested_items: [], // No FFS items anymore
    };

    const response = await api.post('/pas/pa-codes', payload);
    createdPACode.value = response.data.data || response.data;

    showSuccess(`FU-PA Code request submitted successfully! Code: ${createdPACode.value.code}`);

    // Scroll to success message
    window.scrollTo({ top: 0, behavior: 'smooth' });

  } catch (err) {
    const message = err.response?.data?.message || err.message || 'Failed to submit FU-PA Code request';
    showError(message);
    console.error(err);
  } finally {
    submitting.value = false;
  }
};

// Reset form
const resetForm = () => {
  selectedReferralId.value = null;
  selectedReferral.value = null;
  enrolleeDetails.value = null;
  claimCheckStatus.value = null;
  existingClaim.value = null;
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
  createdPACode.value = null;

  if (requestForm.value) {
    requestForm.value.reset();
  }
};

onMounted(async () => {
  await Promise.all([
    fetchApprovedReferrals(),
    fetchCaseRecords(),
    fetchServiceBundles(),
  ]);
});
</script>

<style scoped>
.fu-pa-code-request-page {
  min-height: 100vh;
  background-color: #fafafa;
}

.page-header {
  background-color: white;
  padding: 1.5rem 0;
  margin: -24px -24px 24px -24px;
  border-bottom: 1px solid #e0e0e0;
}

.services-table {
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}

.services-table thead {
  background: #f5f5f5;
}

.services-table thead th {
  font-weight: 600;
  color: #424242;
  padding: 16px 12px;
}

.services-table tbody td {
  padding: 12px;
  vertical-align: middle;
}

.services-table tfoot tr {
  border-top: 2px solid #e0e0e0;
}

.centered-input :deep(input) {
  text-align: center;
}

:deep(.v-card) {
  border-radius: 4px;
}

:deep(.v-field--variant-outlined) {
  border-radius: 4px;
}

:deep(.v-btn) {
  text-transform: none;
  font-weight: 500;
  letter-spacing: 0.25px;
}
</style>


