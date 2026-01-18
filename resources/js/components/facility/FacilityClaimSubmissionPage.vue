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

                  <!-- Step 2: Services & Items Table -->
                  <v-row v-if="selectedReferral">
                    <v-col cols="12">
                      <v-divider class="my-4"></v-divider>
                      <h3 class="text-h6 mb-3">
                        <v-icon class="mr-2" color="primary">mdi-format-list-bulleted</v-icon>
                        Step 2: Services & Items
                      </h3>
                    </v-col>
                    <v-col cols="12">
                      <!-- Services Table -->
                      <div class="services-table-container">
                        <v-table class="services-table" density="comfortable">
                          <thead>
                            <tr class="table-header">
                              <th class="text-left service-column">SERVICE / DRUG</th>
                              <th class="text-center qty-column">QTY</th>
                            
                              <th class="text-right claim-column">PROVIDER CLAIM</th>
                              <th class="text-right patient-pay-column">PATIENT PAY (10%)</th>
                            </tr>
                          </thead>
                          <tbody>
                            <!-- Bundle Service (if exists) -->
                            <template v-if="bundleService">
                              <tr class="service-row bundle-header-row">
                                <td class="service-column">
                                  <div class="d-flex align-center justify-space-between">
                                    <div class="d-flex align-center">
                                      <v-btn
                                        v-if="allServices.findIndex(s => s.id === 'bundle-header') > 0"
                                        icon
                                        size="x-small"
                                        variant="text"
                                        color="grey"
                                        class="mr-2"
                                        @click="moveService('bundle-header', -1)"
                                      >
                                        <v-icon size="20">mdi-chevron-left</v-icon>
                                      </v-btn>
                                      <span class="service-name font-weight-bold">{{ bundleService.name ?? bundleService.description }}</span>
                                    </div>
                                    <v-btn
                                      v-if="allServices.findIndex(s => s.id === 'bundle-header') < allServices.length - 1"
                                      icon
                                      size="x-small"
                                      variant="text"
                                      color="grey"
                                      @click="moveService('bundle-header', 1)"
                                    >
                                      <v-icon size="20">mdi-chevron-right</v-icon>
                                    </v-btn>
                                  </div>
                                </td>
                                <td class="text-center qty-column">
                                  <v-text-field
                                    v-model.number="bundleQuantity"
                                    type="number"
                                    min="1"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    class="qty-input"
                                  ></v-text-field>
                                </td>
                                <td class="text-right claim-column">
                                  <span class="amount-text font-weight-bold">₦{{ formatAmount(bundleService.fixed_price || 0) }}</span>
                                </td>
                                <td class="text-right patient-pay-column">
                                  <span class="patient-pay-text">-</span>
                                </td>
                              </tr>
                            </template>

                            <!-- Bundle Components (nested under bundle) -->
                            <template v-if="bundleService && bundleComponents.length > 0">
                              <tr v-for="(component, idx) in bundleComponents" :key="'bundle-comp-' + idx" class="service-row bundle-component-row">
                                <td class="service-column">
                                  <div class="d-flex align-center justify-space-between">
                                    <div class="d-flex align-center">
                                      <v-btn
                                        v-if="allServices.findIndex(s => s.id === component.id) > 0"
                                        icon
                                        size="x-small"
                                        variant="text"
                                        color="grey"
                                        class="mr-2"
                                        @click="moveService(component.id, -1)"
                                      >
                                        <v-icon size="20">mdi-chevron-left</v-icon>
                                      </v-btn>
                                      <span class="service-name ml-4">{{ component.case_record?.case_record_name || component.item_name }}</span>
                                      <v-chip size="x-small" color="grey" variant="tonal" class="ml-2">Bundled Item</v-chip>
                                    </div>
                                    <v-btn
                                      v-if="allServices.findIndex(s => s.id === component.id) < allServices.length - 1"
                                      icon
                                      size="x-small"
                                      variant="text"
                                      color="grey"
                                      @click="moveService(component.id, 1)"
                                    >
                                      <v-icon size="20">mdi-chevron-right</v-icon>
                                    </v-btn>
                                  </div>
                                </td>
                                <td class="text-center qty-column">
                                  <v-text-field
                                    v-model.number="component.quantity"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    class="qty-input"
                                  ></v-text-field>
                                </td>
                               
                                <td class="text-right claim-column">
                                  <span class="amount-text">---</span>
                                </td>
                                <td class="text-right patient-pay-column">
                                  <span class="patient-pay-text">-</span>
                                </td>
                              </tr>
                            </template>

                            <!-- FFS Line Items (auto-loaded from FFS PA codes) -->
                            <template v-if="ffsServices.length > 0">
                              <tr v-for="(item, idx) in ffsServices" :key="'ffs-' + idx" class="service-row">
                                <td class="service-column">
                                  <div class="d-flex align-center justify-space-between">
                                    <div class="d-flex align-center">
                                      <v-btn
                                        v-if="allServices.findIndex(s => s.id === item.id) > 0"
                                        icon
                                        size="x-small"
                                        variant="text"
                                        color="grey"
                                        class="mr-2"
                                        @click="moveService(item.id, -1)"
                                      >
                                        <v-icon size="20">mdi-chevron-left</v-icon>
                                      </v-btn>
                                      <span class="service-name">{{ item.service_description }}</span>
                                      <v-chip v-if="item.is_top_up" size="x-small" color="primary" variant="tonal" class="ml-2">FFS</v-chip>
                                    </div>
                                    <v-btn
                                      v-if="allServices.findIndex(s => s.id === item.id) < allServices.length - 1"
                                      icon
                                      size="x-small"
                                      variant="text"
                                      color="grey"
                                      @click="moveService(item.id, 1)"
                                    >
                                      <v-icon size="20">mdi-chevron-right</v-icon>
                                    </v-btn>
                                  </div>
                                </td>
                                <td class="text-center qty-column">
                                  <v-text-field
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="0"
                                    variant="outlined"
                                    density="compact"
                                    hide-details
                                    class="qty-input"
                                    @update:model-value="updateFfsItemTotal(idx)"
                                  ></v-text-field>
                                </td>
                                <td class="text-right claim-column">
                                  <span class="amount-text">₦{{ formatAmount(item.quantity * item.unit_price || 0) }}</span>
                                </td>
                                <td class="text-right patient-pay-column">
                                  <span class="patient-pay-text">₦{{ formatAmount(calculatePatientPay(item.unit_price , item.quantity || 0)) }}</span>
                                </td>
                              </tr>
                            </template>

                            <!-- Empty State -->
                            <tr v-if="!bundleService && ffsServices.length === 0">
                              <td colspan="5" class="text-center pa-6">
                                <v-icon size="48" color="grey-lighten-1">mdi-package-variant-closed</v-icon>
                                <p class="text-grey mt-2">No services available for this referral.</p>
                              </td>
                            </tr>
                          </tbody>
                          <tfoot>
                            <tr class="total-row">
                              <td colspan="2" class="text-right font-weight-bold">TOTAL:</td>
                              <td class="text-right">
                                <span class="total-amount">₦{{ formatAmount(totalProviderClaim) }}</span>
                              </td>
                              <td class="text-right">
                                <span class="total-patient-pay">₦{{ formatAmount(totalPatientPay) }}</span>
                              </td>
                            </tr>
                          </tfoot>
                        </v-table>
                      </div>
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

