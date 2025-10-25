<template>
  <div class="tw-space-y-4">
    <!-- Search and Filter Controls -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
      <v-text-field
        v-model="searchQuery"
        label="Search secondary facilities..."
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
    </div>

    <!-- Secondary Facilities List -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4 tw-max-h-96 tw-overflow-y-auto">
      <div v-if="loading" class="tw-col-span-full tw-text-center tw-py-8">
        <v-progress-circular indeterminate color="primary" size="48" />
        <p class="tw-mt-4 tw-text-gray-600">Loading secondary facilities...</p>
      </div>

      <div v-else-if="filteredFacilities.length === 0" class="tw-col-span-full tw-text-center tw-py-8">
        <v-icon size="48" color="grey" class="tw-mb-4">mdi-hospital-building-outline</v-icon>
        <p class="tw-text-gray-600">No secondary facilities found matching your criteria</p>
      </div>

      <v-card
        v-else
        v-for="facility in filteredFacilities"
        :key="facility.id"
        :class="[
          'tw-cursor-pointer tw-transition-all tw-duration-200 tw-border-2',
          selectedFacility?.id === facility.id 
            ? 'tw-border-green-500 tw-bg-green-50' 
            : 'tw-border-gray-200 hover:tw-border-green-300 hover:tw-shadow-md'
        ]"
        @click="selectFacility(facility)"
      >
        <v-card-text class="tw-p-4">
          <div class="tw-flex tw-items-start tw-justify-between">
            <div class="tw-flex-1">
              <h3 class="tw-font-semibold tw-text-gray-900 tw-mb-1">{{ facility.name }}</h3>
              <p class="tw-text-sm tw-text-gray-600 tw-mb-2">Code: {{ facility.hcp_code }}</p>
              
              <div class="tw-flex tw-items-center tw-space-x-4 tw-text-xs tw-text-gray-500">
                <span class="tw-flex tw-items-center">
                  <v-icon size="12" class="tw-mr-1">mdi-map-marker</v-icon>
                  {{ facility?.lga?.name ?? "" }}
                </span>
                <span class="tw-flex tw-items-center">
                  <v-icon size="12" class="tw-mr-1">mdi-hospital</v-icon>
                  {{ facility.level_of_care || 'Secondary' }}
                </span>
              </div>

              <div class="tw-mt-2">
                <v-chip
                  :color="facility.status === 1 ? 'green' : 'red'"
                  size="small"
                  variant="flat"
                >
                  {{ facility.status === 1 ? 'Active' : 'Inactive' }}
                </v-chip>
              </div>

              <div class="tw-mt-2 tw-text-xs tw-text-gray-600">
                <p v-if="facility.phone">📞 {{ facility.phone }}</p>
                <p v-if="facility.capacity">Capacity: {{ facility.capacity }}</p>
              </div>
            </div>

            <div v-if="selectedFacility?.id === facility.id" class="tw-ml-2">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </div>

    <!-- Selected Facility Info -->
    <div v-if="selectedFacility" class="tw-mt-4 tw-p-4 tw-bg-green-50 tw-rounded-lg tw-border tw-border-green-200">
      <div class="tw-flex tw-items-center tw-space-x-2 tw-mb-2">
        <v-icon color="green">mdi-check-circle</v-icon>
        <span class="tw-font-semibold tw-text-green-800">Selected Secondary Facility</span>
      </div>
      <p class="tw-text-green-700">{{ selectedFacility.name }} ({{ selectedFacility.hcp_code }})</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { facilityAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';
import { debounce } from 'lodash-es';

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:modelValue']);

const { error } = useToast();

// Data
const facilities = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const selectedLGA = ref(null);
const selectedFacility = ref(props.modelValue);

// Computed
const lgaOptions = computed(() => {
  const lgas = [...new Set(facilities.value.map(f => f.lga).filter(Boolean))];
  return lgas.sort();
});

const filteredFacilities = computed(() => {
  let filtered = facilities.value.filter(facility => {
    // Only show secondary facilities
    const isSecondary = facility.level_of_care === 'secondary' || 
                       facility.level_of_care === 'Secondary' ||
                       facility.type === 'secondary' ||
                       facility.type === 'Secondary';
    
    if (!isSecondary) return false;

    // Search filter
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase();
      const matchesSearch = 
        facility.name.toLowerCase().includes(query) ||
        facility.hcp_code.toLowerCase().includes(query) ||
        (facility.lga && facility.lga.toLowerCase().includes(query));
      
      if (!matchesSearch) return false;
    }

    // LGA filter
    if (selectedLGA.value && facility.lga !== selectedLGA.value) {
      return false;
    }

    return true;
  });

  return filtered;
});

// Methods
const loadFacilities = async () => {
  try {
    loading.value = true;
    const response = await facilityAPI.getAll({
      status: 1, // Only active facilities
      per_page: 1000 // Get all facilities
    });

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

const selectFacility = (facility) => {
  selectedFacility.value = facility;
  emit('update:modelValue', facility);
};

const onFilterChange = () => {
  // Filters are reactive, no additional action needed
};

const debouncedSearch = debounce(() => {
  // Search is reactive, no additional action needed
}, 300);

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  selectedFacility.value = newValue;
});

// Load facilities on mount
onMounted(() => {
  loadFacilities();
});
</script>

<style scoped>
:deep(.v-card) {
  transition: all 0.2s ease-in-out;
}

:deep(.v-card:hover) {
  transform: translateY(-2px);
}
</style>
