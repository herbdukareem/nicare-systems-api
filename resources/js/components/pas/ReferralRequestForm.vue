<template>
  <v-form ref="formRef" @submit.prevent="submitForm">
    <div class="tw-space-y-6">


      <!-- Receiving Provider Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-purple-50 tw-text-purple-800">
          <v-icon class="tw-mr-2">mdi-hospital-marker</v-icon>
          Receiving Provider/Facility Details
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-select
              v-model="form.receiving_facility_id"
              :items="secondaryFacilities"
              item-title="name"
              item-value="id"
              label="Receiving Facility *"
              variant="outlined"
              density="compact"
              :loading="facilitiesLoading"
              :rules="[rules.required]"
              @update:model-value="handleFacilitySelection"
            >
              <template #item="{ props, item }">
                <v-list-item v-bind="props">
                  <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ item.raw.hcp_code }} - {{ item.raw.level_of_care }}</v-list-item-subtitle>
                </v-list-item>
              </template>
            </v-select>
            <v-text-field
              v-model="form.receiving_nicare_code"
              label="NiCare Code"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-lighten-4"
            />
            <v-textarea
              v-model="form.receiving_address"
              label="Address"
              variant="outlined"
              density="compact"
              rows="2"
              readonly
              bg-color="grey-lighten-4"
            />
            <v-text-field
              v-model="form.receiving_phone"
              label="Phone Number"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-lighten-4"
            />
            <v-text-field
              v-model="form.receiving_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              readonly
              bg-color="grey-lighten-4"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Clinical Justification -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-red-50 tw-text-red-800">
          <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
          Clinical Justification
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-textarea
              v-model="form.presenting_complaints"
              label="Presenting Complaints *"
              variant="outlined"
              rows="3"
              :rules="[rules.required]"
            />
            <v-textarea
              v-model="form.reasons_for_referral"
              label="Reasons for Referral *"
              variant="outlined"
              rows="3"
              :rules="[rules.required]"
            />
            <v-textarea
              v-model="form.treatments_given"
              label="Treatments Given at Facility"
              variant="outlined"
              rows="3"
            />
            <v-textarea
              v-model="form.investigations_done"
              label="Investigations Done at Facility"
              variant="outlined"
              rows="3"
            />
            <v-textarea
              v-model="form.examination_findings"
              label="Examination Findings"
              variant="outlined"
              rows="3"
            />
            <v-textarea
              v-model="form.preliminary_diagnosis"
              label="Preliminary Diagnosis *"
              variant="outlined"
              rows="3"
              :rules="[rules.required]"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Basic History -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-indigo-50 tw-text-indigo-800">
          <v-icon class="tw-mr-2">mdi-history</v-icon>
          Basic History of Patient
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-textarea
              v-model="form.medical_history"
              label="Medical History"
              variant="outlined"
              rows="4"
            />
            <v-textarea
              v-model="form.medication_history"
              label="Medication History"
              variant="outlined"
              rows="4"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- PA Code Severity Level -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-yellow-50 tw-text-yellow-800">
          <v-icon class="tw-mr-2">mdi-alert-circle</v-icon>
          PA Code Severity Level
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <v-radio-group v-model="form.severity_level" :rules="[rules.required]">
            <v-radio
              label="Emergency (30 minutes)"
              value="emergency"
              color="red"
            />
            <v-radio
              label="Urgent/Expedited (3 hours)"
              value="urgent"
              color="orange"
            />
            <v-radio
              label="Routine (72 hours)"
              value="routine"
              color="blue"
            />
          </v-radio-group>
        </v-card-text>
      </v-card>

      <!-- Referring Personnel Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-teal-50 tw-text-teal-800">
          <v-icon class="tw-mr-2">mdi-doctor</v-icon>
          Referring Personnel Details
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-text-field
              v-model="form.personnel_full_name"
              label="Full Name *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-select
              v-model="form.personnel_specialization"
              :items="specializationOptions"
              label="Area of Specialisation"
              variant="outlined"
              density="compact"
            />
            <v-select
              v-model="form.personnel_cadre"
              :items="cadreOptions"
              label="Cadre"
              variant="outlined"
              density="compact"
            />
            <v-text-field
              v-model="form.personnel_phone"
              label="Phone Number *"
              variant="outlined"
              density="compact"
              :rules="[rules.required, rules.phone]"
            />
            <v-text-field
              v-model="form.personnel_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              :rules="[rules.email]"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Supporting Documents -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-paperclip</v-icon>
          Supporting Documents (Attachments)
        </v-card-title>
        <v-card-text class="tw-pt-6">
          <div class="tw-space-y-6">
            <div>
              <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                Enrollee ID Card/Slip
              </label>
              <FileUpload
                v-model="form.enrollee_id_card"
                accept="image/*,.pdf"
                :multiple="false"
                :max-size="5 * 1024 * 1024"
                :upload-progress="uploadProgress"
              />
            </div>

            <div>
              <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                Referral Letter/Slip *
              </label>
              <FileUpload
                v-model="form.referral_letter"
                accept="image/*,.pdf,.doc,.docx"
                :multiple="false"
                :max-size="5 * 1024 * 1024"
                :upload-progress="uploadProgress"
              />
            </div>

            <div>
              <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                Passport/Travel Document
              </label>
              <FileUpload
                v-model="form.passport"
                accept="image/*,.pdf"
                :multiple="false"
                :max-size="5 * 1024 * 1024"
                :upload-progress="uploadProgress"
              />
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>
  </v-form>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useToast } from '../../composables/useToast';
