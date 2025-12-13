<template>
  <AdminLayout>
    <div class="admission-management-page">
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
                Manage patient admissions from approved referrals with validated UTN
              </p>
            </div>
            <v-btn
              color="primary"
              size="large"
              @click="openCreateDialog"
              prepend-icon="mdi-plus"
            >
              New Admission
            </v-btn>
          </div>
        </v-col>
      </v-row>

      <!-- Stats Cards -->
      <v-row class="mb-4">
        <v-col cols="12" md="3">
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
        <v-col cols="12" md="3">
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
        <v-col cols="12" md="3">
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
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Pending</p>
                  <h3 class="text-h5 font-weight-bold text-warning">{{ stats.pending }}</h3>
                </div>
                <v-icon size="40" color="warning">mdi-clock-outline</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Main Content Card -->
      <v-row>
        <v-col cols="12">
          <v-card elevation="2">
            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search by admission #, UTN, or patient"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    hide-details
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="statusFilter"
                    label="Filter by Status"
                    :items="statusOptions"
                    variant="outlined"
                    density="comfortable"
                    clearable
                    hide-details
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-text-field
                    v-model="dateFilter"
                    label="Filter by Date"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    clearable
                    hide-details
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-btn
                    color="secondary"
                    variant="outlined"
                    block
                    @click="resetFilters"
                    prepend-icon="mdi-refresh"
                  >
                    Reset
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Admissions Table -->
              <v-data-table
                :headers="headers"
                :items="filteredAdmissions"
                :loading="loading"
                class="elevation-0"
                item-value="id"
              >
                <template v-slot:item.utn="{ item }">
                  <v-chip
                    size="small"
                    color="primary"
                    variant="outlined"
                  >
                    <v-icon start size="small">mdi-barcode</v-icon>
                    {{ item.utn || item.referral?.utn || 'N/A' }}
                  </v-chip>
                </template>

                <template v-slot:item.patient_name="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.patient_name || item.enrollee?.first_name + ' ' + item.enrollee?.last_name || 'N/A' }}</div>
                    <div class="text-caption text-grey">{{ item.enrollee?.nicare_number || item.enrollee?.enrollee_id || '' }}</div>
                  </div>
                </template>

                <template v-slot:item.admission_date="{ item }">
                  {{ formatDate(item.admission_date) }}
                </template>

                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getStatusColor(item.status)"
                    size="small"
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon="mdi-eye"
                    size="small"
                    color="primary"
                    variant="text"
                    @click="viewAdmission(item)"
                  >
                  </v-btn>
                  <v-btn
                    icon="mdi-pencil"
                    size="small"
                    color="warning"
                    variant="text"
                    @click="editAdmission(item)"
                  >
                  </v-btn>
                </template>

                <template v-slot:no-data>
                  <div class="text-center py-8">
                    <v-icon size="64" color="grey-lighten-2">mdi-hospital-box-outline</v-icon>
                    <p class="text-h6 text-grey mt-4">No admissions found</p>
                    <p class="text-body-2 text-grey">Create a new admission from an approved referral with validated UTN</p>
                  </div>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Create/Edit Dialog -->
      <!-- Create/Edit Dialog -->
      <v-dialog v-model="showCreateDialog" max-width="700px" persistent>
        <v-card>
          <v-card-title class="bg-primary text-white">
            <v-icon start>mdi-hospital-box-outline</v-icon>
            {{ editingAdmission ? 'Edit Admission' : 'Create New Admission' }}
          </v-card-title>
          <v-card-text class="pt-4">
            <!-- Episode Flow Info -->
            <v-alert
              type="info"
              variant="tonal"
              class="mb-4"
              density="compact"
            >
              <div class="d-flex align-center">
                <v-icon start>mdi-information</v-icon>
                <span class="text-body-2">
                  <strong>Episode Flow:</strong> Referral → Admission → FU-PA Code → Claim
                </span>
              </div>
            </v-alert>

            <v-alert
              v-if="eligibleReferrals.length === 0"
              type="warning"
              variant="tonal"
              class="mb-4"
            >
              <div class="text-body-2">
                <strong>No eligible referrals found.</strong><br>
                Patients must have an approved referral with validated UTN before admission.
              </div>
            </v-alert>

            <v-form ref="admissionForm">
              <v-select
                v-model="admissionData.referral_id"
                label="Select Patient Referral *"
                :items="eligibleReferrals"
                :item-title="referralItemLabel"
                item-value="id"
                variant="outlined"
                :rules="[v => !!v || 'Select a patient referral']"
                :disabled="eligibleReferrals.length === 0"
                hint="Only approved referrals with validated UTN are shown"
                persistent-hint
                class="mb-2"
              >
                <template #prepend-inner>
                  <v-icon>mdi-account-search</v-icon>
                </template>
                <template #item="{ item, props }">
                  <v-list-item v-bind="props">
                    <template #prepend>
                      <v-icon color="primary">mdi-account-circle</v-icon>
                    </template>
                    <v-list-item-title>{{ referralItemLabel(item.raw) }}</v-list-item-title>
                    <v-list-item-subtitle>
                      <v-chip size="x-small" color="primary" variant="outlined" class="mr-2">
                        UTN: {{ item.raw.utn || item.raw.utn_number || '—' }}
                      </v-chip>
                      <v-chip size="x-small" color="success">{{ item.raw.status }}</v-chip>
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-select>

              <v-text-field
                v-model="selectedReferralDisplay"
                label="Enrollee Information"
                variant="outlined"
                readonly
                class="mb-2"
                hint="Auto-filled from selected referral"
                persistent-hint
              >
                <template #prepend-inner>
                  <v-icon>mdi-account</v-icon>
                </template>
              </v-text-field>

              <v-text-field
                v-model="selectedFacilityDisplay"
                label="Receiving Facility"
                variant="outlined"
                readonly
                class="mb-2"
              >
                <template #prepend-inner>
                  <v-icon>mdi-hospital-building</v-icon>
                </template>
              </v-text-field>

              <v-text-field
                v-model="admissionData.admission_date"
                label="Admission Date *"
                type="date"
                variant="outlined"
                required
                :rules="[v => !!v || 'Admission date is required']"
                class="mb-2"
              >
                <template #prepend-inner>
                  <v-icon>mdi-calendar</v-icon>
                </template>
              </v-text-field>

              <v-text-field
                v-model="admissionData.ward_type"
                label="Ward Type *"
                variant="outlined"
                required
                :rules="[v => !!v || 'Ward type is required']"
                hint="e.g., General Ward, ICU, Maternity, etc."
                persistent-hint
                class="mb-2"
              >
                <template #prepend-inner>
                  <v-icon>mdi-bed</v-icon>
                </template>
              </v-text-field>

              <v-text-field
                v-model="admissionData.principal_diagnosis_icd10"
                label="Principal Diagnosis (ICD-10) *"
                variant="outlined"
                required
                :rules="[v => !!v || 'Principal diagnosis is required']"
                hint="Enter the ICD-10 code for the principal diagnosis"
                persistent-hint
              >
                <template #prepend-inner>
                  <v-icon>mdi-medical-bag</v-icon>
                </template>
              </v-text-field>
            </v-form>
          </v-card-text>
          <v-divider></v-divider>
          <v-card-actions class="pa-4">
            <v-spacer></v-spacer>
            <v-btn
              color="secondary"
              variant="outlined"
              @click="closeDialog"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              @click="saveAdmission"
              :loading="loading"
              :disabled="eligibleReferrals.length === 0"
            >
              <v-icon start>mdi-content-save</v-icon>
              Save Admission
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '@/js/composables/useToast';
import { useClaimsAPI } from '@/js/composables/useClaimsAPI';
import { useClaimsStore } from '@/js/stores/claimsStore';
import AdminLayout from '../../layout/AdminLayout.vue';

