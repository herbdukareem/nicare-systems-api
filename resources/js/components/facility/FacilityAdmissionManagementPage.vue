<template>
  <AdminLayout>
    <div class="facility-admission-page">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-4">
          <v-col cols="12">
            <div class="d-flex justify-space-between align-center">
              <div>
                <h1 class="text-h4 font-weight-bold">
                  <v-icon size="32" color="primary" class="mr-2">mdi-hospital-box</v-icon>
                  Admission Management
                </h1>
                <p class="text-subtitle-1 text-grey mt-2">
                  Create admissions from validated UTNs and manage patient episodes
                </p>
              </div>
              <v-btn
                color="primary"
                size="large"
                @click="createDialog = true"
                prepend-icon="mdi-plus"
              >
                New Admission
              </v-btn>
            </div>
          </v-col>
        </v-row>

        <!-- Episode Workflow Info -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-alert
              type="info"
              variant="tonal"
              density="comfortable"
            >
              <div class="d-flex align-center">
                <v-icon size="24" class="mr-3">mdi-information</v-icon>
                <div>
                  <strong>Episode Workflow:</strong> Referral (Approved) → UTN Validation → <strong class="text-primary">Admission</strong> → FU-PA Code → Claim
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
                    <p class="text-caption text-grey mb-1">Active Admissions</p>
                    <h3 class="text-h5 font-weight-bold text-success">{{ stats.active }}</h3>
                  </div>
                  <v-icon size="40" color="success">mdi-bed</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="4">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Discharged</p>
                    <h3 class="text-h5 font-weight-bold text-info">{{ stats.discharged }}</h3>
                  </div>
                  <v-icon size="40" color="info">mdi-exit-run</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="4">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Total Admissions</p>
                    <h3 class="text-h5 font-weight-bold">{{ stats.total }}</h3>
                  </div>
                  <v-icon size="40" color="primary">mdi-hospital-building</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Admissions List -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="d-flex justify-space-between align-center">
                <span>Admissions</span>
                <v-chip color="primary" variant="outlined">{{ filteredAdmissions.length }} records</v-chip>
              </v-card-title>

              <v-card-text>
                <!-- Filters -->
                <v-row class="mb-4">
                  <v-col cols="12" md="6">
                    <v-text-field
                      v-model="searchQuery"
                      label="Search by admission code, UTN, or patient name"
                      variant="outlined"
                      density="comfortable"
                      prepend-inner-icon="mdi-magnify"
                      clearable
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" md="3">
                    <v-select
                      v-model="statusFilter"
                      :items="statusOptions"
                      label="Status"
                      variant="outlined"
                      density="comfortable"
                      clearable
                    ></v-select>
                  </v-col>
                  <v-col cols="12" md="3">
                    <v-btn
                      color="secondary"
                      variant="outlined"
                      block
                      @click="resetFilters"
                    >
                      Reset Filters
                    </v-btn>
                  </v-col>
                </v-row>

                <!-- Data Table -->
                <v-data-table
                  :headers="headers"
                  :items="filteredAdmissions"
                  :loading="loading"
                  :items-per-page="15"
                  class="elevation-1"
                >
                  <template #item.admission_code="{ item }">
                    <strong class="text-primary">{{ item.admission_code }}</strong>
                  </template>

                  <template #item.patient="{ item }">
                    <div>
                      <div class="font-weight-medium">{{ item.enrollee?.first_name }} {{ item.enrollee?.last_name }}</div>
                      <div class="text-caption text-grey">{{ item.nicare_number }}</div>
                    </div>
                  </template>

                  <template #item.utn="{ item }">
                    <v-chip size="small" color="primary" variant="outlined">
                      {{ item.referral?.utn || 'N/A' }}
                    </v-chip>
                  </template>

                  <template #item.admission_date="{ item }">
                    {{ formatDate(item.admission_date) }}
                  </template>

                  <template #item.status="{ item }">
                    <v-chip :color="getStatusColor(item.status)" size="small">
                      {{ item.status }}
                    </v-chip>
                  </template>

                  <template #item.actions="{ item }">
                    <v-btn
                      icon="mdi-eye"
                      size="small"
                      variant="text"
                      color="primary"
                      @click="viewAdmission(item)"
                    ></v-btn>
                    <v-btn
                      v-if="item.status === 'active'"
                      icon="mdi-exit-run"
                      size="small"
                      variant="text"
                      color="warning"
                      @click="openDischargeDialog(item)"
                    ></v-btn>
                  </template>

                  <template #no-data>
                    <div class="text-center py-8">
                      <v-icon size="64" color="grey">mdi-hospital-box-outline</v-icon>
                      <p class="text-h6 text-grey mt-4">No admissions found</p>
                      <p class="text-body-2 text-grey">Create your first admission from a validated UTN</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Create Admission Dialog -->
      <v-dialog v-model="createDialog" max-width="800px" persistent>
        <v-card>
          <v-card-title class="bg-primary text-white">
            <v-icon class="mr-2">mdi-plus-circle</v-icon>
            Create New Admission
          </v-card-title>

          <v-card-text class="pt-6">
            <v-form ref="admissionForm" @submit.prevent="createAdmission">
              <!-- Step 1: Select Validated UTN -->
              <v-row>
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">Step 1: Select Validated UTN</h3>
                  <v-autocomplete
                    v-model="formData.referral_id"
                    :items="validatedReferrals"
                    :loading="loadingReferrals"
                    item-title="utn"
                    item-value="id"
                    label="Select UTN (Validated Referrals Only)"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-shield-check"
                    :rules="[v => !!v || 'UTN is required']"
                    @update:model-value="onReferralSelected"
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template #prepend>
                          <v-icon color="success">mdi-check-circle</v-icon>
                        </template>
                        <template #title>
                          <strong>{{ item.raw.utn }}</strong>
                        </template>
                        <template #subtitle>
                          {{ item.raw.enrollee?.first_name }} {{ item.raw.enrollee?.last_name }} - {{ item.raw.preliminary_diagnosis }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>
              </v-row>

              <!-- Patient Details (Auto-filled) -->
              <v-row v-if="selectedReferral">
                <v-col cols="12">
                  <v-divider class="my-4"></v-divider>
                  <h3 class="text-h6 mb-3">Patient Information</h3>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedReferral.enrollee?.first_name + ' ' + selectedReferral.enrollee?.last_name"
                    label="Patient Name"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-account"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedReferral.nicare_number"
                    label="NiCare Number"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-card-account-details"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    :model-value="selectedReferral.preliminary_diagnosis"
                    label="Preliminary Diagnosis"
                    variant="outlined"
                    density="comfortable"
                    readonly
                    prepend-inner-icon="mdi-stethoscope"
                  ></v-text-field>
                </v-col>
              </v-row>

              <!-- Admission Details -->
              <v-row v-if="selectedReferral">
                <v-col cols="12">
                  <v-divider class="my-4"></v-divider>
                  <h3 class="text-h6 mb-3">Step 2: Admission Details</h3>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="formData.admission_date"
                    label="Admission Date"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    :rules="[v => !!v || 'Admission date is required']"
                    prepend-inner-icon="mdi-calendar"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="formData.ward_type"
                    :items="wardTypes"
                    label="Ward Type"
                    variant="outlined"
                    density="comfortable"
                    :rules="[v => !!v || 'Ward type is required']"
                    prepend-inner-icon="mdi-bed"
                  ></v-select>
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn
              color="grey"
              variant="text"
              @click="closeCreateDialog"
              :disabled="submitting"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              variant="elevated"
              @click="createAdmission"
              :loading="submitting"
              :disabled="!formData.referral_id || !formData.admission_date || !formData.ward_type"
            >
              Create Admission
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const { success: showSuccess, error: showError } = useToast();

// State
const loading = ref(false);
const loadingReferrals = ref(false);
const submitting = ref(false);
const createDialog = ref(false);
const admissionForm = ref(null);

const admissions = ref([]);
const validatedReferrals = ref([]);
const selectedReferral = ref(null);

const searchQuery = ref('');
const statusFilter = ref(null);

const formData = ref({
  referral_id: null,
  admission_date: new Date().toISOString().split('T')[0],
  ward_type: null,
  principal_diagnosis_icd10: '',
});

// Options
const statusOptions = ['active', 'discharged'];
const wardTypes = ['General Ward', 'ICU', 'HDU', 'Private Ward', 'Isolation Ward', 'Maternity Ward', 'Pediatric Ward'];

// Headers
const headers = [
  { title: 'Admission Code', key: 'admission_code', sortable: true },
  { title: 'Patient', key: 'patient', sortable: false },
  { title: 'UTN', key: 'utn', sortable: false },
  { title: 'Admission Date', key: 'admission_date', sortable: true },
  { title: 'Ward Type', key: 'ward_type', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

// Computed
const stats = computed(() => {
  return {
    total: admissions.value.length,
    active: admissions.value.filter(a => a.status === 'active').length,
    discharged: admissions.value.filter(a => a.status === 'discharged').length,
  };
});

const filteredAdmissions = computed(() => {
  let filtered = admissions.value;

  if (searchQuery.value) {
    const search = searchQuery.value.toLowerCase();
    filtered = filtered.filter(a =>
      a.admission_code?.toLowerCase().includes(search) ||
      a.nicare_number?.toLowerCase().includes(search) ||
      a.referral?.utn?.toLowerCase().includes(search) ||
      `${a.enrollee?.first_name} ${a.enrollee?.last_name}`.toLowerCase().includes(search)
    );
  }

  if (statusFilter.value) {
    filtered = filtered.filter(a => a.status === statusFilter.value);
  }

  return filtered;
});

// Methods
const fetchAdmissions = async () => {
  loading.value = true;
  try {
    const response = await api.get('/claims-automation/admissions');
    admissions.value = response.data?.data?.data || response.data?.data || response.data || [];
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch admissions');
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const fetchValidatedReferrals = async () => {
  loadingReferrals.value = true;
  try {
    const response = await api.get('/do-dashboard/referrals', {
      params: {
        status: 'approved',
        utn_validated: true,
      }
    });
    validatedReferrals.value = response.data?.data?.data || response.data?.data || response.data || [];
  } catch (err) {
    showError('Failed to fetch validated referrals');
    console.error(err);
  } finally {
    loadingReferrals.value = false;
  }
};

const onReferralSelected = (referralId) => {
  selectedReferral.value = validatedReferrals.value.find(r => r.id === referralId);
  if (selectedReferral.value) {
    formData.value.principal_diagnosis_icd10 = selectedReferral.value.preliminary_diagnosis || '';
  }
};

const createAdmission = async () => {
  const { valid } = await admissionForm.value?.validate();
  if (!valid) {
    showError('Please fill in all required fields');
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      referral_id: formData.value.referral_id,
      admission_date: formData.value.admission_date,
      ward_type: formData.value.ward_type,
      principal_diagnosis_icd10: formData.value.principal_diagnosis_icd10,
    };

    await api.post('/claims-automation/admissions', payload);
    showSuccess('Admission created successfully! You can now request FU-PA codes for this episode.');
    closeCreateDialog();
    await fetchAdmissions();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to create admission');
    console.error(err);
  } finally {
    submitting.value = false;
  }
};

const closeCreateDialog = () => {
  createDialog.value = false;
  selectedReferral.value = null;
  formData.value = {
    referral_id: null,
    admission_date: new Date().toISOString().split('T')[0],
    ward_type: null,
    principal_diagnosis_icd10: '',
  };
  admissionForm.value?.reset();
};

const viewAdmission = (admission) => {
  // Navigate to admission detail page or show details dialog
  console.log('View admission:', admission);
};

const openDischargeDialog = (admission) => {
  // Open discharge dialog
  console.log('Discharge admission:', admission);
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const getStatusColor = (status) => {
  const colors = {
    'active': 'success',
    'discharged': 'info',
  };
  return colors[status] || 'grey';
};

onMounted(async () => {
  await Promise.all([
    fetchAdmissions(),
    fetchValidatedReferrals(),
  ]);
});
</script>

<style scoped>
.facility-admission-page {
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
  border-left-color: rgb(var(--v-theme-success));
}

.stat-card:nth-child(2) {
  border-left-color: rgb(var(--v-theme-info));
}

.stat-card:nth-child(3) {
  border-left-color: rgb(var(--v-theme-primary));
}
</style>

