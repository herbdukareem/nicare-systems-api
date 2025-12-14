<template>
  <AdminLayout>
    <v-container fluid>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex align-center">
              <v-icon class="mr-2">mdi-cash-multiple</v-icon>
              Payment Batch Management
              <v-spacer />
              <v-btn color="primary" @click="showCreateDialog = true">
                <v-icon left>mdi-plus</v-icon>
                Create Payment Batch
              </v-btn>
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="3">
                  <v-select
                    v-model="filters.status"
                    :items="statusOptions"
                    label="Status"
                    clearable
                    density="compact"
                    @update:model-value="loadBatches"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-text-field
                    v-model="filters.batch_month"
                    label="Batch Month"
                    type="month"
                    density="compact"
                    @change="loadBatches"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-autocomplete
                    v-model="filters.facility_id"
                    :items="facilities"
                    item-title="name"
                    item-value="id"
                    label="Facility"
                    clearable
                    density="compact"
                    @update:model-value="loadBatches"
                  />
                </v-col>
              </v-row>

              <!-- Batches Table -->
              <v-data-table
                :headers="headers"
                :items="batches"
                :loading="loading"
                class="elevation-1"
              >
                <template #item.batch_number="{ item }">
                  <a href="#" @click.prevent="viewBatchDetails(item)">
                    {{ item.batch_number }}
                  </a>
                </template>
                <template #item.facility="{ item }">
                  {{ item.facility?.name || 'N/A' }}
                </template>
                <template #item.total_claims_amount="{ item }">
                  ₦{{ formatNumber(item.total_claims_amount) }}
                </template>
                <template #item.total_approved_amount="{ item }">
                  ₦{{ formatNumber(item.total_approved_amount) }}
                </template>
                <template #item.status="{ item }">
                  <v-chip :color="getStatusColor(item.status)" size="small">
                    {{ item.status }}
                  </v-chip>
                </template>
                <template #item.actions="{ item }">
                  <v-btn icon size="small" @click="viewBatchDetails(item)">
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn
                    v-if="item.status === 'PENDING'"
                    icon
                    size="small"
                    color="primary"
                    @click="processBatch(item)"
                  >
                    <v-icon>mdi-cog</v-icon>
                  </v-btn>
                  <v-btn
                    v-if="item.status === 'PROCESSING'"
                    icon
                    size="small"
                    color="success"
                    @click="markBatchPaid(item)"
                  >
                    <v-icon>mdi-check</v-icon>
                  </v-btn>
                  <v-btn
                    v-if="item.status === 'PAID'"
                    icon
                    size="small"
                    color="info"
                    @click="downloadReceipt(item)"
                  >
                    <v-icon>mdi-download</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Create Batch Dialog -->
      <v-dialog v-model="showCreateDialog" max-width="700">
        <v-card>
          <v-card-title>Create Payment Batch</v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newBatch.batch_month"
                  label="Batch Month"
                  type="month"
                  :rules="[v => !!v || 'Required']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="newBatch.facility_id"
                  :items="facilities"
                  item-title="name"
                  item-value="id"
                  label="Facility"
                  :rules="[v => !!v || 'Required']"
                />
              </v-col>
            </v-row>
            <v-btn class="mt-4" @click="loadApprovedClaims" :loading="loadingClaims">
              Load Approved Claims
            </v-btn>

            <!-- Approved Claims for Batch -->
            <v-data-table
              v-if="approvedClaims.length > 0"
              v-model="selectedClaimsForBatch"
              :headers="claimHeaders"
              :items="approvedClaims"
              show-select
              item-value="id"
              class="mt-4"
            >
              <template #item.total_amount_claimed="{ item }">
                ₦{{ formatNumber(item.total_amount_claimed) }}
              </template>
              <template #item.approved_amount="{ item }">
                ₦{{ formatNumber(item.approved_amount) }}
              </template>
            </v-data-table>

            <v-alert v-if="approvedClaims.length > 0" type="info" class="mt-4">
              Selected: {{ selectedClaimsForBatch.length }} claims |
              Total: ₦{{ formatNumber(selectedClaimsTotal) }}
            </v-alert>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showCreateDialog = false">Cancel</v-btn>
            <v-btn
              color="primary"
              :loading="creating"
              :disabled="selectedClaimsForBatch.length === 0"
              @click="createBatch"
            >
              Create Batch
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Batch Details Dialog -->
      <v-dialog v-model="showDetailsDialog" max-width="900">
        <v-card v-if="selectedBatch">
          <v-card-title>
            Batch: {{ selectedBatch.batch_number }}
            <v-chip :color="getStatusColor(selectedBatch.status)" class="ml-2" size="small">
              {{ selectedBatch.status }}
            </v-chip>
          </v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="6" md="3">
                <strong>Facility:</strong><br>
                {{ selectedBatch.facility?.name }}
              </v-col>
              <v-col cols="6" md="3">
                <strong>Batch Month:</strong><br>
                {{ selectedBatch.batch_month }}
              </v-col>
              <v-col cols="6" md="3">
                <strong>Total Claims:</strong><br>
                {{ selectedBatch.total_claims_count }}
              </v-col>
              <v-col cols="6" md="3">
                <strong>Total Amount:</strong><br>
                ₦{{ formatNumber(selectedBatch.total_approved_amount) }}
              </v-col>
            </v-row>

            <v-divider class="my-4" />

            <h4>Claims in Batch</h4>
            <v-data-table
              :headers="batchClaimHeaders"
              :items="selectedBatch.claims || []"
              density="compact"
            >
              <template #item.total_amount_claimed="{ item }">
                ₦{{ formatNumber(item.total_amount_claimed) }}
              </template>
              <template #item.approved_amount="{ item }">
                ₦{{ formatNumber(item.approved_amount) }}
              </template>
            </v-data-table>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showDetailsDialog = false">Close</v-btn>
            <v-btn
              v-if="selectedBatch.status === 'PAID'"
              color="info"
              @click="downloadReceipt(selectedBatch)"
            >
              <v-icon left>mdi-download</v-icon>
              Download Receipt
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Mark Paid Dialog -->
      <v-dialog v-model="showMarkPaidDialog" max-width="500">
        <v-card>
          <v-card-title>Mark Batch as Paid</v-card-title>
          <v-card-text>
            <v-text-field
              v-model="paymentDetails.payment_reference"
              label="Payment Reference"
              :rules="[v => !!v || 'Required']"
            />
            <v-text-field
              v-model="paymentDetails.payment_date"
              label="Payment Date"
              type="date"
              :rules="[v => !!v || 'Required']"
            />
            <v-textarea
              v-model="paymentDetails.payment_notes"
              label="Payment Notes"
              rows="3"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showMarkPaidDialog = false">Cancel</v-btn>
            <v-btn color="success" :loading="processing" @click="confirmMarkPaid">
              Confirm Payment
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { paymentBatchAPI } from '../../utils/api';
import api from '../../utils/api';