// Referral-first flow state
const approvedReferrals = ref([]);
const selectedReferral = ref(null);
const referralAdmissions = ref([]);
const selectedAdmission = ref(null);

// Bundle service state
const bundleService = ref(null);
const bundleComponents = ref([]);
const bundlePACode = ref(null);
const bundleQuantity = ref(1);

// FFS state
const ffsPACodes = ref([]);
const ffsServices = ref([]); // Auto-loaded FFS services from PA codes
const claimLineItems = ref([]);

const showSuccessDialog = ref(false);
const submittedClaim = ref(null);
const downloadingSlip = ref(false);
const downloadingSlipId = ref(null);

// Submitted claims state
const submittedClaims = ref([]);
const loadingClaims = ref(false);

const formData = ref({
  referral_id: null,
  admission_id: null,
  claim_date: new Date().toISOString().split('T')[0],
});

// Headers
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
  return ffsServices.value.reduce((sum, item) => sum + Number(item.unit_price || 0), 0);
});

const totalClaimAmount = computed(() => {
  return bundleAmount.value + ffsTotal.value;
});

const totalProviderClaim = computed(() => {
  return bundleAmount.value + ffsTotal.value;
});

const totalPatientPay = computed(() => {
  // Patient pays 10% of FFS items only (bundle is fully covered)
  return ffsTotal.value * 0.10;
});

// Computed property to combine all services for ordering
const allServices = computed(() => {
  const services = [];

  // Add bundle header if exists
  if (bundleService.value) {
    services.push({
      id: 'bundle-header',
      type: 'bundle-header',
      order: 0
    });
  }

  // Add bundle components
  bundleComponents.value.forEach((comp, idx) => {
    services.push({
      id: comp.id || `bundle-comp-${idx}`,
      type: 'bundle-component',
      order: services.length
    });
  });

  // Add FFS services
  ffsServices.value.forEach((item, idx) => {
    services.push({
      id: item.id || `ffs-${idx}`,
      type: 'ffs',
      order: services.length
    });
  });

  return services;
});

