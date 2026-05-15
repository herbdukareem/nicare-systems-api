<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Payments" subtitle="Overview of all payment batches and disbursements" icon="mdi-bank-transfer" icon-color="primary">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="$router.push('/claims/payment-batches')">
          Manage Payment Batches
        </v-btn>
      </AppPageHeader>

      <!-- Stats -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <AppStatCard label="Total Batches" :value="stats.total_batches" icon="mdi-layers" color="blue" :loading="loading" />
        <AppStatCard label="Total Disbursed" :value="stats.total_amount" icon="mdi-cash-multiple" color="green" :loading="loading" :currency="true" />
        <AppStatCard label="Pending" :value="stats.pending_count" icon="mdi-clock-outline" color="orange" :loading="loading" />
        <AppStatCard label="Paid This Month" :value="stats.paid_this_month" icon="mdi-check-circle" color="teal" :loading="loading" :currency="true" />
      </div>

      <!-- Filters -->
      <AppFilterBar :active-count="activeFiltersCount" @clear="clearFilters" :cols="4">
        <v-text-field
          v-model="filters.search"
          label="Search batches…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined" density="compact" clearable hide-details
          @input="debounceSearch"
        />
        <v-select
          v-model="filters.status"
          label="Status"
          :items="statusOptions"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchBatches"
        />
        <v-text-field
          v-model="filters.date_from"
          label="From"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchBatches"
        />
        <v-text-field
          v-model="filters.date_to"
          label="To"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchBatches"
        />
      </AppFilterBar>

      <!-- Batches Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-data-table
          :headers="headers"
          :items="batches"
          :loading="loading"
          :items-per-page="filters.per_page"
          hide-default-footer
          class="tw-rounded-xl"
        >
          <template #item.batch_number="{ item }">
            <span class="tw-font-mono tw-text-sm tw-font-semibold">{{ item.batch_number ?? item.id }}</span>
          </template>

          <template #item.total_amount="{ item }">
            <span class="tw-font-semibold">{{ formatCurrency(item.total_amount) }}</span>
          </template>

          <template #item.status="{ item }">
            <AppStatusChip :status="item.status" show-icon />
          </template>

          <template #item.created_at="{ item }">
            <span class="tw-text-xs tw-text-gray-500">{{ formatDate(item.created_at) }}</span>
          </template>

          <template #item.processed_at="{ item }">
            <span class="tw-text-xs tw-text-gray-500">{{ item.processed_at ? formatDate(item.processed_at) : '—' }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-gap-1">
              <v-btn
                size="small"
                variant="text"
                color="primary"
                @click="viewBatch(item)"
              >
                View
              </v-btn>
              <v-btn
                v-if="item.status === 'pending'"
                size="small"
                variant="text"
                color="success"
                @click="processBatch(item)"
                :loading="processing === item.id"
              >
                Process
              </v-btn>
              <v-btn
                v-if="item.status === 'processed'"
                size="small"
                variant="text"
                color="teal"
                @click="markPaid(item)"
                :loading="marking === item.id"
              >
                Mark Paid
              </v-btn>
            </div>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-bank-transfer-out"
              title="No payment batches"
              description="Payment batches will appear here once created."
            >
              <v-btn color="primary" @click="$router.push('/claims/payment-batches')">Go to Payment Batches</v-btn>
            </AppEmptyState>
          </template>
        </v-data-table>

        <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-100">
          <span class="tw-text-sm tw-text-gray-500">{{ pagination.total.toLocaleString() }} total batches</span>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="5"
            density="compact"
            @update:model-value="fetchBatches"
          />
        </div>
      </v-card>

      <!-- Batch Detail Dialog -->
      <v-dialog v-model="batchDialog" max-width="640">
        <v-card v-if="selectedBatch" class="tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-6 tw-pb-2 tw-flex tw-items-center tw-justify-between">
            <span class="tw-text-lg tw-font-bold">Batch #{{ selectedBatch.batch_number ?? selectedBatch.id }}</span>
            <v-btn icon="mdi-close" variant="text" @click="batchDialog = false" />
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-px-6 tw-py-4">
            <div class="tw-grid tw-grid-cols-2 tw-gap-4">
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Status</p>
                <AppStatusChip :status="selectedBatch.status" show-icon />
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Total Amount</p>
                <p class="tw-text-sm tw-font-bold">{{ formatCurrency(selectedBatch.total_amount) }}</p>
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Claims Count</p>
                <p class="tw-text-sm tw-font-medium">{{ selectedBatch.claims_count ?? selectedBatch.claims?.length ?? 0 }}</p>
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Created</p>
                <p class="tw-text-sm">{{ formatDate(selectedBatch.created_at) }}</p>
              </div>
              <div v-if="selectedBatch.processed_at">
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Processed</p>
                <p class="tw-text-sm">{{ formatDate(selectedBatch.processed_at) }}</p>
              </div>
              <div v-if="selectedBatch.paid_at">
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Paid At</p>
                <p class="tw-text-sm">{{ formatDate(selectedBatch.paid_at) }}</p>
              </div>
            </div>
          </v-card-text>
          <v-card-actions class="tw-px-6 tw-pb-4">
            <v-btn variant="outlined" prepend-icon="mdi-download" @click="downloadReceipt(selectedBatch)">Receipt</v-btn>
            <v-spacer />
            <v-btn color="primary" variant="flat" @click="batchDialog = false">Close</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppStatCard from '../common/AppStatCard.vue';
import AppFilterBar from '../common/AppFilterBar.vue';
import AppStatusChip from '../common/AppStatusChip.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import { paymentBatchAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();
const router = useRouter();
const loading = ref(false);
const batches = ref([]);
const batchDialog = ref(false);
const selectedBatch = ref(null);
const processing = ref(null);
const marking = ref(null);

const stats = ref({ total_batches: 0, total_amount: 0, pending_count: 0, paid_this_month: 0 });
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });
const filters = ref({ search: '', status: null, date_from: '', date_to: '', per_page: 20 });

