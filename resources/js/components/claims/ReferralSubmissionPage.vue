<template>
  <AdminLayout>
    <div class="referral-submission-page">
      <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white">
              <v-icon left>mdi-file-document-plus</v-icon>
              Submit Referral to Pre-Authorization System (PAS)
            </v-card-title>
            <v-card-text>
              <v-alert type="info" class="mb-4">
                <strong>Admin Referral Submission to PAS</strong><br>
                Submit a referral on behalf of a primary facility to the Pre-Authorization System. Select the facility, enrollee, and provide clinical details.
              </v-alert>

              <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>

              <!-- Stepper -->
              <v-stepper v-model="currentStep" alt-labels>
                <v-stepper-header>
                  <v-stepper-item
                    :complete="currentStep > 1"
                    :value="1"
                    title="Patient & Facility"
                    subtitle="Select facility and enrollee"
                  ></v-stepper-item>

                  <v-divider></v-divider>

                  <v-stepper-item
                    :complete="currentStep > 2"
                    :value="2"
                    title="Clinical Information"
                    subtitle="Diagnosis and findings"
                  ></v-stepper-item>

                  <v-divider></v-divider>

                  <v-stepper-item
                    :complete="currentStep > 3"
                    :value="3"
                    title="Referring Person"
                    subtitle="Medical personnel details"
                  ></v-stepper-item>

                  <v-divider></v-divider>

                  <v-stepper-item
                    :value="4"
                    title="Review & Submit"
                    subtitle="Confirm details"
                  ></v-stepper-item>
                </v-stepper-header>

                <v-stepper-window>
                  <!-- Step 1: Patient & Facility Context -->
                  <v-stepper-window-item :value="1">
                    <v-form ref="step1Form">
                      <v-card flat>
                        <v-card-text>
                          <h3 class="mb-4">Patient & Facility Context</h3>
                          <v-row>
                            <!-- Referring Facility (Admin selects) -->
                            <v-col cols="12" md="4">
                           
                              <v-autocomplete
                                v-model="formData.referring_facility_id"
                                label="Referring Facility *"
                                :items="primaryFacilities"
                                item-title="name"
                                item-value="id"
                                variant="outlined"
                                required
                                :rules="[v => !!v || 'Referring facility is required']"
                                :loading="loadingFacilities"
                                hint="Select the primary facility making the referral"
                                clearable
                                @update:model-value="onReferringFacilityChange"
                              >
                                <template v-slot:item="{ item, props }">
                                  <v-list-item v-bind="props">
                                    <v-list-item-subtitle>{{ item.raw.hcp_code }} | {{ item.raw.type }}</v-list-item-subtitle>
                                  </v-list-item>
                                </template>
                              </v-autocomplete>
                            </v-col>
                        
                            <!-- Enrollee Selection -->
                            <v-col cols="12" md="4">
                              <v-autocomplete
                                v-model="formData.enrollee_id"
                                label="Enrollee *"
                                :items="enrollees"
                                item-title="full_name"
                                item-value="id"
                                 variant="outlined"
                                required
                                :rules="[v => !!v || 'Enrollee is required']"
                                :loading="loadingEnrollees"
                                :disabled="!formData.referring_facility_id"
                                hint="Enrollees from selected referring facility"
                                clearable
                              >
                                <template v-slot:item="{ item, props }">
                                  <v-list-item v-bind="props">
                                    <v-list-item-subtitle>{{ item.raw.enrollee_id }} | {{ item.raw.phone }}</v-list-item-subtitle>
                                  </v-list-item>
                                </template>
                                <template v-slot:no-data>
                                  <v-list-item>
                                    <v-list-item-title>
                                      {{ formData.referring_facility_id ? 'No enrollees found for this facility' : 'Please select a referring facility first' }}
                                    </v-list-item-title>
                                  </v-list-item>
                                </template>
                              </v-autocomplete>
                            </v-col>

                            <!-- Receiving Facility -->
                            <v-col cols="12" md="4">
                              <v-autocomplete
                                v-model="formData.receiving_facility_id"
                                label="Receiving Facility *"
                                :items="secondaryFacilities"
                                item-title="name"
                                item-value="id"
                                 variant="outlined"
                                required
                                :rules="[v => !!v || 'Receiving facility is required']"
                                :loading="loadingFacilities"
                                hint="Select the secondary/tertiary facility"
                                clearable
                              >
                                <template v-slot:item="{ item, props }">
                                  <v-list-item v-bind="props">
                                    <v-list-item-subtitle>{{ item.raw.hcp_code }} | {{ item.raw.type }}</v-list-item-subtitle>
                                  </v-list-item>
                                </template>
                              </v-autocomplete>
                            </v-col>
                          </v-row>
                        </v-card-text>
                        <v-card-actions>
                          <v-spacer></v-spacer>
                          <v-btn color="primary" @click="nextStep(1)">
                            Next
                            <v-icon right>mdi-chevron-right</v-icon>
                          </v-btn>
                        </v-card-actions>
                      </v-card>
                    </v-form>
                  </v-stepper-window-item>

                  <!-- Step 2: Clinical Information -->
                  <v-stepper-window-item :value="2">
                    <v-form ref="step2Form">
                      <v-card flat>
                        <v-card-text>
                          <h3 class="mb-4">Clinical Information</h3>
                          <v-row>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.presenting_complains"
                                label="Presenting Complaints *"
                                :rules="[v => !!v || 'Presenting complaints are required']"
                                 variant="outlined"
                                hint="Chief complaints of the patient"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.preliminary_diagnosis"
                                label="Preliminary Diagnosis (ICD-10 Code) *"
                                :rules="[v => !!v || 'Diagnosis is required']"
                                 variant="outlined"
                                hint="Enter ICD-10 code or diagnosis text"
                              />
                            </v-col>
                          </v-row>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-select
                                v-model="formData.severity_level"
                                :items="severityLevels"
                                label="Severity Level *"
                                :rules="[v => !!v || 'Severity level is required']"
                                 variant="outlined"
                                hint="Routine, Urgent/Expedited, or Emergency"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.reasons_for_referral"
                                label="Reasons for Referral *"
                                :rules="[v => !!v || 'Reasons for referral are required']"
                                 variant="outlined"
                                rows="3"
                                hint="Why is this referral necessary?"
                              />
                            </v-col>
                          </v-row>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.treatments_given"
                                label="Treatments Given *"
                                :rules="[v => !!v || 'Treatments given are required']"
                                 variant="outlined"
                                rows="3"
                                hint="What treatments have been administered?"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.investigations_done"
                                label="Investigations Done *"
                                :rules="[v => !!v || 'Investigations done are required']"
                                 variant="outlined"
                                rows="3"
                                hint="Lab tests, imaging, etc."
                              />
                            </v-col>
                          </v-row>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.examination_findings"
                                label="Examination Findings *"
                                :rules="[v => !!v || 'Examination findings are required']"
                                 variant="outlined"
                                rows="3"
                                hint="Physical examination results"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.medical_history"
                                label="Medical History"
                                 variant="outlined"
                                rows="3"
                                hint="Past medical history (optional)"
                              />
                            </v-col>
                          </v-row>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.medication_history"
                                label="Medication History"
                                 variant="outlined"
                                rows="2"
                                hint="Current medications (optional)"
                              />
                            </v-col>
                          </v-row>

                          <!-- Service Selection Section -->
                          <v-divider class="my-4"></v-divider>
                          <h4 class="mb-3">Service Selection (Optional)</h4>
                          <v-alert type="info" density="compact" class="mb-4">
                            You can optionally pre-select services for this referral. This helps with claim processing later.
                          </v-alert>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-select
                                v-model="formData.service_selection_type"
                                label="Service Selection Type"
                                :items="serviceSelectionTypes"
                                item-title="text"
                                item-value="value"
                                variant="outlined"
                                density="comfortable"
                                clearable
                                hint="Choose how to specify services for this referral"
                                persistent-hint
                                @update:model-value="onServiceTypeChange"
                              />
                            </v-col>
                          </v-row>

                          <!-- Bundle Service Selection -->
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
                                :rules="formData.service_selection_type === 'bundle' ? [v => !!v || 'Service bundle is required'] : []"
                                clearable
                                hint="Select a pre-defined service bundle"
                                persistent-hint
                              >
                                <template v-slot:item="{ item, props }">
                                  <v-list-item v-bind="props">
                                    <template v-slot:prepend>
                                      <v-icon>mdi-package-variant</v-icon>
                                    </template>
                                    <!-- <v-list-item-title>{{ item.raw.description }}</v-list-item-title> -->
                                    <v-list-item-subtitle>
                                      {{ item.raw.description }} | ₦{{ Number(item.raw.fixed_price).toLocaleString() }}
                                      <span v-if="item.raw.diagnosis_icd10"> | ICD-10: {{ item.raw.diagnosis_icd10 }}</span>
                                    </v-list-item-subtitle>
                                  </v-list-item>
                                </template>
                              </v-autocomplete>
                            </v-col>
                          </v-row>

                          <!-- Direct Service Selection -->
                          <v-row v-if="formData.service_selection_type === 'direct'">
                            <v-col cols="12">
                              <v-autocomplete
                                v-model="formData.case_record_id"
                                label="Select Direct Service *"
                                :items="caseRecords"
                                item-title="display_name"
                                item-value="id"
                                variant="outlined"
                                density="comfortable"
                                :loading="loadingCaseRecords"
                                :rules="formData.service_selection_type === 'direct' ? [v => !!v || 'Service is required'] : []"
                                clearable
                                hint="Select a single service/treatment"
                                persistent-hint
                              >
                                <template v-slot:item="{ item, props }">
                                  <v-list-item v-bind="props">
                                    <template v-slot:prepend>
                                      <v-icon>{{ getCaseRecordIcon(item.raw.detail_type) }}</v-icon>
                                    </template>
                                    <v-list-item-title>{{ item.raw.case_name }}</v-list-item-title>
                                    <v-list-item-subtitle>
                                      {{ item.raw.nicare_code }} | {{ item.raw.detail_type }}
                                    </v-list-item-subtitle>
                                  </v-list-item>
                                </template>
                              </v-autocomplete>
                            </v-col>
                          </v-row>
                        </v-card-text>
                        <v-card-actions>
                          <v-btn @click="prevStep">
                            <v-icon left>mdi-chevron-left</v-icon>
                            Back
                          </v-btn>
                          <v-spacer></v-spacer>
                          <v-btn color="primary" @click="nextStep(2)">
                            Next
                            <v-icon right>mdi-chevron-right</v-icon>
                          </v-btn>
                        </v-card-actions>
                      </v-card>
                    </v-form>
                  </v-stepper-window-item>

                  <!-- Step 3: Referring Person -->
                  <v-stepper-window-item :value="3">
                    <v-form ref="step3Form">
                      <v-card flat>
                        <v-card-text>
                          <h3 class="mb-4">Referring Person Details</h3>
                          <v-row>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.referring_person_name"
                                label="Referring Person Name *"
                                :rules="[v => !!v || 'Referring person name is required']"
                                 variant="outlined"
                                hint="Name of the referring medical personnel"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.referring_person_specialisation"
                                label="Specialisation *"
                                :rules="[v => !!v || 'Specialisation is required']"
                                 variant="outlined"
                                hint="e.g., General Medicine, Pediatrics"
                              />
                            </v-col>
                          </v-row>

                          <v-row>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.referring_person_cadre"
                                label="Cadre *"
                                :rules="[v => !!v || 'Cadre is required']"
                                 variant="outlined"
                                hint="e.g., Doctor, Nurse, Medical Officer"
                              />
                            </v-col>
                          </v-row>

                          <h3 class="mt-4 mb-4">Contact Information (Optional)</h3>
                          <v-row>
                            <v-col cols="12" md="4">
                              <v-text-field
                                v-model="formData.contact_person_name"
                                label="Contact Person Name"
                                 variant="outlined"
                                hint="Optional contact person"
                              />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field
                                v-model="formData.contact_person_phone"
                                label="Contact Phone"
                                 variant="outlined"
                                hint="Phone number for follow-up"
                              />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field
                                v-model="formData.contact_person_email"
                                label="Contact Email"
                                type="email"
                                 variant="outlined"
                                hint="Email for notifications"
                              />
                            </v-col>
                          </v-row>
                        </v-card-text>
                        <v-card-actions>
                          <v-btn @click="prevStep">
                            <v-icon left>mdi-chevron-left</v-icon>
                            Back
                          </v-btn>
                          <v-spacer></v-spacer>
                          <v-btn color="primary" @click="nextStep(3)">
                            Next
                            <v-icon right>mdi-chevron-right</v-icon>
                          </v-btn>
                        </v-card-actions>
                      </v-card>
                    </v-form>
                  </v-stepper-window-item>

                  <!-- Step 4: Review & Submit -->
                  <v-stepper-window-item :value="4">
                    <v-card flat>
                      <v-card-text>
                        <h3 class="mb-4">Review & Submit</h3>
                        <v-alert type="info" density="compact" class="mb-4">
                          Please review all information before submitting the referral.
                        </v-alert>
                      </v-card-text>
                      <v-card-actions>
                        <v-btn @click="prevStep">
                          <v-icon left>mdi-chevron-left</v-icon>
                          Back
                        </v-btn>
                        <v-spacer></v-spacer>
                        <v-btn
                            color="primary"
                            @click="handleSubmission"
                            :loading="loading"
                            size="large"
                        >
                            <v-icon left>mdi-send</v-icon>
                            Submit Referral
                        </v-btn>
                      </v-card-actions>
                    </v-card>
                  </v-stepper-window-item>
                </v-stepper-window>
              </v-stepper>

              <!-- Success Message -->
              <v-alert
                v-if="false"
                type="success"
                class="mt-6"
              >
                <div class="text-h6 mb-2">✅ Referral Submitted Successfully!</div>
                <v-divider class="my-3"></v-divider>
                <p><strong>Unique Transaction Number (UTN):</strong> <span class="text-h6 text-primary">{{ createdReferral.utn }}</span></p>
                <p><strong>Referral Code:</strong> {{ createdReferral.referral_code }}</p>
                <p><strong>Status:</strong>
                  <v-chip :color="getStatusColor(createdReferral.status)" text-color="white" small>
                    {{ createdReferral.status || 'PENDING' }}
                  </v-chip>
                </p>
                <p class="mt-3 text-caption">Please use this UTN when submitting claims for these services.</p>
              </v-alert>

            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      </v-container>
    </div>
  </AdminLayout>

  <!-- Success Modal -->
  <v-dialog v-model="showSuccessDialog" max-width="600px" persistent>
    <v-card>
      <v-card-title class="bg-success text-white">
        <v-icon left>mdi-check-circle</v-icon>
        Referral Submitted Successfully
      </v-card-title>
      <v-card-text v-if="createdReferral">
        <v-alert type="success" variant="tonal" border="start" border-color="success" class="mb-4">
          <div class="text-subtitle-1 mb-2">Unique Transaction Number (UTN):</div>
          <div class="text-h6 text-primary">{{ createdReferral.utn }}</div>
        </v-alert>
        <p><strong>Referral Code:</strong> {{ createdReferral.referral_code }}</p>
        <p class="d-flex align-center">
          <strong class="mr-2">Status:</strong>
          <v-chip :color="getStatusColor(createdReferral.status)" text-color="white" small>
            {{ createdReferral.status || 'PENDING' }}
          </v-chip>
        </p>
        <p class="mt-3 text-caption">Please use this UTN when submitting claims for these services.</p>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn color="primary" @click="showSuccessDialog = false">
          Close
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import AdminLayout from '../layout/AdminLayout.vue';
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';

