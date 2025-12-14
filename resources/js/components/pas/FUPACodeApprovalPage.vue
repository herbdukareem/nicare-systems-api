<template>
  <AdminLayout>
    <div class="fu-pa-code-approval-page">
      <v-container>
        <v-row>
          <v-col cols="12">
            <v-card>
              <v-card-title class="bg-primary text-white">
                <v-icon left>mdi-check-decagram</v-icon>
                FU-PA Code Approval Management
              </v-card-title>
              <v-card-text>
                <!-- Filters -->
                <v-row class="mb-4">
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model="searchQuery"
                      label="Search by PA Code or Patient"
                      outlined
                      dense
                      prepend-icon="mdi-magnify"
                      clearable
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

                <!-- PA Codes Table -->
                <v-data-table
                  :headers="headers"
                  :items="filteredPACodes"
                  :loading="loading"
                  class="elevation-1"
                >
                  <template v-slot:item.code="{ item }">
                    <v-chip color="primary" small>{{ item.code }}</v-chip>
                  </template>

                  <template v-slot:item.status="{ item }">
                    <v-chip
                      :color="getStatusColor(item.status)"
                      text-color="white"
                      small
                    >
                      {{ item.status }}
                    </v-chip>
                  </template>

                  <template v-slot:item.type="{ item }">
                    <v-chip
                      :color="item.type === 'FFS_TOP_UP' ? 'orange' : 'blue'"
                      text-color="white"
                      small
                    >
                      {{ item.type }}
                    </v-chip>
                  </template>

                  <template v-slot:item.created_at="{ item }">
                    {{ formatDate(item.created_at) }}
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-btn
                     
                      icon
                      size="small"
                      color="primary"
                      @click="viewDetails(item)"
                      title="View Details"
                    >
                      <v-icon>mdi-eye</v-icon>
                    </v-btn>
                    <v-btn
                      v-if="item.status === 'PENDING'"
                      icon
                      size="small"
                      color="success"
                      @click="approvePA(item)"
                      title="Approve"
                    >
                      <v-icon>mdi-check</v-icon>
                    </v-btn>
                    <v-btn
                      v-if="item.status === 'PENDING'"
                      icon
                      size="small"
                      color="error"
                      @click="openRejectDialog(item)"
                      title="Reject"
                    >
                      <v-icon>mdi-close</v-icon>
                    </v-btn>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Details Dialog -->
      <v-dialog v-model="detailsDialog" max-width="1200" scrollable>
        <v-card v-if="selectedPA">
          <v-card-title class="bg-primary text-white d-flex align-center pa-4">
            <v-icon left class="mr-2">mdi-file-document-check</v-icon>
            <span class="text-h6">FU-PA Code Details</span>
            <v-spacer></v-spacer>
            <v-btn icon variant="text" @click="detailsDialog = false" color="white">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </v-card-title>

          <v-card-text class="pa-0">
            <v-container fluid>
              <!-- Header Info -->
              <v-row class="bg-grey-lighten-4 pa-4">
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">PA Code</div>
                    <div class="text-h6 font-weight-bold">{{ selectedPA.code }}</div>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Type</div>
                    <v-chip :color="selectedPA.type === 'FFS_TOP_UP' ? 'orange' : 'blue'" variant="flat" class="mt-1">
                      {{ selectedPA.type }}
                    </v-chip>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Status</div>
                    <v-chip :color="getStatusColor(selectedPA.status)" variant="flat" class="mt-1">
                      <v-icon left size="small">{{ getStatusIcon(selectedPA.status) }}</v-icon>
                      {{ selectedPA.status }}
                    </v-chip>
                  </div>
                </v-col>
                <v-col cols="12" md="3">
                  <div class="detail-item">
                    <div class="text-caption text-grey">Requested</div>
                    <div class="font-weight-medium">{{ formatDate(selectedPA.created_at) }}</div>
                  </div>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Patient Information -->
              <v-row class="pa-4">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-account</v-icon>
                    Patient Information
                  </h3>
                </v-col>
                <v-col cols="12" md="6">
                  <v-list density="compact">
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-account-circle</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Full Name</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedPA.enrollee?.first_name }} {{ selectedPA.enrollee?.last_name }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-card-account-details</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Enrollee ID</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedPA.enrollee?.enrollee_id || 'N/A' }}</v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-col>
                <v-col cols="12" md="6">
                  <v-list density="compact">
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-phone</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Phone Number</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedPA.enrollee?.phone_number || 'N/A' }}</v-list-item-subtitle>
                    </v-list-item>
                    <v-list-item>
                      <template v-slot:prepend>
                        <v-icon color="grey">mdi-email</v-icon>
                      </template>
                      <v-list-item-title class="font-weight-medium">Email</v-list-item-title>
                      <v-list-item-subtitle>{{ selectedPA.enrollee?.email || 'N/A' }}</v-list-item-subtitle>
                    </v-list-item>
                  </v-list>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Facility Information -->
              <v-row class="pa-4">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-hospital-building</v-icon>
                    Facility Information
                  </h3>
                </v-col>
                <v-col cols="12">
                  <v-card variant="outlined">
                    <v-card-title class="bg-blue-lighten-5 text-subtitle-1">
                      <v-icon left color="blue">mdi-hospital</v-icon>
                      Requesting Facility
                    </v-card-title>
                    <v-card-text>
                      <div class="font-weight-bold text-body-1 mb-1">{{ selectedPA.facility?.name || 'N/A' }}</div>
                      <div class="text-caption text-grey">{{ selectedPA.facility?.type || 'N/A' }} Facility</div>
                      <div class="text-caption text-grey mt-1">{{ selectedPA.facility?.address || 'N/A' }}</div>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Service Selection -->
              <v-row class="pa-4" v-if="selectedPA.service_selection_type">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-medical-bag</v-icon>
                    Service Selection
                  </h3>
                </v-col>
                <v-col cols="12">
                  <!-- Bundle Service -->
                  <v-alert
                    v-if="selectedPA.service_selection_type === 'bundle'"
                    type="info"
                    variant="tonal"
                    density="compact"
                  >
                    <div class="d-flex align-center">
                      <v-icon left>mdi-package-variant</v-icon>
                      <div>
                        <div class="font-weight-bold">Bundle Service Selected</div>
                        <div v-if="selectedPA.service_bundle" class="mt-2">
                          <div class="text-subtitle-2">{{ selectedPA.service_bundle.description || selectedPA.service_bundle.name }}</div>
                          <div class="text-caption">Code: {{ selectedPA.service_bundle.code }} | Price: ₦{{ Number(selectedPA.service_bundle.fixed_price).toLocaleString() }}</div>
                          <div class="text-caption" v-if="selectedPA.service_bundle.diagnosis_icd10">ICD-10: {{ selectedPA.service_bundle.diagnosis_icd10 }}</div>
                        </div>
                      </div>
                    </div>
                  </v-alert>

                  <!-- Direct Services (Multiple) -->
                  <div v-if="selectedPA.service_selection_type === 'direct'">
                    <v-alert
                      type="success"
                      variant="tonal"
                      density="compact"
                      class="mb-3"
                    >
                      <div class="font-weight-bold">
                        <v-icon left>mdi-medical-bag</v-icon>
                        Direct Services Selected ({{ getDirectServicesCount(selectedPA) }})
                      </div>
                    </v-alert>

                    <v-list density="compact" class="bg-grey-lighten-5">
                      <v-list-item
                        v-for="(caseRecord, index) in getDirectServices(selectedPA)"
                        :key="index"
                        class="mb-2"
                      >
                        <template v-slot:prepend>
                          <v-avatar :color="getCaseRecordColor(caseRecord.detail_type)" size="32">
                            <v-icon color="white" size="18">{{ getCaseRecordIcon(caseRecord.detail_type) }}</v-icon>
                          </v-avatar>
                        </template>
                        <v-list-item-title class="font-weight-medium">{{ caseRecord.case_name }}</v-list-item-title>
                        <v-list-item-subtitle>
                          <v-chip size="x-small" :color="getCaseRecordColor(caseRecord.detail_type)" variant="flat" class="mr-2">
                            {{ getCaseTypeLabel(caseRecord.detail_type) }}
                          </v-chip>
                          NiCare Code: {{ caseRecord.nicare_code }}
                        </v-list-item-subtitle>
                      </v-list-item>
                    </v-list>
                  </div>
                </v-col>
              </v-row>

              <v-divider v-if="selectedPA.service_selection_type"></v-divider>

              <!-- Clinical Justification -->
              <v-row class="pa-4">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-text-box-outline</v-icon>
                    Clinical Justification
                  </h3>
                </v-col>
                <v-col cols="12">
                  <v-card variant="outlined">
                    <v-card-text>{{ selectedPA.justification || 'No justification provided' }}</v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <v-divider></v-divider>

              <!-- Requested FFS Services -->
              <v-row class="pa-4" v-if="selectedPA.requested_services && selectedPA.requested_services.length > 0">
                <v-col cols="12">
                  <h3 class="text-h6 mb-3">
                    <v-icon color="primary" class="mr-2">mdi-format-list-bulleted</v-icon>
                    Requested FFS Services
                  </h3>
                </v-col>
                <v-col cols="12">
                  <v-table density="compact">
                    <thead>
                      <tr>
                        <th class="text-left">#</th>
                        <th class="text-left">Service</th>
                        <th class="text-center">Quantity</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(service, index) in selectedPA.requested_services" :key="index">
                        <td>{{ index + 1 }}</td>
                        <td>{{ getServiceName(service.case_record_id) }}</td>
                        <td class="text-center">{{ service.quantity }}</td>
                      </tr>
                    </tbody>
                  </v-table>
                </v-col>
              </v-row>
            </v-container>
          </v-card-text>

          <v-divider></v-divider>

          <v-card-actions class="pa-4">
            <v-btn
              color="purple"
              variant="elevated"
              @click="printPASlip(selectedPA)"
            >
              <v-icon left>mdi-printer</v-icon>
              Print Slip
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn
              v-if="selectedPA.status === 'PENDING'"
              color="error"
              variant="outlined"
              @click="openRejectDialogFromDetails"
            >
              <v-icon left>mdi-close-circle</v-icon>
              Reject
            </v-btn>
            <v-btn
              v-if="selectedPA.status === 'PENDING'"
              color="success"
              variant="elevated"
              @click="approvePAFromDetails"
            >
              <v-icon left>mdi-check-circle</v-icon>
              Approve
            </v-btn>
            <v-btn variant="outlined" @click="detailsDialog = false">Close</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Reject Dialog -->
      <v-dialog v-model="rejectDialog" max-width="500">
        <v-card>
          <v-card-title class="bg-error text-white">
            <v-icon left>mdi-close-circle</v-icon>
            Reject FU-PA Code Request
          </v-card-title>
          <v-card-text class="mt-4">
            <p class="mb-4">Are you sure you want to reject this FU-PA Code request?</p>
            <v-textarea
              v-model="rejectionReason"
              label="Rejection Reason *"
              outlined
              rows="4"
              hint="Provide a reason for rejection"
              :rules="[v => !!v || 'Rejection reason is required']"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn @click="rejectDialog = false">Cancel</v-btn>
            <v-btn color="error" @click="confirmReject" :loading="loading">
              Reject
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import api from '@/js/utils/api';
import { useToast } from '@/js/composables/useToast';

