<template>
  <div class="tw-space-y-6">
    <!-- Summary Cards -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
      <!-- Facility Information -->
      <v-card class="tw-border tw-border-gray-200">
        <v-card-title class="tw-bg-gray-50 tw-text-sm tw-font-medium tw-text-gray-700">
          Facility Information
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-2">
            <div><strong>Name:</strong> {{ selectedFacility?.name }}</div>
            <div><strong>Code:</strong> {{ selectedFacility?.code }}</div>
            <div><strong>Type:</strong> {{ selectedFacility?.level_of_care }}</div>
          </div>
        </v-card-text>
      </v-card>

      <!-- Enrollee Information -->
      <v-card class="tw-border tw-border-gray-200">
        <v-card-title class="tw-bg-gray-50 tw-text-sm tw-font-medium tw-text-gray-700">
          Enrollee Information
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-2">
            <div><strong>Name:</strong> {{ enrolleeName }}</div>
            <div><strong>ID:</strong> {{ selectedEnrollee?.enrollee_id }}</div>
            <div><strong>Phone:</strong> {{ selectedEnrollee?.phone_number }}</div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Approved Referral Information -->
    <v-card class="tw-border tw-border-gray-200">
      <v-card-title class="tw-bg-blue-50 tw-text-sm tw-font-medium tw-text-blue-700">
        Approved Referral Details
      </v-card-title>
      <v-card-text>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
          <div><strong>Referral Code:</strong> {{ selectedApprovedReferral?.referral_code }}</div>
          <div><strong>Service:</strong> {{ selectedApprovedReferral?.service_description }}</div>
          <div><strong>Diagnosis:</strong> {{ selectedApprovedReferral?.preliminary_diagnosis }}</div>
          <div><strong>Approved Date:</strong> {{ formatDate(selectedApprovedReferral?.approved_at) }}</div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Services Information -->
    <v-card v-if="selectedServices.length > 0" class="tw-border tw-border-gray-200">
      <v-card-title class="tw-bg-green-50 tw-text-sm tw-font-medium tw-text-green-700">
        Additional Services
      </v-card-title>
      <v-card-text>
        <div class="tw-space-y-2">
          <div v-for="service in selectedServices" :key="service.id" class="tw-flex tw-justify-between">
            <span>{{ service.service_description || service.drug_name }}</span>
            <span class="tw-font-medium">₦{{ formatCurrency(service.price || service.drug_unit_price) }}</span>
          </div>
          <v-divider class="tw-my-2" />
          <div class="tw-flex tw-justify-between tw-font-bold">
            <span>Total Cost:</span>
            <span>₦{{ formatCurrency(totalCost) }}</span>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- PA Code Configuration -->
    <v-card class="tw-border tw-border-gray-200">
      <v-card-title class="tw-bg-purple-50 tw-text-sm tw-font-medium tw-text-purple-700">
        PA Code Configuration
      </v-card-title>
      <v-card-text>
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
          <v-text-field
            v-model="validityDays"
            label="Validity (Days)"
            type="number"
            min="1"
            max="365"
            variant="outlined"
            density="compact"
          />
          <v-text-field
            v-model="maxUsage"
            label="Maximum Usage"
            type="number"
            min="1"
            max="10"
            variant="outlined"
            density="compact"
          />
        </div>
        <v-textarea
          v-model="issuerComments"
          label="Issuer Comments"
          variant="outlined"
          density="compact"
          rows="3"
          class="tw-mt-4"
        />
      </v-card-text>
    </v-card>

    <!-- Action Buttons -->
    <div class="tw-flex tw-justify-end tw-space-x-4">
      <v-btn
        variant="outlined"
        @click="$emit('cancel')"
      >
        Cancel
      </v-btn>
      <v-btn
        color="green"
        @click="submitPACode"
        :loading="loading"
      >
        <v-icon left>mdi-qrcode</v-icon>
        Generate PA Code
      </v-btn>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  selectedFacility: Object,
  selectedEnrollee: Object,
  selectedApprovedReferral: Object,
  selectedServices: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['submit', 'cancel']);

// PA Code configuration
const validityDays = ref(30);
const maxUsage = ref(1);
const issuerComments = ref('Generated from workflow');
const loading = ref(false);

// Computed properties
const enrolleeName = computed(() => {
  if (!props.selectedEnrollee) return '';
  return `${props.selectedEnrollee.first_name} ${props.selectedEnrollee.last_name}`;
});

const totalCost = computed(() => {
  return props.selectedServices.reduce((total, service) => {
    return total + parseFloat(service.price || service.drug_unit_price || 0);
  }, 0);
});

// Methods
const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString();
};

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-NG').format(amount);
};

const submitPACode = () => {
  const requestData = {
    referral_id: parseInt(props.selectedApprovedReferral.id),
    services: props.selectedServices.map(service => ({
      id: parseInt(service.id),
      type: service.type,
      price: parseFloat(service.price || service.drug_unit_price)
    })),
    service_type: props.selectedServices.length > 1 ? 'Multiple Services' : 'Single Service',
    service_description: props.selectedServices.length > 0 
      ? props.selectedServices.map(s => s.service_description || s.drug_name).join(', ')
      : props.selectedApprovedReferral.service_description,
    approved_amount: props.selectedServices.length > 0 ? parseFloat(totalCost.value) : null,
    conditions: props.selectedApprovedReferral.preliminary_diagnosis,
    validity_days: parseInt(validityDays.value),
    max_usage: parseInt(maxUsage.value),
    issuer_comments: issuerComments.value
  };

  emit('submit', requestData);
};
</script>
