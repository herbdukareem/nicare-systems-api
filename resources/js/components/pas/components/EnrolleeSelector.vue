<template>
  <div class="tw-space-y-4">
    <!-- Search Controls -->
    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
      
      <v-autocomplete
        v-model="selectedEnrollee"
         v-model:search="searchQuery" 
        :items="enrollees"
        :loading="loading"
        :search="searchQuery"
        @update:search="onSearchUpdate"
        item-title="display_name"
        item-value="id"
        label="Search enrollee by name or NiCare number"
        prepend-inner-icon="mdi-account-search"
        variant="outlined"
        clearable
        no-filter
        return-object
        :menu-props="{ maxHeight: 400 }"
      >
        <template v-slot:item="{ props: itemProps, item }">
          <v-list-item
            v-bind="itemProps"
            :title="$utils.formatName(item.raw) || 'Unknown Name'"
            :subtitle="`${item.raw.enrollee_id || 'N/A'} | ${item.raw.gender || 'N/A'}`"
            class="tw-py-2"
          >
            <template v-slot:prepend>
              <v-avatar
                :color="item.raw.gender === 'Male' ? 'blue' : 'pink'"
                size="40"
              >
                <v-icon color="white">
                  {{ item.raw.gender === 'Male' ? 'mdi-account' : 'mdi-account-outline' }}
                </v-icon>
              </v-avatar>
            </template>
            <template v-slot:append>
              <v-chip
                :color="getStatusColor(item.raw.status)"
                size="small"
                variant="flat"
              >
                {{ getStatusText(item.raw.status) }}
              </v-chip>
            </template>
          </v-list-item>
        </template>

        <template v-slot:no-data>
          <div class="tw-text-center tw-py-4">
            <v-icon size="48" color="grey" class="tw-mb-2">mdi-account-search</v-icon>
            <p class="tw-text-gray-600">
              {{ searchQuery ? 'No enrollees found matching your search' : 'Start typing to search enrollees' }}
            </p>
          </div>
        </template>
      </v-autocomplete>

      <v-select
        v-model="statusFilter"
        :items="statusOptions"
        label="Filter by status"
        variant="outlined"
        clearable
        @update:modelValue="filterEnrollees"
      />
    </div>

    <!-- Facility Info -->
    <v-alert
      v-if="facility"
      type="info"
      variant="tonal"
      class="tw-mb-4"
    >
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
        <span>
          Searching enrollees from <strong>{{ facility.name }}</strong> 
          ({{ facility.hcp_code }})
        </span>
      </div>
    </v-alert>

    <!-- Quick Stats -->
    <div v-if="enrolleeStats" class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4">
      <v-card class="tw-text-center tw-p-4">
        <v-icon color="blue" size="24" class="tw-mb-2">mdi-account-group</v-icon>
        <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ enrolleeStats.total }}</p>
        <p class="tw-text-sm tw-text-gray-600">Total Enrollees</p>
      </v-card>
      <v-card class="tw-text-center tw-p-4">
        <v-icon color="green" size="24" class="tw-mb-2">mdi-check-circle</v-icon>
        <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ enrolleeStats.active }}</p>
        <p class="tw-text-sm tw-text-gray-600">Active</p>
      </v-card>
      <v-card class="tw-text-center tw-p-4">
        <v-icon color="orange" size="24" class="tw-mb-2">mdi-clock</v-icon>
        <p class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ enrolleeStats.pending }}</p>
        <p class="tw-text-sm tw-text-gray-600">Pending</p>
      </v-card>
      <v-card class="tw-text-center tw-p-4">
        <v-icon color="red" size="24" class="tw-mb-2">mdi-pause-circle</v-icon>
        <p class="tw-text-2xl tw-font-bold tw-text-red-600">{{ enrolleeStats.suspended }}</p>
        <p class="tw-text-sm tw-text-gray-600">Suspended</p>
      </v-card>
    </div>

    <!-- Selected Enrollee Preview -->
    <v-card v-if="selectedEnrollee" class="tw-bg-green-50 tw-border tw-border-green-200">
      <v-card-text class="tw-p-4">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-4">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <v-avatar
              :color="selectedEnrollee.gender === 'Male' ? 'blue' : 'pink'"
              size="48"
            >
              <v-icon color="white" size="24">
                {{ selectedEnrollee.gender === 'Male' ? 'mdi-account' : 'mdi-account-outline' }}
              </v-icon>
            </v-avatar>
            <div>
              <h4 class="tw-font-semibold tw-text-green-900">{{ $utils.formatName(selectedEnrollee) }}</h4>
              <p class="tw-text-green-700">NiCare: {{ selectedEnrollee.enrollee_id }}</p>
              <p class="tw-text-sm tw-text-green-600">
                {{ selectedEnrollee.gender || 'N/A' }}
              </p>
            </div>
          </div>
          <div class="tw-text-right">
            <v-chip
              :color="getStatusColor(selectedEnrollee.status)"
              size="small"
              variant="flat"
              class="tw-mb-2"
            >
              {{ getStatusText(selectedEnrollee.status) }}
            </v-chip>
            <br>
            <v-btn
              color="green"
              variant="outlined"
              size="small"
              @click="clearSelection"
            >
              Change
            </v-btn>
          </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="tw-mb-4">
          <div class="tw-flex tw-items-center tw-mb-3">
            <v-icon class="tw-mr-2" size="20">mdi-history</v-icon>
            <h5 class="tw-font-semibold tw-text-gray-700">Recent Activity</h5>
            <v-btn
              color="primary"
              variant="text"
              size="small"
              class="tw-ml-auto"
              @click="loadEnrolleeMedicalStats"
              :loading="loadingMedicalStats"
            >
              <v-icon size="16">mdi-refresh</v-icon>
            </v-btn>
          </div>

          <div v-if="loadingMedicalStats" class="tw-text-center tw-py-4">
            <v-progress-circular indeterminate color="primary" size="24" />
            <p class="tw-text-sm tw-text-gray-600 tw-mt-2">Loading activity...</p>
          </div>

          <div v-else-if="!medicalStats" class="tw-text-center tw-py-4">
            <v-icon size="48" color="grey-lighten-1">mdi-history</v-icon>
            <p class="tw-text-gray-600 tw-text-sm">No recent activity found</p>
          </div>
        </div>

        <!-- Medical Summary Section -->
        <div class="tw-mb-4">
          <div class="tw-flex tw-items-center tw-mb-3">
            <v-icon class="tw-mr-2" size="20">mdi-medical-bag</v-icon>
            <h5 class="tw-font-semibold tw-text-gray-700">Medical Summary</h5>
          </div>

          <div class="tw-grid tw-grid-cols-3 tw-gap-3">
            <!-- Total Referrals -->
            <div class="tw-bg-blue-50 tw-p-3 tw-rounded-lg tw-text-center">
              <v-icon color="blue" size="24" class="tw-mb-1">mdi-account-arrow-right</v-icon>
              <p class="tw-text-xl tw-font-bold tw-text-blue-600">
                {{ medicalStats?.total_referrals || 0 }}
              </p>
              <p class="tw-text-xs tw-text-blue-700">Total Referrals</p>
            </div>

            <!-- PA Codes Used -->
            <div class="tw-bg-green-50 tw-p-3 tw-rounded-lg tw-text-center">
              <v-icon color="green" size="24" class="tw-mb-1">mdi-qrcode</v-icon>
              <p class="tw-text-xl tw-font-bold tw-text-green-600">
                {{ medicalStats?.pa_codes_used || 0 }}
              </p>
              <p class="tw-text-xs tw-text-green-700">PA Codes Used</p>
            </div>

            <!-- Last Visit -->
            <div class="tw-bg-orange-50 tw-p-3 tw-rounded-lg tw-text-center">
              <v-icon color="orange" size="24" class="tw-mb-1">mdi-calendar-clock</v-icon>
              <p class="tw-text-sm tw-font-bold tw-text-orange-600">
                {{ formatLastVisit(medicalStats?.last_visit) }}
              </p>
              <p class="tw-text-xs tw-text-orange-700">Last Visit</p>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <!-- Loading State -->
    <div v-if="loading && !enrollees.length" class="tw-text-center tw-py-8">
      <v-progress-circular indeterminate color="primary" size="48" />
      <p class="tw-mt-4 tw-text-gray-600">Loading enrollees...</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { enrolleeAPI } from '../../../utils/api.js';
