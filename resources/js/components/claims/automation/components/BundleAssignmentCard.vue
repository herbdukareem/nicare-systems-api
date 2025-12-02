<template>
  <v-card>
    <v-card-title class="tw-bg-indigo-50 tw-text-indigo-800">
      <v-icon class="tw-mr-2">mdi-package-variant</v-icon>
      Bundle Assignment
    </v-card-title>

    <v-card-text class="tw-p-4">
      <!-- Current Bundle -->
      <div v-if="claim?.principal_bundle" class="tw-mb-4">
        <div class="tw-text-sm tw-text-gray-500 tw-mb-1">Principal Bundle</div>
        <div class="tw-bg-indigo-50 tw-rounded-lg tw-p-3">
          <div class="tw-flex tw-items-center tw-justify-between">
            <div>
              <div class="tw-font-semibold tw-text-indigo-800">
                {{ claim.principal_bundle.bundle_name }}
              </div>
              <div class="tw-text-sm tw-text-indigo-600">
                {{ claim.principal_bundle.bundle_code }}
              </div>
            </div>
            <v-chip color="indigo" size="small">
              ₦{{ formatNumber(claim.principal_bundle.tariff_amount) }}
            </v-chip>
          </div>
          <div class="tw-mt-2 tw-text-sm tw-text-indigo-600">
            <v-icon size="small">mdi-stethoscope</v-icon>
            ICD-10: {{ claim.principal_bundle.icd_10_primary }}
          </div>
        </div>
      </div>

      <!-- No Bundle Assigned -->
      <div v-else class="tw-text-center tw-py-4">
        <v-icon size="48" color="grey-lighten-1">mdi-package-variant-closed</v-icon>
        <div class="tw-text-gray-500 tw-mt-2">No bundle assigned yet</div>
        <div class="tw-text-sm tw-text-gray-400">Process the claim to auto-assign bundle</div>
      </div>

      <!-- One Bundle Rule Info -->
      <v-alert type="info" variant="tonal" density="compact" class="tw-mt-4">
        <template #prepend>
          <v-icon size="small">mdi-information</v-icon>
        </template>
        <div class="tw-text-xs">
          <strong>One Bundle Rule:</strong> Only one principal bundle per admission. 
          Additional treatments are billed as Fee-for-Service.
        </div>
      </v-alert>

      <!-- Process Button -->
      <v-btn 
        block 
        color="indigo" 
        class="tw-mt-4" 
        :loading="processing"
        @click="$emit('process')"
      >
        <v-icon left>mdi-cog-sync</v-icon>
        {{ claim?.principal_bundle ? 'Re-Process Claim' : 'Process & Assign Bundle' }}
      </v-btn>

      <!-- Bundle Details (if assigned) -->
      <div v-if="claim?.principal_bundle" class="tw-mt-4 tw-pt-4 tw-border-t">
        <div class="tw-text-sm tw-font-medium tw-mb-2">Bundle Includes:</div>
        <div class="tw-text-sm tw-text-gray-600 tw-space-y-1">
          <div v-for="(item, idx) in bundleInclusions" :key="idx" class="tw-flex tw-items-center">
            <v-icon size="small" color="success" class="tw-mr-1">mdi-check</v-icon>
            {{ item }}
          </div>
        </div>
        
        <div v-if="bundleExclusions.length" class="tw-mt-3">
          <div class="tw-text-sm tw-font-medium tw-mb-2">Excludes (FFS):</div>
          <div class="tw-text-sm tw-text-gray-600 tw-space-y-1">
            <div v-for="(item, idx) in bundleExclusions" :key="idx" class="tw-flex tw-items-center">
              <v-icon size="small" color="warning" class="tw-mr-1">mdi-minus</v-icon>
              {{ item }}
            </div>
          </div>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  claim: Object,
  processing: Boolean
})

defineEmits(['process'])

const formatNumber = (num) => num?.toLocaleString() || '0'

const bundleInclusions = computed(() => {
  const inclusions = props.claim?.principal_bundle?.included_services
  if (typeof inclusions === 'string') {
    try { return JSON.parse(inclusions) } 
    catch { return inclusions.split(',').map(s => s.trim()) }
  }
  return inclusions || ['Standard treatment package', 'Medications', 'Ward stay']
})

const bundleExclusions = computed(() => {
  const exclusions = props.claim?.principal_bundle?.excluded_services
  if (typeof exclusions === 'string') {
    try { return JSON.parse(exclusions) } 
    catch { return exclusions.split(',').map(s => s.trim()) }
  }
  return exclusions || []
})
</script>

