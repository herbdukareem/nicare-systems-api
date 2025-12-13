<template>
  <AdminLayout>
    <div class="referral-request-page">
      <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-secondary text-white">
              <v-icon left>mdi-chevron-right-box</v-icon>
              Referral & PA Code Requests
            </v-card-title>
            <v-card-text>
              <v-alert type="info" class="mb-4">
                <strong>Submit a new referral for PA Code <em>or</em> attach a follow-up PA Code request to an existing UTN.</strong><br>
                Choose your flow below. Facilities are auto-populated; follow-up flow requires UTN validation.
              </v-alert>

              <v-alert v-if="error" type="error" class="mb-4">{{ error }}</v-alert>

              <!-- Flow Switcher -->
              <div class="d-flex align-center mb-4">
                <span class="text-subtitle-2 mr-3">Flow:</span>
                <v-btn-toggle
                  v-model="flowType"
                  color="primary"
                  rounded
                  mandatory
                  class="elevation-0"
                >
                  <v-btn value="new" prepend-icon="mdi-file-plus-outline">New Referral PA Code</v-btn>
                  <v-btn value="followup" prepend-icon="mdi-repeat">Follow-up PA Code (Existing UTN)</v-btn>
                </v-btn-toggle>
              </div>

              <v-form ref="referralForm" @submit.prevent="handleSubmission">
                <!-- Follow-up UTN validation -->
                <v-expand-transition>
                  <div v-if="flowType === 'followup'">
                    <v-card variant="outlined" class="mb-4">
                      <v-card-title>
                        <v-icon left>mdi-shield-check</v-icon>
                        Validate Existing UTN
                      </v-card-title>
                      <v-card-text>
                        <v-row>
                          <v-col cols="12" md="6">
                            <v-text-field
                              v-model="utnInput"
                              label="Unique Transaction Number (UTN)"
                              placeholder="Enter UTN to attach a follow-up request"
                              outlined
                              :rules="[v => !!v || 'UTN is required']"
                              required
                            />
                          </v-col>
                          <v-col cols="12" md="6" class="d-flex align-center">
                            <v-btn color="primary" @click="validateUTN" :loading="validatingUTN" class="mt-0">
                              <v-icon left>mdi-magnify</v-icon>
                              Validate UTN
                            </v-btn>
                            <v-chip
                              v-if="utnValidationStatus"
                              :color="utnValidationStatus === 'valid' ? 'green' : 'error'"
                              class="ml-3"
                              text-color="white"
                            >
                              {{ utnValidationStatus === 'valid' ? 'UTN Validated' : 'Invalid UTN' }}
                            </v-chip>
                          </v-col>
                        </v-row>

                        <v-alert
                          v-if="validatedReferral"
                          type="success"
                          class="mb-2"
                          border="start"
                          border-color="green"
                        >
                          <div class="font-weight-medium mb-1">Referral Found</div>
                          <div class="text-caption">
                            Enrollee: {{ validatedReferral?.enrollee?.full_name || 'N/A' }} ({{ validatedReferral?.enrollee?.nicare_number || 'N/A' }})<br>
                            Receiving Facility: {{ validatedReferral?.receiving_facility?.name || 'N/A' }}<br>
                            Severity: {{ validatedReferral?.severity_level || 'N/A' }}
                          </div>
                        </v-alert>
                      </v-card-text>
                    </v-card>
                  </div>
                </v-expand-transition>

                <!-- Patient & Facility Context -->
                <h3 class="mt-4 mb-2">Patient & Facility Context</h3>
                <v-row>
                  <!-- Referring Facility (Auto-populated from logged-in user) -->
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="referringFacilityName"
                      label="Referring Facility (Your Facility)"
                      outlined
                      readonly
                      hint="Auto-populated from your account"
                    />
                  </v-col>

                  <!-- Enrollee Selection -->
                  <v-col cols="12" md="6">
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
                      :search-input.sync="enrolleeSearch"
                      hint="Search by name or NiCare number"
                      clearable
                      :disabled="flowType === 'followup' && !!validatedReferral"
                    >
                      <template v-slot:item="{ item, props }">
                        <v-list-item v-bind="props">
                          <v-list-item-title>{{ item.raw.full_name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ item.raw.nicare_number }} | {{ item.raw.phone_number }}</v-list-item-subtitle>
                        </v-list-item>
                      </template>
                    </v-autocomplete>
                  </v-col>
                </v-row>

                <v-row>
                  <!-- Receiving Facility -->
                  <v-col cols="12" md="6">
                    <v-autocomplete
                      v-model="formData.receiving_facility_id"
                      label="Receiving Facility *"
                      :items="receivingFacilities"
                      item-title="name"
                      item-value="id"
                      outlined
                      required
                      :rules="[v => !!v || 'Receiving facility is required']"
                      :loading="loadingFacilities"
                      hint="Select the facility receiving the referral"
                      clearable
                      :disabled="flowType === 'followup' && !!validatedReferral"
                    >
                      <template v-slot:item="{ item, props }">
                        <v-list-item v-bind="props">
                          <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ item.raw.facility_code }} | {{ item.raw.level_of_care }}</v-list-item-subtitle>
                        </v-list-item>
                      </template>
                    </v-autocomplete>
                  </v-col>

                  <!-- Severity Level -->
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
                </v-row>

                <!-- Clinical Information -->
                <h3 class="mt-4 mb-2">Clinical Information</h3>
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
                    <v-textarea
                      v-model="formData.reasons_for_referral"
                      label="Reasons for Referral *"
                      :rules="[v => !!v || 'Reasons for referral are required']"
                      outlined
                      rows="3"
                      hint="Why is this referral necessary?"
                    />
                  </v-col>
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
                </v-row>

                <v-row>
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
                </v-row>

                <v-row>
                  <v-col cols="12" md="6">
                    <v-textarea
                      v-model="formData.medical_history"
                      label="Medical History"
                      outlined
                      rows="2"
                      hint="Past medical history (optional)"
                    />
                  </v-col>
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

                <!-- Referring Personnel -->
                <h3 class="mt-4 mb-2">Referring Personnel Information</h3>
                <v-row>
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model="formData.referring_person_name"
                      label="Referring Person Name *"
                      :rules="[v => !!v || 'Referring person name is required']"
                      outlined
                      hint="Name of the referring medical personnel"
                    />
                  </v-col>
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model="formData.referring_person_specialisation"
                      label="Specialisation *"
                      :rules="[v => !!v || 'Specialisation is required']"
                      outlined
                      hint="e.g., General Medicine, Pediatrics"
                    />
                  </v-col>
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model="formData.referring_person_cadre"
                      label="Cadre *"
                      :rules="[v => !!v || 'Cadre is required']"
                      outlined
                      hint="e.g., Doctor, Nurse, Medical Officer"
                    />
                  </v-col>
                </v-row>

                <!-- Contact Information -->
                <h3 class="mt-4 mb-2">Contact Information</h3>
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

                <!-- Supporting Documents -->
                <h3 class="mt-4 mb-2">Supporting Documents</h3>
                <v-alert type="info" density="compact" class="mb-4">
                  Upload required supporting documents for your referral request.
                </v-alert>

                <v-row v-if="documentRequirements.length > 0">
                  <v-col cols="12">
                    <v-table density="compact" class="mb-4">
                      <thead>
                        <tr>
                          <th style="width: 30%">Document Type</th>
                          <th style="width: 35%">Description</th>
                          <th style="width: 10%">Required</th>
                          <th style="width: 25%">Upload</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="requirement in documentRequirements" :key="requirement.id">
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
                              v-model="uploadedDocuments[requirement.document_type]"
                              :label="`Upload ${requirement.name}`"
                              variant="outlined"
                              density="compact"
                              :accept="requirement.allowed_file_types.split(',').map(t => '.' + t.trim()).join(',')"
                              :rules="requirement.is_required ? [v => !!v || `${requirement.name} is required`] : []"
                              prepend-icon="mdi-paperclip"
                              show-size
                              clearable
                              @update:model-value="(file) => handleDocumentUpload(requirement, file)"
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
                  </v-col>
                </v-row>

                <v-alert v-else type="warning" density="compact" class="mb-4">
                  No document requirements configured for referrals.
                </v-alert>

                <!-- Requested Services (PA Items) -->
                <h3 class="mt-4 mb-2">Requested Services (PA Items)</h3>
                <v-alert
                    v-if="requestedServices.length === 0"
                    type="warning"
                    class="mb-4"
                >
                  Add at least one service/tariff item to be authorized.
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

                <!-- Submit Button -->
                <v-card-actions class="mt-6 pa-0">
                    <v-btn
                        color="primary"
                        type="submit"
                        :loading="loading"
                        :disabled="requestedServices.length === 0"
                        block
                        size="large"
                    >
                        <v-icon left>mdi-send</v-icon>
                        Submit Referral Request
                    </v-btn>
                </v-card-actions>
              </v-form>

              <!-- Success Message -->
              <v-alert
                v-if="createdReferral"
                type="success"
                class="mt-6"
              >
                <div class="text-h6 mb-2">✅ Referral Request Submitted Successfully!</div>
                <v-divider class="my-3"></v-divider>
                <p><strong>Unique Transaction Number (UTN):</strong> <span class="text-h6 text-primary">{{ createdReferral.utn }}</span></p>
                <p><strong>Referral Code:</strong> {{ createdReferral.referral_code }}</p>
                <p><strong>Status:</strong>
                  <v-chip :color="getStatusColor(createdReferral.status)" text-color="white" small>
                    {{ createdReferral.status || 'PENDING' }}
                  </v-chip>
                </p>
                <p class="mt-3 text-caption">Your referral request has been submitted for approval. Please use this UTN when submitting claims for these services.</p>
              </v-alert>

            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useToast } from '../../composables/useToast';
