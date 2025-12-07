<template>
  <div class="admission-management-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white d-flex justify-space-between align-center">
              <div>
                <v-icon left>mdi-hospital-box</v-icon>
                Admission Management
              </div>
              <v-btn color="white" @click="openCreateDialog">
                <v-icon left>mdi-plus</v-icon>
                New Admission
              </v-btn>
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search by admission number or patient"
                    outlined
                    dense
                    prepend-icon="mdi-magnify"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-select
                    v-model="statusFilter"
                    label="Filter by Status"
                    :items="statusOptions"
                    outlined
                    dense
                    clearable
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-btn color="secondary" @click="resetFilters">
                    <v-icon left>mdi-refresh</v-icon>
                    Reset Filters
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Admissions Table -->
              <v-data-table
                :headers="headers"
                :items="filteredAdmissions"
                :loading="loading"
                class="elevation-1"
              >
                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="getStatusColor(item.status)"
                    text-color="white"
                    small
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    small
                    color="primary"
                    @click="viewAdmission(item)"
                  >
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    small
                    color="warning"
                    @click="editAdmission(item)"
                  >
                    <v-icon>mdi-pencil</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Create/Edit Dialog -->
      <v-dialog v-model="showCreateDialog" max-width="600px">
        <v-card>
          <v-card-title>
            {{ editingAdmission ? 'Edit Admission' : 'Create New Admission' }}
          </v-card-title>
          <v-card-text>
            <v-form ref="admissionForm">
              <v-alert
                v-if="eligibleReferrals.length === 0"
                type="warning"
                class="mb-4"
              >
                No patients with an open, UTN-validated referral are available for admission.
              </v-alert>

              <v-select
                v-model="admissionData.referral_id"
                label="Patient (open referral with validated UTN)"
                :items="eligibleReferrals"
                :item-title="referralItemLabel"
                item-value="id"
                outlined
                :rules="[v => !!v || 'Select a patient referral']"
                :disabled="eligibleReferrals.length === 0"
                hint="Only referrals that are approved and have a validated UTN are listed"
                persistent-hint
              >
                <template #item="{ item, props }">
                  <v-list-item v-bind="props">
                    <v-list-item-title>{{ referralItemLabel(item.raw) }}</v-list-item-title>
                    <v-list-item-subtitle>
                      UTN: {{ item.raw.utn || item.raw.utn_number || '—' }} • Status: {{ item.raw.status }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </template>
              </v-select>

              <v-text-field
                v-model="selectedReferralDisplay"
                label="Enrollee"
                outlined
                readonly
                class="mt-3"
                hint="Auto-filled from referral"
                persistent-hint
              />

              <v-text-field
                v-model="selectedFacilityDisplay"
                label="Receiving Facility"
                outlined
                readonly
              />

              <v-text-field
                v-model="admissionData.admission_date"
                label="Admission Date"
                type="date"
                outlined
                required
                :rules="[v => !!v || 'Admission date is required']"
              />
              <v-text-field
                v-model="admissionData.ward_type"
                label="Ward Type"
                outlined
                required
                :rules="[v => !!v || 'Ward type is required']"
              />
              <v-text-field
                v-model="admissionData.principal_diagnosis_icd10"
                label="Principal Diagnosis (ICD-10)"
                outlined
                required
                :rules="[v => !!v || 'Principal diagnosis is required']"
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="secondary" @click="showCreateDialog = false">
              Cancel
            </v-btn>
            <v-btn color="primary" @click="saveAdmission" :loading="loading">
              Save
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '@/js/composables/useToast';
import { useClaimsAPI } from '@/js/composables/useClaimsAPI';
import { useClaimsStore } from '@/js/stores/claimsStore';

const { success: showSuccess, error: showError } = useToast();
const { fetchAdmissions, fetchReferrals, createAdmission, loading } = useClaimsAPI();
const claimsStore = useClaimsStore();

const searchQuery = ref('');
const statusFilter = ref(null);
const showCreateDialog = ref(false);
const editingAdmission = ref(null);
const admissionForm = ref(null);

const statusOptions = [
  { title: 'Active', value: 'ACTIVE' },
  { title: 'Discharged', value: 'DISCHARGED' },
  { title: 'Pending', value: 'PENDING' },
];

const headers = [
  { title: 'Admission Number', value: 'admission_number' },
  { title: 'Patient Name', value: 'patient_name' },
  { title: 'Admission Date', value: 'admission_date' },
  { title: 'Status', value: 'status' },
  { title: 'Actions', value: 'actions' },
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

const filteredAdmissions = computed(() => {
  return claimsStore.admissions.filter(admission => {
    const matchesSearch = !searchQuery.value || 
      admission.admission_number?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      admission.patient_name?.toLowerCase().includes(searchQuery.value.toLowerCase());
    
    const matchesStatus = !statusFilter.value || admission.status === statusFilter.value;
    
    return matchesSearch && matchesStatus;
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

const openCreateDialog = () => {
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
      showSuccess('Admission created successfully');
    }
    showCreateDialog.value = false;
    resetForm();
  } catch (err) {
    showError(err.message || 'Failed to save admission');
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
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
</style>
