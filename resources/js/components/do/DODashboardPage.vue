<template>
  <AdminLayout>
    <div class="tw-p-6">
      <!-- Header -->
      <div class="tw-mb-6">
        <h1 class="tw-text-2xl tw-font-bold tw-text-gray-900">Desk Officer Dashboard</h1>
        <p class="tw-text-gray-600 tw-mt-1">Manage referrals and PA codes for your assigned facilities</p>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-h-64">
        <v-progress-circular indeterminate color="primary" size="64"></v-progress-circular>
      </div>

      <!-- No Facilities Assigned -->
      <div v-else-if="!assignedFacilities.length" class="tw-text-center tw-py-12">
        <v-icon size="64" color="grey-lighten-1" class="tw-mb-4">mdi-hospital-building</v-icon>
        <h3 class="tw-text-xl tw-font-medium tw-text-gray-900 tw-mb-2">No Facilities Assigned</h3>
        <p class="tw-text-gray-600">You have not been assigned to any facilities yet. Please contact your administrator.</p>
      </div>

      <!-- Dashboard Content -->
      <div v-else>
        <!-- Stats Cards -->
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6 tw-mb-8">
          <v-card class="tw-p-4">
            <div class="tw-flex tw-items-center">
              <v-icon color="blue" size="40" class="tw-mr-3">mdi-hospital-building</v-icon>
              <div>
                <p class="tw-text-sm tw-text-gray-600">Assigned Facilities</p>
                <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.total_facilities }}</p>
              </div>
            </div>
          </v-card>

          <v-card class="tw-p-4">
            <div class="tw-flex tw-items-center">
              <v-icon color="green" size="40" class="tw-mr-3">mdi-file-document-multiple</v-icon>
              <div>
                <p class="tw-text-sm tw-text-gray-600">Total Referrals</p>
                <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.total_referrals }}</p>
              </div>
            </div>
          </v-card>

          <v-card class="tw-p-4">
            <div class="tw-flex tw-items-center">
              <v-icon color="orange" size="40" class="tw-mr-3">mdi-clock-outline</v-icon>
              <div>
                <p class="tw-text-sm tw-text-gray-600">Pending Referrals</p>
                <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.pending_referrals }}</p>
              </div>
            </div>
          </v-card>

          <v-card class="tw-p-4">
            <div class="tw-flex tw-items-center">
              <v-icon color="purple" size="40" class="tw-mr-3">mdi-qrcode</v-icon>
              <div>
                <p class="tw-text-sm tw-text-gray-600">Pending UTN Validations</p>
                <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ stats.pending_utn_validations }}</p>
              </div>
            </div>
          </v-card>
        </div>

        <!-- Assigned Facilities -->
        <v-card class="tw-mb-6">
          <v-card-title>
            <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
            Assigned Facilities
          </v-card-title>
          <v-card-text>
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-4">
              <v-card 
                v-for="facility in assignedFacilities" 
                :key="facility.id"
                variant="outlined"
                class="tw-cursor-pointer hover:tw-shadow-md tw-transition-shadow"
                @click="selectFacility(facility)"
              >
                <v-card-text>
                  <div class="tw-flex tw-items-start tw-justify-between">
                    <div class="tw-flex-1">
                      <h4 class="tw-font-medium tw-text-gray-900">{{ facility.name }}</h4>
                      <p class="tw-text-sm tw-text-gray-600 tw-mt-1">{{ facility.hcp_code }}</p>
                      <v-chip 
                        :color="getLevelColor(facility.level_of_care)" 
                        size="small" 
                        class="tw-mt-2"
                      >
                        {{ facility.level_of_care }}
                      </v-chip>
                    </div>
                    <v-icon color="grey-lighten-1">mdi-chevron-right</v-icon>
                  </div>
                </v-card-text>
              </v-card>
            </div>
          </v-card-text>
        </v-card>

        <!-- Tabs for Referrals and PA Codes -->
        <v-card>
          <v-tabs v-model="activeTab" bg-color="primary" dark>
            <v-tab value="referrals">
              <v-icon class="tw-mr-2">mdi-file-document-multiple</v-icon>
              Referrals
            </v-tab>
            <v-tab value="pa-codes">
              <v-icon class="tw-mr-2">mdi-qrcode</v-icon>
              PA Codes
            </v-tab>
            <v-tab value="utn-validation" v-if="hasSecondaryTertiaryFacilities">
              <v-icon class="tw-mr-2">mdi-shield-check</v-icon>
              UTN Validation
            </v-tab>
          </v-tabs>

          <v-tabs-window v-model="activeTab">
            <!-- Referrals Tab -->
            <v-tabs-window-item value="referrals">
              <div class="tw-p-6">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                  <h3 class="tw-text-lg tw-font-medium">Referrals</h3>
                  <div class="tw-flex tw-space-x-2">
                    <v-text-field
                      v-model="referralSearch"
                      placeholder="Search referrals..."
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-magnify"
                      hide-details
                      class="tw-w-64"
                      @input="debouncedSearchReferrals"
                    />
                    <v-select
                      v-model="referralStatusFilter"
                      :items="referralStatusOptions"
                      placeholder="Filter by status"
                      variant="outlined"
                      density="compact"
                      hide-details
                      class="tw-w-48"
                      @update:model-value="fetchReferrals"
                    />
                  </div>
                </div>

                <v-data-table-server
                  :headers="referralHeaders"
                  :items="referrals"
                  :loading="referralsLoading"
                  :items-length="referralsPagination.total"
                  :items-per-page="referralsPagination.per_page"
                  @update:options="updateReferralsOptions"
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

                  <template v-slot:item.severity_level="{ item }">
                    <v-chip
                      :color="getSeverityColor(item.severity_level)"
                      size="small"
                      variant="flat"
                    >
                      {{ item.severity_level }}
                    </v-chip>
                  </template>

                  <template v-slot:item.utn_validated="{ item }">
                    <v-chip
                      :color="item.utn_validated ? 'green' : 'orange'"
                      size="small"
                      variant="flat"
                      v-if="item.utn"
                    >
                      {{ item.utn_validated ? 'Validated' : 'Pending' }}
                    </v-chip>
                    <span v-else class="tw-text-gray-500">N/A</span>
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
                        v-if="item.utn && !item.utn_validated && canValidateUTN(item)"
                        icon
                        size="small"
                        variant="text"
                        color="primary"
                        @click="openUTNValidation(item)"
                      >
                        <v-icon>mdi-shield-check</v-icon>
                      </v-btn>
                    </div>
                  </template>
                </v-data-table-server>
              </div>
            </v-tabs-window-item>

            <!-- PA Codes Tab -->
            <v-tabs-window-item value="pa-codes">
              <div class="tw-p-6">
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-4">
                  <h3 class="tw-text-lg tw-font-medium">PA Codes</h3>
                  <div class="tw-flex tw-space-x-2">
                    <v-text-field
                      v-model="paCodeSearch"
                      placeholder="Search PA codes..."
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-magnify"
                      hide-details
                      class="tw-w-64"
                      @input="debouncedSearchPACodes"
                    />
                    <v-select
                      v-model="paCodeStatusFilter"
                      :items="paCodeStatusOptions"
                      placeholder="Filter by status"
                      variant="outlined"
                      density="compact"
                      hide-details
                      class="tw-w-48"
                      @update:model-value="fetchPACodes"
                    />
                  </div>
                </div>

                <v-data-table-server
                  :headers="paCodeHeaders"
                  :items="paCodes"
                  :loading="paCodesLoading"
                  :items-length="paCodesPagination.total"
                  :items-per-page="paCodesPagination.per_page"
                  @update:options="updatePACodesOptions"
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
                    </div>
                  </template>
                </v-data-table-server>
              </div>
            </v-tabs-window-item>

            <!-- UTN Validation Tab -->
            <v-tabs-window-item value="utn-validation" v-if="hasSecondaryTertiaryFacilities">
              <div class="tw-p-6">
                <h3 class="tw-text-lg tw-font-medium tw-mb-4">UTN Validation</h3>
                <p class="tw-text-gray-600 tw-mb-6">
                  Validate UTN codes for referrals to your secondary/tertiary facilities to enable claim processing.
                </p>
                
                <!-- UTN Validation Form -->
                <v-card variant="outlined" class="tw-p-4">
                  <v-form @submit.prevent="validateUTN">
                    <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
                      <v-text-field
                        v-model="utnValidationForm.utn"
                        label="UTN Code"
                        variant="outlined"
                        required
                        :error-messages="utnValidationErrors.utn"
                      />
                      <v-select
                        v-model="utnValidationForm.referral_id"
                        :items="pendingUTNReferrals"
                        item-title="referral_code"
                        item-value="id"
                        label="Select Referral"
                        variant="outlined"
                        required
                        :error-messages="utnValidationErrors.referral_id"
                      />
                    </div>
                    <v-textarea
                      v-model="utnValidationForm.validation_notes"
                      label="Validation Notes (Optional)"
                      variant="outlined"
                      rows="3"
                      class="tw-mt-4"
                      :error-messages="utnValidationErrors.validation_notes"
                    />
                    <div class="tw-flex tw-justify-end tw-mt-4">
                      <v-btn
                        type="submit"
                        color="primary"
                        :loading="utnValidationLoading"
                      >
                        Validate UTN
                      </v-btn>
                    </div>
                  </v-form>
                </v-card>
              </div>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-card>
      </div>
    </div>
  </AdminLayout>
  <Toast />
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import AdminLayout from '../layout/AdminLayout.vue'
import { doDashboardAPI } from '../../utils/api.js'
import { debounce } from 'lodash'

