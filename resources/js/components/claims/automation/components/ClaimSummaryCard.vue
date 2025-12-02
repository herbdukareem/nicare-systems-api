<template>
  <v-card>
    <v-card-title class="tw-bg-green-50 tw-text-green-800">
      <v-icon class="tw-mr-2">mdi-calculator</v-icon>
      Claim Summary
    </v-card-title>

    <v-card-text class="tw-p-4 tw-space-y-3">
      <!-- Section A: Bundle -->
      <div class="tw-flex tw-justify-between tw-items-center tw-pb-2 tw-border-b">
        <div>
          <div class="tw-text-sm tw-text-gray-500">Section A: Bundle</div>
          <div v-if="claim?.principal_bundle" class="tw-text-xs tw-text-gray-400">
            {{ claim.principal_bundle.bundle_code }}
          </div>
        </div>
        <div class="tw-text-right">
          <div class="tw-font-semibold">₦{{ formatNumber(bundleAmount) }}</div>
        </div>
      </div>

      <!-- Section B: FFS Top-ups -->
      <div class="tw-flex tw-justify-between tw-items-center tw-pb-2 tw-border-b">
        <div>
          <div class="tw-text-sm tw-text-gray-500">Section B: FFS Top-ups</div>
          <div class="tw-text-xs tw-text-gray-400">{{ ffsItemsCount }} items</div>
        </div>
        <div class="tw-text-right">
          <div class="tw-font-semibold">₦{{ formatNumber(ffsAmount) }}</div>
        </div>
      </div>

      <!-- Total -->
      <div class="tw-flex tw-justify-between tw-items-center tw-pt-2">
        <div class="tw-font-semibold tw-text-lg">Total Claim</div>
        <div class="tw-text-right">
          <div class="tw-font-bold tw-text-xl tw-text-green-600">₦{{ formatNumber(totalAmount) }}</div>
        </div>
      </div>

      <!-- Claim Status -->
      <div class="tw-mt-4 tw-pt-4 tw-border-t">
        <div class="tw-flex tw-justify-between tw-items-center">
          <span class="tw-text-sm tw-text-gray-500">Status</span>
          <v-chip :color="getStatusColor(claim?.status)" size="small">
            {{ formatStatus(claim?.status) }}
          </v-chip>
        </div>
        <div v-if="claim?.sections?.length" class="tw-flex tw-justify-between tw-items-center tw-mt-2">
          <span class="tw-text-sm tw-text-gray-500">Sections Built</span>
          <v-chip color="success" size="x-small" variant="outlined">
            <v-icon size="small">mdi-check</v-icon>
            {{ claim.sections.length }} sections
          </v-chip>
        </div>
      </div>

      <!-- Validation Status -->
      <div v-if="claim?.is_validated" class="tw-mt-2">
        <v-alert type="success" density="compact" variant="tonal">
          <v-icon size="small">mdi-check-decagram</v-icon>
          Claim validated
        </v-alert>
      </div>

      <!-- Pending Items Warning -->
      <div v-if="pendingItemsCount > 0" class="tw-mt-2">
        <v-alert type="warning" density="compact" variant="tonal">
          {{ pendingItemsCount }} treatment(s) pending classification
        </v-alert>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ claim: Object })

const bundleAmount = computed(() => {
  if (!props.claim?.treatments) return 0
  return props.claim.treatments
    .filter(t => t.is_bundle_item)
    .reduce((sum, t) => sum + (t.total_amount || 0), 0)
})

const ffsAmount = computed(() => {
  if (!props.claim?.treatments) return 0
  return props.claim.treatments
    .filter(t => t.is_ffs)
    .reduce((sum, t) => sum + (t.total_amount || 0), 0)
})

const totalAmount = computed(() => bundleAmount.value + ffsAmount.value)

const ffsItemsCount = computed(() => 
  props.claim?.treatments?.filter(t => t.is_ffs)?.length || 0
)

const pendingItemsCount = computed(() => 
  props.claim?.treatments?.filter(t => !t.is_bundle_item && !t.is_ffs)?.length || 0
)

const formatNumber = (num) => num?.toLocaleString() || '0'

const getStatusColor = (status) => ({
  draft: 'grey',
  submitted: 'info',
  doctor_review: 'warning',
  pharmacist_review: 'warning',
  claim_review: 'warning',
  claim_confirmed: 'info',
  claim_approved: 'success',
  paid: 'success',
  rejected: 'error'
}[status] || 'grey')

const formatStatus = (status) => 
  status?.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) || 'Unknown'
</script>

