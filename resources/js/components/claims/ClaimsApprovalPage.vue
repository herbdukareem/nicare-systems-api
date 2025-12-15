<template>
  <AdminLayout>
    <v-container fluid>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex align-center bg-success text-white">
              <v-icon class="mr-2">mdi-check-circle</v-icon>
              Claims Approval & Payment Processing
              <v-spacer />
              <v-chip color="white" text-color="success" class="font-weight-bold">
                {{ selectedClaims.length }} Selected
              </v-chip>
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4 mt-4">
                <v-col cols="12" md="3">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search (Claim #, UTN, Enrollee)"
                    density="compact"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    @keyup.enter="loadClaims"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-text-field
                    v-model="monthFilter"
                    label="Month"
                    type="month"
                    density="compact"
                    @change="loadClaims"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-text-field
                    v-model="dateFrom"
                    label="From Date"
                    type="date"
                    density="compact"
                    @change="loadClaims"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-text-field
                    v-model="dateTo"
                    label="To Date"
                    type="date"
                    density="compact"
                    @change="loadClaims"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-btn color="secondary" @click="resetFilters" block>
                    <v-icon left>mdi-refresh</v-icon>
                    Reset
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Summary Cards -->
              <v-row class="mb-4">
                <v-col cols="12" md="3">
                  <v-card variant="outlined">
                    <v-card-text class="text-center">
                      <div class="text-h6">Total Claims</div>
                      <div class="text-h4 text-primary font-weight-bold">{{ claims.length }}</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="3">
                  <v-card variant="outlined">
                    <v-card-text class="text-center">
                      <div class="text-h6">Selected</div>
                      <div class="text-h4 text-success font-weight-bold">{{ selectedClaims.length }}</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="3">
                  <v-card variant="outlined">
                    <v-card-text class="text-center">
                      <div class="text-h6">Total Amount</div>
                      <div class="text-h5 text-warning font-weight-bold">₦{{ formatNumber(totalAmount) }}</div>
                    </v-card-text>
                  </v-card>
                </v-col>
                <v-col cols="12" md="3">
                  <v-card variant="outlined">
                    <v-card-text class="text-center">
                      <div class="text-h6">Selected Amount</div>
                      <div class="text-h5 text-success font-weight-bold">₦{{ formatNumber(selectedAmount) }}</div>
                    </v-card-text>
                  </v-card>
                </v-col>
              </v-row>

              <!-- Claims Table -->
              <v-data-table
                v-model="selectedClaims"
                :headers="headers"
                :items="claims"
                :loading="loading"
                show-select
                item-value="id"
                class="elevation-1"
              >
                <template #item.claim_number="{ item }">
                  <a href="#" @click.prevent="viewClaimDetails(item.id)" class="text-primary">
                    {{ item.claim_number }}
                  </a>
                </template>
                <template #item.enrollee="{ item }">
                  {{ item.enrollee?.full_name || 'N/A' }}
                </template>
                <template #item.facility="{ item }">
                  {{ item.facility?.name || 'N/A' }}
                </template>
                <template #item.total_amount_claimed="{ item }">
                  ₦{{ formatNumber(item.total_amount_claimed) }}
                </template>
                <template #item.submitted_at="{ item }">
                  {{ formatDate(item.submitted_at) }}
                </template>
                <template #item.actions="{ item }">
                  <v-btn icon size="small" @click="viewClaimDetails(item.id)">
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn icon size="small" color="info" @click="downloadSlip(item)">
                    <v-icon>mdi-download</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>

            <!-- Action Buttons -->
            <v-card-actions class="pa-4" v-if="selectedClaims.length > 0">
              <v-spacer></v-spacer>
              <v-btn
                color="success"
                size="large"
                @click="showApprovalDialog = true"
                :disabled="selectedClaims.length === 0"
                prepend-icon="mdi-check-all"
              >
                Approve Selected ({{ selectedClaims.length }})
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <!-- Approval Dialog -->
      <v-dialog v-model="showApprovalDialog" max-width="800px" persistent>
        <v-card>
          <v-card-title class="bg-success text-white">
            <v-icon class="mr-2">mdi-check-circle</v-icon>
            Approve {{ selectedClaims.length }} Claims
          </v-card-title>

          <v-card-text class="pa-6">
            <!-- Summary -->
            <v-alert type="info" variant="tonal" class="mb-4">
              <strong>Total Amount to Approve:</strong> ₦{{ formatNumber(selectedAmount) }}
            </v-alert>

            <!-- Approval Form -->
            <v-form ref="approvalForm">
              <v-row>
                <v-col cols="12">
                  <v-text-field
                    v-model="approvalData.payment_code"
                    label="Payment Code/Reference"
                    hint="Unique payment reference for all claims"
                    persistent-hint
                    required
                    :rules="[v => !!v || 'Payment code is required']"
                  />
                </v-col>
              </v-row>

              <v-row>
                <v-col cols="12">
                  <v-textarea
                    v-model="approvalData.approval_comments"
                    label="Approval Comments"
                    hint="Comments will be applied to all selected claims"
                    persistent-hint
                    rows="4"
                  />
                </v-col>
              </v-row>

              <v-row>
                <v-col cols="12">
                  <v-checkbox
                    v-model="approvalData.generate_approval_letter"
                    label="Generate Approval Letter"
                  />
                </v-col>
              </v-row>

              <v-row>
                <v-col cols="12">
                  <v-checkbox
                    v-model="approvalData.generate_payment_receipts"
                    label="Generate Payment Receipts for Each Claim"
                  />
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>

          <v-card-actions class="pa-4">
            <v-spacer></v-spacer>
            <v-btn variant="outlined" @click="showApprovalDialog = false">
              Cancel
            </v-btn>
            <v-btn
              color="success"
              :loading="approving"
              @click="submitApproval"
              prepend-icon="mdi-check"
            >
              Approve & Process
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Claim Details Modal -->
      <v-dialog v-model="showClaimDetailsModal" max-width="1200px">
        <ClaimDetailsModal
          v-if="selectedClaimForDetails"
          :claimData="selectedClaimForDetails"
          @close="showClaimDetailsModal = false"
          @download-slip="downloadClaimSlipById"
        />
      </v-dialog>
    </v-container>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '@/js/components/layout/AdminLayout.vue';
import ClaimDetailsModal from './ClaimDetailsModal.vue';
import { claimsAPI } from '../../utils/api';
import api from '../../utils/api';
import { useToast } from '@/js/composables/useToast';

const { success: showSuccess, error: showError } = useToast();

// State
const loading = ref(false);
const approving = ref(false);
const claims = ref([]);
const selectedClaims = ref([]);
const searchQuery = ref('');
const monthFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');
const showApprovalDialog = ref(false);
const showClaimDetailsModal = ref(false);
const selectedClaimForDetails = ref(null);

const approvalData = ref({
  payment_code: '',
  approval_comments: '',
  generate_approval_letter: true,
  generate_payment_receipts: true,
});

const headers = [
  { title: 'Claim Number', value: 'claim_number' },
  { title: 'UTN', value: 'utn' },
  { title: 'Enrollee', value: 'enrollee' },
  { title: 'Facility', value: 'facility' },
  { title: 'Amount Claimed', value: 'total_amount_claimed' },
  { title: 'Submitted Date', value: 'submitted_at' },
  { title: 'Actions', value: 'actions', sortable: false },
];

// Computed properties
const totalAmount = computed(() => {
  return claims.value.reduce((sum, claim) => sum + (Number(claim.total_amount_claimed) || 0), 0);
});

const selectedAmount = computed(() => {
  return claims.value
    .filter(claim => selectedClaims.value.includes(claim.id))
    .reduce((sum, claim) => sum + (Number(claim.total_amount_claimed) || 0), 0);
});

// Methods
const loadClaims = async () => {
  loading.value = true;
  try {
    const params = {  };
    if (searchQuery.value) params.search = searchQuery.value;
    if (monthFilter.value) params.month = monthFilter.value;
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;

    const response = await claimsAPI.getAll(params);
    const payload = response.data?.data ?? response.data ?? [];
    claims.value = Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : []);
  } catch (err) {
    showError('Failed to load claims');
    console.error(err);
  } finally {
    loading.value = false;
  }
};

