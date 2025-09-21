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

    <!-- Service Categories -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
      <v-select
        v-model="selectedGroup"
        :items="serviceGroups"
        item-title="name"
        item-value="id"
        label="Filter by Service Group"
        variant="outlined"
        clearable
        @update:modelValue="filterServices"
      />
      <v-select
        v-model="selectedLevelOfCare"
        :items="levelOfCareOptions"
        label="Filter by Level of Care"
        variant="outlined"
        clearable
        @update:modelValue="filterServices"
      />
      <v-text-field
        v-model="searchQuery"
        label="Search services..."
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        clearable
        @input="debouncedSearch"
      />
    </div>

    <!-- Service Type Toggle -->
    <div class="tw-flex tw-items-center tw-space-x-4">
      <v-chip-group
        v-model="serviceTypeFilter"
        selected-class="text-primary"
        @update:modelValue="filterServices"
      >
        <v-chip value="all" variant="outlined">All Services</v-chip>
        <v-chip value="services" variant="outlined">Medical Services</v-chip>
        <v-chip value="drugs" variant="outlined">Drugs & Consumables</v-chip>
      </v-chip-group>
    </div>

    <!-- Selected Services Summary -->
    <v-card v-if="selectedServices.length > 0" class="tw-bg-green-50 tw-border tw-border-green-200">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
          <h4 class="tw-font-semibold tw-text-green-900">
            Selected Services ({{ selectedServices.length }})
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
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-chip
            v-for="service in selectedServices"
            :key="service.id"
            color="green"
            variant="flat"
            closable
            @click:close="removeService(service)"
          >
            {{ service.service_description || service.drug_name || service.description }}
            <span class="tw-ml-2 tw-text-xs">(₦{{ formatPrice(service.type === 'drug' ? service.drug_unit_price : service.price) }})</span>
          </v-chip>
        </div>
        <div class="tw-mt-3 tw-pt-3 tw-border-t tw-border-green-200">
          <div class="tw-flex tw-items-center tw-justify-between">
            <span class="tw-font-semibold tw-text-green-900">Total Estimated Cost:</span>
            <span class="tw-text-lg tw-font-bold tw-text-green-900">₦{{ formatPrice(totalCost) }}</span>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Services List -->
    <div class="tw-space-y-4">
      <!-- Medical Services -->
      <div v-if="serviceTypeFilter === 'all' || serviceTypeFilter === 'services'">
        <h3 class="tw-text-lg tw-font-semibold tw-mb-3 tw-text-gray-900">Medical Services</h3>
        <div v-if="loadingServices" class="tw-text-center tw-py-8">
          <v-progress-circular indeterminate color="primary" size="48" />
          <p class="tw-mt-4 tw-text-gray-600">Loading services...</p>
        </div>
        <div v-else-if="filteredServices.length === 0" class="tw-text-center tw-py-8">
          <v-icon size="48" color="grey" class="tw-mb-2">mdi-medical-bag</v-icon>
          <p class="tw-text-gray-600">No services found matching your criteria</p>
        </div>
        <div v-else class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
          <v-card
            v-for="service in filteredServices"
            :key="service.id"
            :class="[
              'tw-cursor-pointer tw-transition-all tw-duration-300',
              isServiceSelected(service) ? 'tw-ring-2 tw-ring-green-500 tw-bg-green-50' : 'hover:tw-bg-gray-50'
            ]"
            @click="toggleService(service)"
            elevation="1"
          >
            <v-card-text class="tw-p-4">
              <div class="tw-flex tw-items-start tw-justify-between tw-mb-3">
                <div class="tw-flex-1">
                  <h4 class="tw-font-semibold tw-text-gray-900 tw-mb-1 tw-line-clamp-2">
                    {{ service.service_description }}
                  </h4>
                  <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                    Code: {{ service.nicare_code }}
                  </p>
                </div>
                <v-checkbox
                  :model-value="isServiceSelected(service)"
                  color="green"
                  hide-details
                  @click.stop="toggleService(service)"
                />
              </div>

              <div class="tw-space-y-2">
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Price:</span>
                  <span class="tw-font-semibold tw-text-green-600">₦{{ formatPrice(service.price || 0) }}</span>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Level:</span>
                  <v-chip
                    :color="getLevelOfCareColor(service.level_of_care)"
                    size="small"
                    variant="flat"
                  >
                    {{ service.level_of_care }}
                  </v-chip>
                </div>
              
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Group:</span>
                  <span class="tw-text-sm tw-font-medium">{{ service.group || 'N/A' }}</span>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">PA Required:</span>
                  <v-chip
                    :color="service.pa_required ? 'orange' : 'green'"
                    size="small"
                    variant="flat"
                  >
                    {{ service.pa_required ? 'Yes' : 'No' }}
                  </v-chip>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Referable:</span>
                  <v-chip
                    :color="service.referable ? 'blue' : 'grey'"
                    size="small"
                    variant="flat"
                  >
                    {{ service.referable ? 'Yes' : 'No' }}
                  </v-chip>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>

      <!-- Drugs & Consumables -->
      <div v-if="serviceTypeFilter === 'all' || serviceTypeFilter === 'drugs'">
        <h3 class="tw-text-lg tw-font-semibold tw-mb-3 tw-text-gray-900">Drugs & Consumables</h3>
        <div v-if="loadingDrugs" class="tw-text-center tw-py-8">
          <v-progress-circular indeterminate color="primary" size="48" />
          <p class="tw-mt-4 tw-text-gray-600">Loading drugs...</p>
        </div>
        <div v-else-if="filteredDrugs.length === 0" class="tw-text-center tw-py-8">
          <v-icon size="48" color="grey" class="tw-mb-2">mdi-pill</v-icon>
          <p class="tw-text-gray-600">No drugs found matching your criteria</p>
        </div>
        <div v-else class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
          <v-card
            v-for="drug in filteredDrugs"
            :key="`drug-${drug.id}`"
            :class="[
              'tw-cursor-pointer tw-transition-all tw-duration-300',
              isDrugSelected(drug) ? 'tw-ring-2 tw-ring-blue-500 tw-bg-blue-50' : 'hover:tw-bg-gray-50'
            ]"
            @click="toggleDrug(drug)"
            elevation="1"
          >
            <v-card-text class="tw-p-4">
              <div class="tw-flex tw-items-start tw-justify-between tw-mb-3">
                <div class="tw-flex-1">
                  <h4 class="tw-font-semibold tw-text-gray-900 tw-mb-1 tw-line-clamp-2">
                    {{ drug.drug_name }}
                  </h4>
                  <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                    Code: {{ drug.nicare_code }}
                  </p>
                </div>
                <v-checkbox
                  :model-value="isDrugSelected(drug)"
                  color="blue"
                  hide-details
                  @click.stop="toggleDrug(drug)"
                />
              </div>

              <div class="tw-space-y-2">
                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Price:</span>
                  <span class="tw-font-semibold tw-text-blue-600">₦{{ formatPrice(drug.drug_unit_price) }}</span>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Form:</span>
                  <span class="tw-text-sm tw-font-medium">{{ drug.dosage_form }}</span>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Strength:</span>
                  <span class="tw-text-sm tw-font-medium">{{ drug.strength || 'N/A' }}</span>
                </div>

                <div class="tw-flex tw-items-center tw-justify-between">
                  <span class="tw-text-sm tw-text-gray-600">Presentation:</span>
                  <span class="tw-text-sm tw-font-medium">{{ drug.presentation || 'N/A' }}</span>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { serviceAPI, drugAPI } from '../../../utils/api.js';
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
const drugs = ref([]);
const serviceGroups = ref([]);
const searchQuery = ref('');
const selectedGroup = ref('');
const selectedLevelOfCare = ref('');
const serviceTypeFilter = ref('all');
const loadingServices = ref(false);
const loadingDrugs = ref(false);

