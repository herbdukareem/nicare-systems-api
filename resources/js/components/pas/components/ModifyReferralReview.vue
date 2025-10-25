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

      <!-- Referral Information -->
      <v-card class="tw-border tw-border-gray-200">
        <v-card-title class="tw-bg-blue-50 tw-text-sm tw-font-medium tw-text-blue-700">
          Referral Information
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-2">
            <div><strong>Code:</strong> {{ selectedReferral?.referral_code }}</div>
            <div><strong>Enrollee:</strong> {{ enrolleeName }}</div>
            <div><strong>Status:</strong> 
              <v-chip size="small" :color="getStatusColor(selectedReferral?.status)">
                {{ selectedReferral?.status }}
              </v-chip>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Service Modification Details -->
    <v-card class="tw-border tw-border-orange-200">
      <v-card-title class="tw-bg-orange-50 tw-text-sm tw-font-medium tw-text-orange-700">
        Service Modification
      </v-card-title>
      <v-card-text>
        <div class="tw-space-y-4">
          <!-- Current Service -->
          <div class="tw-p-4 tw-bg-red-50 tw-border tw-border-red-200 tw-rounded">
            <h4 class="tw-font-medium tw-text-red-700 tw-mb-2">Current Service</h4>
            <div class="tw-space-y-1">
              <div><strong>Service:</strong> {{ selectedReferral?.service_description }}</div>
              <div v-if="selectedReferral?.service_id">
                <strong>Service ID:</strong> {{ selectedReferral.service_id }}
              </div>
            </div>
          </div>

          <!-- Arrow -->
          <div class="tw-flex tw-justify-center">
            <v-icon size="32" color="orange">mdi-arrow-down</v-icon>
          </div>

          <!-- New Service -->
          <div class="tw-p-4 tw-bg-green-50 tw-border tw-border-green-200 tw-rounded">
            <h4 class="tw-font-medium tw-text-green-700 tw-mb-2">New Service</h4>
            <div class="tw-space-y-1">
              <div><strong>Service:</strong> {{ newService?.service_description }}</div>
              <div><strong>Service ID:</strong> {{ newService?.id }}</div>
              <div v-if="newService?.price">
                <strong>Price:</strong> ₦{{ formatCurrency(newService.price) }}
              </div>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Modification Reason -->
    <v-card class="tw-border tw-border-gray-200">
      <v-card-title class="tw-bg-gray-50 tw-text-sm tw-font-medium tw-text-gray-700">
        Modification Reason
      </v-card-title>
      <v-card-text>
        <v-textarea
          v-model="modificationReasonText"
          label="Reason for Modification"
          variant="outlined"
          density="compact"
          rows="3"
          :rules="[rules.required]"
          required
        />
      </v-card-text>
    </v-card>

    <!-- Confirmation -->
    <v-card class="tw-border tw-border-yellow-200 tw-bg-yellow-50">
      <v-card-text>
        <div class="tw-flex tw-items-start tw-space-x-3">
          <v-icon color="orange" size="24">mdi-alert</v-icon>
          <div>
            <h4 class="tw-font-medium tw-text-orange-800">Confirmation Required</h4>
            <p class="tw-text-sm tw-text-orange-700 tw-mt-1">
              This action will modify the referral service. The change will be recorded in the modification history.
              Please ensure the new service is appropriate for the patient's condition.
            </p>
          </div>
        </div>
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
        color="orange"
        @click="submitModification"
        :loading="loading"
        :disabled="!canSubmit"
      >
        <v-icon left>mdi-check</v-icon>
        Confirm Modification
      </v-btn>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  selectedFacility: Object,
  selectedReferral: Object,
  newService: Object,
  modificationReason: String
});

const emit = defineEmits(['submit', 'cancel']);

// Form data
const modificationReasonText = ref(props.modificationReason || '');
const loading = ref(false);

// Validation rules
const rules = {
  required: (value) => !!value || 'This field is required'
};

// Computed properties
const enrolleeName = computed(() => {
  if (!props.selectedReferral?.enrollee) return '';
  const enrollee = props.selectedReferral.enrollee;
  return `${enrollee.first_name} ${enrollee.last_name}`;
});

const canSubmit = computed(() => {
  return props.newService && 
         modificationReasonText.value && 
         modificationReasonText.value.trim().length > 0;
});

// Methods
const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-NG').format(amount);
};

const getStatusColor = (status) => {
  switch (status?.toLowerCase()) {
    case 'pending': return 'orange';
    case 'approved': return 'green';
    case 'denied': return 'red';
    default: return 'gray';
  }
};

const submitModification = () => {
  if (!canSubmit.value) return;

  const requestData = {
    new_service_id: props.newService.id,
    modification_reason: modificationReasonText.value.trim()
  };

  emit('submit', requestData);
};

// Watch for prop changes
watch(() => props.modificationReason, (newValue) => {
  if (newValue) {
    modificationReasonText.value = newValue;
  }
});
</script>
