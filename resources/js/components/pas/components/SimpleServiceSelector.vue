<template>
  <div class="tw-space-y-6">
    <!-- Current Service Display -->
    <v-card v-if="currentReferral" class="tw-border-l-4 tw-border-orange-500">
      <v-card-text class="tw-p-4">
        <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-3">Current Referral Service</h4>
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Service:</p>
            <p class="tw-text-lg tw-text-gray-900">{{ currentReferral.current_service }}</p>
          </div>
          <v-chip color="orange" size="small">Current</v-chip>
        </div>
      </v-card-text>
    </v-card>

    <!-- New Service Selection -->
    <div>
      <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">Select New Service</h4>
      
      <v-select
        v-model="selectedService"
        :items="availableServices"
        item-title="case_description"
        item-value="id"
        label="New Referral Case *"
        variant="outlined"
        density="comfortable"
        :loading="loadingServices"
        :rules="[rules.required]"
        @update:model-value="onServiceSelected"
      >
        <template #item="{ props, item }">
          <v-list-item v-bind="props">
            <v-list-item-title>{{ item.raw.case_description }}</v-list-item-title>
            <v-list-item-subtitle>{{ item.raw.case_category || 'General' }}</v-list-item-subtitle>
          </v-list-item>
        </template>
        <template #selection="{ item }">
          <span>{{ item.raw.case_description }}</span>
        </template>
      </v-select>
    </div>

    <!-- Modification Reason -->
    <div>
      <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-4">Reason for Modification</h4>
      
      <v-textarea
        v-model="modificationReason"
        label="Reason for changing the referral service"
        variant="outlined"
        rows="3"
        :rules="[rules.required]"
        placeholder="Please provide a reason for modifying the referral service..."
      ></v-textarea>
    </div>

    <!-- Selected Service Preview -->
    <v-card v-if="selectedServiceData" class="tw-border-l-4 tw-border-green-500">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-mb-3">
          <v-icon color="green" size="24" class="tw-mr-2">mdi-check-circle</v-icon>
          <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900">New Service Selected</h4>
        </div>
        
        <div class="tw-space-y-2">
          <div>
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Service:</p>
            <p class="tw-text-lg tw-text-gray-900">{{ selectedServiceData.service_description }}</p>
          </div>
          <div v-if="selectedServiceData.service_category">
            <p class="tw-text-sm tw-font-medium tw-text-gray-700">Category:</p>
            <p class="tw-text-sm tw-text-gray-600">{{ selectedServiceData.service_category }}</p>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Change Summary -->
    <v-card v-if="selectedServiceData && currentReferral" class="tw-bg-blue-50">
      <v-card-text class="tw-p-4">
        <h4 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-3">
          <v-icon class="tw-mr-2">mdi-swap-horizontal</v-icon>
          Service Change Summary
        </h4>
        
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
          <div class="tw-text-center">
            <p class="tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">From:</p>
            <v-chip color="orange" size="small">{{ currentReferral.current_service }}</v-chip>
          </div>
          <div class="tw-text-center">
            <p class="tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1">To:</p>
            <v-chip color="green" size="small">{{ selectedServiceData.service_description }}</v-chip>
          </div>
        </div>
        
        <div class="tw-mt-4">
          <p class="tw-text-sm tw-font-medium tw-text-gray-700">Reason:</p>
          <p class="tw-text-sm tw-text-gray-600">{{ modificationReason || 'No reason provided' }}</p>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { caseAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';

const { error } = useToast();

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  currentReferral: {
    type: Object,
    required: true
  },
  modificationReasonValue: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue', 'update:modificationReason']);

// Reactive data
const availableServices = ref([]);
const loadingServices = ref(false);
const selectedService = ref(null);
const modificationReason = ref(props.modificationReasonValue);

// Validation rules
const rules = {
  required: (value) => !!value || 'This field is required'
};

// Computed
const selectedServiceData = computed(() => {
  if (!selectedService.value) return null;
  return availableServices.value.find(service => service.id === selectedService.value);
});

// Methods
const onServiceSelected = () => {
  const serviceData = selectedServiceData.value;
  emit('update:modelValue', serviceData);
};

const loadServices = async () => {
  try {
    loadingServices.value = true;

    // Try to load from API first, fallback to mock data
    try {
      const response = await caseAPI.getAll({ per_page: 100 });
      console.log('Cases API response:', response.data);

      if (response.data.success && response.data.data) {
        // Handle both paginated and non-paginated responses
        const casesData = response.data.data.data || response.data.data;
        availableServices.value = casesData || [];
        console.log('Loaded cases:', availableServices.value.length);
        return;
      }
    } catch (apiError) {
      console.warn('API call failed, using mock data:', apiError);
    }

    // Fallback to mock services
    const mockServices = [
      { id: 1, service_description: 'General Consultation', service_category: 'Primary Care' },
      { id: 2, service_description: 'Specialist Consultation', service_category: 'Secondary Care' },
      { id: 3, service_description: 'Emergency Care', service_category: 'Emergency' },
      { id: 4, service_description: 'Surgical Procedure', service_category: 'Surgery' },
      { id: 5, service_description: 'Diagnostic Imaging', service_category: 'Diagnostics' },
      { id: 6, service_description: 'Laboratory Tests', service_category: 'Diagnostics' },
      { id: 7, service_description: 'Physiotherapy', service_category: 'Rehabilitation' },
      { id: 8, service_description: 'Mental Health Counseling', service_category: 'Mental Health' }
    ];

    availableServices.value = mockServices;
  } catch (err) {
    console.error('Error loading services:', err);
    error('Failed to load services');
    availableServices.value = [];
  } finally {
    loadingServices.value = false;
  }
};

// Watch for modification reason changes
watch(modificationReason, (newValue) => {
  emit('update:modificationReason', newValue);
});

onMounted(() => {
  loadServices();
});
</script>
