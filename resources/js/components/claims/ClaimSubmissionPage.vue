<template>
  <AdminLayout>
    <div class="claim-submission-page">
      <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white">
              <v-icon left>mdi-file-document-plus</v-icon>
              Submit Claim
            </v-card-title>
            <v-card-text>
              <v-stepper v-model="currentStep" alt-labels>
                <!-- Step 1: Authorization & Claim Header -->
                <v-stepper-header>
                  <v-stepper-item :complete="currentStep > 1" step="1">
                    Authorization
                  </v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item :complete="currentStep > 2" step="2">
                    Claim Header
                  </v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item :complete="currentStep > 3" step="3">
                    Claim Lines
                  </v-stepper-item>
                  <v-divider></v-divider>
                  <v-stepper-item step="4">
                    Review & Submit
                  </v-stepper-item>
                </v-stepper-header>

                <v-stepper-items>
                  <!-- Step 1: Authorization Context -->
                  <v-stepper-content step="1">
                    <v-form ref="authForm">
                      <v-alert type="info" class="mb-4">
                        <strong>Step 1: Authorization</strong><br>
                        Enter the UTN (Unique Transaction Number) from your approved Referral to load authorization context.
                      </v-alert>

                      <v-row>
                        <v-col cols="12" md="8">
                          <v-autocomplete
                            v-model="formData.referral_id"
                            label="UTN / Authorization Key *"
                            :items="referrals"
                            item-title="utn"
                            item-value="id"
                            outlined
                            required
                            :rules="[v => !!v || 'UTN/Authorization is required']"
                            @update:modelValue="onReferralSelected"
                            :loading="loadingReferrals"
                            :search-input.sync="referralSearch"
                            clearable
                            hint="Search by UTN or Enrollee name"
                          >
                            <template v-slot:item="{ item }">
                              <div class="py-2">
                                <div><strong>{{ item.utn }}</strong></div>
                                <div class="text-caption">{{ item.enrollee_name }} | Status: {{ item.status }}</div>
                              </div>
                            </template>
                          </v-autocomplete>
                        </v-col>
                      </v-row>

                      <!-- Authorization Context Display -->
                      <v-row v-if="selectedReferral" class="mt-4">
                        <v-col cols="12">
                          <v-card class="bg-light-blue">
                            <v-card-title class="text-subtitle2">Authorization Context</v-card-title>
                            <v-card-text>
                              <v-row>
                                <v-col cols="12" md="6">
                                  <div><strong>Enrollee:</strong> {{ selectedReferral.enrollee_name }}</div>
                                  <div><strong>Referral Status:</strong>
                                    <v-chip :color="getReferralStatusColor(selectedReferral.status)" text-color="white" small>
                                      {{ selectedReferral.status }}
                                    </v-chip>
                                  </div>
                                </v-col>
                                <v-col cols="12" md="6">
                                  <div><strong>Approved PA Codes:</strong> {{ selectedReferral.pa_codes_count || 0 }}</div>
                                  <div><strong>Approval Date:</strong> {{ formatDate(selectedReferral.approval_date) }}</div>
                                </v-col>
                              </v-row>
                            </v-card-text>
                          </v-card>
                        </v-col>
                      </v-row>

                      <v-btn color="primary" @click="currentStep = 2" :disabled="!formData.referral_id" class="mt-4">
                        Next
                        <v-icon right>mdi-arrow-right</v-icon>
                      </v-btn>
                    </v-form>
                  </v-stepper-content>

                  <!-- Step 2: Claim Header -->
                  <v-stepper-content step="2">
                    <v-form ref="headerForm">
                      <v-alert type="info" class="mb-4">
                        <strong>Step 2: Claim Header</strong><br>
                        Fill in the admission and claim details. Total amount will be calculated from line items.
                      </v-alert>

                      <v-row>
                        <v-col cols="12" md="6">
                          <v-select
                            v-model="formData.admission_id"
                            label="Admission *"
                            :items="admissions"
                            item-title="admission_number"
                            item-value="id"
                            outlined
                            required
                            :rules="[v => !!v || 'Admission is required']"
                            hint="Select the admission linked to this claim"
                          />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model="formData.claim_date"
                            label="Claim Date *"
                            type="date"
                            outlined
                            required
                            :rules="[v => !!v || 'Claim date is required']"
                          />
                        </v-col>
                      </v-row>

                      <v-row>
                        <v-col cols="12" md="6">
                          <v-select
                            v-model="formData.claim_type"
                            label="Claim Type *"
                            :items="claimTypes"
                            outlined
                            required
                            hint="BUNDLE: Fixed price | FFS: Fee-for-service | HYBRID: Mix of both"
                          />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field
                            v-model.number="calculatedTotal"
                            label="Total Amount (Auto-calculated)"
                            type="number"
                            outlined
                            readonly
                            :value="calculatedTotal"
                            hint="Automatically calculated from line items"
                          />
                        </v-col>
                      </v-row>

                      <v-row>
                        <v-col cols="12">
                          <v-textarea
                            v-model="formData.clinical_summary"
                            label="Discharge Summary / Clinical Notes *"
                            outlined
                            rows="4"
                            required
                            :rules="[v => !!v || 'Clinical summary is required']"
                            hint="Provide discharge summary or clinical notes for this claim"
                            counter
                            maxlength="1000"
                          />
                        </v-col>
                      </v-row>

                      <v-row class="mt-4">
                        <v-col cols="12" class="d-flex gap-2">
                          <v-btn color="secondary" @click="currentStep = 1">
                            <v-icon left>mdi-arrow-left</v-icon>
                            Back
                          </v-btn>
                          <v-btn color="primary" @click="currentStep = 3">
                            Next
                            <v-icon right>mdi-arrow-right</v-icon>
                          </v-btn>
                        </v-col>
                      </v-row>
                    </v-form>
                  </v-stepper-content>

                  <!-- Step 3: Claim Line Items -->
                  <v-stepper-content step="3">
                    <v-alert type="info" class="mb-4">
                      <strong>Step 3: Claim Line Items</strong><br>
                      Add services, drugs, or labs. Each line must be linked to an approved PA Code.
                    </v-alert>

                    <v-card class="mb-4">
                      <v-card-title>Claim Line Items</v-card-title>
                      <v-card-text>
                        <!-- Line Items Table -->
                        <div class="table-responsive">
                          <table class="claim-lines-table">
                            <thead>
                              <tr>
                                <th>Service Code</th>
                                <th>Description</th>
                                <th>PA Code</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                                <th>Reporting Type</th>
                                <th>Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(line, index) in formData.claim_lines" :key="index">
                                <!-- Service Code (Searchable Dropdown) -->
                                <td>
                                  <v-autocomplete
                                    v-model="line.case_record_id"
                                    :items="caseRecords"
                                    item-title="nicare_code"
                                    item-value="id"
                                    outlined
                                    dense
                                    required
                                    @update:modelValue="onServiceSelected(index, $event)"
                                    :loading="loadingServices"
                                    hint="Search service code"
                                  />
                                </td>

                                <!-- Description (Read-only) -->
                                <td>
                                  <v-text-field
                                    v-model="line.service_description"
                                    outlined
                                    dense
                                    readonly
                                    hint="Auto-populated"
                                  />
                                </td>

                                <!-- PA Code (Dropdown) -->
                                <td>
                                  <v-select
                                    v-model="line.pa_code_id"
                                    :items="availablePACodes"
                                    item-title="code"
                                    item-value="id"
                                    outlined
                                    dense
                                    required
                                    :rules="[v => !!v || 'PA Code required']"
                                    hint="Select PA Code"
                                  />
                                </td>

                                <!-- Quantity -->
                                <td>
                                  <v-text-field
                                    v-model.number="line.quantity"
                                    type="number"
                                    outlined
                                    dense
                                    min="1"
                                    @input="calculateLineTotal(index)"
                                  />
                                </td>

                                <!-- Unit Price (Read-only) -->
                                <td>
                                  <v-text-field
                                    v-model.number="line.unit_price"
                                    type="number"
                                    outlined
                                    dense
                                    readonly
                                    hint="From tariff"
                                  />
                                </td>

                                <!-- Line Total (Read-only) -->
                                <td>
                                  <v-text-field
                                    v-model.number="line.line_total"
                                    type="number"
                                    outlined
                                    dense
                                    readonly
                                    class="font-weight-bold"
                                  />
                                </td>

                                <!-- Reporting Type -->
                                <td>
                                  <v-select
                                    v-model="line.reporting_type"
                                    :items="reportingTypes"
                                    outlined
                                    dense
                                    required
                                    hint="IN_BUNDLE, FFS_TOP_UP, etc."
                                  />
                                </td>

                                <!-- Actions -->
                                <td>
                                  <v-btn
                                    icon
                                    small
                                    color="error"
                                    @click="removeClaimLine(index)"
                                    title="Delete line"
                                  >
                                    <v-icon>mdi-delete</v-icon>
                                  </v-btn>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <v-btn color="success" @click="addClaimLine" class="mt-4">
                          <v-icon left>mdi-plus</v-icon>
                          Add Line Item
                        </v-btn>
                      </v-card-text>
                    </v-card>

                    <v-row class="mt-4">
                      <v-col cols="12" class="d-flex gap-2">
                        <v-btn color="secondary" @click="currentStep = 2">
                          <v-icon left>mdi-arrow-left</v-icon>
                          Back
                        </v-btn>
                        <v-btn color="primary" @click="currentStep = 4">
                          Next
                          <v-icon right>mdi-arrow-right</v-icon>
                        </v-btn>
                      </v-col>
                    </v-row>
                  </v-stepper-content>

                  <!-- Step 4: Review & Submit -->
                  <v-stepper-content step="4">
                    <v-alert type="info" class="mb-4">
                      <strong>Step 4: Review & Submit</strong><br>
                      Review all claim details before submission. Once submitted, the claim will be sent for approval.
                    </v-alert>

                    <v-card class="mb-4">
                      <v-card-title>Claim Summary</v-card-title>
                      <v-card-text>
                        <v-row>
                          <v-col cols="12" md="6">
                            <div class="mb-3">
                              <strong>UTN:</strong> {{ selectedReferral?.utn }}
                            </div>
                            <div class="mb-3">
                              <strong>Enrollee:</strong> {{ selectedReferral?.enrollee_name }}
                            </div>
                            <div class="mb-3">
                              <strong>Claim Date:</strong> {{ formData.claim_date }}
                            </div>
                          </v-col>
                          <v-col cols="12" md="6">
                            <div class="mb-3">
                              <strong>Claim Type:</strong> {{ formData.claim_type }}
                            </div>
                            <div class="mb-3">
                              <strong>Total Amount:</strong>
                              <span class="text-h6 text-primary">{{ calculatedTotal }}</span>
                            </div>
                            <div class="mb-3">
                              <strong>Line Items:</strong> {{ formData.claim_lines.length }}
                            </div>
                          </v-col>
                        </v-row>

                        <v-divider class="my-4"></v-divider>

                        <v-card-title class="text-subtitle2">Line Items Summary</v-card-title>
                        <v-simple-table>
                          <template v-slot:default>
                            <thead>
                              <tr>
                                <th>Service</th>
                                <th>PA Code</th>
                                <th>Qty</th>
                                <th>Amount</th>
                                <th>Type</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(line, idx) in formData.claim_lines" :key="idx">
                                <td>{{ line.service_description }}</td>
                                <td>{{ line.pa_code_id }}</td>
                                <td>{{ line.quantity }}</td>
                                <td>{{ line.line_total }}</td>
                                <td>
                                  <v-chip :color="getReportingTypeColor(line.reporting_type)" text-color="white" small>
                                    {{ line.reporting_type }}
                                  </v-chip>
                                </td>
                              </tr>
                            </tbody>
                          </template>
                        </v-simple-table>
                      </v-card-text>
                    </v-card>

                    <v-row class="mt-4">
                      <v-col cols="12" class="d-flex gap-2">
                        <v-btn color="secondary" @click="currentStep = 3">
                          <v-icon left>mdi-arrow-left</v-icon>
                          Back
                        </v-btn>
                        <v-btn
                          color="primary"
                          :loading="loading"
                          @click="submitClaim"
                        >
                          <v-icon left>mdi-check</v-icon>
                          Submit Claim
                        </v-btn>
                      </v-col>
                    </v-row>
                  </v-stepper-content>
                </v-stepper-items>
              </v-stepper>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import { useClaimsAPI } from '../../composables/useClaimsAPI';