const viewClaimDetails = async (claimId) => {
  try {
    const response = await api.get(`/claims-automation/claims/${claimId}/full-details`);
    selectedClaimForDetails.value = response.data?.data || response.data;
    showClaimDetailsModal.value = true;
  } catch (error) {
    showError('Failed to load claim details');
    console.error(error);
  }
};

const submitApproval = async () => {
  if (!approvalData.value.payment_code) {
    showError('Payment code is required');
    return;
  }

  approving.value = true;
  try {
    const response = await claimsAPI.batchApprove({
      claim_ids: selectedClaims.value,
      approval_comments: approvalData.value.approval_comments,
      payment_code: approvalData.value.payment_code,
      generate_approval_letter: approvalData.value.generate_approval_letter,
      generate_payment_receipts: approvalData.value.generate_payment_receipts,
    });

    showSuccess(`${selectedClaims.value.length} claims approved successfully`);
    showApprovalDialog.value = false;
    selectedClaims.value = [];
    approvalData.value = {
      payment_code: '',
      approval_comments: '',
      generate_approval_letter: true,
      generate_payment_receipts: true,
    };
    await loadClaims();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to approve claims');
    console.error(err);
  } finally {
    approving.value = false;
  }
};

const downloadSlip = async (claim) => {
  try {
    const response = await claimsAPI.downloadSlip(claim.id);
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `claim-slip-${claim.claim_number}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    showError('Failed to download slip');
    console.error(err);
  }
};

const downloadClaimSlipById = async (claimId) => {
  try {
    const response = await claimsAPI.downloadSlip(claimId);
    const blob = new Blob([response.data], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `claim-slip-${claimId}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (err) {
    showError('Failed to download slip');
    console.error(err);
  }
};

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString();
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};

const resetFilters = () => {
  searchQuery.value = '';
  monthFilter.value = '';
  dateFrom.value = '';
  dateTo.value = '';
  loadClaims();
};

onMounted(() => {
  loadClaims();
});
</script>

<style scoped>
</style>

