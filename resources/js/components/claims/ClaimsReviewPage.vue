<template>
  <AdminLayout>
    <v-container fluid>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex align-center">
              <v-icon class="mr-2">mdi-clipboard-check</v-icon>
              Claims Review & Approval
              <v-spacer />
              <v-btn
                v-if="selectedClaims.length > 0"
                color="success"
                class="mr-2"
                @click="showBatchApproveDialog = true"
              >
                <v-icon left>mdi-check-all</v-icon>
                Approve Selected ({{ selectedClaims.length }})
              </v-btn>
              <v-btn
                v-if="selectedClaims.length > 0"
                color="error"
                @click="showBatchRejectDialog = true"
              >
                <v-icon left>mdi-close-circle</v-icon>
                Reject Selected
              </v-btn>
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
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
                <v-col cols="12" md="3">
                  <v-select
                    v-model="statusFilter"
                    label="Filter by Status"
                    :items="statusOptions"
                    density="compact"
                    clearable
                    @update:model-value="loadClaims"
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
                  <v-btn color="secondary" @click="resetFilters">
                    <v-icon left>mdi-refresh</v-icon>
                    Reset
                  </v-btn>
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
                  <a href="#" @click.prevent="reviewClaim(item)">
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
                <template #item.status="{ item }">
                  <v-chip :color="getStatusColor(item.status)" size="small">
                    {{ item.status }}
                  </v-chip>
                </template>
                <template #item.submitted_at="{ item }">
                  {{ formatDate(item.submitted_at) }}
                </template>
                <template #item.actions="{ item }">
                  <v-btn icon size="small" @click="viewClaimDetails(item.id)">
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn
                    v-if="item.status === 'SUBMITTED'"
                    icon
                    size="small"
                    color="success"
                    @click="approveSingleClaim(item)"
                  >
                    <v-icon>mdi-check</v-icon>
                  </v-btn>
                  <v-btn
                    v-if="item.status === 'SUBMITTED'"
                    icon
                    size="small"
                    color="error"
                    @click="rejectSingleClaim(item)"
                  >
                    <v-icon>mdi-close</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    size="small"
                    color="info"
                    @click="downloadSlip(item)"
                  >
                    <v-icon>mdi-download</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Review Dialog -->
      <v-dialog v-model="showReviewDialog" max-width="900px">
        <v-card>
          <v-card-title>
            Review Claim: {{ currentClaim?.claim_number }}
          </v-card-title>
          <v-card-text>
            <!-- Claim Details -->
            <v-card class="mb-4">
              <v-card-title>Claim Details</v-card-title>
              <v-card-text>
                <v-simple-table>
                  <template v-slot:default>
                    <tbody>
                      <tr>
                        <td><strong>Claim Number:</strong></td>
                        <td>{{ currentClaim?.claim_number }}</td>
                      </tr>
                      <tr>
                        <td><strong>Amount Claimed:</strong></td>
                        <td>{{ currentClaim?.total_amount_claimed }}</td>
                      </tr>
                      <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                          <v-chip
                            :color="getStatusColor(currentClaim?.status)"
                            text-color="white"
                            small
                          >
                            {{ currentClaim?.status }}
                          </v-chip>
                        </td>
                      </tr>
                    </tbody>
                  </template>
                </v-simple-table>
              </v-card-text>
            </v-card>

            <!-- Validation Alerts -->
            <v-card class="mb-4" v-if="currentClaim?.alerts?.length">
              <v-card-title>Validation Alerts</v-card-title>
              <v-card-text>
                <v-alert
                  v-for="(alert, index) in currentClaim.alerts"
                  :key="index"
                  :type="alert.severity"
                  dismissible
                  class="mb-2"
                >
                  {{ alert.message }}
                </v-alert>
              </v-card-text>
            </v-card>

            <!-- Approval Form -->
            <v-card>
              <v-card-title>Approval Decision</v-card-title>
              <v-card-text>
                <v-form ref="approvalForm">
                  <v-row>
                    <v-col cols="12">
                      <v-radio-group v-model="reviewData.decision" row>
                        <v-radio label="Approve" value="APPROVED"></v-radio>
                        <v-radio label="Reject" value="REJECTED"></v-radio>
                      </v-radio-group>
                    </v-col>
                  </v-row>

                  <v-row>
                    <v-col cols="12">
                      <v-text-field
                        v-model.number="reviewData.approved_amount"
                        label="Approved Amount"
                        type="number"
                        outlined
                        v-if="reviewData.decision === 'APPROVED'"
                      />
                    </v-col>
                  </v-row>

                  <v-row>
                    <v-col cols="12">
                      <v-textarea
                        v-model="reviewData.comments"
                        label="Comments"
                        outlined
                        rows="3"
                      />
                    </v-col>
                  </v-row>
                </v-form>
              </v-card-text>
            </v-card>
          </v-card-text>

          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="secondary" @click="showReviewDialog = false">
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              :loading="loading"
              @click="submitReview"
            >
              <v-icon left>mdi-check</v-icon>
              Submit Review
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Batch Approve Dialog -->
      <v-dialog v-model="showBatchApproveDialog" max-width="500">
        <v-card>
          <v-card-title>Batch Approve Claims</v-card-title>
          <v-card-text>
            <p class="mb-4">You are about to approve {{ selectedClaims.length }} claims.</p>
            <v-textarea
              v-model="batchApprovalComments"
              label="Approval Comments (Optional)"
              rows="3"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showBatchApproveDialog = false">Cancel</v-btn>
            <v-btn color="success" :loading="batchProcessing" @click="batchApprove">
              Approve All
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Batch Reject Dialog -->
      <v-dialog v-model="showBatchRejectDialog" max-width="500">
        <v-card>
          <v-card-title>Batch Reject Claims</v-card-title>
          <v-card-text>
            <p class="mb-4">You are about to reject {{ selectedClaims.length }} claims.</p>
            <v-textarea
              v-model="batchRejectionReason"
              label="Rejection Reason (Required)"
              rows="3"
              :rules="[v => !!v || 'Rejection reason is required']"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showBatchRejectDialog = false">Cancel</v-btn>
            <v-btn color="error" :loading="batchProcessing" @click="batchReject">
              Reject All
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Single Reject Dialog -->
      <v-dialog v-model="showSingleRejectDialog" max-width="500">
        <v-card>
          <v-card-title>Reject Claim</v-card-title>
          <v-card-text>
            <p class="mb-4">Claim: {{ singleRejectClaim?.claim_number }}</p>
            <v-textarea
              v-model="singleRejectionReason"
              label="Rejection Reason (Required)"
              rows="3"
              :rules="[v => !!v || 'Rejection reason is required']"
            />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn @click="showSingleRejectDialog = false">Cancel</v-btn>
            <v-btn color="error" :loading="batchProcessing" @click="confirmSingleReject">
              Reject
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </AdminLayout>

  <v-dialog v-model="showClaimDetailsModal" max-width="1200px">
    <ClaimDetailsModal
      v-if="selectedClaimForDetails"
      :claimData="selectedClaimForDetails"
      @close="showClaimDetailsModal = false"
      @download-slip="downloadClaimSlipById"
      @claim-updated="onClaimUpdated"
    />
  </v-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from '@/js/components/layout/AdminLayout.vue';