import api from '../../utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const router = useRouter();
const { showSuccess, showError } = useToast();
const { createClaim, fetchAdmissions, loading } = useClaimsAPI();

const currentStep = ref(1);
const authForm = ref(null);
const headerForm = ref(null);
const admissions = ref([]);
const referrals = ref([]);
const caseRecords = ref([]);
const selectedReferral = ref(null);
const referralSearch = ref('');
const loadingReferrals = ref(false);
const loadingServices = ref(false);

const claimTypes = [
  { title: 'Bundle', value: 'BUNDLE' },
  { title: 'FFS (Fee-For-Service)', value: 'FFS' },
  { title: 'Hybrid', value: 'HYBRID' },
];

const reportingTypes = [
  { title: 'In Bundle', value: 'IN_BUNDLE' },
  { title: 'FFS Top-Up', value: 'FFS_TOP_UP' },
  { title: 'FFS Standalone', value: 'FFS_STANDALONE' },
];

const formData = ref({
  referral_id: null,
  admission_id: null,
  claim_date: new Date().toISOString().split('T')[0],
  claim_type: 'BUNDLE',
  clinical_summary: '',
  claim_lines: [],
});

// Computed property for calculated total
const calculatedTotal = computed(() => {
  return formData.value.claim_lines.reduce((sum, line) => sum + (line.line_total || 0), 0);
});

