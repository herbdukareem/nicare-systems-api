<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Pre-Authorisation System (PAS)</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage referrals and PA codes for Fee-For-Service claims</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-download"
            @click="exportData"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Export
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-file-document-multiple</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Referrals</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ totalReferrals }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Approved PA Codes</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ approvedPACodes }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-3 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-yellow-100">
              <v-icon color="orange" size="24">mdi-clock-outline</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Pending Requests</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ pendingRequests }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-4 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-red-100">
              <v-icon color="red" size="24">mdi-alert-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Emergency Cases</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ emergencyCases }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Workflow Actions -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1">
        <div class="tw-mb-4">
          <h2 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-2">Quick Actions</h2>
          <p class="tw-text-gray-600">Choose a workflow to get started with your healthcare authorization process</p>
        </div>

        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-6">
          <!-- Create Referral -->
          <v-card
            class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg tw-hover:scale-105 tw-border tw-border-blue-200"
            @click="$router.push('/pas/create-referral')"
          >
            <div class="tw-p-6 tw-text-center">
              <div class="tw-mb-4">
                <v-icon
                  size="48"
                  color="blue"
                  class="tw-mb-2"
                >
                  mdi-file-document-plus
                </v-icon>
              </div>
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-2">
                Create Referral Request
              </h3>
              <p class="tw-text-gray-600 tw-mb-4 tw-text-sm">
                Submit a new referral request for patient authorization to receive specialized care
              </p>
              <v-btn
                color="blue"
                variant="flat"
                size="small"
                class="tw-w-full"
              >
                <v-icon left size="16">mdi-plus</v-icon>
                Start Referral
              </v-btn>
            </div>
          </v-card>

          <!-- Generate PA Code -->
          <v-card
            class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg tw-hover:scale-105 tw-border tw-border-green-200"
            @click="$router.push('/pas/generate-pa-code')"
          >
            <div class="tw-p-6 tw-text-center">
              <div class="tw-mb-4">
                <v-icon
                  size="48"
                  color="green"
                  class="tw-mb-2"
                >
                  mdi-qrcode
                </v-icon>
              </div>
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-2">
                Generate PA Code
              </h3>
              <p class="tw-text-gray-600 tw-mb-4 tw-text-sm">
                Generate a Pre-Authorization code from an approved referral for service delivery
              </p>
              <v-btn
                color="green"
                variant="flat"
                size="small"
                class="tw-w-full"
              >
                <v-icon left size="16">mdi-qrcode</v-icon>
                Generate Code
              </v-btn>
            </div>
          </v-card>

          <!-- Modify Referral -->
          <v-card
            class="tw-cursor-pointer tw-transition-all tw-duration-300 tw-hover:shadow-lg tw-hover:scale-105 tw-border tw-border-orange-200"
            @click="$router.push('/pas/modify-referral')"
          >
            <div class="tw-p-6 tw-text-center">
              <div class="tw-mb-4">
                <v-icon
                  size="48"
                  color="orange"
                  class="tw-mb-2"
                >
                  mdi-file-edit
                </v-icon>
              </div>
              <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-mb-2">
                Modify Referral Service
              </h3>
              <p class="tw-text-gray-600 tw-mb-4 tw-text-sm">
                Modify the service details of an existing pending referral request
              </p>
              <v-btn
                color="orange"
                variant="flat"
                size="small"
                class="tw-w-full"
              >
                <v-icon left size="16">mdi-pencil</v-icon>
                Modify Referral
              </v-btn>
            </div>
          </v-card>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4 tw-mb-4">
          <!-- Search -->
          <div class="lg:tw-col-span-2">
            <v-text-field
              v-model="searchQuery"
              label="Search referrals, PA codes, or enrollees..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>

          <!-- Status Filter -->
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
          />

          <!-- Severity Filter -->
          <v-select
            v-model="filters.severity"
            :items="severityOptions"
            label="Severity Level"
            variant="outlined"
            density="compact"
            clearable
          />
        </div>
      </div>

      <!-- Tabs for different views -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-animate-slide-up tw-animate-stagger-2">
        <v-tabs v-model="activeTab" class="tw-border-b">
          <v-tab value="referrals">Referral Requests</v-tab>
          <v-tab value="pa-codes">PA Codes</v-tab>
          <v-tab value="tracking">UTN Tracking</v-tab>
        </v-tabs>

        <v-tabs-window v-model="activeTab">
          <!-- Referrals Tab -->
          <v-tabs-window-item value="referrals">
            <div class="tw-p-6">
              <v-data-table
                :headers="referralHeaders"
                :items="filteredReferrals"
                :loading="loading"
                item-key="id"
                class="tw-elevation-0"
              >
                <template v-slot:item.severity="{ item }">
                  <v-chip
                    :color="getSeverityColor(item.severity)"
                    size="small"
                    variant="flat"
                  >
                    {{ item.severity }}
                  </v-chip>
                </template>

                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getStatusColor(item.status)"
                    size="small"
                    variant="flat"
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <div class="tw-flex tw-space-x-2">
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="viewReferral(item)"
                    >
                      <v-icon>mdi-eye</v-icon>
                    </v-btn>
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="editReferral(item)"
                      v-if="item.status === 'pending'"
                    >
                      <v-icon>mdi-pencil</v-icon>
                    </v-btn>
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      color="success"
                      @click="approveReferral(item)"
                      v-if="item.status === 'pending'"
                    >
                      <v-icon>mdi-check</v-icon>
                    </v-btn>
                  </div>
                </template>
              </v-data-table>
            </div>
          </v-tabs-window-item>

          <!-- PA Codes Tab -->
          <v-tabs-window-item value="pa-codes">
            <div class="tw-p-6">
              <v-data-table
                :headers="paCodeHeaders"
                :items="filteredPACodes"
                :loading="loading"
                item-key="id"
                class="tw-elevation-0"
              >
                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getStatusColor(item.status)"
                    size="small"
                    variant="flat"
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <div class="tw-flex tw-space-x-2">
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="viewPACode(item)"
                    >
                      <v-icon>mdi-eye</v-icon>
                    </v-btn>
                    <v-btn
                      icon
                      size="small"
                      variant="text"
                      @click="generateUTN(item)"
                      v-if="item.status === 'approved'"
                    >
                      <v-icon>mdi-qrcode</v-icon>
                    </v-btn>
                  </div>
                </template>
              </v-data-table>
            </div>
          </v-tabs-window-item>

          <!-- UTN Tracking Tab -->
          <v-tabs-window-item value="tracking">
            <div class="tw-p-6">
              <div class="tw-text-center tw-py-12">
                <v-icon size="64" color="grey">mdi-chart-line</v-icon>
                <h3 class="tw-text-xl tw-font-semibold tw-text-gray-700 tw-mt-4">UTN Tracking Dashboard</h3>
                <p class="tw-text-gray-500 tw-mt-2">Track PA code usage and claim submissions</p>
              </div>
            </div>
          </v-tabs-window-item>
        </v-tabs-window>
      </div>
    </div>

    <!-- Create Referral Dialog -->
    <v-dialog v-model="showCreateReferralDialog" max-width="1200px" persistent scrollable>
      <v-card v-if="showCreateReferralDialog">
        <v-card-title class="tw-text-lg tw-font-semibold tw-bg-blue-50 tw-text-blue-800">
          <v-icon class="tw-mr-2">mdi-file-document-plus</v-icon>
          New Referral/PA Code Request
        </v-card-title>
        <v-card-text class="tw-p-0">
          <ReferralPACodeWizard
            ref="referralFormRef"
            @submit="handleReferralSubmit"
            @cancel="showCreateReferralDialog = false"
          />
        </v-card-text>
        <v-card-actions class="tw-px-6 tw-py-4 tw-bg-gray-50">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <div v-if="referralFormRef?.loading" class="tw-flex tw-items-center tw-text-blue-600">
              <v-progress-circular size="20" indeterminate class="tw-mr-2" />
              <span class="tw-text-sm">Submitting referral...</span>
            </div>
          </div>
          <v-spacer />
          <v-btn
            variant="text"
            @click="showCreateReferralDialog = false"
            :disabled="referralFormRef?.loading"
          >
            Cancel
          </v-btn>
          <v-btn
            color="blue"
            @click="submitReferralForm"
            :loading="referralFormRef?.loading"
            :disabled="referralFormRef?.loading"
          >
            Submit Referral
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import ReferralPACodeWizard from './ReferralPACodeWizard.vue';
import { useToast } from '../../composables/useToast';
import { pasAPI } from '../../utils/api.js';

