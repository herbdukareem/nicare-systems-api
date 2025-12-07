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
                                outlined
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
                                outlined
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
                                outlined
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
                                outlined
                                hint="Chief complaints of the patient"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.preliminary_diagnosis"
                                label="Preliminary Diagnosis (ICD-10 Code) *"
                                :rules="[v => !!v || 'Diagnosis is required']"
                                outlined
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
                                outlined
                                hint="Routine, Urgent/Expedited, or Emergency"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.reasons_for_referral"
                                label="Reasons for Referral *"
                                :rules="[v => !!v || 'Reasons for referral are required']"
                                outlined
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
                                outlined
                                rows="3"
                                hint="What treatments have been administered?"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.investigations_done"
                                label="Investigations Done *"
                                :rules="[v => !!v || 'Investigations done are required']"
                                outlined
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
                                outlined
                                rows="3"
                                hint="Physical examination results"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-textarea
                                v-model="formData.medical_history"
                                label="Medical History"
                                outlined
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
                                outlined
                                rows="2"
                                hint="Current medications (optional)"
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
                                outlined
                                hint="Name of the referring medical personnel"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="formData.referring_person_specialisation"
                                label="Specialisation *"
                                :rules="[v => !!v || 'Specialisation is required']"
                                outlined
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
                                outlined
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
                                outlined
                                hint="Optional contact person"
                              />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field
                                v-model="formData.contact_person_phone"
                                label="Contact Phone"
                                outlined
                                hint="Phone number for follow-up"
                              />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field
                                v-model="formData.contact_person_email"
                                label="Contact Email"
                                type="email"
                                outlined
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

                        <!-- Requested Services (PA Items) -->
                        <h4 class="mt-4 mb-2">Requested Services (PA Items) <span class="text-caption">(optional)</span></h4>
                        <v-alert
                            v-if="requestedServices.length === 0"
                            type="info"
                            class="mb-4"
                        >
                          You can skip services now and add them later, or request at least one tariff item for PA.
                        </v-alert>

                        <v-table density="compact" class="mb-4">
                            <thead>
                                <tr>
                                    <th style="width: 60%">Service / Tariff Item</th>
                                    <th class="text-center" style="width: 15%">Quantity</th>
                                    <th class="text-center" style="width: 15%">Price</th>
                                    <th class="text-center" style="width: 10%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(service, index) in requestedServices" :key="index">
                                    <td>
                                        <v-autocomplete
                                            v-model="service.case_record_id"
                                            :items="caseRecords"
                                            item-title="service_description"
                                            item-value="id"
                                            variant="underlined"
                                            density="compact"
                                            :rules="[v => !!v || 'Required']"
                                            @update:modelValue="onServiceSelected(index, $event)"
                                            :loading="loadingServices"
                                            hint="Search service"
                                            clearable
                                        >
                                          <template v-slot:item="{ item, props }">
                                            <v-list-item v-bind="props">
                                              <v-list-item-title>{{ item.raw.service_description }}</v-list-item-title>
                                              <v-list-item-subtitle>{{ item.raw.nicare_code }} | ₦{{ item.raw.price }}</v-list-item-subtitle>
                                            </v-list-item>
                                          </template>
                                        </v-autocomplete>
                                    </td>
                                    <td class="text-center">
                                        <v-text-field
                                            v-model.number="service.quantity"
                                            type="number"
                                            min="1"
                                            variant="underlined"
                                            density="compact"
                                            :rules="[v => v >= 1 || 'Min 1']"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <span class="font-weight-medium">₦{{ service.price || 0 }}</span>
                                    </td>
                                    <td class="text-center">
                                        <v-btn
                                            icon
                                            variant="plain"
                                            size="small"
                                            color="error"
                                            @click="removeService(index)"
                                            title="Remove service"
                                        >
                                            <v-icon>mdi-delete</v-icon>
                                        </v-btn>
                                    </td>
                                </tr>
                            </tbody>
                        </v-table>

                        <v-btn color="success" @click="addService" class="mb-4">
                            <v-icon left>mdi-plus</v-icon>
                            Add Service Request
                        </v-btn>
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
const loadingFacilities = ref(false);
const loadingEnrollees = ref(false);
const loadingServices = ref(false);

const requestedServices = ref([]);
const showSuccessDialog = ref(false);

const severityLevels = [
  { title: 'Routine', value: 'Routine' },
  { title: 'Urgent/Expedited', value: 'Urgent/Expidited' },
  { title: 'Emergency', value: 'Emergency' },
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

// Fetch case records (services)
const fetchCaseRecords = async () => {
  loadingServices.value = true;
  try {
    const response = await api.get('/cases');
    caseRecords.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load services');
  } finally {
    loadingServices.value = false;
  }
};

// Add service to requested services
const addService = () => {
  requestedServices.value.push({
    case_record_id: null,
    quantity: 1,
    price: 0,
  });
};

// Remove service from requested services
const removeService = (index) => {
  requestedServices.value.splice(index, 1);
};

// Handle service selection
const onServiceSelected = (index, caseRecordId) => {
  if (!caseRecordId) return;

  const caseRecord = caseRecords.value.find(c => c.id === caseRecordId);
  if (caseRecord) {
    requestedServices.value[index].price = caseRecord.price;
  }
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
      requested_services: requestedServices.value.map(s => ({
        case_record_id: s.case_record_id,
        quantity: s.quantity,
      })),
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
