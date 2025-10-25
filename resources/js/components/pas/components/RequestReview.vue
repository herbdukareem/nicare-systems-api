<template>
  <div class="tw-space-y-6">
    <!-- Request Summary -->
    <v-card>
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2" :color="getRequestTypeColor()">
          {{ getRequestTypeIcon() }}
        </v-icon>
        {{ getRequestTypeTitle() }} Summary
      </v-card-title>
      <v-card-text>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
          <!-- Facility Information -->
          <div>
            <h4 class="tw-font-semibold tw-mb-3 tw-text-gray-900">Facility Information</h4>
            <div class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-hospital-building</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Name:</span>
                <span class="tw-ml-2 tw-font-medium">{{ facility.name }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-identifier</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Code:</span>
                <span class="tw-ml-2 tw-font-medium">{{ facility.hcp_code }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-hospital</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Level:</span>
                <v-chip
                  :color="getLevelOfCareColor(facility.level_of_care)"
                  size="small"
                  variant="flat"
                >
                  {{ facility.level_of_care }}
                </v-chip>
              </div>
            </div>
          </div>

          <!-- Enrollee Information (not shown for modify referral) -->
          <div v-if="requestType !== 'modify_referral'">
            <h4 class="tw-font-semibold tw-mb-3 tw-text-gray-900">Enrollee Information</h4>
            <div class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-account</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Name:</span>
                <span class="tw-ml-2 tw-font-medium">{{ $utils.formatName(enrollee) }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-card-account-details</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Enrollment Number:</span>
                <span class="tw-ml-2 tw-font-medium">{{ enrollee.enrollee_id }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-calendar</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Age:</span>
                <span class="tw-ml-2 tw-font-medium"> ({{ enrollee.gender }})</span>
              </div>
            </div>
          </div>

          <!-- Modify Referral Information -->
          <div v-if="requestType === 'modify_referral' && selectedReferral">
            <h4 class="tw-font-semibold tw-mb-3 tw-text-gray-900">Referral Information</h4>
            <div class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-account</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Patient:</span>
                <span class="tw-ml-2 tw-font-medium">{{ selectedReferral.patient_name }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-identifier</v-icon>
                <span class="tw-text-sm tw-text-gray-600">NiCare Number:</span>
                <span class="tw-ml-2 tw-font-medium">{{ selectedReferral.nicare_number }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-file-document</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Referral Code:</span>
                <span class="tw-ml-2 tw-font-medium">{{ selectedReferral.referral_code }}</span>
              </div>
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-alert-circle</v-icon>
                <span class="tw-text-sm tw-text-gray-600">Severity:</span>
                <v-chip
                  :color="getSeverityColor(selectedReferral.severity_level)"
                  size="small"
                  variant="flat"
                >
                  {{ selectedReferral.severity_level || 'Routine' }}
                </v-chip>
              </div>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Selected Services (not shown for modify referral) -->
    <v-card v-if="requestType !== 'modify_referral'">
      <v-card-title class="tw-flex tw-items-center tw-justify-between">
        <div class="tw-flex tw-items-center">
          <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
          Selected Services & Items ({{ services.length }})
        </div>
        <div class="tw-text-right">
          <span class="tw-text-lg tw-font-bold tw-text-primary">
            Total: ₦{{ formatPrice(totalCost) }}
          </span>
        </div>
      </v-card-title>
      <v-card-text>
        <div class="tw-space-y-3">
          <div
            v-for="(service, index) in services"
            :key="`${service.type}-${service.id}`"
            class="tw-flex tw-items-center tw-justify-between tw-p-3 tw-bg-gray-50 tw-rounded-lg"
          >
          
            <div class="tw-flex tw-items-center tw-space-x-3">
              <v-icon
                :color="service.type === 'service' ? 'blue' : 'green'"
                size="20"
              >
                {{ service.type === 'service' ? 'mdi-medical-bag' : 'mdi-pill' }}
              </v-icon>
              <div>
                <p class="tw-font-medium">
                  {{ service.service_description ?? service.drug_name }}
                </p>
                <p class="tw-text-sm tw-text-gray-600">
                  Code: {{ service.service_code ?? service.nicare_code }}
                </p>
                <div v-if="service.type === 'drug'" class="tw-text-xs tw-text-gray-500">
                  {{ service.dosage_form }} | {{ service.strength }} | {{ service.presentation }}
                </div>
              </div>
            </div>
            <div class="tw-text-right">
              <p class="tw-font-semibold tw-text-primary">
                ₦{{ formatPrice(service.price || service.drug_unit_price) }}
              </p>
              <v-chip
                :color="service.type === 'service' ? 'blue' : 'green'"
                size="small"
                variant="flat"
              >
                {{ service.type === 'service' ? 'Service' : 'Drug' }}
              </v-chip>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Service Modification Summary (for modify referral requests only) -->
    <v-card v-if="requestType === 'modify_referral' && selectedReferral && newService">
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2" color="orange">mdi-swap-horizontal</v-icon>
        Service Modification Summary
      </v-card-title>
      <v-card-text>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
          <!-- Current Service -->
          <div class="tw-bg-orange-50 tw-border tw-border-orange-200 tw-rounded-lg tw-p-4">
            <h5 class="tw-font-semibold tw-text-orange-900 tw-mb-3">Current Service</h5>
            <div class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-orange-600">mdi-medical-bag</v-icon>
                <span class="tw-text-sm tw-font-medium">{{ selectedReferral.current_service }}</span>
              </div>
            </div>
          </div>

          <!-- New Service -->
          <div class="tw-bg-green-50 tw-border tw-border-green-200 tw-rounded-lg tw-p-4">
            <h5 class="tw-font-semibold tw-text-green-900 tw-mb-3">New Service</h5>
            <div class="tw-space-y-2">
              <div class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-600">mdi-medical-bag</v-icon>
                <span class="tw-text-sm tw-font-medium">{{ newService.service_description }}</span>
              </div>
              <div v-if="newService.service_category" class="tw-flex tw-items-center">
                <v-icon size="16" class="tw-mr-2 tw-text-green-600">mdi-tag</v-icon>
                <span class="tw-text-sm tw-text-gray-600">{{ newService.service_category }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Modification Reason -->
        <div v-if="modificationReason" class="tw-mt-6">
          <h5 class="tw-font-semibold tw-text-gray-900 tw-mb-2">Reason for Modification</h5>
          <div class="tw-bg-gray-50 tw-border tw-border-gray-200 tw-rounded-lg tw-p-3">
            <p class="tw-text-sm tw-text-gray-700">{{ modificationReason }}</p>
          </div>
        </div>

        <!-- Important Note -->
        <v-alert
          type="info"
          variant="tonal"
          class="tw-mt-4"
        >
          <div class="tw-space-y-1">
            <p class="tw-font-semibold">Important:</p>
            <ul class="tw-list-disc tw-list-inside tw-space-y-1 tw-text-sm">
              <li>The original referral code will be maintained</li>
              <li>Modification history will be tracked for audit purposes</li>
              <li>The patient will be notified of the service change</li>
            </ul>
          </div>
        </v-alert>
      </v-card-text>
    </v-card>

    <!-- PA Code Selected Referral Summary (for PA Code requests only) -->
    <v-card v-if="requestType === 'pa_code' && selectedApprovedReferral">
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-file-document-check</v-icon>
        Selected Approved Referral
      </v-card-title>
      <v-card-text>
        <v-card class="tw-bg-green-50 tw-border tw-border-green-200">
          <v-card-text class="tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
              <h5 class="tw-font-semibold tw-text-green-900">Referral Details</h5>
              <v-chip
                color="success"
                size="small"
                variant="flat"
                prepend-icon="mdi-check-circle"
              >
                {{ selectedApprovedReferral.status?.toUpperCase() }}
              </v-chip>
            </div>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4 tw-text-sm">
              <div>
                <p class="tw-text-gray-600">Referral Code:</p>
                <p class="tw-font-medium">{{ selectedApprovedReferral.referral_code }}</p>
              </div>
              <div>
                <p class="tw-text-gray-600">Approved Date:</p>
                <p class="tw-font-medium">{{ formatDate(selectedApprovedReferral.approved_at) }}</p>
              </div>
              <div>
                <p class="tw-text-gray-600">Diagnosis:</p>
                <p class="tw-font-medium">{{ selectedApprovedReferral.preliminary_diagnosis || '—' }}</p>
              </div>
              <div>
                <p class="tw-text-gray-600">Severity:</p>
                <p class="tw-font-medium">{{ (selectedApprovedReferral.severity_level || '').toUpperCase() }}</p>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>

    <!-- Clinical Information Form (for Referral requests only) -->
    <v-card v-if="requestType === 'referral'">
      <v-card-title class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-clipboard-text</v-icon>
        Clinical Information
      </v-card-title>
      <v-card-text>
        <v-form ref="clinicalForm" v-model="formValid">
          <div class="tw-space-y-4">
            <!-- Presenting Complaints -->
            <v-textarea
              v-model="formData.presenting_complaints"
              label="Presenting Complaints *"
              placeholder="Describe the patient's presenting complaints..."
              variant="outlined"
              rows="3"
              :rules="[v => !!v || 'Presenting complaints are required']"
              required
            />

            <!-- Reasons for Referral -->
            <v-textarea
              v-model="formData.reasons_for_referral"
              label="Reasons for Referral/PA Code *"
              placeholder="Explain why this referral or PA code is needed..."
              variant="outlined"
              rows="3"
              :rules="[v => !!v || 'Reasons for referral are required']"
              required
            />

            <!-- Preliminary Diagnosis -->
            <v-text-field
              v-model="formData.preliminary_diagnosis"
              label="Preliminary Diagnosis *"
              placeholder="Enter preliminary diagnosis..."
              variant="outlined"
              :rules="[v => !!v || 'Preliminary diagnosis is required']"
              required
            />

            <!-- Severity Level -->
            <v-select
              v-model="formData.severity_level"
              :items="severityLevels"
              label="Severity Level *"
              variant="outlined"
              :rules="[v => !!v || 'Severity level is required']"
              required
            />

            <!-- Personnel Information -->
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="formData.personnel_full_name"
                label="Referring Personnel Name *"
                placeholder="Full name of referring personnel..."
                variant="outlined"
                :rules="[v => !!v || 'Personnel name is required']"
                required
              />
              <v-text-field
                v-model="formData.personnel_phone"
                label="Personnel Phone *"
                placeholder="Phone number of referring personnel..."
                variant="outlined"
                :rules="[v => !!v || 'Personnel phone is required']"
                required
              />
            </div>

            <!-- Contact Information -->
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="formData.contact_full_name"
                label="Contact Person Name *"
                placeholder="Name of contact person..."
                variant="outlined"
                :rules="[v => !!v || 'Contact name is required']"
                required
              />
              <v-text-field
                v-model="formData.contact_phone"
                label="Contact Phone *"
                placeholder="Phone number of contact person..."
                variant="outlined"
                :rules="[v => !!v || 'Contact phone is required']"
                required
              />
            </div>

            <v-text-field
              v-model="formData.contact_email"
              label="Contact Email"
              placeholder="Email of contact person (optional)..."
              variant="outlined"
              type="email"
            />

            <!-- Receiving Facility (for referrals) -->
            <v-autocomplete
              v-if="requestType === 'referral'"
              v-model="formData.receiving_facility_id"
              :items="referringFacilities"
              item-title="name"
              item-value="id"
              label="Receiving Facility *"
              placeholder="Select receiving facility..."
              variant="outlined"
              :rules="[v => !!v || 'Receiving facility is required for referrals']"
              required
            />

            <!-- File Uploads -->
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-file-input
                v-model="formData.enrollee_id_card"
                label="Enrollee ID Card"
                placeholder="Upload enrollee ID card..."
                variant="outlined"
                accept="image/*,application/pdf"
                prepend-icon="mdi-card-account-details"
              />
              <v-file-input
                v-model="formData.referral_letter"
                label="Referral Letter"
                placeholder="Upload referral letter..."
                variant="outlined"
                accept="image/*,application/pdf,.doc,.docx"
                prepend-icon="mdi-file-document"
              />
            </div>

            <!-- Additional Notes -->
            <v-textarea
              v-model="formData.notes"
              label="Additional Notes"
              placeholder="Any additional information or special instructions..."
              variant="outlined"
              rows="3"
            />

            <!-- File Attachments -->
            <div>
              <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
                Supporting Documents
              </label>
              <v-file-input
                v-model="formData.attachments"
                label="Upload supporting documents"
                variant="outlined"
                multiple
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                prepend-icon="mdi-paperclip"
                show-size
              />
              <p class="tw-text-xs tw-text-gray-500 tw-mt-1">
                Accepted formats: PDF, Images, Word documents (Max 5MB each)
              </p>
            </div>
          </div>
        </v-form>
      </v-card-text>
    </v-card>

    <!-- Submit Button -->
    <div class="tw-flex tw-justify-center">
      <v-btn
        color="primary"
        size="large"
        :loading="loading"
        :disabled="isSubmitDisabled"
        @click="submitRequest"
        class="tw-px-8"
      >
        <v-icon class="tw-mr-2">
          {{ requestType === 'referral' ? 'mdi-send' : 'mdi-qrcode-plus' }}
        </v-icon>
        {{ requestType === 'referral' ? 'Submit Referral Request' : 'Generate PA Code' }}
      </v-btn>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { facilityAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const props = defineProps({
  facility: {
    type: Object,
    required: true
  },
  enrollee: {
    type: Object,
    required: true
  },
  requestType: {
    type: String,
    required: true
  },
  services: {
    type: Array,
    default: () => []
  },
  selectedApprovedReferral: {
    type: Object,
    default: null
  },
  additionalData: {
    type: Object,
    default: () => ({})
  },
  loading: {
    type: Boolean,
    default: false
  },
  selectedReferral: {
    type: Object,
    default: null
  },
  newService: {
    type: Object,
    default: null
  },
  modificationReason: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['submit']);

const { error } = useToast();

// Reactive data
const formValid = ref(false);
const referringFacilities = ref([]);
const clinicalForm = ref(null);

const formData = ref({
  presenting_complaints: '',
  reasons_for_referral: '',
  preliminary_diagnosis: '',
  severity_level: 'routine',
  personnel_full_name: '',
  personnel_phone: '',
  contact_full_name: '',
  contact_phone: '',
  contact_email: '',
  receiving_facility_id: '',
  notes: '',
  enrollee_id_card: null,
  referral_letter: null
});

// Computed
const totalCost = computed(() => {
  return props.services.reduce((total, service) => {
    const price = service.type === 'drug' ? service.drug_unit_price : service.price;
    return total + (parseFloat(price) || 0);
  }, 0);
});

const isSubmitDisabled = computed(() => {
  if (props.requestType === 'pa_code') {
    // For PA codes, only require approved referral selection
    return !props.selectedApprovedReferral;
  } else {
    // For referrals, require form validation and services
    return !formValid.value || props.services.length === 0;
  }
});

const severityLevels = [
  { title: 'Routine - Standard care', value: 'routine' },
  { title: 'Urgent - Needs prompt attention', value: 'urgent' },
  { title: 'Emergency - Immediate care required', value: 'emergency' }
];

// Methods
const loadReferringFacilities = async () => {
  try {
    const response = await facilityAPI.getAll({
      level_of_care: getHigherLevels(props.facility.level_of_care),
      status: 1
    });
    if (response.data.success) {
      referringFacilities.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load referring facilities:', err);
    error('Failed to load referring facilities');
  }
};

const getHigherLevels = (currentLevel) => {
  switch (currentLevel) {
    case 'Primary': return ['Secondary', 'Tertiary'];
    case 'Secondary': return ['Tertiary'];
    case 'Tertiary': return [];
    default: return [];
  }
};

const submitRequest = async () => {
  // For PA Code requests, validate referral selection instead of clinical form
  if (props.requestType === 'pa_code') {
    if (!props.selectedApprovedReferral) {
      error('Please select an approved referral for PA Code generation');
      return;
    }
  } else if (props.requestType === 'modify_referral') {
    // For modify referral requests, validate referral and service selection
    if (!props.selectedReferral) {
      error('Please select a referral to modify');
      return;
    }
    if (!props.newService) {
      error('Please select a new service for the referral');
      return;
    }
  } else {
    // For referral requests, validate clinical form
    if (!clinicalForm.value?.validate()) {
      return;
    }
  }

  let requestData;

  if (props.requestType === 'pa_code') {
    // Validate that approved referral is selected
    if (!props.selectedApprovedReferral || !props.selectedApprovedReferral.id) {
      error('Selected approved referral is missing or invalid');
      return;
    }

    console.log('PA Code generation data:', {
      selectedApprovedReferral: props.selectedApprovedReferral,
      referralId: props.selectedApprovedReferral.id,
      services: props.services
    });

    // PA Code request data
    requestData = {
      referral_id: parseInt(props.selectedApprovedReferral.id),
      services: props.services.map(service => ({
        id: parseInt(service.id),
        type: service.type,
        price: parseFloat(service.price || service.drug_unit_price)
      })),
      service_type: 'Multiple Services',
      service_description: props.services.map(s => s.service_description || s.drug_name).join(', '),
      approved_amount: parseFloat(totalCost.value),
      conditions: props.selectedApprovedReferral.preliminary_diagnosis,
      validity_days: 30, // Integer, not string
      max_usage: 1, // Integer, not string
      issuer_comments: 'Generated from workflow'
    };
  } else if (props.requestType === 'modify_referral') {
    // Modify referral request data
    requestData = {
      referral_id: props.selectedReferral.id,
      new_service_id: props.newService.id,
      modification_reason: props.modificationReason || 'Service modification requested'
    };
  } else {
    // Referral request data
    requestData = {
      facility_id: props.facility.id,
      enrollee_id: props.enrollee.id,
      request_type: props.requestType,
      receiving_facility_id: formData.value.receiving_facility_id || props.facility.id,
      services: props.services.map(service => ({
        id: service.id,
        type: service.type,
        price: service.price || service.drug_unit_price
      })),
      ...formData.value,
      total_cost: totalCost.value
    };
  }

  emit('submit', requestData);
};

const formatPrice = (price) => {
  return new Intl.NumberFormat('en-NG').format(price || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  const date = new Date(dateString);
  if (isNaN(date)) return '—';
  return date.toLocaleString('en-NG', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const getLevelOfCareColor = (level) => {
  switch (level) {
    case 'Primary': return 'green';
    case 'Secondary': return 'orange';
    case 'Tertiary': return 'red';
    default: return 'grey';
  }
};

const getRequestTypeColor = () => {
  switch (props.requestType) {
    case 'referral': return 'blue';
    case 'pa_code': return 'green';
    case 'modify_referral': return 'orange';
    default: return 'grey';
  }
};

const getRequestTypeIcon = () => {
  switch (props.requestType) {
    case 'referral': return 'mdi-account-arrow-right';
    case 'pa_code': return 'mdi-qrcode';
    case 'modify_referral': return 'mdi-pencil-circle';
    default: return 'mdi-help';
  }
};

const getRequestTypeTitle = () => {
  switch (props.requestType) {
    case 'referral': return 'Referral Request';
    case 'pa_code': return 'PA Code Request';
    case 'modify_referral': return 'Modify Referral Request';
    default: return 'Request';
  }
};

const getSeverityColor = (severity) => {
  switch (severity?.toLowerCase()) {
    case 'emergency': return 'red';
    case 'urgent': return 'orange';
    case 'routine': return 'green';
    default: return 'grey';
  }
};

// Approved referrals are now handled in the parent component (CreateReferralPAPage)

// Note: Approved referral selection is now handled in the parent component

// Lifecycle
onMounted(() => {
  if (props.requestType === 'referral') {
    loadReferringFacilities();
  }
  // Note: Approved referral loading is now handled in the parent component (CreateReferralPAPage)
});
</script>