const loading = ref(false);
const loadingClaims = ref(false);
const creating = ref(false);
const processing = ref(false);
const batches = ref([]);
const facilities = ref([]);
const approvedClaims = ref([]);
const selectedClaimsForBatch = ref([]);

const filters = ref({
  status: null,
  batch_month: '',
  facility_id: null,
});

const newBatch = ref({
  batch_month: '',
  facility_id: null,
});

const showCreateDialog = ref(false);
const showDetailsDialog = ref(false);
const showMarkPaidDialog = ref(false);
const selectedBatch = ref(null);
const batchToMarkPaid = ref(null);

const paymentDetails = ref({
  payment_reference: '',
  payment_date: new Date().toISOString().split('T')[0],
  payment_notes: '',
});

const statusOptions = [
  { title: 'All', value: null },
  { title: 'Pending', value: 'PENDING' },
  { title: 'Processing', value: 'PROCESSING' },
  { title: 'Paid', value: 'PAID' },
  { title: 'Failed', value: 'FAILED' },
];

const headers = [
  { title: 'Batch Number', value: 'batch_number' },
  { title: 'Facility', value: 'facility' },
  { title: 'Batch Month', value: 'batch_month' },
  { title: 'Claims Count', value: 'total_claims_count' },
  { title: 'Claims Amount', value: 'total_claims_amount' },
  { title: 'Approved Amount', value: 'total_approved_amount' },
  { title: 'Status', value: 'status' },
  { title: 'Actions', value: 'actions', sortable: false },
];

const claimHeaders = [
  { title: 'Claim #', value: 'claim_number' },
  { title: 'UTN', value: 'utn' },
  { title: 'Amount Claimed', value: 'total_amount_claimed' },
  { title: 'Approved Amount', value: 'approved_amount' },
];

