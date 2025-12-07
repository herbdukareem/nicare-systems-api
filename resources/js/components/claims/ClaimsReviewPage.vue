<template>
  <div class="claims-review-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white">
              <v-icon left>mdi-clipboard-check</v-icon>
              Claims Review & Approval
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search by claim number"
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
                    Reset
                  </v-btn>
                </v-col>
              </v-row>

              <!-- Claims Table -->
              <v-data-table
                :headers="headers"
                :items="filteredClaims"
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
                    @click="reviewClaim(item)"
                  >
                    <v-icon>mdi-eye</v-icon>
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
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '../../composables/useToast';
import { useClaimsAPI } from '../../composables/useClaimsAPI';
import { useClaimsStore } from '../../stores/claimsStore';

const { showSuccess, showError } = useToast();
const { fetchClaims, updateClaim, loading } = useClaimsAPI();
const claimsStore = useClaimsStore();

const searchQuery = ref('');
const statusFilter = ref(null);
const showReviewDialog = ref(false);
const currentClaim = ref(null);
const approvalForm = ref(null);

const statusOptions = [
  { title: 'Submitted', value: 'SUBMITTED' },
  { title: 'Reviewing', value: 'REVIEWING' },
  { title: 'Approved', value: 'APPROVED' },
  { title: 'Rejected', value: 'REJECTED' },
];

const headers = [
  { title: 'Claim Number', value: 'claim_number' },
  { title: 'Amount Claimed', value: 'total_amount_claimed' },
  { title: 'Status', value: 'status' },
  { title: 'Submitted Date', value: 'submitted_at' },
  { title: 'Actions', value: 'actions' },
];

const reviewData = ref({
  decision: 'APPROVED',
  approved_amount: 0,
  comments: '',
});

const filteredClaims = computed(() => {
  return claimsStore.claims.filter(claim => {
    const matchesSearch = !searchQuery.value || 
      claim.claim_number?.toLowerCase().includes(searchQuery.value.toLowerCase());
    
    const matchesStatus = !statusFilter.value || claim.status === statusFilter.value;
    
    return matchesSearch && matchesStatus;
  });
});

onMounted(async () => {
  try {
    await fetchClaims({ status: 'SUBMITTED' });
  } catch (err) {
    showError('Failed to load claims');
  }
});

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
  if (!approvalForm.value.validate()) return;

  try {
    await updateClaim(currentClaim.value.id, {
      status: reviewData.value.decision,
      approved_amount: reviewData.value.approved_amount,
      approval_comments: reviewData.value.comments,
      approved_at: new Date().toISOString(),
    });
    showSuccess(`Claim ${reviewData.value.decision.toLowerCase()} successfully`);
    showReviewDialog.value = false;
    await fetchClaims({ status: 'SUBMITTED' });
  } catch (err) {
    showError(err.message || 'Failed to submit review');
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
};
</script>

<style scoped>
.claims-review-page {
  padding: 20px 0;
}
</style>