const statusOptions = ['pending', 'processing', 'processed', 'paid', 'failed'];

const headers = [
  { title: 'Batch No.', key: 'batch_number', width: 130 },
  { title: 'Total Amount', key: 'total_amount', minWidth: 150 },
  { title: 'Claims', key: 'claims_count', width: 90 },
  { title: 'Status', key: 'status', width: 120 },
  { title: 'Created', key: 'created_at', width: 120 },
  { title: 'Processed', key: 'processed_at', width: 120 },
  { title: 'Actions', key: 'actions', width: 180, sortable: false },
];

const activeFiltersCount = computed(() =>
  [filters.value.search, filters.value.status, filters.value.date_from, filters.value.date_to].filter(v => v !== null && v !== '').length
);

async function fetchBatches() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: filters.value.per_page,
      ...(filters.value.search && { search: filters.value.search }),
      ...(filters.value.status && { status: filters.value.status }),
      ...(filters.value.date_from && { date_from: filters.value.date_from }),
      ...(filters.value.date_to && { date_to: filters.value.date_to }),
    };
    const res = await paymentBatchAPI.getAll(params);
    const data = res.data?.data ?? res.data;
    batches.value = data.data ?? data ?? [];
    const m = data.meta ?? res.data?.meta;
    if (m) pagination.value = { current_page: m.current_page, last_page: m.last_page, total: m.total };
    computeStats();
  } catch {
    showToast('Failed to load payment batches', 'error');
  } finally {
    loading.value = false;
  }
}

function computeStats() {
  const all = batches.value;
  const now = new Date();
  stats.value = {
    total_batches: pagination.value.total || all.length,
    total_amount: all.reduce((s, b) => s + (b.total_amount || 0), 0),
    pending_count: all.filter(b => b.status === 'pending').length,
    paid_this_month: all
      .filter(b => b.status === 'paid' && b.paid_at && new Date(b.paid_at).getMonth() === now.getMonth())
      .reduce((s, b) => s + (b.total_amount || 0), 0),
  };
}

function clearFilters() {
  filters.value = { search: '', status: null, date_from: '', date_to: '', per_page: 20 };
  pagination.value.current_page = 1;
  fetchBatches();
}

const debounceSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchBatches();
}, 400);

function viewBatch(batch) {
  selectedBatch.value = batch;
  batchDialog.value = true;
}

async function processBatch(batch) {
  processing.value = batch.id;
  try {
    await paymentBatchAPI.process(batch.id, {});
    showToast('Batch processing initiated', 'success');
    fetchBatches();
  } catch {
    showToast('Failed to process batch', 'error');
  } finally {
    processing.value = null;
  }
}

async function markPaid(batch) {
  marking.value = batch.id;
  try {
    await paymentBatchAPI.markPaid(batch.id, {});
    showToast('Batch marked as paid', 'success');
    fetchBatches();
  } catch {
    showToast('Failed to mark as paid', 'error');
  } finally {
    marking.value = null;
  }
}

async function downloadReceipt(batch) {
  try {
    const res = await paymentBatchAPI.downloadReceipt(batch.id);
    const url = window.URL.createObjectURL(new Blob([res.data]));
    const a = document.createElement('a');
    a.href = url;
    a.download = `receipt-batch-${batch.id}.pdf`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch {
    showToast('Failed to download receipt', 'error');
  }
}

function formatCurrency(v) {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(v || 0);
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(fetchBatches);
</script>