const { success, error } = useToast();
const route = useRoute();
const router = useRouter();

// Reactive data
const loading = ref(false);
const searchQuery = ref('');
const activeTab = ref('referrals');
const showCreateReferralDialog = ref(false);
const showCreatePACodeDialog = ref(false);
const selectedSeverity = ref('');
const selectedStatus = ref('');
const referralFormRef = ref(null);

// Statistics
const totalReferrals = ref(156);
const approvedPACodes = ref(89);
const pendingRequests = ref(23);
const emergencyCases = ref(4);

// Filters
const filters = ref({
  status: null,
  severity: null
});

const statusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Denied', value: 'denied' },
  { title: 'Expired', value: 'expired' }
];

const severityOptions = [
  { title: 'Emergency (30 mins)', value: 'emergency' },
  { title: 'Urgent (3 hours)', value: 'urgent' },
  { title: 'Routine (72 hours)', value: 'routine' }
];

// Table headers
const referralHeaders = [
  { title: 'Referral Code', key: 'referral_code', sortable: true },
  { title: 'Enrollee', key: 'enrollee_name', sortable: true },
  { title: 'From Facility', key: 'referring_facility', sortable: true },
  { title: 'To Facility', key: 'receiving_facility', sortable: true },
  { title: 'Severity', key: 'severity', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

const paCodeHeaders = [
  { title: 'PA Code', key: 'pa_code', sortable: true },
  { title: 'UTN', key: 'utn', sortable: true },
  { title: 'Enrollee', key: 'enrollee_name', sortable: true },
  { title: 'Facility', key: 'facility_name', sortable: true },
  { title: 'Service', key: 'service_type', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Expiry', key: 'expires_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// Data
const referrals = ref([]);
const paCodes = ref([]);
const pagination = ref({
  page: 1,
  itemsPerPage: 15,
  totalItems: 0
});

// Methods
const getSeverityColor = (severity) => {
  switch (severity) {
    case 'emergency': return 'red';
    case 'urgent': return 'orange';
    case 'routine': return 'blue';
    default: return 'grey';
  }
};

const getStatusColor = (status) => {
  switch (status) {
    case 'approved': return 'green';
    case 'pending': return 'orange';
    case 'denied': return 'red';
    case 'expired': return 'grey';
    default: return 'grey';
  }
};

const viewReferral = (item) => {
  // Navigate to referral details page
  if (item.referral_code) {
    router.push(`/pas/referrals/${item.referral_code}`);
  } else {
    error('Referral code not found');
  }
};

const editReferral = (item) => {
  // Navigate to modify referral page
  // Note: The modify referral wizard will need to be enhanced to accept referral ID
  // For now, navigate to the page and user can select the referral manually
  router.push('/pas/modify-referral');
  success(`Navigate to modify referral page for: ${item.referral_code}`);
};

const approveReferral = async (item) => {
  // Show confirmation dialog first
  if (confirm(`Are you sure you want to approve referral ${item.referral_code}?`)) {
    try {
      loading.value = true;
      const response = await pasAPI.approveReferral(item.id, {
        comments: 'Approved from dashboard'
      });

      if (response.data.success) {
        success(`Referral ${item.referral_code} approved successfully`);
        // Reload referrals to update the status
        loadReferrals();
      } else {
        error(response.data.message || 'Failed to approve referral');
      }
    } catch (err) {
      console.error('Failed to approve referral:', err);
      error('Failed to approve referral');
    } finally {
      loading.value = false;
    }
  }
};

const viewPACode = (item) => {
  if (item.id) {
    router.push(`/pas/pa-codes/${item.id}`);
  } else {
    error('PA Code ID not found');
  }
};

const generateUTN = (item) => {
  success(`Generated UTN for PA Code: ${item.pa_code}`);
};

const createReferral = () => {
  showCreateReferralDialog.value = false;
  success('Referral request created successfully');
};

const exportData = () => {
  success('Data exported successfully');
};

// New Methods
const submitReferralForm = () => {
  if (referralFormRef.value) {
    referralFormRef.value.submitForm();
  }
};

const handleReferralSubmit = async (requestData) => {
  console.log('Request submitted:', requestData);

  try {
    // Handle different request types
    switch (requestData.request_type) {
      case 'referral':
        // Referral creation is handled by the form itself
        success('Referral request created successfully');
        break;

      case 'pa_code':
        // Generate PA Code from the selected referral
        if (requestData.referral_id) {
          // Call the PA Code generation API
          const response = await pasAPI.generatePACodeFromReferral(requestData.referral_id, {
            service_type: requestData.referral?.current_service || 'General Service',
            service_description: requestData.referral?.current_service || 'General Service',
            validity_days: 30,
            max_usage: 1,
            issuer_comments: 'Generated from referral via wizard'
          });

          if (response.data.success) {
            success('PA Code generated successfully');
          } else {
            error(response.data.message || 'Failed to generate PA Code');
          }
        }
        break;

      case 'modify_referral':
        // Modification is handled by the wizard itself
        success('Referral modified successfully');
        break;

      default:
        success('Request processed successfully');
    }
  } catch (err) {
    console.error('Error processing request:', err);
    error('Failed to process request');
  }

  showCreateReferralDialog.value = false;
  // Refresh data
  loadStatistics();
  loadReferrals();
};

const loadStatistics = async () => {
  try {
    const [referralStats, paCodeStats] = await Promise.all([
      pasAPI.getReferralStatistics(),
      pasAPI.getPACodeStatistics()
    ]);

    if (referralStats.data.success) {
      const stats = referralStats.data.data;
      totalReferrals.value = stats.total_referrals || 0;
      pendingRequests.value = stats.pending_requests || 0;
      emergencyCases.value = stats.emergency_cases || 0;
    }

    if (paCodeStats.data.success) {
      const stats = paCodeStats.data.data;
      approvedPACodes.value = stats.active_pa_codes || 0;
    }
  } catch (err) {
    console.error('Failed to load statistics:', err);
    error('Failed to load statistics');
  }
};

const loadReferrals = async () => {
  try {
    loading.value = true;
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value,
      severity_level: selectedSeverity.value,
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage
    };

    const response = await pasAPI.getReferrals(params);
    if (response.data.success) {
      const data = response.data.data;
      referrals.value = data.data || [];
      pagination.value.totalItems = data.total || 0;
      pagination.value.page = data.current_page || 1;
    }
  } catch (err) {
    console.error('Failed to load referrals:', err);
    error('Failed to load referrals');
  } finally {
    loading.value = false;
  }
};

const loadPACodes = async () => {
  try {
    loading.value = true;
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value,
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage
    };

    console.log('Loading PA codes with params:', params);
    const response = await pasAPI.getPACodes(params);
    console.log('PA codes response:', response.data);

    if (response.data.success) {
      const data = response.data.data;
      // Handle both paginated and non-paginated responses
      if (data.data) {
        // Paginated response
        paCodes.value = data.data || [];
        pagination.value.totalItems = data.total || 0;
        pagination.value.page = data.current_page || 1;
      } else {
        // Non-paginated response
        paCodes.value = data || [];
        pagination.value.totalItems = data.length || 0;
      }
      console.log('PA codes loaded:', paCodes.value.length, 'items');
    } else {
      console.error('PA codes API returned error:', response.data.message);
      error(response.data.message || 'Failed to load PA codes');
    }
  } catch (err) {
    console.error('Failed to load PA codes:', err);
    error('Failed to load PA codes');
  } finally {
    loading.value = false;
  }
};

// Computed
const filteredReferrals = computed(() => {
  return referrals.value.map(referral => ({
    ...referral,
    enrollee_name: referral.enrollee_full_name,
    referring_facility: referral.referring_facility_name,
    receiving_facility: referral.receiving_facility_name,
    severity: referral.severity_level,
    created_at: new Date(referral.created_at).toLocaleDateString()
  }));
});

const filteredPACodes = computed(() => {
  return paCodes.value.map(paCode => ({
    ...paCode,
    expires_at: paCode.expires_at ? new Date(paCode.expires_at).toLocaleDateString() : 'N/A'
  }));
});

// Watchers
watch(activeTab, (newTab) => {
  if (newTab === 'referrals') {
    loadReferrals();
  } else if (newTab === 'pa-codes') {
    loadPACodes();
  }
});

watch([searchQuery, selectedStatus, selectedSeverity], () => {
  if (activeTab.value === 'referrals') {
    loadReferrals();
  } else if (activeTab.value === 'pa-codes') {
    loadPACodes();
  }
}, { debounce: 300 });

// Lifecycle
onMounted(() => {
  // Check for tab parameter in URL
  if (route.query.tab && ['referrals', 'pa-codes', 'tracking'].includes(route.query.tab)) {
    activeTab.value = route.query.tab;
  }

  loadStatistics();

  // Load data based on active tab
  if (activeTab.value === 'referrals') {
    loadReferrals();
  } else if (activeTab.value === 'pa-codes') {
    loadPACodes();
  }
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