import { useToast } from '../../../composables/useToast';
import { debounce } from 'lodash-es';
import { useUtils } from '../../../utils/utils';

const props = defineProps({
  modelValue: {
    type: Object,
    default: null
  },
  facility: {
    type: Object,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const { error } = useToast();
const { formatName } = useUtils();

// Reactive data
const enrollees = ref([]);
const enrolleeStats = ref(null);
const medicalStats = ref(null);
const searchQuery = ref('');
const statusFilter = ref('');
const loading = ref(false);
const loadingMedicalStats = ref(false);

// Computed
const selectedEnrollee = computed({
  get: () => props.modelValue,
  set: (value) => {
    // If value is an ID, find the full enrollee object
    if (typeof value === 'number' || typeof value === 'string') {
      const enrollee = enrollees.value.find(e => e.id === value);
      emit('update:modelValue', enrollee || null);
    } else {
      emit('update:modelValue', value);
    }
  }
});

const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Pending', value: 0 },
  { title: 'Suspended', value: 2 }
];

// Methods
const loadEnrollees = async (search = '') => {
  if (!props.facility) return;

  try {
    loading.value = true;
    const params = {
      facility_id: props.facility.id,
      search: search,
      status: statusFilter.value,
      per_page: 50
    };

    const response = await enrolleeAPI.getAll(params);
    if (response.data.success) {
      const data = response.data.data;
      enrollees.value = (data.data || data || []).map(enrollee => ({
        ...enrollee,
        display_name: `${formatName(enrollee) || 'Unknown'} (${enrollee.enrollee_id || 'N/A'})`
      }));
    }
  } catch (err) {
    console.error('Failed to load enrollees:', err);
    error('Failed to load enrollees');
  } finally {
    loading.value = false;
  }
};

const loadEnrolleeStats = async () => {
  if (!props.facility) return;

  try {
    const response = await enrolleeAPI.getStatsByFacility(props.facility.id);
    if (response.data.success) {
      enrolleeStats.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load enrollee stats:', err);
  }
};

const loadEnrolleeMedicalStats = async () => {
  if (!selectedEnrollee.value?.enrollee_id) return;

  try {
    loadingMedicalStats.value = true;
    // Import pasAPI for medical stats
    const { pasAPI } = await import('../../../utils/api.js');

    const [referralsResponse, paCodesResponse] = await Promise.all([
      pasAPI.getReferrals({
        search: selectedEnrollee.value.enrollee_id,
        per_page: 100
      }),
      pasAPI.getPACodes({
        search: selectedEnrollee.value.enrollee_id,
        per_page: 100
      })
    ]);

    console.log('Referrals response:', referralsResponse.data);
    console.log('PA Codes response:', paCodesResponse.data);

    // Handle referrals data - could be array or paginated object
    let referrals = [];
    if (referralsResponse.data.success) {
      const referralsData = referralsResponse.data.data;
      if (Array.isArray(referralsData)) {
        referrals = referralsData;
      } else if (referralsData && Array.isArray(referralsData.data)) {
        referrals = referralsData.data;
      } else if (referralsData && referralsData.length !== undefined) {
        referrals = Array.from(referralsData);
      }
    }

    // Handle PA codes data - could be array or paginated object
    let paCodes = [];
    if (paCodesResponse.data.success) {
      const paCodesData = paCodesResponse.data.data;
      if (Array.isArray(paCodesData)) {
        paCodes = paCodesData;
      } else if (paCodesData && Array.isArray(paCodesData.data)) {
        paCodes = paCodesData.data;
      } else if (paCodesData && paCodesData.length !== undefined) {
        paCodes = Array.from(paCodesData);
      }
    }

    // Filter referrals for this specific enrollee
    const enrolleeReferrals = referrals.filter(r =>
      r.nicare_number === selectedEnrollee.value.enrollee_id ||
      r.enrollee_full_name?.toLowerCase().includes(selectedEnrollee.value.first_name?.toLowerCase() || '') ||
      r.enrollee_id === selectedEnrollee.value.id
    );

    // Filter PA codes for this specific enrollee
    const enrolleePACodes = paCodes.filter(pa =>
      pa.nicare_number === selectedEnrollee.value.enrollee_id ||
      pa.enrollee_name?.toLowerCase().includes(selectedEnrollee.value.first_name?.toLowerCase() || '')
    );

    // Calculate stats
    const totalReferrals = enrolleeReferrals.length;
    const paCodesUsed = enrolleePACodes.filter(pa => pa.status === 'used').length;

    // Find last visit (most recent referral or PA code)
    const allDates = [
      ...enrolleeReferrals.map(r => r.created_at),
      ...enrolleePACodes.map(p => p.issued_at || p.created_at)
    ].filter(Boolean).sort((a, b) => new Date(b) - new Date(a));

    const lastVisit = allDates.length > 0 ? allDates[0] : null;

    medicalStats.value = {
      total_referrals: totalReferrals,
      pa_codes_used: paCodesUsed,
      last_visit: lastVisit
    };

    console.log('Medical stats loaded:', medicalStats.value);

  } catch (err) {
    console.error('Failed to load medical stats:', err);
    error('Failed to load medical statistics');
    // Set default values on error
    medicalStats.value = {
      total_referrals: 0,
      pa_codes_used: 0,
      last_visit: null
    };
  } finally {
    loadingMedicalStats.value = false;
  }
};

const debouncedSearch = debounce((search) => {
  loadEnrollees(search);
}, 300);

const onSearchUpdate = (search) => {
  searchQuery.value = search;
  if (search && search.length >= 2) {
    debouncedSearch(search);
  } else if (!search) {
    loadEnrollees();
  }
};

const filterEnrollees = () => {
  loadEnrollees(searchQuery.value);
};

const clearSelection = () => {
  selectedEnrollee.value = null;
};

const getStatusColor = (status) => {
  switch (status) {
    case 1: return 'success';
    case 0: return 'warning';
    case 2: return 'error';
    default: return 'grey';
  }
};

const getStatusText = (status) => {
   return status;
};

const formatLastVisit = (dateString) => {
  if (!dateString) return 'N/A';

  const date = new Date(dateString);
  if (isNaN(date)) return 'N/A';

  const now = new Date();
  const diffTime = Math.abs(now - date);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays === 1) return 'Today';
  if (diffDays === 2) return 'Yesterday';
  if (diffDays <= 7) return `${diffDays - 1} days ago`;
  if (diffDays <= 30) return `${Math.floor(diffDays / 7)} weeks ago`;
  if (diffDays <= 365) return `${Math.floor(diffDays / 30)} months ago`;

  return date.toLocaleDateString('en-NG', {
    year: 'numeric',
    month: 'short'
  });
};


watch(searchQuery, (q) => {
  if (q && q.length >= 2) {
    debouncedSearch(q);
  } else {
    loadEnrollees('');
  }
});

// Watchers
watch(() => props.facility, (newFacility) => {
  if (newFacility) {
    selectedEnrollee.value = null;
    enrollees.value = [];
    searchQuery.value = '';
    statusFilter.value = '';
    medicalStats.value = null;
    loadEnrollees();
    loadEnrolleeStats();
  }
}, { immediate: true });

watch(() => selectedEnrollee.value, (newEnrollee) => {
  if (newEnrollee?.enrollee_id) {
    loadEnrolleeMedicalStats();
  } else {
    medicalStats.value = null;
  }
});

// Lifecycle
onMounted(() => {
  if (props.facility) {
    loadEnrollees();
    loadEnrolleeStats();
  }
});
</script>
