<template>
  <v-form ref="formRef" @submit.prevent="submitForm">
    <div class="tw-space-y-6">
      <!-- Referring Provider Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
          Referring Provider/Facility Details
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-text-field
              v-model="form.referring_facility_name"
              label="Facility Name *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.referring_nicare_code"
              label="NiCare Code *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-textarea
              v-model="form.referring_address"
              label="Address *"
              variant="outlined"
              density="compact"
              rows="2"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.referring_phone"
              label="Provider Phone Number *"
              variant="outlined"
              density="compact"
              :rules="[rules.required, rules.phone]"
            />
            <v-text-field
              v-model="form.referring_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              :rules="[rules.email]"
            />
            <v-text-field
              v-model="form.tpa_name"
              label="TPA Name"
              variant="outlined"
              density="compact"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Contact Person Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-green-50 tw-text-green-800">
          <v-icon class="tw-mr-2">mdi-account-circle</v-icon>
          Contact Person for Questions/Enquiries
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-text-field
              v-model="form.contact_full_name"
              label="Full Name *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.contact_phone"
              label="Direct Phone Number *"
              variant="outlined"
              density="compact"
              :rules="[rules.required, rules.phone]"
            />
            <v-text-field
              v-model="form.contact_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              :rules="[rules.email]"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Receiving Provider Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-purple-50 tw-text-purple-800">
          <v-icon class="tw-mr-2">mdi-hospital-marker</v-icon>
          Receiving Provider/Facility Details
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
            <v-text-field
              v-model="form.receiving_facility_name"
              label="Facility Name *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.receiving_nicare_code"
              label="NiCare Code *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-textarea
              v-model="form.receiving_address"
              label="Address *"
              variant="outlined"
              density="compact"
              rows="2"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.receiving_phone"
              label="Phone Number *"
              variant="outlined"
              density="compact"
              :rules="[rules.required, rules.phone]"
            />
            <v-text-field
              v-model="form.receiving_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              :rules="[rules.email]"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Patient/Enrollee Details -->
      <v-card class="tw-mb-6">
        <v-card-title class="tw-bg-orange-50 tw-text-orange-800">
          <v-icon class="tw-mr-2">mdi-account-heart</v-icon>
          Patient/Enrollee Details
        </v-card-title>
        <v-card-text class="tw-pt-4">
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
            <v-text-field
              v-model="form.nicare_number"
              label="NiCare Number *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.enrollee_full_name"
              label="Full Name (as on NiCare Card) *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-select
              v-model="form.gender"
              :items="genderOptions"
              label="Gender *"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-text-field
              v-model="form.age"
              label="Age *"
              type="number"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
            />
            <v-select
              v-model="form.marital_status"
              :items="maritalStatusOptions"
              label="Marital Status"
              variant="outlined"
              density="compact"
            />
            <v-select
              v-model="form.enrollee_category"
              :items="categoryOptions"
              label="Enrollee Category"
              variant="outlined"
              density="compact"
            />
            <v-text-field
              v-model="form.enrollee_phone_main"
              label="Phone Number (Main) *"
              variant="outlined"
              density="compact"
              :rules="[rules.required, rules.phone]"
            />
            <v-text-field
              v-model="form.enrollee_phone_encounter"
              label="Phone Number (During Care/Encounter)"
              variant="outlined"
              density="compact"
              :rules="[rules.phone]"
            />
            <v-text-field
              v-model="form.enrollee_phone_relation"
              label="Phone Number (Patient Relation)"
              variant="outlined"
              density="compact"
              :rules="[rules.phone]"
            />
            <v-text-field
              v-model="form.enrollee_email"
              label="Email Address"
              variant="outlined"
              density="compact"
              :rules="[rules.email]"
            />
            <v-select
              v-model="form.programme"
              :items="programmeOptions"
              label="Programme"
              variant="outlined"
              density="compact"
            />
            <v-text-field
              v-model="form.organization"
              label="Organization (for formal sector/TiSHIP)"
              variant="outlined"
              density="compact"
            />
            <v-select
              v-model="form.benefit_plan"
              :items="benefitPlanOptions"
              label="Benefit Plan"
              variant="outlined"
              density="compact"
            />
            <v-text-field
              v-model="form.referral_date"
              label="Date of Referral *"
              type="date"
              variant="outlined"
              density="compact"
              :rules="[rules.required]"
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
                Enrollee ID Card/Slip *
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
          </div>
        </v-card-text>
      </v-card>
    </div>
  </v-form>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useToast } from '../../composables/useToast';
import FileUpload from '../common/FileUpload.vue';
import axios from 'axios';

const emit = defineEmits(['submit', 'cancel']);
const { success, error } = useToast();

const formRef = ref(null);
const loading = ref(false);
const uploadProgress = ref({});

// Form data
const form = reactive({
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
  enrollee_id_card: null,
  referral_letter: null
});

// Validation rules
const rules = {
  required: (value) => !!value || 'This field is required',
  email: (value) => !value || /.+@.+\..+/.test(value) || 'Invalid email format',
  phone: (value) => !value || /^[\d\s\-\+\(\)]+$/.test(value) || 'Invalid phone number format'
};

// Options for select fields
const genderOptions = ['Male', 'Female'];
const maritalStatusOptions = ['Single', 'Married', 'Divorced', 'Widowed'];
const categoryOptions = ['U5', 'Elderly', 'Pregnant Women', 'General'];
const programmeOptions = ['Formal', 'Informal', 'BHCPF'];
const benefitPlanOptions = ['Standard', 'Basic', 'Premium'];
const specializationOptions = ['O&G', 'Paediatrics', 'Public Health', 'ENT', 'Family Medicine', 'Internal Medicine', 'Surgery'];
const cadreOptions = ['CHEW', 'M.O', 'CHO', 'D.O', 'N.O', 'Registrar', 'Consultant'];

// Methods
const submitForm = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  loading.value = true;

  try {
    // Create FormData for file uploads
    const formData = new FormData();

    // Add all form fields
    Object.keys(form).forEach(key => {
      if (form[key] !== null && form[key] !== '') {
        if (key === 'enrollee_id_card' || key === 'referral_letter') {
          // Handle file uploads
          if (form[key] && form[key].length > 0) {
            formData.append(key, form[key][0]);
          }
        } else {
          formData.append(key, form[key]);
        }
      }
    });

    // Submit to API
    const response = await axios.post('/api/v1/pas/referrals', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        uploadProgress.value = { percent: percentCompleted };
      }
    });

    if (response.data.success) {
      success('Referral request submitted successfully!');
      emit('submit', response.data.data);
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
    } else if (form[key] === null || Array.isArray(form[key])) {
      form[key] = null;
    }
  });
};

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
