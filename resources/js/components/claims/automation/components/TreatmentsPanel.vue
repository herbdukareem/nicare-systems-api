<template>
  <v-card>
    <v-card-title class="tw-bg-teal-50 tw-text-teal-800 tw-flex tw-items-center tw-justify-between">
      <div class="tw-flex tw-items-center">
        <v-icon class="tw-mr-2">mdi-medical-bag</v-icon>
        Treatments & Services
      </div>
      <div class="tw-flex tw-gap-2">
        <v-chip size="small" color="success" variant="outlined">
          Bundle: {{ bundleCount }}
        </v-chip>
        <v-chip size="small" color="info" variant="outlined">
          FFS: {{ ffsCount }}
        </v-chip>
      </div>
    </v-card-title>

    <v-card-text class="tw-p-0">
      <v-data-table
        :headers="headers"
        :items="claim?.treatments || []"
        :items-per-page="10"
        density="comfortable"
      >
        <template #item.service_description="{ item }">
          <div>
            <div class="tw-font-medium">{{ item.service_description }}</div>
            <div class="tw-text-sm tw-text-gray-500">{{ item.service_type }}</div>
          </div>
        </template>

        <template #item.classification="{ item }">
          <v-chip :color="getClassificationColor(item.is_bundle_item, item.is_ffs)" size="small">
            {{ item.is_bundle_item ? 'Bundle' : item.is_ffs ? 'FFS' : 'Pending' }}
          </v-chip>
          <div v-if="item.ffs_reason" class="tw-text-xs tw-text-gray-500 tw-mt-1">
            {{ item.ffs_reason }}
          </div>
        </template>

        <template #item.linked_diagnosis_code="{ item }">
          <v-chip v-if="item.linked_diagnosis_code" size="x-small" color="purple" variant="outlined">
            {{ item.linked_diagnosis_code }}
          </v-chip>
          <span v-else class="tw-text-gray-400">-</span>
        </template>

        <template #item.amount="{ item }">
          <div class="tw-text-right">
            <div class="tw-font-medium">₦{{ formatNumber(item.total_amount) }}</div>
            <div v-if="item.unit_price" class="tw-text-sm tw-text-gray-500">
              {{ item.quantity }} × ₦{{ formatNumber(item.unit_price) }}
            </div>
          </div>
        </template>

        <template #item.pa_status="{ item }">
          <div class="tw-flex tw-items-center tw-gap-1">
            <v-icon v-if="item.pa_code_line_item_id" color="success" size="small">mdi-check-circle</v-icon>
            <v-icon v-else-if="item.is_ffs" color="warning" size="small">mdi-alert</v-icon>
            <span class="tw-text-sm">{{ item.pa_code_line_item_id ? 'Covered' : item.is_ffs ? 'Needs PA' : '-' }}</span>
          </div>
        </template>

        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props }">
              <v-btn icon size="small" variant="text" v-bind="props">
                <v-icon>mdi-dots-vertical</v-icon>
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item v-if="item.is_bundle_item" @click="convertToFFS(item)">
                <v-list-item-title>Convert to FFS</v-list-item-title>
              </v-list-item>
              <v-list-item @click="editTreatment(item)">
                <v-list-item-title>Edit</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>
      </v-data-table>
    </v-card-text>

    <!-- Convert to FFS Dialog -->
    <v-dialog v-model="showConvertDialog" max-width="400">
      <v-card>
        <v-card-title>Convert to Fee-for-Service</v-card-title>
        <v-card-text>
          <p class="tw-mb-4">Select the reason for converting this treatment to FFS:</p>
          <v-select
            v-model="convertReason"
            :items="ffsReasons"
            label="FFS Reason"
            variant="outlined"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showConvertDialog = false">Cancel</v-btn>
          <v-btn color="warning" :loading="converting" @click="confirmConvert">Convert</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed } from 'vue'
import { claimsAutomationAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'

const props = defineProps({ claim: Object })
const emit = defineEmits(['updated'])

const toast = useToast()
const showConvertDialog = ref(false)
const selectedTreatment = ref(null)
const convertReason = ref('complication')
const converting = ref(false)

const headers = [
  { title: 'Service', key: 'service_description', sortable: true },
  { title: 'Classification', key: 'classification', sortable: false },
  { title: 'Linked Dx', key: 'linked_diagnosis_code', sortable: false },
  { title: 'Amount', key: 'amount', align: 'end', sortable: true },
  { title: 'PA Status', key: 'pa_status', sortable: false },
  { title: '', key: 'actions', sortable: false, width: 50 }
]

const ffsReasons = [
  { title: 'Complication', value: 'complication' },
  { title: 'Not in Bundle', value: 'not_in_bundle' },
  { title: 'Extended Stay', value: 'extended_stay' },
  { title: 'Emergency Add-on', value: 'emergency_addon' }
]

const bundleCount = computed(() => props.claim?.treatments?.filter(t => t.is_bundle_item)?.length || 0)
const ffsCount = computed(() => props.claim?.treatments?.filter(t => t.is_ffs)?.length || 0)

const getClassificationColor = (isBundle, isFFS) => isBundle ? 'success' : isFFS ? 'info' : 'grey'
const formatNumber = (num) => num?.toLocaleString() || '0'

const convertToFFS = (treatment) => { selectedTreatment.value = treatment; showConvertDialog.value = true }
const editTreatment = (treatment) => { /* TODO: implement edit */ console.log('Edit', treatment) }

const confirmConvert = async () => {
  if (!selectedTreatment.value) return
  converting.value = true
  try {
    await claimsAutomationAPI.convertToFFS(selectedTreatment.value.id, { reason: convertReason.value })
    toast.add({ severity: 'success', summary: 'Success', detail: 'Converted to FFS', life: 3000 })
    showConvertDialog.value = false
    emit('updated')
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Conversion failed', life: 5000 })
  } finally { converting.value = false }
}
</script>