const router = useRouter();
const { success: showSuccess, error: showError } = useToast();

// Reactive state
const loading = ref(false);
const error = ref(null);
const createdReferral = ref(null);
const step1Form = ref(null);
const step2Form = ref(null);
const step3Form = ref(null);
const currentStep = ref(1);

const facilities = ref([]);
const enrollees = ref([]);
const caseRecords = ref([]);
const serviceBundles = ref([]);
const loadingFacilities = ref(false);
const loadingEnrollees = ref(false);
const loadingBundles = ref(false);
const loadingCaseRecords = ref(false);

const showSuccessDialog = ref(false);

const severityLevels = [
  { title: 'Routine', value: 'Routine' },
  { title: 'Urgent/Expedited', value: 'Urgent/Expidited' },
  { title: 'Emergency', value: 'Emergency' },
];

const serviceSelectionTypes = [
  { value: 'bundle', text: 'Bundle Service (Package)' },
  { value: 'direct', text: 'Direct Service (Single Item)' },
];

const formData = ref({
  enrollee_id: null,
  referring_facility_id: null,
  receiving_facility_id: null,
  presenting_complains: '',
  reasons_for_referral: '',
  treatments_given: '',
  investigations_done: '',
  examination_findings: '',
  preliminary_diagnosis: '',
  medical_history: '',
  medication_history: '',
  severity_level: 'Routine',
  referring_person_name: '',
  referring_person_specialisation: '',
  referring_person_cadre: '',
  contact_person_name: '',
  contact_person_phone: '',
  contact_person_email: '',
  service_selection_type: null,
  service_bundle_id: null,
  case_record_id: null,
});