// Toast
const toast = useToast()

// Loading states
const loading = ref(true)
const referralsLoading = ref(false)
const paCodesLoading = ref(false)
const utnValidationLoading = ref(false)

// Data
const assignedFacilities = ref([])
const stats = ref({
  total_facilities: 0,
  total_referrals: 0,
  pending_referrals: 0,
  total_pa_codes: 0,
  pending_utn_validations: 0
})

// Tabs
const activeTab = ref('referrals')

// Referrals
const referrals = ref([])
const referralSearch = ref('')
const referralStatusFilter = ref('')
const referralsPagination = ref({
  page: 1,
  per_page: 15,
  total: 0
})

// PA Codes
const paCodes = ref([])
const paCodeSearch = ref('')
const paCodeStatusFilter = ref('')
const paCodesPagination = ref({
  page: 1,
  per_page: 15,
  total: 0
})

// UTN Validation
const utnValidationForm = ref({
  utn: '',
  referral_id: '',
  validation_notes: ''
})
const utnValidationErrors = ref({})
const pendingUTNReferrals = ref([])

// Computed
const hasSecondaryTertiaryFacilities = computed(() => {
  return assignedFacilities.value.some(facility =>
    ['Secondary', 'Tertiary'].includes(facility.level_of_care)
  )
})

