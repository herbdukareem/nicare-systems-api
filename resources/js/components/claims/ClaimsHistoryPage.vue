<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Claims History" subtitle="Complete history of all submitted claims" icon="mdi-history" icon-color="primary">
        <v-btn variant="outlined" prepend-icon="mdi-download" :loading="exporting" @click="exportClaims">Export</v-btn>
      </AppPageHeader>

      <!-- Stats -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <AppStatCard label="Total Claims" :value="stats.total" icon="mdi-file-document-multiple" color="blue" :loading="statsLoading" />
        <AppStatCard label="Approved" :value="stats.approved" icon="mdi-check-circle" color="green" :loading="statsLoading" />
        <AppStatCard label="Rejected" :value="stats.rejected" icon="mdi-close-circle" color="red" :loading="statsLoading" />
        <AppStatCard label="Total Value" :value="stats.total_value" icon="mdi-cash-multiple" color="orange" :loading="statsLoading" :currency="true" />
      </div>

      <!-- Filters -->
      <AppFilterBar :active-count="activeFiltersCount" @clear="clearFilters" :cols="5">
        <v-text-field
          v-model="filters.search"
          label="Search claims…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined" density="compact" clearable hide-details
          @input="debounceSearch"
        />
        <v-select
          v-model="filters.status"
          label="Status"
          :items="statusOptions"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchClaims"
        />
        <v-select
          v-model="filters.facility_id"
          label="Facility"
          :items="facilities"
          item-title="name"
          item-value="id"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchClaims"
        />
        <v-text-field
          v-model="filters.date_from"
          label="From"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchClaims"
        />
        <v-text-field
          v-model="filters.date_to"
          label="To"
          type="date"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchClaims"
        />
      </AppFilterBar>

      <!-- Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-data-table
          :headers="headers"
          :items="claims"
          :loading="loading"
          :items-per-page="pagination.per_page"
          hide-default-footer
          class="tw-rounded-xl"
        >
          <template #item.claim_number="{ item }">
            <div>
              <div class="tw-font-mono tw-text-sm tw-font-semibold tw-text-primary">{{ item.claim_number ?? item.id }}</div>
              <div class="tw-text-xs tw-text-gray-400">{{ item.referral_code ?? '' }}</div>
            </div>
          </template>

          <template #item.enrollee="{ item }">
            <div v-if="item.enrollee || item.patient_name">
              <div class="tw-text-sm tw-font-medium">{{ item.enrollee?.first_name ?? '' }} {{ item.enrollee?.last_name ?? item.patient_name ?? '' }}</div>
              <div class="tw-text-xs tw-text-gray-400">{{ item.enrollee?.enrollee_id ?? '' }}</div>
            </div>
            <span v-else class="tw-text-gray-400 tw-text-sm">—</span>
          </template>

          <template #item.facility="{ item }">
            <span class="tw-text-sm">{{ item.facility?.name ?? item.facility_name ?? '—' }}</span>
          </template>

          <template #item.total_amount="{ item }">
            <span class="tw-font-semibold">{{ formatCurrency(item.total_amount ?? item.amount) }}</span>
          </template>

          <template #item.status="{ item }">
            <AppStatusChip :status="item.status" show-icon />
          </template>

          <template #item.created_at="{ item }">
            <span class="tw-text-xs tw-text-gray-500">{{ formatDate(item.created_at) }}</span>
          </template>

          <template #item.actions="{ item }">
            <v-btn size="small" variant="text" color="primary" @click="viewClaim(item)">View</v-btn>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-file-document-off"
              title="No claims found"
              description="No claims match the selected filters."
            />
          </template>
        </v-data-table>

        <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-100">
          <span class="tw-text-sm tw-text-gray-500">{{ pagination.total.toLocaleString() }} total claims</span>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="5"
            density="compact"
            @update:model-value="fetchClaims"
          />
        </div>
      </v-card>

      <!-- View Claim Dialog -->
      <v-dialog v-model="claimDialog" max-width="680">
        <v-card v-if="selectedClaim" class="tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-6 tw-pb-2 tw-flex tw-items-center tw-justify-between">
            <div class="tw-flex tw-items-center tw-gap-2">
              <span class="tw-text-lg tw-font-bold">Claim #{{ selectedClaim.claim_number ?? selectedClaim.id }}</span>
              <AppStatusChip :status="selectedClaim.status" show-icon />
            </div>
            <v-btn icon="mdi-close" variant="text" @click="claimDialog = false" />
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-px-6 tw-py-4">
            <div class="tw-grid tw-grid-cols-2 tw-gap-4">
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Patient</p>
                <p class="tw-text-sm tw-font-medium">{{ selectedClaim.enrollee?.first_name ?? '' }} {{ selectedClaim.enrollee?.last_name ?? selectedClaim.patient_name ?? '—' }}</p>
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Facility</p>
                <p class="tw-text-sm">{{ selectedClaim.facility?.name ?? '—' }}</p>
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Total Amount</p>
                <p class="tw-text-sm tw-font-bold">{{ formatCurrency(selectedClaim.total_amount ?? selectedClaim.amount) }}</p>
              </div>
              <div>
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Submitted</p>
                <p class="tw-text-sm">{{ formatDate(selectedClaim.created_at) }}</p>
              </div>
              <div v-if="selectedClaim.approved_at">
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Approved</p>
                <p class="tw-text-sm">{{ formatDate(selectedClaim.approved_at) }}</p>
              </div>
              <div v-if="selectedClaim.approved_by_user">
                <p class="tw-text-xs tw-text-gray-400 tw-uppercase tw-mb-1">Approved By</p>
                <p class="tw-text-sm">{{ selectedClaim.approved_by_user?.name ?? '—' }}</p>
              </div>
            </div>
            <div v-if="selectedClaim.rejection_reason" class="tw-mt-4 tw-p-3 tw-bg-red-50 tw-rounded-lg">
              <p class="tw-text-xs tw-font-semibold tw-text-red-700 tw-mb-1">Rejection Reason</p>
              <p class="tw-text-sm tw-text-red-600">{{ selectedClaim.rejection_reason }}</p>
            </div>
          </v-card-text>
          <v-card-actions class="tw-px-6 tw-pb-4">
            <v-btn variant="outlined" prepend-icon="mdi-download" @click="downloadSlip(selectedClaim)">Download Slip</v-btn>
            <v-spacer />
            <v-btn color="primary" variant="flat" @click="claimDialog = false">Close</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppStatCard from '../common/AppStatCard.vue';