// Computed properties for filtering facilities
const primaryFacilities = computed(() => {
  return facilities.value.filter(f => f.type === 'Primary');
});

const secondaryFacilities = computed(() => {
  return facilities.value.filter(f => f.type === 'Secondary' || f.type === 'Tertiary');
});

onMounted(async () => {
  await Promise.all([
    fetchFacilities(),
    fetchServiceBundles(),
    fetchCaseRecords(),
  ]);
});

// Fetch facilities
const fetchFacilities = async () => {
  loadingFacilities.value = true;
  try {
    const response = await api.get('/facilities');
    facilities.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load facilities');
  } finally {
    loadingFacilities.value = false;
  }
};

// Fetch enrollees for a specific facility
const fetchEnrollees = async (facilityId) => {
  if (!facilityId) {
    enrollees.value = [];
    return;
  }

  loadingEnrollees.value = true;
  try {
    const response = await api.get(`/facilities/${facilityId}/enrollees`);
    enrollees.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load enrollees for this facility');
    enrollees.value = [];
  } finally {
    loadingEnrollees.value = false;
  }
};

// Handle referring facility change
const onReferringFacilityChange = (facilityId) => {
  // Clear enrollee selection when facility changes
  formData.value.enrollee_id = null;
  enrollees.value = [];

  // Fetch enrollees for the selected facility
  if (facilityId) {
    fetchEnrollees(facilityId);
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
      display_name: `${bundle.description} - ₦${Number(bundle.fixed_price).toLocaleString()}`
    }));
  } catch (err) {
    showError('Failed to load service bundles');
  } finally {
    loadingBundles.value = false;
  }
};