// Computed property for available PA codes from selected referral
const availablePACodes = computed(() => {
  if (!selectedReferral.value) return [];
  return selectedReferral.value.pa_codes || [];
});

onMounted(async () => {
  try {
    await fetchAdmissions();
    await fetchReferrals();
    await fetchCaseRecords();
  } catch (err) {
    showError('Failed to load data');
  }
});

// Fetch referrals with approved status
const fetchReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await api.get('/api/referrals', {
      params: { status: 'APPROVED' }
    });
    referrals.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load referrals');
  } finally {
    loadingReferrals.value = false;
  }
};

// Fetch case records (services, drugs, labs)
const fetchCaseRecords = async () => {
  loadingServices.value = true;
  try {
    const response = await api.get('/api/case-records');
    caseRecords.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load services');
  } finally {
    loadingServices.value = false;
  }
};

// Handle referral selection
const onReferralSelected = async (referralId) => {
  if (!referralId) {
    selectedReferral.value = null;
    return;
  }

  try {
    const response = await api.get(`/api/referrals/${referralId}`);
    selectedReferral.value = response.data.data || response.data;
    formData.value.referral_id = referralId;
  } catch (err) {
    showError('Failed to load referral details');
  }
};

// Handle service selection
const onServiceSelected = async (index, caseRecordId) => {
  if (!caseRecordId) return;

  try {
    const response = await api.get(`/api/case-records/${caseRecordId}`);
    const caseRecord = response.data.data || response.data;

    formData.value.claim_lines[index].service_description = caseRecord.service_description;
    formData.value.claim_lines[index].unit_price = caseRecord.price;
    calculateLineTotal(index);
  } catch (err) {
    showError('Failed to load service details');
  }
};

