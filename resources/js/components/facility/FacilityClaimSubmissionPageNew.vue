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
                  Submit claims for referrals with validated UTN
                </p>
              </div>
            </div>
          </v-col>
        </v-row>

        <!-- Episode Workflow Info -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-alert type="info" variant="tonal" density="comfortable">
              <div class="d-flex align-center">
                <v-icon size="24" class="mr-3">mdi-information</v-icon>
                <div>
                  <strong>Episode Workflow:</strong> Referral (Approved) → UTN Validation → [Optional: Admission → FU-PA Code] → <strong class="text-primary">Claim Submission</strong>
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
                  <li>Admission is <strong>OPTIONAL</strong> (not all referrals require admission)</li>
                  <li>Bundle claims require admission with service_bundle_id</li>
                  <li>FFS line items require <strong>approved PA codes</strong></li>
                  <li>At least one of bundle or FFS must be present</li>
                </ul>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- 4-Step Wizard -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-stepper v-model="currentStep" alt-labels>
                <v-stepper-header>
                  <v-stepper-item :complete="currentStep > 1" :value="1" title="Select Referral/UTN"></v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item :complete="currentStep > 2" :value="2" title="Admission & Bundle"></v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item :complete="currentStep > 3" :value="3" title="Add FFS Line Items"></v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item :complete="currentStep > 4" :value="4" title="Review & Submit"></v-stepper-item>
                </v-stepper-header>

                <v-stepper-window>
                  <!-- Step 1: Select Referral/UTN -->
                  <v-stepper-window-item :value="1">
                    <v-card-text class="pa-6">
                      <h3 class="text-h6 mb-4">Step 1: Select Referral with Validated UTN</h3>
                      
                      <v-autocomplete
                        v-model="selectedReferralId"
                        :items="approvedReferrals"
                        :loading="loadingReferrals"
                        item-title="display_text"
                        item-value="id"
                        label="Search and Select Referral (UTN)"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-magnify"
                        @update:model-value="onReferralSelected"
                      >
                        <template #item="{ props, item }">
                          <v-list-item v-bind="props">
                            <template #prepend>
                              <v-icon color="success">mdi-check-circle</v-icon>
                            </template>
                            <template #title>
                              <strong>UTN: {{ item.raw.utn }}</strong>
                            </template>
                            <template #subtitle>
                              {{ item.raw.enrollee_full_name }} | {{ item.raw.referral_code }}
                            </template>
                          </v-list-item>
                        </template>
                      </v-autocomplete>

                      <!-- Claim Check Status -->
                      <v-alert v-if="claimCheckStatus === 'checking'" type="info" variant="outlined" class="mt-4">
                        <v-progress-circular indeterminate size="20" width="2" class="mr-2"></v-progress-circular>
                        Checking if claim already exists...
                      </v-alert>

                      <v-alert v-if="claimCheckStatus === 'exists'" type="error" variant="outlined" class="mt-4">
                        <strong>Claim Already Submitted</strong><br>
                        A claim has already been submitted for this referral (UTN: {{ selectedReferral?.utn }}).
                        <div class="mt-2">
                          <strong>Claim Number:</strong> {{ existingClaim?.claim_number }}<br>
                          <strong>Status:</strong> {{ existingClaim?.status }}
                        </div>
                      </v-alert>

                      <v-alert v-if="claimCheckStatus === 'none'" type="success" variant="outlined" class="mt-4">
                        <v-icon class="mr-2">mdi-check-circle</v-icon>
                        No claim submitted yet. You can proceed with claim submission.
                      </v-alert>

                      <!-- Referral Details -->
                      <v-card v-if="selectedReferral && claimCheckStatus === 'none'" class="mt-4" elevation="1">
                        <v-card-title class="bg-grey-lighten-4">Referral Details</v-card-title>
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
                          </v-row>
                        </v-card-text>
                      </v-card>
                    </v-card-text>
                  </v-stepper-window-item>

                  <!-- Step 2: Admission & Bundle Information -->
                  <v-stepper-window-item :value="2">
                    <v-card-text class="pa-6">
                      <h3 class="text-h6 mb-4">Step 2: Admission & Bundle Information (Optional)</h3>

                      <!-- Admission Status -->
                      <v-alert v-if="!admission" type="info" variant="outlined" class="mb-4">
                        <strong>No Admission Found</strong><br>
                        No admission found for this referral. You can still submit an FFS-only claim.
                      </v-alert>

                      <!-- Admission Details (if exists) -->
                      <v-card v-if="admission" class="mb-4" elevation="1">
                        <v-card-title class="bg-grey-lighten-4">Admission Details</v-card-title>
                        <v-card-text class="pa-4">
                          <v-row>
                            <v-col cols="12" md="6">
                              <v-text-field
                                :model-value="admission.admission_code"
                                label="Admission Code"
                                variant="outlined"
                                density="comfortable"
                                readonly
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                :model-value="formatDate(admission.admission_date)"
                                label="Admission Date"
                                variant="outlined"
                                density="comfortable"
                                readonly
                              />
                            </v-col>
                            <v-col cols="12">
                              <v-text-field
                                :model-value="admission.principal_diagnosis_icd10"
                                label="Principal Diagnosis (ICD-10)"
                                variant="outlined"
                                density="comfortable"
                                readonly
                              />
                            </v-col>
                          </v-row>
                        </v-card-text>
                      </v-card>

                      <!-- Bundle Information (if exists) -->
                      <v-card v-if="admission && admission.service_bundle" class="mb-4" elevation="1" color="success-lighten-5">
                        <v-card-title class="bg-success-lighten-4">
                          <v-icon class="mr-2" color="success">mdi-package-variant</v-icon>
                          Service Bundle
                        </v-card-title>
                        <v-card-text class="pa-4">
                          <v-row>
                            <v-col cols="12" md="8">
                              <v-text-field
                                :model-value="admission.service_bundle.description || admission.service_bundle.name"
                                label="Bundle Description"
                                variant="outlined"
                                density="comfortable"
                                readonly
                              />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field
                                :model-value="'₦' + Number(admission.service_bundle.fixed_price).toLocaleString()"
                                label="Bundle Amount"
                                variant="outlined"
                                density="comfortable"
                                readonly
                                class="font-weight-bold"
                              />
                            </v-col>
                          </v-row>

                          <v-alert type="success" variant="tonal" density="compact" class="mt-2">
                            This bundle amount will be automatically included in the claim total.
                          </v-alert>
                        </v-card-text>
                      </v-card>

                      <!-- No Bundle Alert -->
                      <v-alert v-if="admission && !admission.service_bundle" type="warning" variant="outlined" class="mb-4">
                        <strong>No Service Bundle</strong><br>
                        This admission does not have a service bundle. You can add FFS line items in the next step.
                      </v-alert>
                    </v-card-text>
                  </v-stepper-window-item>

                  <!-- Step 3: Add FFS Line Items -->
                  <v-stepper-window-item :value="3">
                    <v-card-text class="pa-6">
                      <h3 class="text-h6 mb-4">Step 3: Add FFS Line Items (Optional)</h3>

                      <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                        Add Fee-For-Service (FFS) line items. Each line item must be linked to an approved PA code.
                      </v-alert>

                      <!-- Add Line Item Button -->
                      <v-btn
                        color="primary"
                        prepend-icon="mdi-plus"
                        @click="openLineItemDialog"
                        class="mb-4"
                      >
                        Add FFS Line Item
                      </v-btn>

                      <!-- Line Items Table -->
                      <v-data-table
                        v-if="claimLineItems.length > 0"
                        :headers="lineItemHeaders"
                        :items="claimLineItems"
                        class="elevation-1"
                      >
                        <template #item.unit_price="{ item }">
                          ₦{{ Number(item.unit_price).toLocaleString() }}
                        </template>
                        <template #item.total_price="{ item }">
                          ₦{{ Number(item.total_price).toLocaleString() }}
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
                      </v-data-table>

                      <v-alert v-else type="warning" variant="outlined" class="mt-4">
                        No FFS line items added yet. Click "Add FFS Line Item" to add services.
                      </v-alert>

                      <!-- FFS Total -->
                      <v-card v-if="claimLineItems.length > 0" class="mt-4" elevation="1" color="primary-lighten-5">
                        <v-card-text class="pa-4">
                          <div class="d-flex justify-space-between align-center">
                            <strong class="text-h6">FFS Total:</strong>
                            <strong class="text-h5 text-primary">₦{{ ffsTotal.toLocaleString() }}</strong>
                          </div>
                        </v-card-text>
                      </v-card>
                    </v-card-text>
                  </v-stepper-window-item>

                  <!-- Step 4: Review & Submit -->
                  <v-stepper-window-item :value="4">
                    <v-card-text class="pa-6">
                      <h3 class="text-h6 mb-4">Step 4: Review & Submit Claim</h3>

                      <!-- Claim Summary -->
                      <v-card class="mb-4" elevation="1">
                        <v-card-title class="bg-grey-lighten-4">Claim Summary</v-card-title>
                        <v-card-text class="pa-4">
                          <v-row>
                            <v-col cols="12" md="6">
                              <div class="mb-3">
                                <strong>UTN:</strong> {{ selectedReferral?.utn }}
                              </div>
                              <div class="mb-3">
                                <strong>Patient:</strong> {{ selectedReferral?.enrollee_full_name }}
                              </div>
                              <div class="mb-3">
                                <strong>Referral Code:</strong> {{ selectedReferral?.referral_code }}
                              </div>
                            </v-col>
                            <v-col cols="12" md="6">
                              <div class="mb-3">
                                <strong>Admission:</strong> {{ admission ? admission.admission_code : 'N/A (FFS-only claim)' }}
                              </div>
                              <div class="mb-3">
                                <strong>Bundle Amount:</strong> ₦{{ bundleAmount.toLocaleString() }}
                              </div>
                              <div class="mb-3">
                                <strong>FFS Amount:</strong> ₦{{ ffsTotal.toLocaleString() }}
                              </div>
                            </v-col>
                          </v-row>
                        </v-card-text>
                      </v-card>

                      <!-- Total Amount Card -->
                      <v-card class="mb-4" elevation="2" color="success-lighten-5">
                        <v-card-text class="pa-6">
                          <div class="d-flex justify-space-between align-center">
                            <strong class="text-h5">Total Claim Amount:</strong>
                            <strong class="text-h4 text-success">₦{{ totalClaimAmount.toLocaleString() }}</strong>
                          </div>
                        </v-card-text>
                      </v-card>

                      <!-- Validation Alerts -->
                      <v-alert v-if="validationErrors.length > 0" type="error" variant="outlined" class="mb-4">
                        <strong>Please fix the following errors:</strong>
                        <ul class="ml-4 mt-2">
                          <li v-for="(error, index) in validationErrors" :key="index">{{ error }}</li>
                        </ul>
                      </v-alert>

                      <v-alert v-else type="success" variant="tonal" class="mb-4">
                        <v-icon class="mr-2">mdi-check-circle</v-icon>
                        All validations passed. You can submit the claim.
                      </v-alert>
                    </v-card-text>
                  </v-stepper-window-item>
                </v-stepper-window>

                <!-- Stepper Actions -->
                <v-card-actions class="pa-4">
                  <v-btn
                    v-if="currentStep > 1"
                    variant="text"
                    @click="currentStep--"
                  >
                    Back
                  </v-btn>
                  <v-spacer></v-spacer>
                  <v-btn
                    v-if="currentStep < 4"
                    color="primary"
                    variant="elevated"
                    @click="nextStep"
                    :disabled="!canProceedToNextStep"
                  >
                    Next
                  </v-btn>
                  <v-btn
                    v-if="currentStep === 4"
                    color="success"
                    variant="elevated"
                    @click="submitClaim"
                    :loading="submitting"
                    :disabled="validationErrors.length > 0"
                  >
                    Submit Claim
                  </v-btn>
                </v-card-actions>
              </v-stepper>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Add Line Item Dialog -->
      <v-dialog v-model="showLineItemDialog" max-width="800px" persistent>
        <v-card>
          <v-card-title class="bg-primary text-white">
            <v-icon class="mr-2">mdi-plus-circle</v-icon>
            Add FFS Line Item
          </v-card-title>
          <v-card-text class="pa-6">
            <v-form ref="lineItemForm">
              <v-row>
                <!-- PA Code Selection -->
                <v-col cols="12">
                  <v-autocomplete
                    v-model="lineItemFormData.pa_code_id"
                    :items="approvedPACodes"
                    :loading="loadingPACodes"
                    item-title="display_text"
                    item-value="id"
                    label="Select Approved PA Code *"
                    variant="outlined"
                    density="comfortable"
                    :rules="[v => !!v || 'PA Code is required']"
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #prepend>
                          <v-icon color="success">mdi-check-decagram</v-icon>
                        </template>
                        <template #title>
                          <strong>{{ item.raw.code }}</strong>
                        </template>
                        <template #subtitle>
                          {{ item.raw.type }} | {{ item.raw.justification }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>

                <!-- Service Description -->
                <v-col cols="12">
                  <v-textarea
                    v-model="lineItemFormData.service_description"
                    label="Service Description *"
                    variant="outlined"
                    density="comfortable"
                    rows="3"
                    :rules="[v => !!v || 'Service description is required']"
                  ></v-textarea>
                </v-col>

                <!-- Quantity -->
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model.number="lineItemFormData.quantity"
                    label="Quantity *"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="1"
                    :rules="[v => v > 0 || 'Quantity must be greater than 0']"
                    @update:model-value="calculateLineTotal"
                  ></v-text-field>
                </v-col>

                <!-- Unit Price -->
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model.number="lineItemFormData.unit_price"
                    label="Unit Price (₦) *"
                    variant="outlined"
                    density="comfortable"
                    type="number"
                    min="0"
                    :rules="[v => v >= 0 || 'Unit price must be 0 or greater']"
                    @update:model-value="calculateLineTotal"
                  ></v-text-field>
                </v-col>

                <!-- Total Price (Calculated) -->
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="'₦' + lineItemFormData.total_price.toLocaleString()"
                    label="Total Price"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    class="font-weight-bold"
                  ></v-text-field>
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="grey" variant="text" @click="closeLineItemDialog">Cancel</v-btn>
            <v-btn color="primary" variant="elevated" @click="addLineItem">Add Line Item</v-btn>
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
const currentStep = ref(1);
const loadingReferrals = ref(false);
const loadingPACodes = ref(false);
const submitting = ref(false);
const lineItemForm = ref(null);

const approvedReferrals = ref([]);
const selectedReferralId = ref(null);
const selectedReferral = ref(null);
const admission = ref(null);
const approvedPACodes = ref([]);
const claimLineItems = ref([]);
const claimCheckStatus = ref(null); // 'checking', 'exists', 'none'
const existingClaim = ref(null);

const showLineItemDialog = ref(false);
const lineItemFormData = ref({
  pa_code_id: null,
  service_description: '',
  quantity: 1,
  unit_price: 0,
  total_price: 0,
});

// Line Item Table Headers
const lineItemHeaders = [
  { title: 'Service Description', key: 'service_description', sortable: false },
  { title: 'Quantity', key: 'quantity', sortable: false },
  { title: 'Unit Price', key: 'unit_price', sortable: false },
  { title: 'Total Price', key: 'total_price', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

// Computed Properties
const bundleAmount = computed(() => {
  if (admission.value && admission.value.service_bundle) {
    return Number(admission.value.service_bundle.fixed_price) || 0;
  }
  return 0;
});

const ffsTotal = computed(() => {
  return claimLineItems.value.reduce((sum, item) => sum + Number(item.total_price), 0);
});

const totalClaimAmount = computed(() => {
  return bundleAmount.value + ffsTotal.value;
});

const canProceedToNextStep = computed(() => {
  if (currentStep.value === 1) {
    return selectedReferralId.value && claimCheckStatus.value === 'none';
  }
  return true;
});

const validationErrors = computed(() => {
  const errors = [];

  if (!selectedReferralId.value) {
    errors.push('Please select a referral with validated UTN');
  }

  if (claimCheckStatus.value === 'exists') {
    errors.push('A claim has already been submitted for this referral');
  }

  if (totalClaimAmount.value <= 0) {
    errors.push('Claim must have at least one of: bundle amount or FFS line items');
  }

  return errors;
});

// Methods
const fetchApprovedReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await api.get('/referrals', {
      params: {
        status: 'APPROVED',
        utn_validated: true,
        facility_requested: true,
      }
    });

    if (response.data.success) {
      approvedReferrals.value = response.data.data.map(ref => ({
        ...ref,
        display_text: `${ref.utn} - ${ref.enrollee_full_name} (${ref.referral_code})`,
      }));
    }
  } catch (error) {
    showError('Failed to load referrals: ' + (error.response?.data?.message || error.message));
  } finally {
    loadingReferrals.value = false;
  }
};

const onReferralSelected = async (referralId) => {
  if (!referralId) return;

  // Reset state
  admission.value = null;
  approvedPACodes.value = [];
  claimLineItems.value = [];
  claimCheckStatus.value = 'checking';
  existingClaim.value = null;

  // Find selected referral
  selectedReferral.value = approvedReferrals.value.find(r => r.id === referralId);

  // Check if claim already exists
  try {
    const claimResponse = await api.get('/claims', {
      params: { referral_id: referralId }
    });

    if (claimResponse.data.success && claimResponse.data.data.length > 0) {
      claimCheckStatus.value = 'exists';
      existingClaim.value = claimResponse.data.data[0];
      showWarning('A claim has already been submitted for this referral');
      return;
    }

    claimCheckStatus.value = 'none';
  } catch (error) {
    showError('Failed to check claim status: ' + (error.response?.data?.message || error.message));
    claimCheckStatus.value = null;
    return;
  }

  // Fetch admission (if exists)
  try {
    const admissionResponse = await api.get('/claims-automation/admissions', {
      params: { referral_id: referralId }
    });

    if (admissionResponse.data.success && admissionResponse.data.data.length > 0) {
      admission.value = admissionResponse.data.data[0];
    }
  } catch (error) {
    console.log('No admission found (this is OK for FFS-only claims)');
  }

  // Fetch approved PA codes for this referral
  fetchApprovedPACodes(referralId);
};

const fetchApprovedPACodes = async (referralId) => {
  loadingPACodes.value = true;
  try {
    const response = await api.get('/pas/pa-codes', {
      params: {
        referral_id: referralId,
        status: 'APPROVED',
      }
    });

    if (response.data.success) {
      approvedPACodes.value = response.data.data.map(pa => ({
        ...pa,
        display_text: `${pa.code} - ${pa.type} (${pa.justification})`,
      }));
    }
  } catch (error) {
    showError('Failed to load PA codes: ' + (error.response?.data?.message || error.message));
  } finally {
    loadingPACodes.value = false;
  }
};

const nextStep = () => {
  if (canProceedToNextStep.value) {
    currentStep.value++;
  }
};

const openLineItemDialog = () => {
  showLineItemDialog.value = true;
  lineItemFormData.value = {
    pa_code_id: null,
    service_description: '',
    quantity: 1,
    unit_price: 0,
    total_price: 0,
  };
};

const closeLineItemDialog = () => {
  showLineItemDialog.value = false;
  if (lineItemForm.value) {
    lineItemForm.value.reset();
  }
};

const calculateLineTotal = () => {
  lineItemFormData.value.total_price = lineItemFormData.value.quantity * lineItemFormData.value.unit_price;
};

const addLineItem = async () => {
  if (!lineItemForm.value) return;

  const { valid } = await lineItemForm.value.validate();
  if (!valid) return;

  claimLineItems.value.push({ ...lineItemFormData.value });
  showSuccess('Line item added successfully');
  closeLineItemDialog();
};

const removeLineItem = (index) => {
  claimLineItems.value.splice(index, 1);
  showSuccess('Line item removed');
};