import { useAuthStore } from '../../stores/auth';
import api, { doDashboardAPI } from '../../utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const authStore = useAuthStore();
const { showSuccess, showError } = useToast();

// Reactive state
const loading = ref(false);
const error = ref(null);
const createdReferral = ref(null);
const referralForm = ref(null);
const flowType = ref('new'); // 'new' | 'followup'
const utnInput = ref('');
const utnValidationStatus = ref(null); // 'valid' | 'invalid'
const validatingUTN = ref(false);
const validatedReferral = ref(null);

const facilities = ref([]);
const enrollees = ref([]);
const caseRecords = ref([]);
const documentRequirements = ref([]);
const uploadedDocuments = ref({});
const loadingFacilities = ref(false);
const loadingEnrollees = ref(false);
const loadingServices = ref(false);
const loadingDocuments = ref(false);
const enrolleeSearch = ref('');

const requestedServices = ref([]);

const severityLevels = [
  { title: 'Routine', value: 'Routine' },
  { title: 'Urgent/Expedited', value: 'Urgent/Expidited' },
  { title: 'Emergency', value: 'Emergency' },
];

const formData = ref({
  enrollee_id: null,
  referring_facility_id: null, // Will be auto-populated from logged-in user
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

// Computed property for referring facility name
const referringFacilityName = computed(() => {
  if (!formData.value.referring_facility_id) return 'Loading...';
  const facility = facilities.value.find(f => f.id === formData.value.referring_facility_id);
  return facility ? `${facility.name} (${facility.facility_code})` : 'Your Facility';
});

// Computed property for receiving facilities (exclude referring facility)
const receivingFacilities = computed(() => {
  if (!formData.value.referring_facility_id) return facilities.value;
  return facilities.value.filter(f => f.id !== formData.value.referring_facility_id);
});

onMounted(async () => {
  // Auto-populate referring facility from logged-in user's facility
  formData.value.referring_facility_id = authStore.user?.facility_id || 1;

  await Promise.all([
    fetchFacilities(),
    fetchEnrollees(),
    fetchCaseRecords(),
    fetchDocumentRequirements(),
  ]);
});

// Fetch facilities
const fetchFacilities = async () => {
  loadingFacilities.value = true;
  try {
    const response = await api.get('/v1/facilities');
    facilities.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load facilities');
  } finally {
    loadingFacilities.value = false;
  }
};

// Fetch enrollees
const fetchEnrollees = async () => {
  loadingEnrollees.value = true;
  try {
    const response = await api.get('/v1/enrollees');
    enrollees.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load enrollees');
  } finally {
    loadingEnrollees.value = false;
  }
};

// Fetch case records (services)
const fetchCaseRecords = async () => {
  loadingServices.value = true;
  try {
    const response = await api.get('/v1/cases');
    caseRecords.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load services');
  } finally {
    loadingServices.value = false;
  }
};

// Fetch document requirements for referrals
const fetchDocumentRequirements = async () => {
  loadingDocuments.value = true;
  try {
    const response = await api.get('/v1/document-requirements', {
      params: { request_type: 'referral', status: 1 }
    });
    documentRequirements.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load document requirements');
  } finally {
    loadingDocuments.value = false;
  }
};

// Reset follow-up specific state when switching flows
watch(flowType, (val) => {
  if (val === 'new') {
    utnInput.value = '';
    utnValidationStatus.value = null;
    validatedReferral.value = null;
  }
});

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

// Handle document upload
const handleDocumentUpload = (requirement, files) => {
  if (!files || files.length === 0) {
    uploadedDocuments.value[requirement.document_type] = null;
    return;
  }

  const uploadedFile = files[0];

  // Validate file size
  const maxSizeBytes = requirement.max_file_size_mb * 1024 * 1024;
  if (uploadedFile.size > maxSizeBytes) {
    showError(`File size exceeds ${requirement.max_file_size_mb}MB limit`);
    uploadedDocuments.value[requirement.document_type] = null;
    return;
  }

  // Validate file type
  const allowedTypes = requirement.allowed_file_types.split(',').map(t => t.trim());
  const fileExtension = uploadedFile.name.split('.').pop().toLowerCase();
  if (!allowedTypes.includes(fileExtension)) {
    showError(`File type .${fileExtension} is not allowed. Allowed types: ${requirement.allowed_file_types}`);
    uploadedDocuments.value[requirement.document_type] = null;
    return;
  }

  // File is valid, store it
  uploadedDocuments.value[requirement.document_type] = uploadedFile;
};

// Validate UTN for follow-up flow
const validateUTN = async () => {
  if (!utnInput.value) {
    showError('Enter a UTN to validate');
    return;
  }

  validatingUTN.value = true;
  utnValidationStatus.value = null;
  validatedReferral.value = null;

  try {
    const response = await doDashboardAPI.validateUTN({ utn: utnInput.value });
    validatedReferral.value = response.data?.data || response.data?.referral || response.data || null;
    utnValidationStatus.value = 'valid';

    // Auto-fill form based on validated referral
    if (validatedReferral.value) {
      formData.value.enrollee_id = validatedReferral.value.enrollee_id || validatedReferral.value.enrollee?.id || null;
      formData.value.receiving_facility_id = validatedReferral.value.receiving_facility_id || validatedReferral.value.receiving_facility?.id || null;
      formData.value.referring_facility_id = validatedReferral.value.referring_facility_id || validatedReferral.value.facility_id || formData.value.referring_facility_id;
      formData.value.severity_level = validatedReferral.value.severity_level || formData.value.severity_level;
    }
  } catch (err) {
    utnValidationStatus.value = 'invalid';
    const message = err.response?.data?.message || err.message || 'Failed to validate UTN';
    showError(message);
  } finally {
    validatingUTN.value = false;
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
  const isFollowUp = flowType.value === 'followup';

  if (isFollowUp && utnValidationStatus.value !== 'valid') {
    showError('Please validate the UTN before submitting a follow-up PA request');
    return;
  }

  const validation = await referralForm.value?.validate();
  if (!validation?.valid) {
    showError('Please fill in all required fields');
    return;
  }

  if (requestedServices.value.length === 0) {
    showError('Please add at least one service request');
    return;
  }

  // Validate required documents
  const requiredDocs = documentRequirements.value.filter(req => req.is_required);
  for (const req of requiredDocs) {
    if (!uploadedDocuments.value[req.document_type]) {
      showError(`Required document "${req.name}" is missing`);
      return;
    }
  }

  loading.value = true;
  error.value = null;
  createdReferral.value = null;

  try {
    // Create FormData for file uploads
    const formDataPayload = new FormData();

    // Add all form fields
    Object.keys(formData.value).forEach(key => {
      if (formData.value[key] !== null && formData.value[key] !== undefined) {
        formDataPayload.append(key, formData.value[key]);
      }
    });

    // Add requested services as JSON
    formDataPayload.append('requested_services', JSON.stringify(
      requestedServices.value.map(s => ({
        case_record_id: s.case_record_id,
        quantity: s.quantity,
      }))
    ));

    // Add flow type and UTN for follow-up
    formDataPayload.append('flow_type', flowType.value);
    if (isFollowUp) {
      formDataPayload.append('utn', utnInput.value);
    }

    // Add uploaded documents
    Object.keys(uploadedDocuments.value).forEach(docType => {
      const file = uploadedDocuments.value[docType];
      if (file) {
        formDataPayload.append(`documents[${docType}]`, file);
      }
    });

    const response = await api.post('/v1/referrals', formDataPayload, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    createdReferral.value = response.data.data || response.data;

    showSuccess(`Referral request submitted successfully! UTN: ${createdReferral.value.utn}`);

    // Reset form
    resetForm();
  } catch (err) {
    const message = err.response?.data?.message || err.message || 'Failed to submit referral request';
    error.value = message;
    showError(message);
  } finally {
    loading.value = false;
  }
};

// Reset form
const resetForm = () => {
  formData.value = {
    enrollee_id: null,
    referring_facility_id: authStore.user?.facility_id || 1,
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
  uploadedDocuments.value = {};
  flowType.value = 'new';
  utnInput.value = '';
  utnValidationStatus.value = null;
  validatedReferral.value = null;
  referralForm.value?.reset();
};
</script>

<style scoped>
.referral-request-page {
  padding: 20px 0;
}
</style>