// Check if at least one service has quantity > 0
const hasClaimItems = computed(() => {
  const hasBundleItems = bundleComponents.value.some(comp => comp.quantity > 0);
  const hasFfsItems = ffsServices.value.some(item => item.quantity > 0);
  return hasBundleItems || hasFfsItems || (bundleService.value && bundleQuantity.value > 0);
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
    hasClaimItems: hasClaimItems.value,
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
        with: 'paCodes.serviceBundle.components.caseRecord,admissions,serviceBundle',
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
  bundleQuantity.value = 1;
  ffsPACodes.value = [];
  ffsServices.value = [];
  claimLineItems.value = [];
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

    // Load bundle components (default quantity: 1)
    bundleComponents.value = (bundlePA.service_bundle.components || []).map((comp, idx) => ({
      ...comp,
      id: comp.id || `bundle-comp-${idx}`,
      quantity: 1 || comp.quantity ,
      unit_price: comp.unit_price || 1
    }));
  }

  // Get FFS PA codes and auto-load their services
  const ffsPAs = paCodes.filter(pa => pa.type === 'FFS_TOP_UP' && pa.status === 'APPROVED');

  ffsPACodes.value = ffsPAs.map(pa => ({
    ...pa,
    display_text: `${pa.code} - ${pa.justification || 'FFS Service'}`,
  }));

  // Auto-load FFS services from PA codes
  const autoLoadedFfsServices = [];
  ffsPAs.forEach((pa, paIdx) => {
    if (pa.case_records && pa.case_records.length > 0) {
      pa.case_records.forEach((caseRecord, crIdx) => {
        const unitPrice = Number(caseRecord.price || 0);
        const quantity = 0; // Default to 0, user will set quantity
        autoLoadedFfsServices.push({
          id: `ffs-${paIdx}-${crIdx}`,
          pa_code_id: pa.id,
          pa_code: pa,
          case_record_id: caseRecord.id,
          case_record: caseRecord,
          service_description: caseRecord.case_record_name || caseRecord.case_name || caseRecord.service_description || 'FFS Service',
          quantity: 1,
          unit_price: unitPrice,
          line_total: quantity * unitPrice,
          frequency: 'NA',
          is_top_up: true,
        });
      });
    }
  });

  ffsServices.value = autoLoadedFfsServices;
};