const { success: showSuccess, error: showError } = useToast();
const { fetchAdmissions, fetchReferrals, createAdmission, loading } = useClaimsAPI();
const claimsStore = useClaimsStore();

const searchQuery = ref('');
const statusFilter = ref(null);
const dateFilter = ref(null);
const showCreateDialog = ref(false);
const editingAdmission = ref(null);
const admissionForm = ref(null);

const statusOptions = [
  { title: 'Active', value: 'ACTIVE' },
  { title: 'Discharged', value: 'DISCHARGED' },
  { title: 'Pending', value: 'PENDING' },
];

const headers = [
  { title: 'Admission #', value: 'admission_number', key: 'admission_number' },
  { title: 'UTN', value: 'utn', key: 'utn' },
  { title: 'Patient Name', value: 'patient_name', key: 'patient_name' },
  { title: 'Admission Date', value: 'admission_date', key: 'admission_date' },
  { title: 'Ward Type', value: 'ward_type', key: 'ward_type' },
  { title: 'Status', value: 'status', key: 'status' },
  { title: 'Actions', value: 'actions', key: 'actions', sortable: false },
];

const admissionData = ref({
  referral_id: null,
  admission_date: new Date().toISOString().slice(0, 10),
  ward_type: '',
  principal_diagnosis_icd10: '',
});

const eligibleReferrals = computed(() => {
  return claimsStore.referrals.filter((referral) => {
    const status = (referral.status || '').toLowerCase();
    return referral.utn_validated && (status === 'approved' || status === 'approved/referral');
  });
});