import AppFilterBar from '../common/AppFilterBar.vue';
import AppStatusChip from '../common/AppStatusChip.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import { claimsAPI, facilityAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();
const loading = ref(false);
const statsLoading = ref(false);
const exporting = ref(false);
const claims = ref([]);
const facilities = ref([]);
const claimDialog = ref(false);
const selectedClaim = ref(null);

const stats = ref({ total: 0, approved: 0, rejected: 0, total_value: 0 });
const pagination = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 });
const filters = ref({ search: '', status: null, facility_id: null, date_from: '', date_to: '' });

const statusOptions = ['pending', 'submitted', 'in_review', 'approved', 'rejected', 'paid'];

const headers = [
  { title: 'Claim No.', key: 'claim_number', minWidth: 130 },
  { title: 'Patient', key: 'enrollee', minWidth: 160 },
  { title: 'Facility', key: 'facility', minWidth: 140 },
  { title: 'Amount', key: 'total_amount', minWidth: 130 },
  { title: 'Status', key: 'status', width: 120 },
  { title: 'Submitted', key: 'created_at', width: 120 },
  { title: '', key: 'actions', width: 80, sortable: false },
];

const activeFiltersCount = computed(() =>
  [filters.value.search, filters.value.status, filters.value.facility_id, filters.value.date_from, filters.value.date_to]
    .filter(v => v !== null && v !== '').length
);

async function fetchClaims() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page,
      ...(filters.value.search && { search: filters.value.search }),
      ...(filters.value.status && { status: filters.value.status }),
      ...(filters.value.facility_id && { facility_id: filters.value.facility_id }),
      ...(filters.value.date_from && { date_from: filters.value.date_from }),
      ...(filters.value.date_to && { date_to: filters.value.date_to }),
    };
    const res = await claimsAPI.getAll(params);
    const data = res.data?.data ?? res.data;
    claims.value = data.data ?? data ?? [];
    const m = data.meta ?? res.data?.meta;
    if (m) pagination.value = { ...pagination.value, current_page: m.current_page, last_page: m.last_page, total: m.total };
    computeStats();
  } catch {
    showToast('Failed to load claims', 'error');
  } finally {
    loading.value = false;
  }
}

function computeStats() {
  const all = claims.value;
  stats.value = {
    total: pagination.value.total || all.length,
    approved: all.filter(c => c.status === 'approved').length,
    rejected: all.filter(c => c.status === 'rejected').length,
    total_value: all.reduce((s, c) => s + (c.total_amount || c.amount || 0), 0),
  };
}

async function fetchFacilities() {
  try {
    const res = await facilityAPI.getAll({ per_page: 300, status: 1 });
    const data = res.data?.data ?? res.data;
    facilities.value = data.data ?? data ?? [];
  } catch { /* ignore */ }
}

function clearFilters() {
  filters.value = { search: '', status: null, facility_id: null, date_from: '', date_to: '' };
  pagination.value.current_page = 1;
  fetchClaims();
}

const debounceSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchClaims();
}, 400);

function viewClaim(claim) {
  selectedClaim.value = claim;
  claimDialog.value = true;
}

async function downloadSlip(claim) {
  try {
    const res = await claimsAPI.downloadSlip(claim.id);
    const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `claim-slip-${claim.id}.pdf`;
    a.click();
    window.URL.revokeObjectURL(url);
  } catch {
    showToast('Failed to download claim slip', 'error');
  }
}

async function exportClaims() {
  exporting.value = true;
  try {
    showToast('Export functionality coming soon', 'info');
  } finally {
    exporting.value = false;
  }
}

function formatCurrency(v) {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(v || 0);
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(() => {
  fetchClaims();
  fetchFacilities();
});
</script>
