<template>
  <div class="tw-space-y-4">
    <!-- Search and Filter Controls -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4">
      <v-text-field
        v-model="searchQuery"
        label="Search facilities..."
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        density="compact"
        clearable
        @input="debouncedSearch"
      />
      <v-select
        v-model="selectedLGA"
        :items="lgaOptions"
        label="Filter by LGA"
        variant="outlined"
        density="compact"
        clearable
        @update:modelValue="onFilterChange"
      />
      <v-select
        v-model="selectedLevelOfCare"
        :items="levelOfCareOptions"
        label="Filter by Level of Care"
        variant="outlined"
        density="compact"
        clearable
        @update:modelValue="onFilterChange"
      />
    </div>

    <!-- Facilities List -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4 tw-max-h-96 tw-overflow-y-auto">
      <div v-if="loading" class="tw-col-span-full tw-text-center tw-py-8">
        <v-progress-circular indeterminate color="primary" size="48" />
        <p class="tw-mt-4 tw-text-gray-600">Loading facilities...</p>
      </div>

      <div v-else-if="filteredFacilities.length === 0" class="tw-col-span-full tw-text-center tw-py-8">
        <v-icon size="48" color="grey" class="tw-mb-4">mdi-hospital-building-outline</v-icon>
        <p class="tw-text-gray-600">No facilities found matching your criteria</p>
      </div>

      <v-card
        v-else
        v-for="facility in filteredFacilities"
        :key="facility.id"
        :class="[
          'tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg',
          selectedFacility?.id === facility.id ? 'tw-ring-2 tw-ring-primary tw-bg-blue-50' : 'hover:tw-bg-gray-50'
        ]"
        @click="selectFacility(facility)"
        elevation="1"
      >
        <v-card-text class="tw-p-4">
          <div class="tw-flex tw-items-start tw-justify-between tw-mb-3">
            <div class="tw-flex-1">
              <h4 class="tw-font-semibold tw-text-gray-900 tw-mb-1 tw-line-clamp-2">
                {{ facility.name }}
              </h4>
              <p class="tw-text-sm tw-text-gray-600 tw-mb-2">
                Code: {{ facility.hcp_code }}
              </p>
            </div>
            <v-icon 
              v-if="selectedFacility?.id === facility.id"
              color="primary"
              size="20"
            >
              mdi-check-circle
            </v-icon>
          </div>

          <div class="tw-space-y-2">
            <div class="tw-flex tw-items-center tw-text-sm">
              <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-map-marker</v-icon>
              <span class="tw-text-gray-700">{{ facility.lga?.name || 'N/A' }}</span>
            </div>

            <div class="tw-flex tw-items-center tw-text-sm">
              <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-hospital</v-icon>
              <v-chip
                :color="getLevelOfCareColor(facility.level_of_care)"
                size="small"
                variant="flat"
              >
                {{ facility.level_of_care }}
              </v-chip>
            </div>


            <div v-if="facility.phone" class="tw-flex tw-items-center tw-text-sm">
              <v-icon size="16" class="tw-mr-2 tw-text-gray-500">mdi-phone</v-icon>
              <span class="tw-text-gray-700">{{ facility.phone }}</span>
            </div>
          </div>

          <div class="tw-mt-3 tw-pt-3 tw-border-t tw-border-gray-200">
            <div class="tw-flex tw-items-center tw-justify-between">
              <v-chip
                :color="facility.status ? 'success' : 'error'"
                size="small"
                variant="flat"
              >
                {{ facility.status ? 'Active' : 'Inactive' }}
              </v-chip>
              <span class="tw-text-xs tw-text-gray-500">
                Capacity: {{ facility.capacity || '' }}
              </span>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Selected Facility Summary -->
    <v-card v-if="selectedFacility" class="tw-bg-blue-50 tw-border tw-border-blue-200">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-justify-between">
          <div>
            <h4 class="tw-font-semibold tw-text-blue-900">Selected Facility</h4>
            <p class="tw-text-blue-700">{{ selectedFacility.name }}</p>
            <p class="tw-text-sm tw-text-blue-600">{{ selectedFacility.hcp_code }}</p>
          </div>
          <v-btn
            color="blue"
            variant="outlined"
            size="small"
            @click="clearSelection"
          >
            Change
          </v-btn>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { facilityAPI, lgaAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';
import { debounce } from 'lodash-es';

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const { error } = useToast();

// Reactive data
const facilities = ref([]);
const lgaOptions = ref([]);
const searchQuery = ref('');
const selectedLGA = ref('');
const selectedLevelOfCare = ref('');
const loading = ref(false);

// Computed
const selectedFacility = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const levelOfCareOptions = [
  { title: 'Primary', value: 'Primary' },
  { title: 'Secondary', value: 'Secondary' },
  { title: 'Tertiary', value: 'Tertiary' }
];

const filteredFacilities = computed(() => {
  // Since we're now doing backend filtering, just return the facilities
  return facilities.value;
});

// Methods
const loadFacilities = async () => {
  try {
    loading.value = true;
    const params = {
      with_enrollees_count: true,
      search: searchQuery.value,
      lga_id: selectedLGA.value,
      level_of_care: selectedLevelOfCare.value,
      status: 1 // Only active facilities
    };

    const response = await facilityAPI.getAll(params);

    if (response.data.success) {
      facilities.value = response.data.data.data || response.data.data || [];
    }
  } catch (err) {
    console.error('Failed to load facilities:', err);
    error('Failed to load facilities');
  } finally {
    loading.value = false;
  }
};

const loadLGAs = async () => {
  try {
    const response = await lgaAPI.getAll();
    if (response.data.success) {
      lgaOptions.value = (response.data.data.data || response.data.data || []).map(lga => ({
        title: lga.name,
        value: lga.id
      }));
    }
  } catch (err) {
    console.error('Failed to load LGAs:', err);
  }
};

const selectFacility = (facility) => {
  selectedFacility.value = facility;
};

const clearFilters = () => {
  searchQuery.value = '';
  selectedLGA.value = '';
  selectedLevelOfCare.value = '';
  loadFacilities();
};

const debouncedSearch = debounce(() => {
  loadFacilities();
}, 300);

const onSearchUpdate = () => {
  debouncedSearch();
};

const onFilterChange = () => {
  loadFacilities();
};

const clearSelection = () => {
  selectedFacility.value = null;
};



const getLevelOfCareColor = (level) => {
  switch (level) {
    case 'Primary': return 'green';
    case 'Secondary': return 'orange';
    case 'Tertiary': return 'red';
    default: return 'grey';
  }
};

// Lifecycle
onMounted(() => {
  loadFacilities();
  loadLGAs();
});
</script>
