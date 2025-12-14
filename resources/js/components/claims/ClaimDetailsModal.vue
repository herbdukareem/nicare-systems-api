<template>
  <v-card class="claim-details-modal">
    <v-toolbar color="primary" dark>
      <v-toolbar-title>
        <v-icon class="mr-2">mdi-file-document-outline</v-icon>
        Claim #{{ claimData?.claim_number }}
      </v-toolbar-title>
      <v-spacer />
      <v-chip :color="getStatusColor(claimData?.status)" class="mr-3" variant="elevated">
        {{ claimData?.status }}
      </v-chip>
      <v-btn icon @click="$emit('close')">
        <v-icon>mdi-close</v-icon>
      </v-btn>
    </v-toolbar>

    <v-card-text v-if="claimData" class="pa-4" style="max-height: 70vh; overflow-y: auto;">
      <!-- Enrollee, Facility, Dates Grid -->
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="h-100">
            <v-card-title class="text-subtitle-1 bg-grey-lighten-4">
              <v-icon class="mr-2" size="small">mdi-account</v-icon>
              Enrollee Details
            </v-card-title>
            <v-card-text>
              <div class="d-flex flex-column gap-1">
                <div><strong>Name:</strong> {{ claimData.enrollee?.full_name }}</div>
                <div><strong>ID:</strong> {{ claimData.enrollee?.enrollee_id }}</div>
                <div><strong>Phone:</strong> {{ claimData.enrollee?.phone || 'N/A' }}</div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="h-100">
            <v-card-title class="text-subtitle-1 bg-grey-lighten-4">
              <v-icon class="mr-2" size="small">mdi-hospital-building</v-icon>
              Facility Details
            </v-card-title>
            <v-card-text>
              <div class="d-flex flex-column gap-1">
                <div><strong>Name:</strong> {{ claimData.facility?.name }}</div>
                <div><strong>HCP Code:</strong> {{ claimData.facility?.hcp_code }}</div>
                <div><strong>Type:</strong> {{ claimData.facility?.type }}</div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="outlined" class="h-100">
            <v-card-title class="text-subtitle-1 bg-grey-lighten-4">
              <v-icon class="mr-2" size="small">mdi-calendar</v-icon>
              Claim Dates
            </v-card-title>
            <v-card-text>
              <div class="d-flex flex-column gap-1">
                <div><strong>Service Date:</strong> {{ formatDate(claimData.service_date) }}</div>
                <div><strong>Submitted:</strong> {{ formatDate(claimData.submitted_at) }}</div>
                <div><strong>Claim Date:</strong> {{ formatDate(claimData.claim_date) }}</div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Financial Summary -->
      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1 bg-blue-lighten-5">
          <v-icon class="mr-2" size="small" color="primary">mdi-currency-ngn</v-icon>
          Financial Summary
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="6" md="3">
              <div class="text-caption text-grey">Total Claimed</div>
              <div class="text-h6">{{ formatCurrency(claimData.total_amount_claimed) }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-grey">Bundle Amount</div>
              <div class="text-h6">{{ formatCurrency(claimData.bundle_amount) }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-grey">FFS Amount</div>
              <div class="text-h6">{{ formatCurrency(claimData.ffs_amount) }}</div>
            </v-col>
            <v-col cols="6" md="3">
              <div class="text-caption text-grey">Approved Amount</div>
              <div class="text-h6 text-success">{{ formatCurrency(computedApprovedAmount) }}</div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Referral & Service Bundle -->
      <v-card v-if="claimData.referral" variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1 bg-grey-lighten-4">
          <v-icon class="mr-2" size="small">mdi-file-send</v-icon>
          Referral & Service Bundle
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="4">
              <strong>Referral Code:</strong> {{ claimData.referral.referral_code }}
            </v-col>
            <v-col cols="12" md="4">
              <strong>UTN:</strong> {{ claimData.referral.utn }}
              <v-chip size="x-small" :color="claimData.referral.utn_validated ? 'success' : 'warning'" class="ml-1">
                {{ claimData.referral.utn_validated ? 'Validated' : 'Not Validated' }}
              </v-chip>
            </v-col>
            <v-col cols="12" md="4">
              <strong>Bundle:</strong> {{ claimData.referral.service_bundle?.description || 'N/A' }}
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Claimed Line Items - Editable -->
      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1 bg-amber-lighten-5 d-flex align-center">
          <v-icon class="mr-2" size="small" color="amber-darken-2">mdi-format-list-bulleted</v-icon>
          Claimed Line Items
          <v-spacer />
          <v-chip v-if="isReviewMode" size="small" color="info" variant="flat">
            <v-icon start size="small">mdi-pencil</v-icon>
            Review Mode - Edit quantities or exclude items
          </v-chip>
        </v-card-title>
        <v-card-text class="pa-0">
          <v-table density="compact">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th v-if="isReviewMode" class="text-center" style="width: 60px;">Include</th>
                <th>Service/Item Name</th>
                <th>Type</th>
                <th class="text-center" style="width: 120px;">Quantity</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in editableLineItems" :key="item.id"
                  :class="{ 'bg-red-lighten-5': !item.included }">
                <td v-if="isReviewMode" class="text-center">
                  <v-checkbox
                    v-model="item.included"
                    hide-details
                    density="compact"
                    color="success"
                  />
                </td>
                <td>{{ getLineItemName(item) }}</td>
                <td>
                  <v-chip size="x-small" :color="getServiceTypeColor(item.service_type)">
                    {{ item.service_type }}
                  </v-chip>
                </td>
                <td class="text-center">
                  <v-text-field
                    v-if="isReviewMode && item.included"
                    v-model.number="item.approved_quantity"
                    type="number"
                    min="1"
                    :max="item.quantity"
                    density="compact"
                    hide-details
                    variant="outlined"
                    style="max-width: 80px; margin: auto;"
                    @update:model-value="recalculateTotal"
                  />
                  <span v-else>{{ item.quantity }}</span>
                </td>
                <td class="text-right">{{ formatCurrency(item.unit_price) }}</td>
                <td class="text-right font-weight-medium">
                  {{ formatCurrency(item.included ? (item.approved_quantity * item.unit_price) : 0) }}
                </td>
              </tr>
              <tr v-if="!editableLineItems.length">
                <td :colspan="isReviewMode ? 6 : 5" class="text-center text-grey py-4">
                  No line items found
                </td>
              </tr>
            </tbody>
            <tfoot v-if="isReviewMode && editableLineItems.length" class="bg-grey-lighten-3">
              <tr>
                <td :colspan="isReviewMode ? 5 : 4" class="text-right font-weight-bold">Adjusted Total:</td>
                <td class="text-right font-weight-bold text-primary">{{ formatCurrency(adjustedLineItemsTotal) }}</td>
              </tr>
            </tfoot>
          </v-table>
        </v-card-text>
      </v-card>

      <!-- Bundle Components -->
      <v-card v-if="claimData.bundle_components?.length" variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1 bg-purple-lighten-5">
          <v-icon class="mr-2" size="small" color="purple">mdi-package-variant</v-icon>
          Bundle Definition Components
        </v-card-title>
        <v-card-text class="pa-0">
          <v-table density="compact">
            <thead>
              <tr class="bg-grey-lighten-4">
                <th>Item Name</th>
                <th>Type</th>
                <th class="text-center">Max Quantity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="component in claimData.bundle_components" :key="component.id">
                <td>{{ component.item_name }}</td>
                <td>{{ component.item_type }}</td>
                <td class="text-center">{{ component.max_quantity }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>

      <!-- Approval/Rejection Form (only for reviewable claims) -->
      <v-card v-if="isReviewMode" variant="outlined" class="mb-4" color="primary">
        <v-card-title class="text-subtitle-1">
          <v-icon class="mr-2" size="small">mdi-clipboard-check</v-icon>
          Review Decision
        </v-card-title>
        <v-card-text>
          <v-form ref="reviewForm">
            <v-row>
              <v-col cols="12">
                <v-textarea
                  v-model="reviewComments"
                  label="Review Comments / Feedback"
                  placeholder="Enter your observations, reasons for approval/rejection, or any adjustments made..."
                  rows="3"
                  variant="outlined"
                  :rules="[v => reviewDecision !== 'REJECTED' || !!v || 'Comments are required for rejection']"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-card-text>

    <v-divider />

    <!-- Actions Footer -->
    <v-card-actions class="pa-4 bg-grey-lighten-4">
      <v-btn variant="outlined" @click="$emit('download-slip', claimData.id)">
        <v-icon start>mdi-download</v-icon>
        Download Slip
      </v-btn>
      <v-spacer />
      <v-btn variant="text" @click="$emit('close')">Close</v-btn>

      <template v-if="isReviewMode">
        <v-btn
          color="error"
          variant="elevated"
          :loading="processing"
          @click="submitDecision('REJECTED')"
        >
          <v-icon start>mdi-close-circle</v-icon>
          Reject Claim
        </v-btn>
        <v-btn
          color="success"
          variant="elevated"
          :loading="processing"
          @click="submitDecision('APPROVED')"
        >
          <v-icon start>mdi-check-circle</v-icon>
          Approve Claim ({{ formatCurrency(computedApprovedAmount) }})
        </v-btn>
      </template>
    </v-card-actions>

    <!-- Confirmation Dialog -->
    <v-dialog v-model="showConfirmDialog" max-width="450">
      <v-card>
        <v-card-title :class="confirmDecision === 'APPROVED' ? 'bg-success text-white' : 'bg-error text-white'">
          <v-icon class="mr-2">{{ confirmDecision === 'APPROVED' ? 'mdi-check-circle' : 'mdi-close-circle' }}</v-icon>
          Confirm {{ confirmDecision === 'APPROVED' ? 'Approval' : 'Rejection' }}
        </v-card-title>
        <v-card-text class="pt-4">
          <p v-if="confirmDecision === 'APPROVED'">
            You are about to <strong>approve</strong> this claim for
            <strong class="text-success">{{ formatCurrency(computedApprovedAmount) }}</strong>.
          </p>
          <p v-else>
            You are about to <strong class="text-error">reject</strong> this claim.
          </p>
          <v-alert v-if="excludedItemsCount > 0" type="info" variant="tonal" class="mt-3" density="compact">
            <strong>{{ excludedItemsCount }}</strong> line item(s) will be excluded from the final amount.
          </v-alert>
          <v-alert v-if="adjustedItemsCount > 0" type="warning" variant="tonal" class="mt-3" density="compact">
            <strong>{{ adjustedItemsCount }}</strong> line item(s) have adjusted quantities.
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showConfirmDialog = false">Cancel</v-btn>
          <v-btn
            :color="confirmDecision === 'APPROVED' ? 'success' : 'error'"
            variant="elevated"
            :loading="processing"
            @click="confirmSubmitDecision"
          >
            Confirm {{ confirmDecision === 'APPROVED' ? 'Approval' : 'Rejection' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { claimsAPI } from '../../utils/api';

const props = defineProps({
  claimData: {
    type: Object,
    required: true,
    default: null
  }
});

const emit = defineEmits(['close', 'download-slip', 'claim-updated']);

// State
const processing = ref(false);
const reviewComments = ref('');
const showConfirmDialog = ref(false);
const confirmDecision = ref(null);
const editableLineItems = ref([]);

// Computed
const isReviewMode = computed(() => {
  return ['SUBMITTED', 'REVIEWING'].includes(props.claimData?.status);
});

const adjustedLineItemsTotal = computed(() => {
  return editableLineItems.value
    .filter(item => item.included)
    .reduce((sum, item) => sum + (item.approved_quantity * parseFloat(item.unit_price || 0)), 0);
});

const computedApprovedAmount = computed(() => {
  if (isReviewMode.value) {
    // Bundle amount + adjusted FFS items
    const bundleAmount = parseFloat(props.claimData?.bundle_amount || 0);
    return bundleAmount + adjustedLineItemsTotal.value;
  }
  return props.claimData?.total_amount_approved || props.claimData?.total_amount_claimed || 0;
});

const excludedItemsCount = computed(() => {
  return editableLineItems.value.filter(item => !item.included).length;
});

const adjustedItemsCount = computed(() => {
  return editableLineItems.value.filter(item => item.included && item.approved_quantity !== item.quantity).length;
});

// Initialize editable line items when claimData changes
watch(() => props.claimData, (newData) => {
  if (newData?.line_items) {
    editableLineItems.value = newData.line_items.map(item => ({
      ...item,
      included: true,
      approved_quantity: item.quantity
    }));
  }
}, { immediate: true });

// Methods
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatCurrency = (value) => {
  const amount = parseFloat(value);
  if (isNaN(amount)) return '₦0.00';
  return '₦' + amount.toLocaleString('en-NG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const getLineItemName = (item) => {
  return item.case_record?.case_name || item.service_description || 'Unknown Service';
};

const getStatusColor = (status) => {
  const colors = {
    'DRAFT': 'blue',
    'SUBMITTED': 'orange',
    'REVIEWING': 'purple',
    'APPROVED': 'success',
    'REJECTED': 'error',
  };
  return colors[status] || 'grey';
};

const getServiceTypeColor = (type) => {
  const colors = {
    'drug': 'teal',
    'laboratory': 'indigo',
    'professional': 'deep-purple',
    'radiology': 'cyan',
    'consultation': 'blue',
    'consumable': 'orange',
    'service': 'primary',
  };
  return colors[type?.toLowerCase()] || 'grey';
};

const recalculateTotal = () => {
  // Trigger reactivity for adjustedLineItemsTotal
};

const submitDecision = async (decision) => {
  // Validate comments for rejection
  if (decision === 'REJECTED' && !reviewComments.value.trim()) {
    alert('Please provide comments for rejection.');
    return;
  }

  confirmDecision.value = decision;
  showConfirmDialog.value = true;
};

const confirmSubmitDecision = async () => {
  processing.value = true;

  try {
    // Prepare line item adjustments
    const lineItemAdjustments = editableLineItems.value.map(item => ({
      id: item.id,
      included: item.included,
      approved_quantity: item.included ? item.approved_quantity : 0,
    }));

    const payload = {
      status: confirmDecision.value,
      approved_amount: computedApprovedAmount.value,
      comments: reviewComments.value,
      line_item_adjustments: lineItemAdjustments,
    };

    await claimsAPI.reviewClaim(props.claimData.id, payload);

    showConfirmDialog.value = false;
    emit('claim-updated');
    emit('close');
  } catch (error) {
    console.error('Failed to submit review:', error);
    alert('Failed to submit review. Please try again.');
  } finally {
    processing.value = false;
  }
};
</script>

<style scoped>
.claim-details-modal {
  overflow: hidden;
}

.h-100 {
  height: 100%;
}

.gap-1 {
  gap: 0.25rem;
}
</style>