// Calculate line total
const calculateLineTotal = (index) => {
  const line = formData.value.claim_lines[index];
  line.line_total = (line.quantity || 0) * (line.unit_price || 0);
};

// Add claim line
const addClaimLine = () => {
  formData.value.claim_lines.push({
    case_record_id: null,
    pa_code_id: null,
    service_description: '',
    quantity: 1,
    unit_price: 0,
    line_total: 0,
    reporting_type: 'IN_BUNDLE',
  });
};

// Remove claim line
const removeClaimLine = (index) => {
  formData.value.claim_lines.splice(index, 1);
};

// Format date helper
const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};

// Get referral status color
const getReferralStatusColor = (status) => {
  const colors = {
    'APPROVED': 'green',
    'PENDING': 'orange',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

// Get reporting type color
const getReportingTypeColor = (type) => {
  const colors = {
    'IN_BUNDLE': 'blue',
    'FFS_TOP_UP': 'orange',
    'FFS_STANDALONE': 'purple',
  };
  return colors[type] || 'gray';
};

// Submit claim
const submitClaim = async () => {
  try {
    // Validate required fields
    if (!formData.value.referral_id) {
      showError('Please select a referral/UTN');
      return;
    }
    if (!formData.value.admission_id) {
      showError('Please select an admission');
      return;
    }
    if (formData.value.claim_lines.length === 0) {
      showError('Please add at least one line item');
      return;
    }

    // Validate all line items have PA codes
    const missingPACodes = formData.value.claim_lines.some(line => !line.pa_code_id);
    if (missingPACodes) {
      showError('All line items must be linked to a PA Code');
      return;
    }

    // Add calculated total
    formData.value.total_amount_claimed = calculatedTotal.value;

    await createClaim(formData.value);
    showSuccess('Claim submitted successfully');
    router.push('/claims/review');
  } catch (err) {
    showError(err.message || 'Failed to submit claim');
  }
};
</script>

<style scoped>
.claim-submission-page {
  padding: 20px 0;
}

.gap-2 {
  gap: 8px;
}

.table-responsive {
  overflow-x: auto;
  margin-bottom: 16px;
}

.claim-lines-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.claim-lines-table thead {
  background-color: #f5f5f5;
  border-bottom: 2px solid #ddd;
}

.claim-lines-table th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #333;
}

.claim-lines-table td {
  padding: 8px;
  border-bottom: 1px solid #eee;
}

.claim-lines-table tbody tr:hover {
  background-color: #fafafa;
}

.claim-lines-table input,
.claim-lines-table select {
  width: 100%;
}

.bg-light-blue {
  background-color: #e3f2fd;
}

.font-weight-bold {
  font-weight: 600;
}
</style>

