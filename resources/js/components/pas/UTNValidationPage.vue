<template>
  <AdminLayout>
    <div class="utn-validation-page">
      <v-container fluid>
      <!-- Page Header -->
      <v-row class="mb-4">
        <v-col cols="12">
          <div class="d-flex justify-space-between align-center">
            <div>
              <h1 class="text-h4 font-weight-bold">
                <v-icon size="32" color="primary" class="mr-2">mdi-shield-check</v-icon>
                UTN Validation
              </h1>
              <p class="text-subtitle-1 text-grey mt-2">
                Validate Unique Transaction Numbers for approved referrals before admission
              </p>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- Episode Flow Info -->
      <v-row class="mb-4">
        <v-col cols="12">
          <v-alert
            type="info"
            variant="tonal"
            density="comfortable"
          >
            <div class="d-flex align-center">
              <v-icon start>mdi-information</v-icon>
              <div>
                <strong>Episode Flow:</strong> Referral (Approved) → <strong class="text-primary">UTN Validation</strong> → Admission → FU-PA Code → Claim
                <br>
                <span class="text-caption">UTN must be validated before patient can be admitted to the facility</span>
              </div>
            </div>
          </v-alert>
        </v-col>
      </v-row>

      <!-- Stats Cards -->
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Pending Validation</p>
                  <h3 class="text-h5 font-weight-bold text-warning">{{ stats.pending }}</h3>
                </div>
                <v-icon size="40" color="warning">mdi-clock-alert</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Validated Today</p>
                  <h3 class="text-h5 font-weight-bold text-success">{{ stats.validatedToday }}</h3>
                </div>
                <v-icon size="40" color="success">mdi-check-circle</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Total Validated</p>
                  <h3 class="text-h5 font-weight-bold text-info">{{ stats.totalValidated }}</h3>
                </div>
                <v-icon size="40" color="info">mdi-shield-check</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- UTN Search Card -->
      <v-row>
        <v-col cols="12">
          <v-card elevation="2">
            <v-card-title class="bg-primary text-white">
              <v-icon start>mdi-magnify</v-icon>
              Search and Validate UTN
            </v-card-title>
            <v-card-text class="pt-6">
              <!-- UTN Search Input -->
              <v-row class="mb-4">
                <v-col cols="12" md="9">
                  <v-text-field
                    v-model="utnSearchQuery"
                    label="Enter UTN to validate"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-shield-search"
                    clearable
                    hide-details
                    placeholder="e.g., UTN-2024-001234"
                    @keyup.enter="searchUTN"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-btn
                    color="primary"
                    size="large"
                    block
                    @click="searchUTN"
                    :loading="searching"
                    prepend-icon="mdi-magnify"
                  >
                    Search UTN
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Search Result -->
              <v-row v-if="searchedReferral" class="mt-6" :key="searchedReferral.id">
                <v-col cols="12">
                  <v-alert
                    type="success"
                    variant="tonal"
                    prominent
                    border="start"
                  >
                    <v-row>
                      <v-col cols="12">
                        <h3 class="text-h6 mb-4">
                          <v-icon start>mdi-check-circle</v-icon>
                          Referral Found
                        </h3>
                      </v-col>
                    </v-row>

                    <v-row>
                      <v-col cols="12" md="6">
                        <div class="mb-3">
                          
                          <strong>Patient Name:</strong> {{ searchedReferral.enrollee?.first_name || '' }} {{ searchedReferral.enrollee?.last_name || '' }}
                        </div>
                        <div class="mb-3">
                          <strong>Enrollee ID:</strong> {{ searchedReferral.enrollee?.nicare_number || searchedReferral.enrollee?.enrollee_id || 'N/A' }}
                        </div>
                        <div class="mb-3">
                          <strong>Referral Code:</strong> {{ searchedReferral.referral_code || 'N/A' }}
                        </div>
                        <div class="mb-3">
                          <strong>UTN:</strong>
                          <v-chip color="primary" size="small" class="ml-2">
                            {{ searchedReferral.utn || 'N/A' }}
                          </v-chip>
                        </div>
                      </v-col>
                      <v-col cols="12" md="6">
                        <div class="mb-3">
                          <strong>Referring Facility:</strong> {{ searchedReferral.referring_facility?.name || 'N/A' }}
                        </div>
                        <div class="mb-3">
                          <strong>Receiving Facility:</strong> {{ searchedReferral.receiving_facility?.name || 'N/A' }}
                        </div>
                        <div class="mb-3">
                          <strong>Preliminary Diagnosis:</strong> {{ searchedReferral.preliminary_diagnosis || 'N/A' }}
                        </div>
                        <div class="mb-3">
                          <strong>Status:</strong>
                          <v-chip v-if="searchedReferral.status" :color="getStatusColor(searchedReferral.status)" size="small" class="ml-2">
                            {{ searchedReferral.status }}
                          </v-chip>
                          <span v-else>N/A</span>
                        </div>
                      </v-col>
                    </v-row>

                    <v-row class="mt-4">
                      <v-col cols="12">
                        <v-divider class="mb-4"></v-divider>
                        <div class="d-flex justify-end gap-2">
                          <v-btn
                            color="secondary"
                            variant="outlined"
                            @click="clearSearch"
                          >
                            Clear
                          </v-btn>
                          <v-btn
                            v-if="searchedReferral && !searchedReferral.utn_validated"
                            color="success"
                            @click="validateUTN"
                            :loading="validating"
                            prepend-icon="mdi-check-circle"
                          >
                            Confirm & Validate UTN
                          </v-btn>
                          <v-chip v-else-if="searchedReferral && searchedReferral.utn_validated" color="success" size="large">
                            <v-icon start>mdi-check-circle</v-icon>
                            Already Validated
                          </v-chip>
                        </div>
                      </v-col>
                    </v-row>
                  </v-alert>
                </v-col>
              </v-row>

              <!-- No Result Message -->
              <v-row v-else-if="searchAttempted && !searchedReferral" class="mt-6">
                <v-col cols="12">
                  <v-alert
                    type="warning"
                    variant="tonal"
                    prominent
                  >
                    <v-row>
                      <v-col cols="12" class="text-center">
                        <v-icon size="64" color="warning">mdi-alert-circle-outline</v-icon>
                        <h3 class="text-h6 mt-4">No Referral Found</h3>
                        <p class="text-body-2 mt-2">
                          No approved referral found with UTN "<strong>{{ utnSearchQuery }}</strong>" for your assigned facilities.
                        </p>
                        <p class="text-caption text-grey mt-2">
                          Please verify the UTN and try again.
                        </p>
                      </v-col>
                    </v-row>
                  </v-alert>
                </v-col>
              </v-row>

              <!-- Instructions -->
              <v-row v-else class="mt-6">
                <v-col cols="12">
                  <v-alert
                    type="info"
                    variant="outlined"
                  >
                    <div class="text-center">
                      <v-icon size="48" color="info" class="mb-3">mdi-information-outline</v-icon>
                      <h4 class="text-h6 mb-2">How to Validate UTN</h4>
                      <ol class="text-left mt-4" style="max-width: 600px; margin: 0 auto;">
                        <li class="mb-2">Enter the UTN provided by the patient or referring facility</li>
                        <li class="mb-2">Click "Search UTN" to find the referral</li>
                        <li class="mb-2">Review the referral details to confirm it matches the patient</li>
                        <li class="mb-2">Click "Confirm & Validate UTN" to mark as validated</li>
                        <li>Patient can now be admitted to your facility</li>
                      </ol>
                    </div>
                  </v-alert>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>


    </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { doDashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';
import AdminLayout from '../layout/AdminLayout.vue';

const { success: showSuccess, error: showError } = useToast();

// State
const loading = ref(false);
const searching = ref(false);
const validating = ref(false);
const referrals = ref([]);
const utnSearchQuery = ref('');
const searchedReferral = ref(null);
const searchAttempted = ref(false);

// Computed stats
const stats = computed(() => {
  const pending = referrals.value.filter(r => !r.utn_validated).length;
  const validated = referrals.value.filter(r => r.utn_validated).length;
  const today = new Date().toISOString().split('T')[0];
  const validatedToday = referrals.value.filter(r =>
    r.utn_validated && r.utn_validated_at?.startsWith(today)
  ).length;

  return {
    pending,
    validatedToday,
    totalValidated: validated,
  };
});

// Search UTN
const searchUTN = async () => {
  if (!utnSearchQuery.value || !utnSearchQuery.value.trim()) {
    showError('Please enter a UTN to search');
    return;
  }

  searching.value = true;
  searchAttempted.value = false; // Reset first

  // Use nextTick to ensure Vue has processed the state change
  await nextTick();

  searchedReferral.value = null;
  searchAttempted.value = true;

  try {
    const response = await doDashboardAPI.getReferrals({
      status: 'approved',
      utn: utnSearchQuery.value.trim(),
    });

    const data = response.data?.data?.data || response.data?.data || response.data || [];
    console.log(data)

    if (Array.isArray(data) && data.length > 0) {
      searchedReferral.value = data[0];
    } else if (data && !Array.isArray(data)) {
      searchedReferral.value = data;
    } else {
      searchedReferral.value = null;
    }
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to search UTN');
    console.error('UTN Search Error:', err);
    searchedReferral.value = null;
  } finally {
    searching.value = false;
  }
};

// Clear search
const clearSearch = () => {
  utnSearchQuery.value = '';
  searchedReferral.value = null;
  searchAttempted.value = false;
};

// Validate UTN
const validateUTN = async () => {
  if (!searchedReferral.value) return;

  validating.value = true;
  try {
    await doDashboardAPI.validateUTN({
      utn: searchedReferral.value.utn,
      referral_id: searchedReferral.value.id,
      validation_notes: 'UTN validated by facility',
    });

    showSuccess('UTN validated successfully! Patient can now be admitted.');

    // Update the searched referral
    searchedReferral.value.utn_validated = true;
    searchedReferral.value.utn_validated_at = new Date().toISOString();

    // Refresh stats
    await fetchReferrals();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to validate UTN');
    console.error(err);
  } finally {
    validating.value = false;
  }
};

// Fetch referrals for stats only
const fetchReferrals = async () => {
  loading.value = true;
  try {
    const response = await doDashboardAPI.getReferrals({
      status: 'approved',
      with_utn: true,
    });
    referrals.value = response.data?.data || response.data || [];
  } catch (err) {
    console.error('Failed to fetch referrals for stats:', err);
  } finally {
    loading.value = false;
  }
};

// Helper functions
const getStatusColor = (status) => {
  const colors = {
    'PENDING': 'orange',
    'APPROVED': 'green',
    'DENIED': 'red',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

const getStatusIcon = (status) => {
  const icons = {
    'PENDING': 'mdi-clock-outline',
    'APPROVED': 'mdi-check-circle',
    'DENIED': 'mdi-close-circle',
    'REJECTED': 'mdi-close-circle',
  };
  return icons[status] || 'mdi-help-circle';
};

const getSeverityColor = (severity) => {
  const colors = {
    'Routine': 'blue',
    'Urgent/Expidited': 'orange',
    'Emergency': 'red',
  };
  return colors[severity] || 'gray';
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// Lifecycle
onMounted(() => {
  fetchReferrals();
});
</script>

<style scoped>
.utn-validation-page {
  padding: 20px 0;
}

.stat-card {
  border-left: 4px solid;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-card:nth-child(1) {
  border-left-color: rgb(var(--v-theme-warning));
}

.stat-card:nth-child(2) {
  border-left-color: rgb(var(--v-theme-success));
}

.stat-card:nth-child(3) {
  border-left-color: rgb(var(--v-theme-info));
}
</style>