import FileUpload from '../common/FileUpload.vue';
import { facilityAPI, pasAPI } from '../../utils/api.js';

const props = defineProps({
  selectedFacility: {
    type: Object,
    default: null
  },
  selectedEnrollee: {
    type: Object,
    default: null
  },
  selectedServices: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['submit', 'cancel']);
const { success, error } = useToast();

const formRef = ref(null);
const loading = ref(false);
const uploadProgress = ref({});
const secondaryFacilities = ref([]);
const facilitiesLoading = ref(false);

// Form data
const form = reactive({
  // Required API fields
  facility_id: null,
  enrollee_id: '',
  request_type: 'referral',
  services: [],

  // Referring Provider
  referring_facility_name: '',
  referring_nicare_code: '',
  referring_address: '',
  referring_phone: '',
  referring_email: '',
  tpa_name: '',
  
  // Contact Person
  contact_full_name: '',
  contact_phone: '',
  contact_email: '',
  
  // Receiving Provider
  receiving_facility_id: null,
  receiving_facility_name: '',
  receiving_nicare_code: '',
  receiving_address: '',
  receiving_phone: '',
  receiving_email: '',
  
  // Patient/Enrollee
  nicare_number: '',
  enrollee_full_name: '',
  gender: '',
  age: '',
  marital_status: '',
  enrollee_category: '',
  enrollee_phone_main: '',
  enrollee_phone_encounter: '',
  enrollee_phone_relation: '',
  enrollee_email: '',
  programme: '',
  organization: '',
  benefit_plan: '',
  referral_date: '',
  
  // Clinical Justification
  presenting_complaints: '',
  reasons_for_referral: '',
  treatments_given: '',
  investigations_done: '',
  examination_findings: '',
  preliminary_diagnosis: '',
  
  // Basic History
  medical_history: '',
  medication_history: '',
  
  // Severity Level
  severity_level: '',
  
  // Referring Personnel
  personnel_full_name: '',
  personnel_specialization: '',
  personnel_cadre: '',
  personnel_phone: '',
  personnel_email: '',
  
  // Supporting Documents
  enrollee_id_card: [],
  referral_letter: [],
  passport: []
});

// Validation rules
const rules = {
  required: (value) => !!value || 'This field is required',
  email: (value) => !value || /.+@.+\..+/.test(value) || 'Invalid email format',
  phone: (value) => !value || /^[\d\s\-\+\(\)]+$/.test(value) || 'Invalid phone number format'
};

// Options for select fields
const specializationOptions = ['O&G', 'Paediatrics', 'Public Health', 'ENT', 'Family Medicine', 'Internal Medicine', 'Surgery'];
const cadreOptions = ['CHEW', 'M.O', 'CHO', 'D.O', 'N.O', 'Registrar', 'Consultant'];

// Methods
const loadSecondaryFacilities = async () => {
  try {
    facilitiesLoading.value = true;
    const response = await facilityAPI.getAll({
      status: 1, // Only active facilities
      level_of_care: 'secondary', // Only secondary facilities
      per_page: 1000 // Get all facilities
    });

    if (response.data.success) {
      secondaryFacilities.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load secondary facilities:', err);
    error('Failed to load secondary facilities');
  } finally {
    facilitiesLoading.value = false;
  }
};

const handleFacilitySelection = (facilityId) => {
  const selectedFacility = secondaryFacilities.value.find(f => f.id === facilityId);
  if (selectedFacility) {
    form.receiving_facility_name = selectedFacility.name;
    form.receiving_nicare_code = selectedFacility.hcp_code;
    form.receiving_address = selectedFacility.address || '';
    form.receiving_phone = selectedFacility.phone || '';
    form.receiving_email = selectedFacility.email || '';
  }
};
const submitForm = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  loading.value = true;

  try {
    // Create FormData for file uploads
    const formData = new FormData();

    // Add all form fields
    Object.keys(form).forEach(key => {
      if (form[key] !== null && form[key] !== '' && form[key] !== undefined) {
        if (key === 'enrollee_id_card' || key === 'referral_letter' || key === 'passport') {
          // Handle file uploads
          if (form[key] && form[key].length > 0) {
            formData.append(key, form[key][0]);
          }
        } else if (key === 'services') {
          // Handle services array - send as JSON string for FormData
          formData.append('services', JSON.stringify(form[key]));
        } else {
          formData.append(key, form[key]);
        }
      }
    });

    // Submit to API using the API utility
    const response = await pasAPI.createReferral(formData);

    if (response.data.success) {
      const referralData = response.data.data;
      // The API returns { referral: {...}, uploads: {...} }
      const referral = referralData.referral || referralData;
      const referralCode = referral.referral_code;

      success(`Referral submitted successfully! Code: ${referralCode}`);

      // Emit the referral data with navigation info
      emit('submit', {
        ...referral,
        showSuccessDialog: true,
        referral_code: referralCode,
        referralCode: referralCode // For backward compatibility
      });

      resetForm();
    } else {
      error(response.data.message || 'Failed to submit referral request');
    }

  } catch (err) {
    console.error('Submission error:', err);
    if (err.response?.data?.errors) {
      // Handle validation errors
      const validationErrors = err.response.data.errors;
      Object.keys(validationErrors).forEach(field => {
        if (formRef.value) {
          // Set field errors if possible
          error(`${field}: ${validationErrors[field][0]}`);
        }
      });
    } else {
      error(err.response?.data?.message || 'Failed to submit referral request');
    }
  } finally {
    loading.value = false;
    uploadProgress.value = {};
  }
};

const resetForm = () => {
  Object.keys(form).forEach(key => {
    if (typeof form[key] === 'string') {
      form[key] = '';
    } else if (typeof form[key] === 'number') {
      form[key] = '';
    } else if (Array.isArray(form[key])) {
      form[key] = [];
    } else if (form[key] === null) {
      form[key] = null;
    }
  });
  // Specifically reset facility selection
  form.receiving_facility_id = null;
};

// Load secondary facilities on component mount
onMounted(() => {
  loadSecondaryFacilities();

  // Pre-populate referring facility data if provided
  if (props.selectedFacility) {
    form.facility_id = props.selectedFacility.id;
    form.referring_facility_name = props.selectedFacility.name;
    form.referring_nicare_code = props.selectedFacility.hcp_code || props.selectedFacility.code;
    form.referring_address = props.selectedFacility.address || '';
    form.referring_phone = props.selectedFacility.phone || '';
    form.referring_email = props.selectedFacility.email || '';
  }

  // Pre-populate enrollee data if provided
  if (props.selectedEnrollee) {
    // Use the database ID, not the enrollee_id string
    form.enrollee_id = props.selectedEnrollee.id;
    form.nicare_number = props.selectedEnrollee.enrollee_id;
    form.enrollee_full_name = `${props.selectedEnrollee.first_name} ${props.selectedEnrollee.last_name}`;
    form.gender = props.selectedEnrollee.sex || props.selectedEnrollee.gender;
    form.age = props.selectedEnrollee.age;
    form.enrollee_phone_main = props.selectedEnrollee.phone || props.selectedEnrollee.phone_number;
    form.enrollee_email = props.selectedEnrollee.email;
    form.marital_status = props.selectedEnrollee.marital_status;
    form.enrollee_category = props.selectedEnrollee.category;
    form.programme = props.selectedEnrollee.programme;
    form.organization = props.selectedEnrollee.organization;
    form.benefit_plan = props.selectedEnrollee.benefit_plan;
  }

  // Pre-populate services if provided
  if (props.selectedServices && props.selectedServices.length > 0) {
    form.services = props.selectedServices.map(service => ({
      id: service.id || service
    }));
  }

  // Set referral date to today
  form.referral_date = new Date().toISOString().split('T')[0];
});

// Watch for prop changes and update form
watch(() => props.selectedServices, (newServices) => {
  if (newServices && newServices.length > 0) {
    form.services = newServices.map(service => ({
      id: service.id || service
    }));
  }
}, { immediate: true });

defineExpose({
  submitForm,
  resetForm,
  loading
});
</script>

<style scoped>
:deep(.v-card-title) {
  font-weight: 600;
  font-size: 1rem;
}
</style>