const onAdmissionSelected = (admissionId) => {
  selectedAdmission.value = referralAdmissions.value.find(a => a.id === admissionId) || null;
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
    // Build line items array combining bundle components and FFS items
    const allLineItems = [];

    // Add bundle components as line items (only those with quantity > 0)
    const usedBundleComponents = bundleComponents.value.filter(comp => comp.quantity > 0);
    usedBundleComponents.forEach(comp => {
      allLineItems.push({
        pa_code_id: bundlePACode.value?.id || null,
        case_record_id: comp.case_record_id || null,
        bundle_component_id: comp.id,
        tariff_type: 'BUNDLE',
        service_type: 'service',
        service_description: comp.item_name || comp.component_name || comp.case_record?.case_record_name || 'Bundle Component',
        quantity: comp.quantity || 0,
        unit_price: 0, // Set to 0 since bundle fixed price is used
        line_total: 0, // Set to 0 since bundle fixed price is used
        reporting_type: 'IN_BUNDLE',
        frequency: comp.frequency || 'NA',
      });
    });

    // Add FFS line items (only those with quantity > 0)
    const usedFfsServices = ffsServices.value.filter(item => item.quantity > 0);
    usedFfsServices.forEach(item => {
      allLineItems.push({
        pa_code_id: item.pa_code_id,
        case_record_id: item.case_record_id,
        tariff_type: 'FFS',
        service_type: 'service',
        service_description: item.service_description,
        quantity: 1,
        unit_price: item.unit_price,
        line_total: item.line_total,
        reporting_type: 'FFS_TOP_UP',
        frequency: item.frequency || 'NA',
      });
    });

    // Build payload
    const payload = {
      referral_id: formData.value.referral_id,
      admission_id: formData.value.admission_id || null, // Optional
      claim_date: formData.value.claim_date,
      // Bundle components metadata (for reference)
      bundle_components: usedBundleComponents.map(comp => ({
        bundle_component_id: comp.id,
        case_record_id: comp.case_record_id || null,
        service_description: comp.item_name || comp.component_name || comp.case_record?.case_record_name || 'Bundle Component',
        quantity: comp.quantity || 0,
        unit_price: comp.unit_price || 0,
      })),
      // Bundle fixed price (if bundle exists)
      bundle_amount: bundleAmount.value,
      bundle_pa_code_id: bundlePACode.value?.id || null,
      // All line items (bundle components + FFS)
      line_items: allLineItems,
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
  bundleQuantity.value = 1;
  ffsPACodes.value = [];
  ffsServices.value = [];
  claimLineItems.value = [];
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

const formatAmount = (amount) => {
  return Number(amount || 0).toLocaleString();
};

const calculatePatientPay = (amount, quantity) => {
  return Number(amount || 0) * Number(quantity|| 0) * 0.10;
};

const updateFfsItemTotal = (index) => {
  const item = ffsServices.value[index];
  if (item) {
    item.line_total = (item.quantity || 0) * (item.unit_price || 0);
  }
};

const moveService = (serviceId, direction) => {
  // Find the service in the appropriate array
  if (serviceId === 'bundle-header') {
    // Can't move bundle header independently - it's always first if it exists
    return;
  }

  // Find if it's a bundle component
  const bundleCompIndex = bundleComponents.value.findIndex(c => c.id === serviceId);
  if (bundleCompIndex !== -1) {
    const newIndex = bundleCompIndex + direction;
    if (newIndex >= 0 && newIndex < bundleComponents.value.length) {
      const temp = bundleComponents.value[bundleCompIndex];
      bundleComponents.value[bundleCompIndex] = bundleComponents.value[newIndex];
      bundleComponents.value[newIndex] = temp;
    }
    return;
  }

  // Find if it's an FFS service
  const ffsIndex = ffsServices.value.findIndex(s => s.id === serviceId);
  if (ffsIndex !== -1) {
    const newIndex = ffsIndex + direction;
    if (newIndex >= 0 && newIndex < ffsServices.value.length) {
      const temp = ffsServices.value[ffsIndex];
      ffsServices.value[ffsIndex] = ffsServices.value[newIndex];
      ffsServices.value[newIndex] = temp;
    }
  }
};

onMounted(async () => {
  await Promise.all([fetchReferrals(), fetchSubmittedClaims()]);
});
</script>

<style scoped>
.facility-claim-submission-page {
  padding: 20px 0;
}

.services-table-container {
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
}

.services-table {
  width: 100%;
  border-collapse: collapse;
}

.services-table thead tr.table-header {
  background-color: #f5f5f5;
  border-bottom: 2px solid #e0e0e0;
}

.services-table thead th {
  padding: 16px 12px;
  font-weight: 600;
  font-size: 13px;
  text-transform: uppercase;
  color: #666;
  letter-spacing: 0.5px;
}

.services-table tbody tr.service-row {
  border-bottom: 1px solid #f0f0f0;
  transition: background-color 0.2s;
}

.services-table tbody tr.service-row:hover {
  background-color: #fafafa;
}

.services-table tbody tr.bundle-header-row {
  background-color: #f9f9f9;
  font-weight: 600;
}

.services-table tbody tr.bundle-component-row {
  background-color: #fafafa;
}

.services-table tbody td {
  padding: 12px;
  vertical-align: middle;
}

.service-column {
  width: 40%;
  min-width: 300px;
}

.qty-column {
  width: 12%;
  min-width: 80px;
}

.freq-column {
  width: 12%;
  min-width: 100px;
}

.claim-column {
  width: 18%;
  min-width: 140px;
}

.patient-pay-column {
  width: 18%;
  min-width: 140px;
  color: #d32f2f;
  font-weight: 500;
}

.service-name {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.qty-input,
.freq-input {
  max-width: 100px;
  margin: 0 auto;
}

.qty-input :deep(.v-field__input) {
  text-align: center;
  padding: 4px 8px;
}

.freq-input :deep(.v-field__input) {
  text-align: center;
  padding: 4px 8px;
}

.freq-text {
  font-size: 14px;
  color: #666;
}

.amount-text {
  font-size: 14px;
  font-weight: 500;
  color: #333;
}

.patient-pay-text {
  font-size: 14px;
  font-weight: 600;
  color: #d32f2f;
}

.services-table tfoot tr.total-row {
  background-color: #f9f9f9;
  border-top: 2px solid #e0e0e0;
}

.services-table tfoot td {
  padding: 16px 12px;
  font-weight: 700;
  font-size: 15px;
}

.total-amount {
  font-size: 16px;
  font-weight: 700;
  color: #2e7d32;
}

.total-patient-pay {
  font-size: 16px;
  font-weight: 700;
  color: #d32f2f;
}
</style>