const selectedReferral = computed(() => {
  return eligibleReferrals.value.find((r) => r.id === admissionData.value.referral_id) || null;
});

const selectedReferralDisplay = computed(() => {
  const enrollee = selectedReferral.value?.enrollee;
  if (!enrollee) return '—';
  return `${enrollee.first_name || ''} ${enrollee.last_name || ''} (${enrollee.enrollee_id || enrollee.nicare_number || 'N/A'})`.trim();
});

const selectedFacilityDisplay = computed(() => {
  const facility = selectedReferral.value?.receiving_facility || selectedReferral.value?.facility;
  return facility?.name || '—';
});

const stats = computed(() => {
  const admissions = claimsStore.admissions || [];
  return {
    total: admissions.length,
    active: admissions.filter(a => a.status?.toLowerCase() === 'active').length,
    discharged: admissions.filter(a => a.status?.toLowerCase() === 'discharged').length,
    pending: admissions.filter(a => a.status?.toLowerCase() === 'pending').length,
  };
});

const filteredAdmissions = computed(() => {
  return claimsStore.admissions.filter(admission => {
    const matchesSearch = !searchQuery.value ||
      admission.admission_number?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      admission.patient_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      admission.utn?.toLowerCase().includes(searchQuery.value.toLowerCase());

    const matchesStatus = !statusFilter.value || admission.status?.toLowerCase() === statusFilter.value.toLowerCase();

    const matchesDate = !dateFilter.value || admission.admission_date?.startsWith(dateFilter.value);

    return matchesSearch && matchesStatus && matchesDate;
  });
});

onMounted(async () => {
  try {
    await fetchAdmissions();
    await fetchReferrals({ status: 'approved', utn_validated: true });
  } catch (err) {
    showError('Failed to load admissions or referrals');
  }
});

const getStatusColor = (status) => {
  const colors = {
    'ACTIVE': 'green',
    'DISCHARGED': 'gray',
    'PENDING': 'orange',
  };
  return colors[status] || 'blue';
};

const viewAdmission = (admission) => {
  claimsStore.setCurrentAdmission(admission);
  // Navigate to detail page
};

const openCreateDialog = async () => {
  resetForm();
  // Fetch eligible referrals when opening dialog
  try {
    await fetchReferrals({ status: 'approved', utn_validated: true });
  } catch (err) {
    console.error('Failed to fetch referrals:', err);
  }
  showCreateDialog.value = true;
};

const editAdmission = (admission) => {
  editingAdmission.value = admission;
  admissionData.value = { ...admission };
  showCreateDialog.value = true;
};

const saveAdmission = async () => {
  const validation = await admissionForm.value?.validate();
  if (!validation?.valid) return;

  try {
    if (editingAdmission.value) {
      // Update existing
      claimsStore.updateAdmission(editingAdmission.value.id, admissionData.value);
      showSuccess('Admission updated successfully');
    } else {
      // Create new
      await createAdmission({
        referral_id: admissionData.value.referral_id,
        admission_date: admissionData.value.admission_date,
        ward_type: admissionData.value.ward_type,
        principal_diagnosis_icd10: admissionData.value.principal_diagnosis_icd10,
      });
      showSuccess('Admission created successfully. Episode started!');
    }
    showCreateDialog.value = false;
    resetForm();
    // Refresh admissions list
    await fetchAdmissions();
  } catch (err) {
    showError(err.response?.data?.message || err.message || 'Failed to save admission');
  }
};

const closeDialog = () => {
  showCreateDialog.value = false;
  resetForm();
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  dateFilter.value = null;
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  try {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  } catch (e) {
    return date;
  }
};

const resetForm = () => {
  editingAdmission.value = null;
  admissionData.value = {
    referral_id: null,
    admission_date: new Date().toISOString().slice(0, 10),
    ward_type: '',
    principal_diagnosis_icd10: '',
  };
};

const referralItemLabel = (referral) => {
  const enrollee = referral?.enrollee || {};
  const fullName = `${enrollee.first_name || ''} ${enrollee.last_name || ''}`.trim();
  const nicareNumber = enrollee.enrollee_id || enrollee.nicare_number || 'N/A';
  const utn = referral.utn || referral.utn_number || referral.referral_code || 'UTN';
  return `${fullName || 'Unknown Patient'} (${nicareNumber}) • UTN: ${utn}`;
};
</script>

<style scoped>
.admission-management-page {
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
  border-left-color: rgb(var(--v-theme-primary));
}

.stat-card:nth-child(2) {
  border-left-color: rgb(var(--v-theme-success));
}

.stat-card:nth-child(3) {
  border-left-color: rgb(var(--v-theme-info));
}

.stat-card:nth-child(4) {
  border-left-color: rgb(var(--v-theme-warning));
}
</style>
