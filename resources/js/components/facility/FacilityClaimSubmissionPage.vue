<template>
  <AdminLayout>
    <div class="facility-claim-submission-page">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-4">
          <v-col cols="12">
            <div class="d-flex justify-space-between align-center">
              <div>
                <h1 class="text-h4 font-weight-bold">
                  <v-icon size="32" color="primary" class="mr-2">mdi-file-document-plus</v-icon>
                  Submit Claim
                </h1>
                <p class="text-subtitle-1 text-grey mt-2">
                  Submit claims for discharged patients with validated UTN and approved PA codes
                </p>
              </div>
            </div>
          </v-col>
        </v-row>

        <!-- Episode Workflow Info -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-alert
              type="info"
              variant="tonal"
              density="comfortable"
            >
              <div class="d-flex align-center">
                <v-icon size="24" class="mr-3">mdi-information</v-icon>
                <div>
                  <strong>Episode Workflow:</strong> Referral (Approved) → UTN Validation → Admission → FU-PA Code → <strong class="text-primary">Claim Submission</strong>
                </div>
              </div>
            </v-alert>
          </v-col>
        </v-row>

        <!-- Business Rules Info -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-card elevation="1" color="warning-lighten-5">
              <v-card-title class="text-h6">
                <v-icon class="mr-2" color="warning">mdi-alert-circle</v-icon>
                Claim Submission Requirements
              </v-card-title>
              <v-card-text>
                <ul class="ml-4">
                  <li>Claims must be linked to a <strong>validated UTN</strong></li>
                  <li>Bundle claims must match the <strong>principal ICD-10 diagnosis</strong> from the referral</li>
                  <li>FFS line items require <strong>approved PA codes</strong></li>
                  <li>No FFS items allowed without valid PA codes</li>
                  <li>Patient must be <strong>discharged</strong> before claim submission</li>
                </ul>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Claim Submission Form -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-primary text-white">
                <v-icon class="mr-2">mdi-clipboard-text</v-icon>
                Claim Details
              </v-card-title>

              <v-card-text class="pt-6">
                <v-form ref="claimForm">
                  <!-- Step 1: Select Discharged Admission -->
                  <v-row>
                    <v-col cols="12">
                      <h3 class="text-h6 mb-3">Step 1: Select Discharged Admission</h3>
                      <v-autocomplete
                        v-model="formData.admission_id"
                        :items="dischargedAdmissions"
                        :loading="loadingAdmissions"
                        item-title="display_text"
                        item-value="id"
                        label="Select Discharged Admission"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-hospital-box"
                        :rules="[v => !!v || 'Admission is required']"
                        @update:model-value="onAdmissionSelected"
                      >
                        <template #item="{ props, item }">
                          <v-list-item v-bind="props">
                            <template #prepend>
                              <v-icon color="success">mdi-check-circle</v-icon>
                            </template>
                            <template #title>
                              <strong>{{ item.raw.admission_code }}</strong> - {{ item.raw.enrollee?.first_name }} {{ item.raw.enrollee?.last_name }}
                            </template>
                            <template #subtitle>
                              UTN: {{ item.raw.referral?.utn }} | Discharged: {{ formatDate(item.raw.discharge_date) }}
                            </template>
                          </v-list-item>
                        </template>
                      </v-autocomplete>
                    </v-col>
                  </v-row>

                  <!-- Admission Details (Auto-filled) -->
                  <v-row v-if="selectedAdmission">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Admission Information</h3>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedAdmission.enrollee?.first_name + ' ' + selectedAdmission.enrollee?.last_name"
                        label="Patient Name"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-account"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedAdmission.referral?.utn"
                        label="UTN"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-shield-check"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedAdmission.principal_diagnosis_description"
                        label="Principal Diagnosis"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-stethoscope"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedAdmission.bundle?.name || 'No Bundle'"
                        label="Matched Bundle"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-package-variant"
                      ></v-text-field>
                    </v-col>
                  </v-row>

                  <!-- Claim Date -->
                  <v-row v-if="selectedAdmission">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Step 2: Claim Details</h3>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        v-model="formData.claim_date"
                        label="Claim Date"
                        type="date"
                        variant="outlined"
                        density="comfortable"
                        :rules="[v => !!v || 'Claim date is required']"
                        prepend-inner-icon="mdi-calendar"
                      ></v-text-field>
                    </v-col>
                  </v-row>

                  <!-- Approved PA Codes -->
                  <v-row v-if="selectedAdmission && approvedPACodes.length > 0">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Step 3: Approved PA Codes for this Episode</h3>
                      <v-alert type="success" variant="tonal" density="comfortable" class="mb-4">
                        <strong>{{ approvedPACodes.length }}</strong> approved PA code(s) found for this admission
                      </v-alert>
                    </v-col>
                    <v-col cols="12">
                      <v-data-table
                        :headers="paCodeHeaders"
                        :items="approvedPACodes"
                        :items-per-page="5"
                        class="elevation-1"
                      >
                        <template #item.code="{ item }">
                          <v-chip size="small" color="primary" variant="outlined">
                            {{ item.code }}
                          </v-chip>
                        </template>

                        <template #item.type="{ item }">
                          <v-chip size="small" :color="item.type === 'BUNDLE' ? 'success' : 'info'">
                            {{ item.type }}
                          </v-chip>
                        </template>

                        <template #item.status="{ item }">
                          <v-chip size="small" color="success">
                            {{ item.status }}
                          </v-chip>
                        </template>
                      </v-data-table>
                    </v-col>
                  </v-row>

                  <!-- Claim Line Items -->
                  <v-row v-if="selectedAdmission && approvedPACodes.length > 0">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Step 4: Add Claim Line Items</h3>
                      <v-alert type="info" variant="tonal" density="comfortable" class="mb-4">
                        Add services and items provided during this episode. Each line item must be linked to an approved PA code.
                      </v-alert>
                    </v-col>

                    <!-- Add Line Item Button -->
                    <v-col cols="12">
                      <v-btn
                        color="primary"
                        variant="outlined"
                        prepend-icon="mdi-plus"
                        @click="openAddLineItemDialog"
                      >
                        Add Line Item
                      </v-btn>
                    </v-col>

                    <!-- Line Items Table -->
                    <v-col cols="12" v-if="claimLineItems.length > 0">
                      <v-data-table
                        :headers="lineItemHeaders"
                        :items="claimLineItems"
                        :items-per-page="10"
                        class="elevation-1"
                      >
                        <template #item.tariff_type="{ item }">
                          <v-chip size="small" :color="item.tariff_type === 'BUNDLE' ? 'success' : 'info'">
                            {{ item.tariff_type }}
                          </v-chip>
                        </template>

                        <template #item.unit_price="{ item }">
                          ₦{{ Number(item.unit_price).toLocaleString() }}
                        </template>

                        <template #item.line_total="{ item }">
                          <strong>₦{{ Number(item.line_total).toLocaleString() }}</strong>
                        </template>

                        <template #item.actions="{ item, index }">
                          <v-btn
                            icon="mdi-delete"
                            size="small"
                            color="error"
                            variant="text"
                            @click="removeLineItem(index)"
                          ></v-btn>
                        </template>

                        <template #bottom>
                          <div class="pa-4 d-flex justify-end">
                            <div class="text-h6">
                              Total: <strong class="text-primary">₦{{ totalClaimAmount.toLocaleString() }}</strong>
                            </div>
                          </div>
                        </template>
                      </v-data-table>
                    </v-col>

                    <!-- Empty State -->
                    <v-col cols="12" v-else>
                      <v-alert type="warning" variant="tonal" density="comfortable">
                        No line items added yet. Click "Add Line Item" to start building your claim.
                      </v-alert>
                    </v-col>
                  </v-row>

                  <!-- Warning if no PA codes -->
                  <v-row v-if="selectedAdmission && approvedPACodes.length === 0">
                    <v-col cols="12">
                      <v-alert type="warning" variant="tonal" density="comfortable">
                        <strong>No approved PA codes found for this admission.</strong><br>
                        You must request and get approval for FU-PA codes before submitting a claim.
                      </v-alert>
                    </v-col>
                  </v-row>

                  <!-- Validation Summary -->
                  <v-row v-if="selectedAdmission">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Validation Summary</h3>
                      <v-list density="compact">
                        <v-list-item>
                          <template #prepend>
                            <v-icon :color="validationChecks.hasValidatedUTN ? 'success' : 'error'">
                              {{ validationChecks.hasValidatedUTN ? 'mdi-check-circle' : 'mdi-close-circle' }}
                            </v-icon>
                          </template>
                          <v-list-item-title>UTN Validated</v-list-item-title>
                        </v-list-item>

                        <v-list-item>
                          <template #prepend>
                            <v-icon :color="validationChecks.isDischarge ? 'success' : 'error'">
                              {{ validationChecks.isDischarged ? 'mdi-check-circle' : 'mdi-close-circle' }}
                            </v-icon>
                          </template>
                          <v-list-item-title>Patient Discharged</v-list-item-title>
                        </v-list-item>

                        <v-list-item>
                          <template #prepend>
                            <v-icon :color="validationChecks.hasApprovedPA ? 'success' : 'error'">
                              {{ validationChecks.hasApprovedPA ? 'mdi-check-circle' : 'mdi-close-circle' }}
                            </v-icon>
                          </template>
                          <v-list-item-title>Has Approved PA Codes</v-list-item-title>
                        </v-list-item>

                        <v-list-item>
                          <template #prepend>
                            <v-icon :color="validationChecks.bundleMatchesDiagnosis ? 'success' : 'warning'">
                              {{ validationChecks.bundleMatchesDiagnosis ? 'mdi-check-circle' : 'mdi-alert-circle' }}
                            </v-icon>
                          </template>
                          <v-list-item-title>Bundle Matches Principal Diagnosis</v-list-item-title>
                        </v-list-item>
                      </v-list>
                    </v-col>
                  </v-row>
                </v-form>
              </v-card-text>

              <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn
                  color="grey"
                  variant="text"
                  @click="resetForm"
                  :disabled="submitting"
                >
                  Reset
                </v-btn>
                <v-btn
                  color="primary"
                  variant="elevated"
                  @click="submitClaim"
                  :loading="submitting"
                  :disabled="!canSubmit"
                >
                  Submit Claim
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Add Line Item Dialog -->
      <v-dialog v-model="showLineItemDialog" max-width="800px" persistent>
        <v-card>
          <v-card-title class="bg-primary text-white">
            <v-icon class="mr-2">mdi-plus-circle</v-icon>
            Add Claim Line Item
          </v-card-title>

          <v-card-text class="pt-6">
            <v-form ref="lineItemForm">
              <v-row>
                <!-- PA Code Selection -->
                <v-col cols="12">
                  <v-autocomplete
                    v-model="lineItemFormData.pa_code_id"
                    :items="approvedPACodes"
                    item-title="code"
                    item-value="id"
                    label="Select PA Code *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-shield-check"
                    :rules="[v => !!v || 'PA Code is required']"
                    @update:model-value="onPACodeSelected"
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #title>
                          <strong>{{ item.raw.code }}</strong> - {{ item.raw.type }}
                        </template>
                        <template #subtitle>
                          {{ item.raw.justification }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>

                <!-- Tariff Type -->
                <v-col cols="12" md="6">
                  <v-select
                    v-model="lineItemFormData.tariff_type"
                    :items="['BUNDLE', 'FFS']"
                    label="Tariff Type *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-tag"
                    :rules="[v => !!v || 'Tariff type is required']"
                  ></v-select>
                </v-col>

                <!-- Service Type -->
                <v-col cols="12" md="6">
                  <v-select
                    v-model="lineItemFormData.service_type"
                    :items="serviceTypes"
                    item-title="text"
                    item-value="value"
                    label="Service Type *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-medical-bag"
                    :rules="[v => !!v || 'Service type is required']"
                  ></v-select>
                </v-col>

                <!-- Service Description -->
                <v-col cols="12">
                  <v-textarea
                    v-model="lineItemFormData.service_description"
                    label="Service Description *"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                    prepend-inner-icon="mdi-text"
                    :rules="[v => !!v || 'Service description is required']"
                  ></v-textarea>
                </v-col>

                <!-- Quantity -->
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model.number="lineItemFormData.quantity"
                    label="Quantity *"
                    type="number"
                    min="1"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-counter"
                    :rules="[v => !!v && v > 0 || 'Quantity must be greater than 0']"
                    @update:model-value="calculateLineTotal"
                  ></v-text-field>
                </v-col>

                <!-- Unit Price -->
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model.number="lineItemFormData.unit_price"
                    label="Unit Price (₦) *"
                    type="number"
                    min="0"
                    step="0.01"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-currency-ngn"
                    :rules="[v => v >= 0 || 'Unit price must be 0 or greater']"
                    @update:model-value="calculateLineTotal"
                  ></v-text-field>
                </v-col>

                <!-- Line Total (Read-only) -->
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="lineItemFormData.line_total"
                    label="Line Total (₦)"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-calculator"
                  ></v-text-field>
                </v-col>

                <!-- Reporting Type -->
                <v-col cols="12" md="6">
                  <v-select
                    v-model="lineItemFormData.reporting_type"
                    :items="reportingTypes"
                    item-title="text"
                    item-value="value"
                    label="Reporting Type *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-file-document"
                    :rules="[v => !!v || 'Reporting type is required']"
                  ></v-select>
                </v-col>

                <!-- Diagnosis Code (Optional) -->
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="lineItemFormData.reported_diagnosis_code"
                    label="Diagnosis Code (Optional)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-medical-bag"
                  ></v-text-field>
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn
              color="grey"
              variant="text"
              @click="closeLineItemDialog"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              variant="elevated"
              @click="addLineItem"
            >
              Add Line Item
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const { success: showSuccess, error: showError, warning: showWarning } = useToast();

// State
const loadingAdmissions = ref(false);
const submitting = ref(false);
const claimForm = ref(null);
const lineItemForm = ref(null);

const dischargedAdmissions = ref([]);
const selectedAdmission = ref(null);
const approvedPACodes = ref([]);
const claimLineItems = ref([]);

const showLineItemDialog = ref(false);
const lineItemFormData = ref({
  pa_code_id: null,
  tariff_type: 'FFS',
  service_type: 'service',
  service_description: '',
  quantity: 1,
  unit_price: 0,
  line_total: 0,
  reporting_type: 'FFS_STANDALONE',
  reported_diagnosis_code: '',
});

const formData = ref({
  admission_id: null,
  claim_date: new Date().toISOString().split('T')[0],
});

// Headers
const paCodeHeaders = [
  { title: 'PA Code', key: 'code', sortable: false },
  { title: 'Type', key: 'type', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Justification', key: 'justification', sortable: false },
];

const lineItemHeaders = [
  { title: 'Service Description', key: 'service_description', sortable: false },
  { title: 'Type', key: 'tariff_type', sortable: false },
  { title: 'Quantity', key: 'quantity', sortable: false },
  { title: 'Unit Price', key: 'unit_price', sortable: false },
  { title: 'Line Total', key: 'line_total', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

// Options
const serviceTypes = [
  { text: 'Service', value: 'service' },
  { text: 'Drug', value: 'drug' },
  { text: 'Bundle Component', value: 'bundle_component' },
];

const reportingTypes = [
  { text: 'In Bundle', value: 'IN_BUNDLE' },
  { text: 'FFS Top-Up', value: 'FFS_TOP_UP' },
  { text: 'FFS Standalone', value: 'FFS_STANDALONE' },
];

// Computed
const validationChecks = computed(() => {
  if (!selectedAdmission.value) {
    return {
      hasValidatedUTN: false,
      isDischarged: false,
      hasApprovedPA: false,
      bundleMatchesDiagnosis: false,
    };
  }

  return {
    hasValidatedUTN: selectedAdmission.value.referral?.utn_validated || false,
    isDischarged: selectedAdmission.value.status === 'discharged',
    hasApprovedPA: approvedPACodes.value.length > 0,
    bundleMatchesDiagnosis: selectedAdmission.value.bundle_id ? true : false,
  };
});

const totalClaimAmount = computed(() => {
  return claimLineItems.value.reduce((sum, item) => sum + Number(item.line_total), 0);
});

const canSubmit = computed(() => {
  return (
    formData.value.admission_id &&
    formData.value.claim_date &&
    validationChecks.value.hasValidatedUTN &&
    validationChecks.value.isDischarged &&
    validationChecks.value.hasApprovedPA &&
    claimLineItems.value.length > 0
  );
});

// Methods
const fetchDischargedAdmissions = async () => {
  loadingAdmissions.value = true;
  try {
    const response = await api.get('/claims-automation/admissions', {
      params: {
        status: 'discharged',
      }
    });
    const admissions = response.data?.data?.data || response.data?.data || response.data || [];

    // Filter admissions that have validated UTN and no existing claim
    dischargedAdmissions.value = admissions.filter(admission => {
      return admission.referral?.utn_validated && !admission.claim;
    }).map(admission => ({
      ...admission,
      display_text: `${admission.admission_code} - ${admission.enrollee?.first_name} ${admission.enrollee?.last_name}`,
    }));
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch discharged admissions');
    console.error(err);
  } finally {
    loadingAdmissions.value = false;
  }
};

const onAdmissionSelected = async (admissionId) => {
  selectedAdmission.value = dischargedAdmissions.value.find(a => a.id === admissionId);

  if (!selectedAdmission.value) {
    return;
  }

  // Fetch approved PA codes for this admission
  try {
    const response = await api.get('/pas/pa-codes', {
      params: {
        admission_id: admissionId,
        status: 'APPROVED',
      }
    });
    approvedPACodes.value = response.data?.data || response.data || [];
  } catch (err) {
    showError('Failed to fetch PA codes');
    console.error(err);
    approvedPACodes.value = [];
  }

  // Validate business rules
  if (!selectedAdmission.value.referral?.utn_validated) {
    showWarning('Warning: UTN has not been validated for this admission');
  }

  if (selectedAdmission.value.status !== 'discharged') {
    showWarning('Warning: Patient must be discharged before submitting a claim');
  }

  if (approvedPACodes.value.length === 0) {
    showWarning('Warning: No approved PA codes found for this admission');
  }
};

const openAddLineItemDialog = () => {
  showLineItemDialog.value = true;
};

const closeLineItemDialog = () => {
  showLineItemDialog.value = false;
  lineItemFormData.value = {
    pa_code_id: null,
    tariff_type: 'FFS',
    service_type: 'service',
    service_description: '',
    quantity: 1,
    unit_price: 0,
    line_total: 0,
    reporting_type: 'FFS_STANDALONE',
    reported_diagnosis_code: '',
  };
  lineItemForm.value?.reset();
};

const onPACodeSelected = (paCodeId) => {
  const selectedPA = approvedPACodes.value.find(pa => pa.id === paCodeId);
  if (selectedPA) {
    // Auto-set tariff type based on PA type
    lineItemFormData.value.tariff_type = selectedPA.type === 'BUNDLE' ? 'BUNDLE' : 'FFS';
    lineItemFormData.value.reporting_type = selectedPA.type === 'BUNDLE' ? 'IN_BUNDLE' : 'FFS_STANDALONE';
  }
};

const calculateLineTotal = () => {
  const quantity = Number(lineItemFormData.value.quantity) || 0;
  const unitPrice = Number(lineItemFormData.value.unit_price) || 0;
  lineItemFormData.value.line_total = (quantity * unitPrice).toFixed(2);
};

const addLineItem = async () => {
  const { valid } = await lineItemForm.value?.validate();
  if (!valid) {
    showError('Please fill in all required fields');
    return;
  }

  // Add line item to the list
  claimLineItems.value.push({
    ...lineItemFormData.value,
    pa_code: approvedPACodes.value.find(pa => pa.id === lineItemFormData.value.pa_code_id),
  });

  showSuccess('Line item added successfully');
  closeLineItemDialog();
};

const removeLineItem = (index) => {
  claimLineItems.value.splice(index, 1);
  showSuccess('Line item removed');
};

const submitClaim = async () => {
  const { valid } = await claimForm.value?.validate();
  if (!valid) {
    showError('Please fill in all required fields');
    return;
  }

  if (!canSubmit.value) {
    showError('Claim validation failed. Please check all requirements and add at least one line item.');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      admission_id: formData.value.admission_id,
      claim_date: formData.value.claim_date,
      line_items: claimLineItems.value.map(item => ({
        pa_code_id: item.pa_code_id,
        tariff_type: item.tariff_type,
        service_type: item.service_type,
        service_description: item.service_description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        line_total: item.line_total,
        reporting_type: item.reporting_type,
        reported_diagnosis_code: item.reported_diagnosis_code,
      })),
    };

    const response = await api.post('/claims-automation/claims', payload);
    const claim = response.data?.data;

    showSuccess(`Claim created successfully! Claim Number: ${claim.claim_number}`);
    resetForm();
    await fetchDischargedAdmissions();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to create claim');
    console.error(err);
  } finally {
    submitting.value = false;
  }
};

const resetForm = () => {
  selectedAdmission.value = null;
  approvedPACodes.value = [];
  claimLineItems.value = [];
  formData.value = {
    admission_id: null,
    claim_date: new Date().toISOString().split('T')[0],
  };
  claimForm.value?.reset();
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

onMounted(async () => {
  await fetchDischargedAdmissions();
});
</script>

<style scoped>
.facility-claim-submission-page {
  padding: 20px 0;
}
</style>