import { claimsAPI } from '../../utils/api';
import api from '../../utils/api';
import ClaimDetailsModal from './ClaimDetailsModal.vue';


const showClaimDetailsModal = ref(false);
const selectedClaimForDetails = ref(null);

const loading = ref(false);
const batchProcessing = ref(false);
const claims = ref([]);
const selectedClaims = ref([]);
const searchQuery = ref('');
const statusFilter = ref('SUBMITTED');
const dateFrom = ref('');
const dateTo = ref('');
const showReviewDialog = ref(false);
const currentClaim = ref(null);
const approvalForm = ref(null);

// Batch dialogs
const showBatchApproveDialog = ref(false);
const showBatchRejectDialog = ref(false);
const batchApprovalComments = ref('');
const batchRejectionReason = ref('');

// Single reject dialog
const showSingleRejectDialog = ref(false);
const singleRejectClaim = ref(null);
const singleRejectionReason = ref('');

const statusOptions = [
  { title: 'All', value: null },
  { title: 'Submitted', value: 'SUBMITTED' },
  { title: 'Reviewing', value: 'REVIEWING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
];

const headers = [
  { title: 'Claim Number', value: 'claim_number' },
  { title: 'UTN', value: 'utn' },
  { title: 'Enrollee', value: 'enrollee' },
  { title: 'Facility', value: 'facility' },
  { title: 'Amount Claimed', value: 'total_amount_claimed' },
  { title: 'Status', value: 'status' },
  { title: 'Submitted Date', value: 'submitted_at' },
  { title: 'Actions', value: 'actions', sortable: false },
];

const reviewData = ref({
  decision: 'APPROVED',
  approved_amount: 0,
  comments: '',
});


// Function to open the modal and load claim details
const viewClaimDetails = async (claimId) => {
  try {
    // 1. Fetch the full claim details from the API
    // Assuming you have an API endpoint to get a single claim with all its relations
    // const response = await api.get(`/claims/${claimId}/full-details`); 
    const response = await api.get(`/claims-automation/claims/${claimId}/full-details`);
    
    // 2. Assign the fetched data to state
    selectedClaimForDetails.value = response.data?.data || response.data;
    
    // 3. Open the modal
    showClaimDetailsModal.value = true;
  } catch (error) {
    console.error('Error fetching claim details:', error);
  }
}

const loadClaims = async () => {
  loading.value = true;
  try {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (searchQuery.value) params.search = searchQuery.value;
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;

    const response = await claimsAPI.getAll(params);
    const payload = response.data?.data ?? response.data ?? [];
    claims.value = Array.isArray(payload) ? payload : (Array.isArray(payload.data) ? payload.data : []);
  } catch (err) {
    console.error('Failed to load claims:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadClaims();
});

const formatNumber = (num) => {
  return Number(num || 0).toLocaleString();
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};

const getStatusColor = (status) => {
  const colors = {
    'DRAFT': 'blue',
    'SUBMITTED': 'orange',
    'REVIEWING': 'purple',
    'APPROVED': 'green',
    'REJECTED': 'red',
  };
  return colors[status] || 'gray';
};

const reviewClaim = (claim) => {
  currentClaim.value = claim;
  reviewData.value = {
    decision: 'APPROVED',
    approved_amount: claim.total_amount_claimed,
    comments: '',
  };
  showReviewDialog.value = true;
};

const submitReview = async () => {
  try {
    await claimsAPI.reviewClaim(currentClaim.value.id, {
      status: reviewData.value.decision,
      approved_amount: reviewData.value.approved_amount,
      comments: reviewData.value.comments,
    });
    showReviewDialog.value = false;
    await loadClaims();
  } catch (err) {
    console.error('Failed to submit review:', err);
  }
};

const approveSingleClaim = async (claim) => {
  batchProcessing.value = true;
  try {
    await claimsAPI.batchApprove({
      claim_ids: [claim.id],
      comments: 'Approved',
    });
    await loadClaims();
  } catch (err) {
    console.error('Failed to approve claim:', err);
  } finally {
    batchProcessing.value = false;
  }
};

const rejectSingleClaim = (claim) => {
  singleRejectClaim.value = claim;
  singleRejectionReason.value = '';
  showSingleRejectDialog.value = true;
};

const confirmSingleReject = async () => {
  if (!singleRejectionReason.value) return;
  batchProcessing.value = true;
  try {
    await claimsAPI.batchReject({
      claim_ids: [singleRejectClaim.value.id],
      reason: singleRejectionReason.value,
    });
    showSingleRejectDialog.value = false;
    await loadClaims();
  } catch (err) {
    console.error('Failed to reject claim:', err);
  } finally {
    batchProcessing.value = false;
  }
};

const batchApprove = async () => {
  batchProcessing.value = true;
  try {
    await claimsAPI.batchApprove({
      claim_ids: selectedClaims.value,
      comments: batchApprovalComments.value,
    });
    showBatchApproveDialog.value = false;
    selectedClaims.value = [];
    batchApprovalComments.value = '';
    await loadClaims();
  } catch (err) {
    console.error('Failed to batch approve:', err);
  } finally {
    batchProcessing.value = false;
  }
};

const batchReject = async () => {
  if (!batchRejectionReason.value) return;
  batchProcessing.value = true;
  try {
    await claimsAPI.batchReject({
      claim_ids: selectedClaims.value,
      reason: batchRejectionReason.value,
    });
    showBatchRejectDialog.value = false;
    selectedClaims.value = [];
    batchRejectionReason.value = '';
    await loadClaims();
  } catch (err) {
    console.error('Failed to batch reject:', err);
  } finally {
    batchProcessing.value = false;
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
    console.error('Failed to download slip:', err);
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = 'SUBMITTED';
  dateFrom.value = '';
  dateTo.value = '';
  loadClaims();
};

const onClaimUpdated = () => {
  showClaimDetailsModal.value = false;
  selectedClaimForDetails.value = null;
  loadClaims();
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
    console.error('Failed to download slip:', err);
  }
};
</script>

<style scoped>
</style>