// Fetch case records (services)
const fetchCaseRecords = async () => {
  loadingCaseRecords.value = true;
  try {
    const response = await api.get('/cases');
    caseRecords.value = (response.data.data || response.data).map(record => ({
      ...record,
      display_name: `${record.case_name} (${record.nicare_code})`
    }));
  } catch (err) {
    showError('Failed to load services');
  } finally {
    loadingCaseRecords.value = false;
  }
};

// Handle service selection type change
const onServiceTypeChange = (type) => {
  // Clear selections when type changes
  formData.value.service_bundle_id = null;
  formData.value.case_record_id = null;
};

// Get icon for case record type
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

// Stepper navigation
const nextStep = async (step) => {
  let formRef = null;

  if (step === 1) {
    formRef = step1Form.value;
  } else if (step === 2) {
    formRef = step2Form.value;
  } else if (step === 3) {
    formRef = step3Form.value;
  }

  if (formRef) {
    const { valid } = await formRef.validate();
    if (!valid) {
      showError('Please fill in all required fields');
      return;
    }
  }

  currentStep.value = step + 1;
};

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

// Get status color
const getStatusColor = (status) => {
  const colors = {
    'APPROVED': 'green',
    'PENDING': 'orange',
    'DENIED': 'red',
  };
  return colors[status] || 'gray';
};