// Table headers
const referralHeaders = [
  { title: 'Referral Code', key: 'referral_code', sortable: true },
  { title: 'Enrollee', key: 'enrollee_full_name', sortable: true },
  { title: 'Referring Facility', key: 'referring_facility_name', sortable: true },
  { title: 'Receiving Facility', key: 'receiving_facility_name', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Severity', key: 'severity_level', sortable: true },
  { title: 'UTN Status', key: 'utn_validated', sortable: false },
  { title: 'Date', key: 'referral_date', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
]

const paCodeHeaders = [
  { title: 'PA Code', key: 'pa_code', sortable: true },
  { title: 'UTN', key: 'utn', sortable: true },
  { title: 'Enrollee', key: 'enrollee_name', sortable: true },
  { title: 'Facility', key: 'facility_name', sortable: true },
  { title: 'Service', key: 'service_type', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Expiry', key: 'expires_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
]

// Filter options
const referralStatusOptions = [
  { title: 'All Statuses', value: '' },
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Denied', value: 'denied' },
  { title: 'Expired', value: 'expired' }
]

const paCodeStatusOptions = [
  { title: 'All Statuses', value: '' },
  { title: 'Active', value: 'active' },
  { title: 'Used', value: 'used' },
  { title: 'Expired', value: 'expired' },
  { title: 'Cancelled', value: 'cancelled' }
]

// Methods
const fetchDashboardData = async () => {
  try {
    loading.value = true
    const response = await doDashboardAPI.getOverview()

    if (response.data.success) {
      assignedFacilities.value = response.data.data.assigned_facilities
      stats.value = response.data.data.stats

      // Fetch initial data for tabs
      if (assignedFacilities.value.length > 0) {
        await Promise.all([
          fetchReferrals(),
          fetchPACodes(),
          fetchPendingUTNReferrals()
        ])
      }
    }
  } catch (e) {
    console.error('Error fetching dashboard data:', e)
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load dashboard data', life: 3000 })
  } finally {
    loading.value = false
  }
}

const fetchReferrals = async () => {
  try {
    referralsLoading.value = true
    const params = {
      page: referralsPagination.value.page,
      per_page: referralsPagination.value.per_page,
      search: referralSearch.value,
      status: referralStatusFilter.value
    }

    const response = await doDashboardAPI.getReferrals(params)

    if (response.data.success) {
      referrals.value = response.data.data.data
      referralsPagination.value = {
        page: response.data.data.current_page,
        per_page: response.data.data.per_page,
        total: response.data.data.total
      }
    }
  } catch (e) {
    console.error('Error fetching referrals:', e)
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load referrals', life: 3000 })
  } finally {
    referralsLoading.value = false
  }
}

const fetchPACodes = async () => {
  try {
    paCodesLoading.value = true
    const params = {
      page: paCodesPagination.value.page,
      per_page: paCodesPagination.value.per_page,
      search: paCodeSearch.value,
      status: paCodeStatusFilter.value
    }

    const response = await doDashboardAPI.getPACodes(params)

    if (response.data.success) {
      paCodes.value = response.data.data.data
      paCodesPagination.value = {
        page: response.data.data.current_page,
        per_page: response.data.data.per_page,
        total: response.data.data.total
      }
    }
  } catch (e) {
    console.error('Error fetching PA codes:', e)
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load PA codes', life: 3000 })
  } finally {
    paCodesLoading.value = false
  }
}

const fetchPendingUTNReferrals = async () => {
  try {
    const response = await doDashboardAPI.getReferrals({
      utn_pending: true,
      per_page: 100
    })

    if (response.data.success) {
      pendingUTNReferrals.value = response.data.data.data
    }
  } catch (e) {
    console.error('Error fetching pending UTN referrals:', e)
  }
}

const validateUTN = async () => {
  try {
    utnValidationLoading.value = true
    utnValidationErrors.value = {}

    const response = await doDashboardAPI.validateUTN(utnValidationForm.value)

    if (response.data.success) {
      toast.add({ severity: 'success', summary: 'Success', detail: 'UTN validated successfully', life: 3000 })
      utnValidationForm.value = {
        utn: '',
        referral_id: '',
        validation_notes: ''
      }

      // Refresh data
      await Promise.all([
        fetchDashboardData(),
        fetchReferrals(),
        fetchPendingUTNReferrals()
      ])
    }
  } catch (e) {
    console.error('Error validating UTN:', e)

    if (e.response?.status === 422 && e.response?.data?.errors) {
      utnValidationErrors.value = e.response.data.errors
    }

    const message = e.response?.data?.message || 'Failed to validate UTN'
    toast.add({ severity: 'error', summary: 'Error', detail: message, life: 3000 })
  } finally {
    utnValidationLoading.value = false
  }
}

// Utility methods
const selectFacility = (facility) => {
  // Could navigate to facility-specific view or show more details
  console.log('Selected facility:', facility)
}

const viewReferral = (referral) => {
  // Navigate to referral details
  window.open(`/referrals/${referral.referral_code}`, '_blank')
}

const viewPACode = (paCode) => {
  // Navigate to PA code details or show modal
  console.log('View PA code:', paCode)
}

const openUTNValidation = (referral) => {
  utnValidationForm.value.referral_id = referral.id
  activeTab.value = 'utn-validation'
}

const canValidateUTN = (referral) => {
  // Check if the receiving facility is secondary/tertiary and assigned to current user
  const receivingFacility = assignedFacilities.value.find(f => f.id === referral.receiving_facility_id)
  return receivingFacility && ['Secondary', 'Tertiary'].includes(receivingFacility.level_of_care)
}

// Table pagination handlers
const updateReferralsOptions = (options) => {
  referralsPagination.value.page = options.page
  referralsPagination.value.per_page = options.itemsPerPage
  fetchReferrals()
}

const updatePACodesOptions = (options) => {
  paCodesPagination.value.page = options.page
  paCodesPagination.value.per_page = options.itemsPerPage
  fetchPACodes()
}

// Debounced search functions
const debouncedSearchReferrals = debounce(() => {
  referralsPagination.value.page = 1
  fetchReferrals()
}, 500)

const debouncedSearchPACodes = debounce(() => {
  paCodesPagination.value.page = 1
  fetchPACodes()
}, 500)

// Color utility functions
const getLevelColor = (level) => {
  switch (level) {
    case 'Primary': return 'green'
    case 'Secondary': return 'orange'
    case 'Tertiary': return 'red'
    default: return 'grey'
  }
}

const getStatusColor = (status) => {
  switch (status) {
    case 'active':
    case 'approved': return 'green'
    case 'pending': return 'orange'
    case 'used': return 'blue'
    case 'expired':
    case 'denied': return 'red'
    case 'cancelled': return 'grey'
    default: return 'grey'
  }
}

const getSeverityColor = (severity) => {
  switch (severity) {
    case 'emergency': return 'red'
    case 'urgent': return 'orange'
    case 'routine': return 'green'
    default: return 'grey'
  }
}

// Lifecycle
onMounted(() => {
  fetchDashboardData()
})
</script>