const batchClaimHeaders = [
  { title: 'Claim #', value: 'claim_number' },
  { title: 'UTN', value: 'utn' },
  { title: 'Enrollee', value: 'enrollee.full_name' },
  { title: 'Amount Claimed', value: 'total_amount_claimed' },
  { title: 'Approved Amount', value: 'approved_amount' },
];

const selectedClaimsTotal = computed(() => {
  return approvedClaims.value
    .filter(c => selectedClaimsForBatch.value.includes(c.id))
    .reduce((sum, c) => sum + (c.approved_amount || c.total_amount_claimed), 0);
});

const loadBatches = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.batch_month) params.batch_month = filters.value.batch_month;
    if (filters.value.facility_id) params.facility_id = filters.value.facility_id;

    const response = await paymentBatchAPI.getAll(params);
    batches.value = response.data.data || response.data || [];
  } catch (err) {
    console.error('Failed to load batches:', err);
  } finally {
    loading.value = false;
  }
};

const loadFacilities = async () => {
  try {
    const response = await api.get('/facilities');
    facilities.value = response.data.data || response.data || [];
  } catch (err) {
    console.error('Failed to load facilities:', err);
  }
};

const loadApprovedClaims = async () => {
  if (!newBatch.value.batch_month || !newBatch.value.facility_id) return;
  loadingClaims.value = true;
  try {
    const response = await paymentBatchAPI.getApprovedClaims({
      batch_month: newBatch.value.batch_month,
      facility_id: newBatch.value.facility_id,
    });
    approvedClaims.value = response.data.data || response.data || [];
    selectedClaimsForBatch.value = approvedClaims.value.map(c => c.id);
  } catch (err) {
    console.error('Failed to load approved claims:', err);
  } finally {
    loadingClaims.value = false;
  }
};

const createBatch = async () => {
  creating.value = true;
  try {
    await paymentBatchAPI.create({
      batch_month: newBatch.value.batch_month,
      facility_id: newBatch.value.facility_id,
      claim_ids: selectedClaimsForBatch.value,
    });
    showCreateDialog.value = false;
    approvedClaims.value = [];
    selectedClaimsForBatch.value = [];
    newBatch.value = { batch_month: '', facility_id: null };
    await loadBatches();
  } catch (err) {
    console.error('Failed to create batch:', err);
  } finally {
    creating.value = false;
  }
};

const viewBatchDetails = async (batch) => {
  try {
    const response = await paymentBatchAPI.getById(batch.id);
    selectedBatch.value = response.data.data || response.data;
    showDetailsDialog.value = true;
  } catch (err) {
    console.error('Failed to load batch details:', err);
  }
};

const processBatch = async (batch) => {
  processing.value = true;
  try {
    await paymentBatchAPI.process(batch.id, {});
    await loadBatches();
  } catch (err) {
    console.error('Failed to process batch:', err);
  } finally {
    processing.value = false;
  }
};

const markBatchPaid = (batch) => {
  batchToMarkPaid.value = batch;
  paymentDetails.value = {
    payment_reference: '',
    payment_date: new Date().toISOString().split('T')[0],
    payment_notes: '',
  };
  showMarkPaidDialog.value = true;
};

const confirmMarkPaid = async () => {
  if (!paymentDetails.value.payment_reference || !paymentDetails.value.payment_date) return;
  processing.value = true;
  try {
    await paymentBatchAPI.markPaid(batchToMarkPaid.value.id, paymentDetails.value);
    showMarkPaidDialog.value = false;
    await loadBatches();
  } catch (err) {
    console.error('Failed to mark batch as paid:', err);
  } finally {
    processing.value = false;
  }
};

const downloadReceipt = async (batch) => {
  try {
    const response = await paymentBatchAPI.downloadReceipt(batch.id);
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `payment-receipt-${batch.batch_number}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    console.error('Failed to download receipt:', err);
  }
};

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString();
};

const getStatusColor = (status) => {
  const colors = {
    'PENDING': 'orange',
    'PROCESSING': 'blue',
    'PAID': 'green',
    'FAILED': 'red',
  };
  return colors[status] || 'gray';
};

onMounted(() => {
  loadBatches();
  loadFacilities();
});
</script>

<style scoped>
</style>