// Handle form submission
const handleSubmission = async () => {
  // Validate all steps
  const step1Valid = await step1Form.value?.validate();
  const step2Valid = await step2Form.value?.validate();
  const step3Valid = await step3Form.value?.validate();

  if (!step1Valid?.valid || !step2Valid?.valid || !step3Valid?.valid) {
    showError('Please fill in all required fields in all steps');
    return;
  }

  loading.value = true;
  error.value = null;
  createdReferral.value = null;

  try {
    const payload = {
      ...formData.value,
    };

    const response = await api.post('/referrals', payload);
    createdReferral.value = response.data.data || response.data;

    showSuccess(`Referral created successfully! UTN: ${createdReferral.value.utn}`);
    showSuccessDialog.value = true;
    resetForm();
  } catch (err) {
    const message = err.response?.data?.message || err.message || 'Failed to submit referral';
    error.value = message;
    showError(message);
  } finally {
    loading.value = false;
  }
};

// Reset form fields and stepper
const resetForm = () => {
  formData.value = {
    enrollee_id: null,
    referring_facility_id: null,
    receiving_facility_id: null,
    presenting_complains: '',
    reasons_for_referral: '',
    treatments_given: '',
    investigations_done: '',
    examination_findings: '',
    preliminary_diagnosis: '',
    medical_history: '',
    medication_history: '',
    severity_level: 'Routine',
    referring_person_name: '',
    referring_person_specialisation: '',
    referring_person_cadre: '',
    contact_person_name: '',
    contact_person_phone: '',
    contact_person_email: '',
  };
  requestedServices.value = [];
  currentStep.value = 1;
  step1Form.value?.reset?.();
  step2Form.value?.reset?.();
  step3Form.value?.reset?.();
};
</script>

<style scoped>
.referral-submission-page {
  padding: 20px 0;
}
</style>
