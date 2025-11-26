<template>
  <div class="tw-space-y-6">
    <!-- PA Code Information -->
    <v-alert
      v-if="requestType === 'pa_code'"
      type="info"
      variant="tonal"
      class="tw-mb-6"
    >
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-information</v-icon>
        <div>
          <strong>PA Code Generation</strong>
          <p class="tw-text-sm tw-mt-1">
            PA codes are generated from approved referrals. Services and pricing will be taken from the selected referral.
            You can optionally select additional services if needed.
          </p>
        </div>
      </div>
    </v-alert>
    <!-- Cases Selection Section -->
    <v-card class="tw-mb-6">
      <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
        <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
        Select Cases
      </v-card-title>
       
      <v-card-text class="tw-pt-4">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4 tw-mb-4">
          <!-- Filter by Case Group -->
         
          <v-select
            v-model="selectedGroup"
            :items="caseGroups"
            item-title="name"
            item-value="id"
            label="Filter by Case Group"
            variant="outlined"
            clearable
            @update:modelValue="filterServices"
          />

          <!-- Search by Case Name -->
          <v-text-field
            v-model="searchQuery"
            label="Search by Case Name"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            clearable
            @input="debouncedSearch"
          />
        </div>
     
        <!-- Cases Select Input -->
        <v-autocomplete
          v-model="selectedCaseIds"
          :items="filteredServices"
          item-title="service_description"
          item-value="id"
          label="Select Cases *"
          variant="outlined"
          multiple
          chips
          closable-chips
          :loading="loadingServices"
          :rules="[rules.required]"
          no-filter
          @update:modelValue="onCasesSelected"
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <!-- <v-list-item-title>{{ item.raw.service_description }}</v-list-item-title> -->
              <v-list-item-subtitle>
                {{ item.raw.nicare_code }} - ₦{{ formatPrice(item.raw.price || 0) }}
              </v-list-item-subtitle>
            </v-list-item>
          </template>

          <template #chip="{ props, item }">
            <v-chip
              v-bind="props"
              closable
              :text="getChipLabel(item.raw.id)"
            />
          </template>

          <template #no-data>
            <v-list-item>
              <v-list-item-title class="tw-text-gray-500">
                No cases available for this facility
              </v-list-item-title>
            </v-list-item>
          </template>
        </v-autocomplete>

        <!-- Selected Cases Summary -->
        <v-card v-if="selectedCasesData.length > 0" class="tw-mt-4 tw-bg-green-50 tw-border tw-border-green-200">
          <v-card-text class="tw-p-4">
            <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
              <h4 class="tw-font-semibold tw-text-green-900">
                Selected Cases ({{ selectedCasesData.length }})
              </h4>
              <v-btn
                color="green"
                variant="outlined"
                size="small"
                @click="clearAllSelections"
              >
                Clear All
              </v-btn>
            </div>
            <div class="tw-space-y-2">
              <div v-for="caseItem in selectedCasesData" :key="caseItem.id" class="tw-flex tw-items-center tw-justify-between tw-p-2 tw-bg-white tw-rounded tw-border tw-border-green-100">
                <div>
                  <p class="tw-font-medium tw-text-gray-900">{{ caseItem.service_description }}</p>
                  <p class="tw-text-sm tw-text-gray-600">{{ caseItem.nicare_code }}</p>
                </div>
                <span class="tw-font-semibold tw-text-green-600">₦{{ formatPrice(caseItem.price || 0) }}</span>
              </div>
            </div>
            <div class="tw-mt-3 tw-pt-3 tw-border-t tw-border-green-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <span class="tw-font-semibold tw-text-green-900">Total Estimated Cost:</span>
                <span class="tw-text-lg tw-font-bold tw-text-green-900">₦{{ formatPrice(totalCost) }}</span>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { caseAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';
import { debounce } from 'lodash-es';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  requestType: {
    type: String,
    required: true
  },
  facility: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['update:modelValue']);

