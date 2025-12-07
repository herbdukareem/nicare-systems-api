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
                      v-if="item.status === 'PENDING'"
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
      <v-dialog v-model="detailsDialog" max-width="800">
        <v-card v-if="selectedPA">
          <v-card-title class="bg-primary text-white">
            <v-icon left>mdi-information</v-icon>
            FU-PA Code Details
          </v-card-title>
          <v-card-text class="mt-4">
            <v-row>
              <v-col cols="12" md="6">
                <p><strong>PA Code:</strong> {{ selectedPA.code }}</p>
                <p><strong>Type:</strong> {{ selectedPA.type }}</p>
                <p><strong>Status:</strong> <v-chip :color="getStatusColor(selectedPA.status)" small>{{ selectedPA.status }}</v-chip></p>
              </v-col>
              <v-col cols="12" md="6">
                <p><strong>Patient:</strong> {{ selectedPA.enrollee?.full_name || 'N/A' }}</p>
                <p><strong>Facility:</strong> {{ selectedPA.facility?.name || 'N/A' }}</p>
                <p><strong>Requested:</strong> {{ formatDate(selectedPA.created_at) }}</p>
              </v-col>
            </v-row>

            <v-divider class="my-4"></v-divider>

            <h4 class="mb-2">Justification:</h4>
            <p>{{ selectedPA.justification || 'No justification provided' }}</p>

            <v-divider class="my-4"></v-divider>

            <h4 class="mb-2">Requested Services:</h4>
            <v-table density="compact">
              <thead>
                <tr>
                  <th>Service</th>
                  <th class="text-center">Quantity</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(service, index) in selectedPA.requested_services" :key="index">
                  <td>{{ getServiceName(service.case_record_id) }}</td>
                  <td class="text-center">{{ service.quantity }}</td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn @click="detailsDialog = false">Close</v-btn>
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
      params: { type: 'FFS_TOP_UP' }
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

const viewDetails = (pa) => {
  selectedPA.value = pa;
  detailsDialog.value = true;
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

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
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