const { success: showSuccess, error: showError } = useToast();

// Reactive state
const loading = ref(false);
const paCodes = ref([]);
const caseRecords = ref([]);
const searchQuery = ref('');
const statusFilter = ref(null);
const detailsDialog = ref(false);
const rejectDialog = ref(false);
const selectedPA = ref(null);
const rejectionReason = ref('');

const statusOptions = [
  { title: 'Pending', value: 'PENDING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
];

const headers = [
  { title: 'PA Code', key: 'code', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Patient', key: 'enrollee.full_name', sortable: true },
  { title: 'Facility', key: 'facility.name', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Requested', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

// Computed
const filteredPACodes = computed(() => {
  let filtered = paCodes.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(pa =>
      pa.code.toLowerCase().includes(query) ||
      pa.enrollee?.full_name?.toLowerCase().includes(query)
    );
  }

  if (statusFilter.value) {
    filtered = filtered.filter(pa => pa.status === statusFilter.value);
  }

  return filtered;
});

// Methods
const fetchPACodes = async () => {
  loading.value = true;
  try {
    const response = await api.get('/pas/pa-codes', {
      params: { 
        // type: 'FFS_TOP_UP'
       }
    });
    paCodes.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load FU-PA codes');
  } finally {
    loading.value = false;
  }
};

const fetchCaseRecords = async () => {
  try {
    const response = await api.get('/cases');
    caseRecords.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load case records');
  }
};

const getServiceName = (caseRecordId) => {
  const caseRecord = caseRecords.value.find(c => c.id === caseRecordId);
  return caseRecord ? caseRecord.service_description : 'Unknown Service';
};

const viewDetails = async (pa) => {
  loading.value = true;
  try {
    // Fetch full PA details with relationships
    const response = await api.get(`/pas/pa-codes/${pa.id}`);
    selectedPA.value = response.data.data || response.data;
    detailsDialog.value = true;
  } catch (err) {
    showError('Failed to load PA code details');
  } finally {
    loading.value = false;
  }
};

const openRejectDialogFromDetails = () => {
  detailsDialog.value = false;
  rejectionReason.value = '';
  rejectDialog.value = true;
};

const approvePAFromDetails = async () => {
  detailsDialog.value = false;
  await approvePA(selectedPA.value);
};

const approvePA = async (pa) => {
  if (!confirm(`Are you sure you want to approve PA Code ${pa.code}?`)) {
    return;
  }

  loading.value = true;
  try {
    await api.post(`/pas/pa-codes/${pa.id}/approve`);
    showSuccess(`PA Code ${pa.code} approved successfully`);
    await fetchPACodes();
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to approve PA code';
    showError(message);
  } finally {
    loading.value = false;
  }
};

const openRejectDialog = (pa) => {
  selectedPA.value = pa;
  rejectionReason.value = '';
  rejectDialog.value = true;
};

const confirmReject = async () => {
  if (!rejectionReason.value) {
    showError('Please provide a rejection reason');
    return;
  }

  loading.value = true;
  try {
    await api.post(`/pas/pa-codes/${selectedPA.value.id}/reject`, {
      rejection_reason: rejectionReason.value
    });
    showSuccess(`PA Code ${selectedPA.value.code} rejected`);
    rejectDialog.value = false;
    await fetchPACodes();
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to reject PA code';
    showError(message);
  } finally {
    loading.value = false;
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
};

const getStatusColor = (status) => {
  const colors = {
    'PENDING': 'orange',
    'APPROVED': 'green',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

const getStatusIcon = (status) => {
  const icons = {
    'PENDING': 'mdi-clock-outline',
    'APPROVED': 'mdi-check-circle',
    'REJECTED': 'mdi-close-circle',
  };
  return icons[status] || 'mdi-help-circle';
};

const printPASlip = (pa) => {
  showInfo('Print functionality coming soon!');
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

// Get direct services from PA code
const getDirectServices = (pa) => {
  if (!pa.case_record_ids || pa.case_record_ids.length === 0) {
    return [];
  }

  return caseRecords.value.filter(record => pa.case_record_ids.includes(record.id));
};

const getDirectServicesCount = (pa) => {
  return pa.case_record_ids ? pa.case_record_ids.length : 0;
};

// Get case record icon
const getCaseRecordIcon = (detailType) => {
  const iconMap = {
    'App\\Models\\DrugDetail': 'mdi-pill',
    'App\\Models\\LaboratoryDetail': 'mdi-flask',
    'App\\Models\\ProfessionalServiceDetail': 'mdi-stethoscope',
    'App\\Models\\RadiologyDetail': 'mdi-radioactive',
    'App\\Models\\ConsultationDetail': 'mdi-doctor',
    'App\\Models\\ConsumableDetail': 'mdi-package-variant-closed',
  };
  return iconMap[detailType] || 'mdi-medical-bag';
};

// Get case record color
const getCaseRecordColor = (detailType) => {
  const colorMap = {
    'App\\Models\\DrugDetail': 'blue',
    'App\\Models\\LaboratoryDetail': 'purple',
    'App\\Models\\ProfessionalServiceDetail': 'green',
    'App\\Models\\RadiologyDetail': 'orange',
    'App\\Models\\ConsultationDetail': 'teal',
    'App\\Models\\ConsumableDetail': 'brown',
  };
  return colorMap[detailType] || 'grey';
};

// Get case type label
const getCaseTypeLabel = (detailType) => {
  const labelMap = {
    'App\\Models\\DrugDetail': 'Drug',
    'App\\Models\\LaboratoryDetail': 'Laboratory',
    'App\\Models\\ProfessionalServiceDetail': 'Professional Service',
    'App\\Models\\RadiologyDetail': 'Radiology',
    'App\\Models\\ConsultationDetail': 'Consultation',
    'App\\Models\\ConsumableDetail': 'Consumable',
  };
  return labelMap[detailType] || 'Service';
};

onMounted(async () => {
  await Promise.all([
    fetchPACodes(),
    fetchCaseRecords(),
  ]);
});
</script>

<style scoped>
.fu-pa-code-approval-page {
  min-height: 100vh;
}
</style>