const { error } = useToast();

// Reactive data
const services = ref([]);
const caseGroups = ref([]);
const searchQuery = ref('');
const selectedGroup = ref('');
const loadingServices = ref(false);
const selectedCaseIds = ref([]);

// Validation rules
const rules = {
  // required: (value) => (Array.isArray(value) && value.length > 0) || 'Please select at least one case'
    required: () => (selectedCaseIds.value.length > 0) || 'Please select at least one case'

};

// Computed
const selectedCasesData = computed(() => {
  return selectedCaseIds.value
    .map(id => services.value.find(s => s.id === id))
    .filter(Boolean)
    .map(c => ({ ...c, type: 'service' }));
});

const filteredServices = computed(() => {
  let filtered = services.value;

  // Filter by request type
  if (props.requestType === 'referral') {
    filtered = filtered.filter(service => service.referable);
  } else if (props.requestType === 'pa_code') {
    filtered = filtered.filter(service => service.pa_required);
  }

  // Filter by facility level of care
  if (props.facility?.level_of_care) {
    const facilityLevel = props.facility.level_of_care;
    if (facilityLevel === 'Primary') {
      filtered = filtered.filter(service => service.level_of_care === 'Primary');
    } else if (facilityLevel === 'Secondary') {
      filtered = filtered.filter(service => ['Primary', 'Secondary'].includes(service.level_of_care));
    }
    // Tertiary can access all levels
  }

  // Apply case group filter
  if (selectedGroup.value) {
    filtered = filtered.filter(service => service.case_group_id === selectedGroup.value);
  }

  // Apply search filter by case name
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(service =>
      service.service_description.toLowerCase().includes(query) ||
      service.nicare_code.toLowerCase().includes(query)
    );
  }

  return filtered.filter(service => service.status);
});

const totalCost = computed(() => {
  return selectedCasesData.value.reduce((total, item) => {
    return total + (parseFloat(item.price) || 0);
  }, 0);
});

// Methods
const loadServices = async () => {
  try {
    loadingServices.value = true;
    const params = {
      per_page: 1000,
      status: 1 // Only active cases
    };

    // Filter by referable status for referral requests
    if (props.requestType === 'referral') {
      params.referable = 1;
    }

    const response = await caseAPI.getAll(params);
    if (response.data.success) {
      services.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load services:', err);
    error('Failed to load services');
  } finally {
    loadingServices.value = false;
  }
};

const loadCaseGroups = async () => {
  try {
    const response = await caseAPI.getGroups();
    if (response.data.success) {
      caseGroups.value = response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load case groups:', err);
  }
};

const onCasesSelected = () => {
  const selectedCases = selectedCaseIds.value
    .map(id => services.value.find(s => s.id === id))
    .filter(Boolean)
    .map(c => ({ ...c, type: 'service' }));
    
  emit('update:modelValue', selectedCases);
};


const clearAllSelections = () => {
  selectedCaseIds.value = [];
  emit('update:modelValue', []);
};

const filterServices = () => {
  // Trigger reactivity
};

const debouncedSearch = debounce(() => {
  filterServices();
}, 300);

const formatPrice = (price) => {
  return new Intl.NumberFormat('en-NG').format(price || 0);
};

const getChipLabel = (item) => {
  const service = services.value.find(s => s.id === item);
  if (service) {
    const label = service.service_description || service.service_description;
    return `${label} (₦${formatPrice(service.price || 0)})`;
  }
  console.log('Service not found:', item);
  return `Case #${item}`;
};


// Watchers
watch(() => props.requestType, () => {
  clearAllSelections();
});

watch(() => props.modelValue, (newValue) => {
  // Sync selectedCaseIds with modelValue
  selectedCaseIds.value = newValue.map(item => item.id || item).filter(Boolean);
}, { immediate: true });

// Lifecycle
onMounted(() => {
  loadServices();
  loadCaseGroups();
});
</script>
<!-- update selector -->