// Computed
const selectedServices = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const levelOfCareOptions = [
  { title: 'Primary', value: 'Primary' },
  { title: 'Secondary', value: 'Secondary' },
  { title: 'Tertiary', value: 'Tertiary' }
];

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

  // Apply other filters
  if (selectedGroup.value) {
    filtered = filtered.filter(service => service.service_group_id === selectedGroup.value);
  }

  if (selectedLevelOfCare.value) {
    filtered = filtered.filter(service => service.level_of_care === selectedLevelOfCare.value);
  }

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(service => 
      service.description.toLowerCase().includes(query) ||
      service.service_code.toLowerCase().includes(query)
    );
  }

  return filtered.filter(service => service.status);
});

const filteredDrugs = computed(() => {
  let filtered = drugs.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(drug => 
      drug.drug_name.toLowerCase().includes(query) ||
      drug.nicare_code.toLowerCase().includes(query)
    );
  }

  return filtered.filter(drug => drug.status);
});

const totalCost = computed(() => {
  return selectedServices.value.reduce((total, item) => {
    const price = item.type === 'drug' ? item.drug_unit_price : item.price;
    return total + (parseFloat(price) || 0);
  }, 0);
});

// Methods
const loadServices = async () => {
  try {
    loadingServices.value = true;
    const response = await serviceAPI.getAll({ per_page: 1000 });
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

const loadDrugs = async () => {
  try {
    loadingDrugs.value = true;
    const response = await drugAPI.getAll({ per_page: 1000 });
    if (response.data.success) {
      drugs.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load drugs:', err);
    error('Failed to load drugs');
  } finally {
    loadingDrugs.value = false;
  }
};

const loadServiceGroups = async () => {
  try {
    const response = await serviceAPI.getGroups();
    if (response.data.success) {
      serviceGroups.value = response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load service groups:', err);
  }
};

const isServiceSelected = (service) => {
  return selectedServices.value.some(s => s.id === service.id && s.type === 'service');
};

const isDrugSelected = (drug) => {
  return selectedServices.value.some(s => s.id === drug.id && s.type === 'drug');
};

const toggleService = (service) => {
  const serviceWithType = { ...service, type: 'service' };
  if (isServiceSelected(service)) {
    selectedServices.value = selectedServices.value.filter(s => !(s.id === service.id && s.type === 'service'));
  } else {
    selectedServices.value = [...selectedServices.value, serviceWithType];
  }
};

const toggleDrug = (drug) => {
  const drugWithType = { ...drug, type: 'drug', price: drug.drug_unit_price };
  if (isDrugSelected(drug)) {
    selectedServices.value = selectedServices.value.filter(s => !(s.id === drug.id && s.type === 'drug'));
  } else {
    selectedServices.value = [...selectedServices.value, drugWithType];
  }
};

const removeService = (service) => {
  selectedServices.value = selectedServices.value.filter(s => 
    !(s.id === service.id && s.type === service.type)
  );
};

const clearAllSelections = () => {
  selectedServices.value = [];
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

const getLevelOfCareColor = (level) => {
  switch (level) {
    case 'Primary': return 'green';
    case 'Secondary': return 'orange';
    case 'Tertiary': return 'red';
    default: return 'grey';
  }
};

// Watchers
watch(() => props.requestType, () => {
  clearAllSelections();
});

// Lifecycle
onMounted(() => {
  loadServices();
  loadDrugs();
  loadServiceGroups();
});
</script>
