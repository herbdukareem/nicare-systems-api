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
                  <!-- Step 1: Select Referral with Validated UTN -->
                  <v-row>
                    <v-col cols="12">
                      <h3 class="text-h6 mb-3">Step 1: Select Referral (Validated UTN)</h3>
                      <v-autocomplete
                        v-model="formData.referral_id"
                        :items="approvedReferrals"
                        :loading="loadingReferrals"
                        item-title="display_text"
                        item-value="id"
                        label="Search and Select Referral"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-magnify"
                        :rules="[v => !!v || 'Referral is required']"
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
                              {{ item.raw.enrollee?.first_name }} {{ item.raw.enrollee?.last_name }} | {{ item.raw.referral_code }}
                            </template>
                          </v-list-item>
                        </template>
                      </v-autocomplete>
                    </v-col>
                  </v-row>

                  <!-- Referral Details (Auto-filled) -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Referral Information</h3>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedReferral.enrollee?.first_name + ' ' + selectedReferral.enrollee?.last_name"
                        label="Patient Name"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-account"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedReferral.utn"
                        label="UTN"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-shield-check"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="selectedReferral.primary_diagnosis || selectedReferral.clinical_summary"
                        label="Primary Diagnosis"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-stethoscope"
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="6">
                      <v-text-field
                        :model-value="formatDate(selectedReferral.valid_until)"
                        label="UTN Valid Until"
                        variant="outlined"
                        density="comfortable"
                        readonly
                        prepend-inner-icon="mdi-calendar-clock"
                      ></v-text-field>
                    </v-col>
                  </v-row>

                  <!-- Admission Selection (Optional) -->
                  <v-row v-if="selectedReferral && referralAdmissions.length > 0">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Admission (Optional)</h3>
                      <v-alert type="info" variant="tonal" density="compact" class="mb-3">
                        This referral has {{ referralAdmissions.length }} admission(s). Select one if applicable.
                      </v-alert>
                      <v-select
                        v-model="formData.admission_id"
                        :items="referralAdmissions"
                        item-title="display_text"
                        item-value="id"
                        label="Select Admission (Optional)"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-hospital-box"
                        clearable
                        @update:model-value="onAdmissionSelected"
                      ></v-select>
                    </v-col>
                  </v-row>

                  <!-- Admission Details (if selected) -->
                  <v-card v-if="selectedAdmission" class="mb-4 mt-2" elevation="1" color="grey-lighten-5">
                    <v-card-title class="text-subtitle-1">Admission Details</v-card-title>
                    <v-card-text>
                      <v-row dense>
                        <v-col cols="6" md="3">
                          <strong>Admission Code:</strong><br>{{ selectedAdmission.admission_code }}
                        </v-col>
                        <v-col cols="6" md="3">
                          <strong>Status:</strong><br>
                          <v-chip :color="selectedAdmission.status === 'discharged' ? 'success' : 'warning'" size="small">
                            {{ selectedAdmission.status }}
                          </v-chip>
                        </v-col>
                        <v-col cols="6" md="3">
                          <strong>Admission Date:</strong><br>{{ formatDate(selectedAdmission.admission_date) }}
                        </v-col>
                        <v-col cols="6" md="3">
                          <strong>Discharge Date:</strong><br>{{ formatDate(selectedAdmission.discharge_date) || 'N/A' }}
                        </v-col>
                      </v-row>
                    </v-card-text>
                  </v-card>

                  <!-- Step 2: Bundle Service (Auto-detected) -->
                  <v-row v-if="selectedReferral && bundleService">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">
                        <v-icon class="mr-2" color="success">mdi-package-variant</v-icon>
                        Step 2: Bundle Service (Auto-detected)
                      </h3>
                      <v-alert type="success" variant="tonal" density="comfortable" class="mb-4">
                        A bundle service was found for this referral. Select the components that were used.
                      </v-alert>
                    </v-col>
                    <v-col cols="12">
                      <v-card elevation="1" color="success-lighten-5">
                        <v-card-title>
                          {{ bundleService.name ?? bundleService.description }}
                          <v-chip class="ml-2" color="success" size="small">
                            Fixed Price: ₦{{ Number(bundleService.fixed_price || 0).toLocaleString() }}
                          </v-chip>
                        </v-card-title>
                        <v-card-text>
                          <v-table density="comfortable">
                            <thead>
                              <tr>
                                <th style="width: 50px;">Used</th>
                                <th>Case/Service</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(component, idx) in bundleComponents" :key="idx">
                                <td>
                                  <v-checkbox
                                    v-model="component.selected"
                                    hide-details
                                    density="compact"
                                    color="success"
                                  ></v-checkbox>
                                </td>
                                <td>{{ component.case_record?.name || component.description }}</td>
                              </tr>
                            </tbody>
                            <tfoot>
                              <tr>
                                <td colspan="4" class="text-right font-weight-bold">
                                  Bundle Fixed Price: <span class="text-success">₦{{ Number(bundleService.fixed_price || 0).toLocaleString() }}</span>
                                </td>
                              </tr>
                            </tfoot>
                          </v-table>
                        </v-card-text>
                      </v-card>
                    </v-col>
                  </v-row>

                  <!-- No Bundle Alert -->
                  <v-row v-if="selectedReferral && !bundleService">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <v-alert type="info" variant="tonal" density="comfortable">
                        <strong>No Bundle Service</strong> - This referral does not have a bundle service. You can add FFS line items below.
                      </v-alert>
                    </v-col>
                  </v-row>

                  <!-- Claim Date -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Claim Date</h3>
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

                  <!-- Step 3: Approved PA Codes for FFS -->
                  <v-row v-if="selectedReferral && ffsPACodes.length > 0">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Step 3: FFS PA Codes (Optional)</h3>
                      <v-alert type="info" variant="tonal" density="comfortable" class="mb-4">
                        <strong>{{ ffsPACodes.length }}</strong> FFS PA code(s) available for additional line items
                      </v-alert>
                    </v-col>
                   
                  </v-row>

                  <!-- Step 4: Claim Line Items (FFS) -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Step 4: FFS Line Items (Optional)</h3>
                      <v-alert type="info" variant="tonal" density="comfortable" class="mb-4">
                        Add Fee-For-Service line items. Each line item must be linked to an approved FFS PA code.
                      </v-alert>
                    </v-col>

                    <!-- Add Line Item Button -->
                   
                    <v-col cols="12">
                      <v-btn
                        color="primary"
                        variant="outlined"
                        prepend-icon="mdi-plus"
                        @click="openAddLineItemDialog"
                        :disabled="ffsPACodes.length === 0"
                      >
                        Add FFS Line Item
                      </v-btn>
                      <span v-if="ffsPACodes.length === 0" class="ml-2 text-grey">
                        (No FFS PA codes available)
                      </span>
                    </v-col>

                    <!-- Line Items Table -->
                    <v-col cols="12" v-if="claimLineItems.length > 0">
                      <v-data-table
                        :headers="lineItemHeaders"
                        :items="claimLineItems"
                        :items-per-page="10"
                        class="elevation-1"
                      >
                        <template #item.unit_price="{ item }">
                          ₦{{ Number(item.unit_price).toLocaleString() }}
                        </template>

                        <template #item.line_total="{ item }">
                          <strong>₦{{ Number(item.line_total).toLocaleString() }}</strong>
                        </template>

                        <template #item.actions="{ index }">
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
                              FFS Total: <strong class="text-info">₦{{ ffsTotal.toLocaleString() }}</strong>
                            </div>
                          </div>
                        </template>
                      </v-data-table>
                    </v-col>

                    <!-- Empty State -->
                    <v-col cols="12" v-else>
                      <v-alert type="info" variant="tonal" density="comfortable">
                        No FFS line items added. This is optional if a bundle service exists.
                      </v-alert>
                    </v-col>
                  </v-row>

                  <!-- Claim Summary -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">Claim Summary</h3>
                    </v-col>
                    <v-col cols="12">
                      <v-card elevation="1">
                        <v-card-text>
                          <v-row>
                            <v-col cols="12" md="6">
                              <div class="mb-2"><strong>UTN:</strong> {{ selectedReferral.utn }}</div>
                              <div class="mb-2"><strong>Patient:</strong> {{ selectedReferral.enrollee?.first_name }} {{ selectedReferral.enrollee?.last_name }}</div>
                              <div class="mb-2"><strong>Referral Code:</strong> {{ selectedReferral.referral_code }}</div>
                            </v-col>
                            <v-col cols="12" md="6">
                              <div class="mb-2" v-if="selectedAdmission">
                                <strong>Admission:</strong> {{ selectedAdmission.admission_code }}
                                <v-chip :color="selectedAdmission.status === 'discharged' ? 'success' : 'warning'" size="x-small" class="ml-1">
                                  {{ selectedAdmission.status }}
                                </v-chip>
                              </div>
                              <div class="mb-2" v-else>
                                <strong>Admission:</strong> N/A (No admission)
                              </div>
                              <div class="mb-2" v-if="bundleService">
                                <strong>Bundle Amount:</strong> ₦{{ Number(bundleService.fixed_price || 0).toLocaleString() }}
                              </div>
                              <div class="mb-2"><strong>FFS Amount:</strong> ₦{{ ffsTotal.toLocaleString() }}</div>
                            </v-col>
                          </v-row>
                        </v-card-text>
                      </v-card>
                    </v-col>
                  </v-row>

                  <!-- Total Amount -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-card elevation="2" color="success-lighten-5" class="mt-2">
                        <v-card-text class="pa-4">
                          <div class="d-flex justify-space-between align-center">
                            <strong class="text-h5">Total Claim Amount:</strong>
                            <strong class="text-h4 text-success">₦{{ totalClaimAmount.toLocaleString() }}</strong>
                          </div>
                        </v-card-text>
                      </v-card>
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
                  color="success"
                  variant="elevated"
                  @click="submitClaim"
                  :loading="submitting"
                  :disabled="!canSubmit"
                >
                  <v-icon class="mr-1">mdi-send</v-icon>
                  Submit Claim
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>

        <!-- Submitted Claims Table -->
        <v-row class="mt-6">
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-primary text-white d-flex justify-space-between align-center">
                <div>
                  <v-icon class="mr-2">mdi-clipboard-list</v-icon>
                  Submitted Claims
                </div>
                <v-btn
                  variant="text"
                  color="white"
                  size="small"
                  @click="fetchSubmittedClaims"
                  :loading="loadingClaims"
                >
                  <v-icon>mdi-refresh</v-icon>
                </v-btn>
              </v-card-title>

              <v-card-text class="pa-0">
                <v-data-table
                  :headers="claimsTableHeaders"
                  :items="submittedClaims"
                  :loading="loadingClaims"
                  density="comfortable"
                  class="elevation-0"
                >
                  <template #item.claim_number="{ item }">
                    <strong class="text-primary">{{ item.claim_number }}</strong>
                  </template>

                  <template #item.utn="{ item }">
                    <v-chip size="small" color="info" variant="tonal">{{ item.utn }}</v-chip>
                  </template>

                  <template #item.enrollee="{ item }">
                    {{ item.enrollee?.first_name }} {{ item.enrollee?.last_name }}
                  </template>

                  <template #item.total_amount="{ item }">
                    <strong class="text-success">₦{{ Number(item.total_amount || 0).toLocaleString() }}</strong>
                  </template>

                  <template #item.status="{ item }">
                    <v-chip
                      :color="getStatusColor(item.status)"
                      size="small"
                      variant="elevated"
                    >
                      {{ item.status }}
                    </v-chip>
                  </template>

                  <template #item.claim_date="{ item }">
                    {{ formatDate(item.claim_date) }}
                  </template>

                  <template #item.actions="{ item }">
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      color="primary"
                      @click="downloadClaimSlipById(item.id)"
                      :loading="downloadingSlipId === item.id"
                    >
                      <v-icon>mdi-download</v-icon>
                      <v-tooltip activator="parent" location="top">Download Claim Slip</v-tooltip>
                    </v-btn>
                  </template>

                  <template #no-data>
                    <div class="text-center pa-6">
                      <v-icon size="64" color="grey-lighten-1">mdi-clipboard-text-outline</v-icon>
                      <p class="text-grey mt-2">No claims submitted yet</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
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

          <v-card-text class="pt-6">
            <v-form ref="lineItemForm">
              <v-row>
                <!-- PA Code Selection -->
                <v-col cols="12">
                  <v-autocomplete
                    v-model="lineItemFormData.pa_code_id"
                    :items="ffsPACodes"
                    item-title="display_text"
                    item-value="id"
                    label="Select FFS PA Code *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-shield-check"
                    :rules="[v => !!v || 'PA Code is required']"
                    @update:model-value="onPACodeSelected"
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #title>
                          <strong>{{ item.raw.code }}</strong>
                        </template>
                        <template #subtitle>
                          {{ item.raw.justification }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>

                <!-- Case Record Selection (from PA Code's case_records) -->
                <v-col cols="12" v-if="selectedPACodeCaseRecords.length > 0">
                  <v-autocomplete
                    v-model="lineItemFormData.case_record_id"
                    :items="selectedPACodeCaseRecords"
                    item-title="display_text"
                    item-value="id"
                    label="Select Service/Case *"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-medical-bag"
                    :rules="[v => !!v || 'Service/Case is required']"
                    @update:model-value="onCaseRecordSelected"
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #title>
                          <strong>{{ item.raw.case_name }}</strong>
                        </template>
                        <template #subtitle>
                          {{ item.raw.nicare_code }} | ₦{{ Number(item.raw.price || 0).toLocaleString() }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>

                <!-- Service Description (auto-filled, editable) -->
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
                    :rules="[v => !!v && v > 0 || 'Quantity must be greater than 0']"
                    @update:model-value="calculateLineTotal"
                  ></v-text-field>
                </v-col>

                <!-- Unit Price (read-only, from case record) -->
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="'₦' + Number(lineItemFormData.unit_price || 0).toLocaleString()"
                    label="Unit Price (from tariff)"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-currency-ngn"
                  ></v-text-field>
                </v-col>

                <!-- Line Total (Read-only) -->
                <v-col cols="12" md="4">
                  <v-text-field
                    :model-value="'₦' + Number(lineItemFormData.line_total).toLocaleString()"
                    label="Line Total"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-calculator"
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

      <!-- Claim Submission Success Dialog -->
      <v-dialog v-model="showSuccessDialog" max-width="500px" persistent>
        <v-card>
          <v-card-title class="bg-success text-white">
            <v-icon class="mr-2">mdi-check-circle</v-icon>
            Claim Submitted Successfully
          </v-card-title>

          <v-card-text class="pt-6 text-center">
            <v-icon color="success" size="80" class="mb-4">mdi-file-document-check</v-icon>
            <h3 class="text-h5 mb-2">Claim Number: {{ submittedClaim?.claim_number }}</h3>
            <p class="text-body-1 text-grey mb-4">
              Your claim has been submitted successfully. You can download the claim submission slip for your records.
            </p>
            <v-chip color="info" class="mb-4">
              UTN: {{ submittedClaim?.utn }}
            </v-chip>
          </v-card-text>

          <v-card-actions class="justify-center pb-4">
            <v-btn
              color="primary"
              variant="elevated"
              @click="downloadClaimSlip"
              :loading="downloadingSlip"
            >
              <v-icon class="mr-1">mdi-download</v-icon>
              Download Claim Slip
            </v-btn>
            <v-btn
              color="grey"
              variant="text"
              @click="closeSuccessDialog"
            >
              Close
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

const { success: showSuccess, error: showError } = useToast();

// State
const loadingReferrals = ref(false);
const submitting = ref(false);
const claimForm = ref(null);
const lineItemForm = ref(null);

// Referral-first flow state
const approvedReferrals = ref([]);
const selectedReferral = ref(null);
const referralAdmissions = ref([]);
const selectedAdmission = ref(null);

// Bundle service state
const bundleService = ref(null);
const bundleComponents = ref([]);
const bundlePACode = ref(null);

// FFS state
const ffsPACodes = ref([]);
const claimLineItems = ref([]);
const selectedPACodeCaseRecords = ref([]);

const showLineItemDialog = ref(false);
const showSuccessDialog = ref(false);
const submittedClaim = ref(null);
const downloadingSlip = ref(false);
const downloadingSlipId = ref(null);

// Submitted claims state
const submittedClaims = ref([]);
const loadingClaims = ref(false);

const lineItemFormData = ref({
  pa_code_id: null,
  case_record_id: null,
  service_description: '',
  quantity: 1,
  unit_price: 0,
  line_total: 0,
});

const formData = ref({
  referral_id: null,
  admission_id: null,
  claim_date: new Date().toISOString().split('T')[0],
});

// Headers
const lineItemHeaders = [
  { title: 'Service', key: 'service_description', sortable: false },
  { title: 'Quantity', key: 'quantity', sortable: false },
  { title: 'Unit Price', key: 'unit_price', sortable: false },
  { title: 'Line Total', key: 'line_total', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

const claimsTableHeaders = [
  { title: 'Claim Number', key: 'claim_number', sortable: true },
  { title: 'UTN', key: 'utn', sortable: true },
  { title: 'Enrollee', key: 'enrollee', sortable: false },
  { title: 'Total Amount', key: 'total_amount', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Claim Date', key: 'claim_date', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

// Computed
const bundleAmount = computed(() => {
  // Bundle amount is the fixed price of the service bundle (if any)
  return bundleService.value ? Number(bundleService.value.fixed_price || 0) : 0;
});

const ffsTotal = computed(() => {
  return claimLineItems.value.reduce((sum, item) => sum + Number(item.line_total), 0);
});

const totalClaimAmount = computed(() => {
  return bundleAmount.value + ffsTotal.value;
});

// Check if at least one bundle component is selected
const hasSelectedBundleComponents = computed(() => {
  return bundleComponents.value.some(comp => comp.selected);
});

const validationChecks = computed(() => {
  if (!selectedReferral.value) {
    return {
      hasValidatedUTN: false,
      hasClaimItems: false,
    };
  }

  return {
    hasValidatedUTN: selectedReferral.value.utn_validated || false,
    hasClaimItems: hasSelectedBundleComponents.value || claimLineItems.value.length > 0,
  };
});

const canSubmit = computed(() => {
  return (
    formData.value.referral_id &&
    formData.value.claim_date &&
    validationChecks.value.hasValidatedUTN &&
    validationChecks.value.hasClaimItems
  );
});

// Methods
const fetchReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await api.get('/referrals', {
      params: {
        status: 'APPROVED',
        utn_validated: true,
        claim_submitted: false,
        with: 'paCodes.serviceBundle.components.caseRecord,admissions',
      }
    });
    const referrals = response.data?.data?.data || response.data?.data || response.data || [];

    approvedReferrals.value = referrals.map(referral => ({
      ...referral,
      display_text: `${referral.utn} - ${referral.enrollee?.first_name} ${referral.enrollee?.last_name}`,
    }));
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch referrals');
    console.error(err);
  } finally {
    loadingReferrals.value = false;
  }
};

const onReferralSelected = async (referralId) => {
  selectedReferral.value = approvedReferrals.value.find(r => r.id === referralId);

  // Reset dependent state
  selectedAdmission.value = null;
  bundleService.value = null;
  bundleComponents.value = [];
  bundlePACode.value = null;
  ffsPACodes.value = [];
  claimLineItems.value = [];
  selectedPACodeCaseRecords.value = [];
  formData.value.admission_id = null;

  if (!selectedReferral.value) {
    referralAdmissions.value = [];
    return;
  }

  // Load admissions for this referral
  referralAdmissions.value = (selectedReferral.value.admissions || []).map(adm => ({
    ...adm,
    display_text: `${adm.admission_code || adm.admission_number} - ${adm.status}`,
  }));

  // Auto-detect bundle service from PA codes
  const paCodes = selectedReferral.value.pa_codes || [];
  const bundlePA = paCodes.find(pa => pa.type === 'BUNDLE' && pa.status === 'APPROVED');

  if (bundlePA && bundlePA.service_bundle) {
    bundlePACode.value = bundlePA;
    bundleService.value = bundlePA.service_bundle;

    // Load bundle components with selection checkbox (default: all selected, quantity: 1)
    bundleComponents.value = (bundlePA.service_bundle.components || []).map(comp => ({
      ...comp,
      selected: true, // Default to selected
      quantity: comp.quantity || 1, // Ensure quantity has default value
      unit_price: comp.unit_price || 0,
    }));
  }

  // Get FFS PA codes only (exclude BUNDLE type)
  ffsPACodes.value = paCodes
    .filter(pa => pa.type === 'FFS_TOP_UP' && pa.status === 'APPROVED')
    .map(pa => ({
      ...pa,
      display_text: `${pa.code} - ${pa.justification || 'FFS Service'}`,
    }));
};

const onAdmissionSelected = (admissionId) => {
  selectedAdmission.value = referralAdmissions.value.find(a => a.id === admissionId) || null;
};

// When PA code is selected in dialog, load its case records
const onPACodeSelected = (paCodeId) => {
  lineItemFormData.value.case_record_id = null;
  lineItemFormData.value.service_description = '';
  lineItemFormData.value.unit_price = 0;
  lineItemFormData.value.line_total = 0;
  selectedPACodeCaseRecords.value = [];

  if (!paCodeId) return;

  const selectedPA = ffsPACodes.value.find(pa => pa.id === paCodeId);
  if (selectedPA && selectedPA.case_records) {
    selectedPACodeCaseRecords.value = selectedPA.case_records.map(cr => ({
      ...cr,
      display_text: `${cr.case_name} (₦${Number(cr.price || 0).toLocaleString()})`,
    }));
  }
};

// When case record is selected, populate unit price and description
const onCaseRecordSelected = (caseRecordId) => {
  if (!caseRecordId) {
    lineItemFormData.value.unit_price = 0;
    lineItemFormData.value.service_description = '';
    lineItemFormData.value.line_total = 0;
    return;
  }

  const selectedCase = selectedPACodeCaseRecords.value.find(cr => cr.id === caseRecordId);
  if (selectedCase) {
    lineItemFormData.value.unit_price = Number(selectedCase.price || 0);
    lineItemFormData.value.service_description = selectedCase.case_name || '';
    calculateLineTotal();
  }
};

const openAddLineItemDialog = () => {
  selectedPACodeCaseRecords.value = [];
  showLineItemDialog.value = true;
};

const closeLineItemDialog = () => {
  showLineItemDialog.value = false;
  selectedPACodeCaseRecords.value = [];
  lineItemFormData.value = {
    pa_code_id: null,
    case_record_id: null,
    service_description: '',
    quantity: 1,
    unit_price: 0,
    line_total: 0,
  };
  lineItemForm.value?.reset();
};

const calculateLineTotal = () => {
  const quantity = Number(lineItemFormData.value.quantity) || 0;
  const unitPrice = Number(lineItemFormData.value.unit_price) || 0;
  lineItemFormData.value.line_total = quantity * unitPrice;
};

const addLineItem = async () => {
  const { valid } = await lineItemForm.value?.validate();
  if (!valid) {
    showError('Please fill in all required fields');
    return;
  }

  // Find the selected case record for display
  const selectedCase = selectedPACodeCaseRecords.value.find(cr => cr.id === lineItemFormData.value.case_record_id);

  // Add line item to the list
  claimLineItems.value.push({
    ...lineItemFormData.value,
    pa_code: ffsPACodes.value.find(pa => pa.id === lineItemFormData.value.pa_code_id),
    case_record: selectedCase,
  });

  showSuccess('FFS line item added successfully');
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
    showError('Claim validation failed. Please ensure you have a validated UTN and at least one claim item.');
    return;
  }

  submitting.value = true;
  try {
    // Build payload with selected bundle components and FFS line items
    const payload = {
      referral_id: formData.value.referral_id,
      admission_id: formData.value.admission_id || null, // Optional
      claim_date: formData.value.claim_date,
      // Only include selected bundle components
      bundle_components: bundleComponents.value
        .filter(comp => comp.selected)
        .map(comp => ({
          bundle_component_id: comp.id,
          case_record_id: comp.case_record_id || null,
          quantity: comp.quantity || 1,
          unit_price: comp.unit_price || 0,
        })),
      // Bundle fixed price (if bundle exists)
      bundle_amount: bundleAmount.value,
      bundle_pa_code_id: bundlePACode.value?.id || null,
      // FFS line items
      line_items: claimLineItems.value.map(item => ({
        pa_code_id: item.pa_code_id,
        case_record_id: item.case_record_id,
        tariff_type: 'FFS',
        service_type: 'service',
        service_description: item.service_description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        line_total: item.line_total,
        reporting_type: 'FFS_TOP_UP',
      })),
    };

    const response = await api.post('/claims-automation/claims', payload);
    const claim = response.data?.data;

    // Store submitted claim and show success dialog
    submittedClaim.value = claim;
    showSuccessDialog.value = true;

    resetForm();
    await fetchReferrals();
    await fetchSubmittedClaims();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to create claim');
    console.error(err);
  } finally {
    submitting.value = false;
  }
};

// Fetch submitted claims for this facility
const fetchSubmittedClaims = async () => {
  loadingClaims.value = true;
  try {
    const response = await api.get('/claims-automation/claims', {
      params: {
        with: 'enrollee,referral',
        per_page: 20,
        sort: '-created_at',
      }
    });
    submittedClaims.value = response.data?.data?.data || response.data?.data || [];
  } catch (err) {
    console.error('Failed to fetch submitted claims:', err);
  } finally {
    loadingClaims.value = false;
  }
};

// Download claim slip for the just-submitted claim
const downloadClaimSlip = async () => {
  if (!submittedClaim.value?.id) return;
  await downloadClaimSlipById(submittedClaim.value.id);
};

// Download claim slip by claim ID
const downloadClaimSlipById = async (claimId) => {
  downloadingSlipId.value = claimId;
  downloadingSlip.value = true;
  try {
    const response = await api.get(`/claims-automation/claims/${claimId}/slip`, {
      responseType: 'blob',
    });

    // Create blob and download
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `claim-slip-${claimId}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    showSuccess('Claim slip downloaded successfully');
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to download claim slip');
    console.error(err);
  } finally {
    downloadingSlip.value = false;
    downloadingSlipId.value = null;
  }
};

// Close success dialog
const closeSuccessDialog = () => {
  showSuccessDialog.value = false;
  submittedClaim.value = null;
};

// Get status color for chip
const getStatusColor = (status) => {
  const colors = {
    'SUBMITTED': 'info',
    'REVIEWING': 'warning',
    'APPROVED': 'success',
    'REJECTED': 'error',
    'DRAFT': 'grey',
  };
  return colors[status] || 'grey';
};

const resetForm = () => {
  selectedReferral.value = null;
  selectedAdmission.value = null;
  referralAdmissions.value = [];
  bundleService.value = null;
  bundleComponents.value = [];
  bundlePACode.value = null;
  ffsPACodes.value = [];
  claimLineItems.value = [];
  selectedPACodeCaseRecords.value = [];
  formData.value = {
    referral_id: null,
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
  await Promise.all([fetchReferrals(), fetchSubmittedClaims()]);
});
</script>

<style scoped>
.facility-claim-submission-page {
  padding: 20px 0;
}
</style>

