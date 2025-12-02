<template>
  <v-dialog v-model="dialog" max-width="900" scrollable>
    <v-card>
      <v-card-title class="tw-bg-blue-600 tw-text-white tw-flex tw-items-center tw-justify-between">
        <div class="tw-flex tw-items-center">
          <v-icon class="tw-mr-2">mdi-file-document-outline</v-icon>
          Claim Preview
        </div>
        <v-btn icon variant="text" color="white" @click="dialog = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-card-text class="tw-p-6">
        <!-- Header Info -->
        <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4 tw-mb-6 tw-pb-4 tw-border-b">
          <div>
            <div class="tw-text-sm tw-text-gray-500">Claim ID</div>
            <div class="tw-font-mono tw-font-medium">{{ claim?.claim_number || claim?.id }}</div>
          </div>
          <div>
            <div class="tw-text-sm tw-text-gray-500">Patient</div>
            <div class="tw-font-medium">{{ claim?.enrollee?.full_name }}</div>
          </div>
          <div>
            <div class="tw-text-sm tw-text-gray-500">Facility</div>
            <div class="tw-font-medium">{{ claim?.facility?.name }}</div>
          </div>
          <div>
            <div class="tw-text-sm tw-text-gray-500">Date</div>
            <div class="tw-font-medium">{{ formatDate(claim?.created_at) }}</div>
          </div>
        </div>

        <!-- Section A: Principal Bundle -->
        <div class="tw-mb-6">
          <div class="tw-flex tw-items-center tw-mb-3">
            <div class="tw-w-8 tw-h-8 tw-rounded-full tw-bg-blue-600 tw-text-white tw-flex tw-items-center tw-justify-center tw-font-bold tw-mr-2">A</div>
            <h3 class="tw-text-lg tw-font-semibold">Section A: Principal Bundle</h3>
          </div>
          <v-card variant="outlined" class="tw-bg-blue-50">
            <v-card-text v-if="sectionA">
              <div class="tw-flex tw-justify-between tw-items-start">
                <div>
                  <div class="tw-font-semibold">{{ sectionA.bundle?.bundle_name || 'Bundle' }}</div>
                  <div class="tw-text-sm tw-text-gray-600">{{ sectionA.bundle?.bundle_code }}</div>
                  <div class="tw-text-sm tw-text-gray-500 tw-mt-1">
                    ICD-10: {{ sectionA.bundle?.icd_10_primary }}
                  </div>
                </div>
                <div class="tw-text-right">
                  <div class="tw-text-2xl tw-font-bold tw-text-blue-600">
                    ₦{{ formatNumber(sectionA.amount) }}
                  </div>
                </div>
              </div>
            </v-card-text>
            <v-card-text v-else class="tw-text-center tw-text-gray-500">
              No bundle assigned
            </v-card-text>
          </v-card>
        </div>

        <!-- Section B: FFS Top-ups -->
        <div class="tw-mb-6">
          <div class="tw-flex tw-items-center tw-mb-3">
            <div class="tw-w-8 tw-h-8 tw-rounded-full tw-bg-teal-600 tw-text-white tw-flex tw-items-center tw-justify-center tw-font-bold tw-mr-2">B</div>
            <h3 class="tw-text-lg tw-font-semibold">Section B: Fee-for-Service Top-ups</h3>
          </div>
          <v-card variant="outlined" class="tw-bg-teal-50">
            <v-card-text v-if="sectionB?.line_items?.length">
              <v-table density="compact">
                <thead>
                  <tr>
                    <th>Service</th>
                    <th>Reason</th>
                    <th>Linked Dx</th>
                    <th class="tw-text-right">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in sectionB.line_items" :key="item.id">
                    <td>{{ item.service_description }}</td>
                    <td><v-chip size="x-small" color="teal">{{ item.ffs_reason }}</v-chip></td>
                    <td>{{ item.linked_diagnosis_code || '-' }}</td>
                    <td class="tw-text-right">₦{{ formatNumber(item.total_amount) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="tw-font-bold">
                    <td colspan="3">Total FFS</td>
                    <td class="tw-text-right">₦{{ formatNumber(sectionB.amount) }}</td>
                  </tr>
                </tfoot>
              </v-table>
            </v-card-text>
            <v-card-text v-else class="tw-text-center tw-text-gray-500">
              No FFS items
            </v-card-text>
          </v-card>
        </div>

        <!-- Section C: Medical Justification -->
        <div class="tw-mb-6">
          <div class="tw-flex tw-items-center tw-mb-3">
            <div class="tw-w-8 tw-h-8 tw-rounded-full tw-bg-purple-600 tw-text-white tw-flex tw-items-center tw-justify-center tw-font-bold tw-mr-2">C</div>
            <h3 class="tw-text-lg tw-font-semibold">Section C: Medical Justification</h3>
          </div>
          <v-card variant="outlined" class="tw-bg-purple-50">
            <v-card-text v-if="sectionC">
              <div class="tw-prose tw-prose-sm tw-max-w-none">
                <div v-html="sectionC.justification_text || 'No justification generated'"></div>
              </div>
            </v-card-text>
            <v-card-text v-else class="tw-text-center tw-text-gray-500">
              No justification generated
            </v-card-text>
          </v-card>
        </div>

        <!-- Grand Total -->
        <div class="tw-bg-gray-100 tw-rounded-lg tw-p-4 tw-flex tw-justify-between tw-items-center">
          <div class="tw-text-lg tw-font-semibold">Grand Total</div>
          <div class="tw-text-3xl tw-font-bold tw-text-green-600">
            ₦{{ formatNumber(grandTotal) }}
          </div>
        </div>
      </v-card-text>

      <v-card-actions class="tw-px-6 tw-pb-4">
        <v-btn variant="outlined" @click="printClaim">
          <v-icon left>mdi-printer</v-icon>
          Print
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Close</v-btn>
        <v-btn color="primary">Submit Claim</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({ modelValue: Boolean, claim: Object })
const emit = defineEmits(['update:modelValue'])

const dialog = ref(props.modelValue)
watch(() => props.modelValue, (val) => dialog.value = val)
watch(dialog, (val) => emit('update:modelValue', val))

const sectionA = computed(() => props.claim?.sections?.find(s => s.section_type === 'A'))
const sectionB = computed(() => props.claim?.sections?.find(s => s.section_type === 'B'))
const sectionC = computed(() => props.claim?.sections?.find(s => s.section_type === 'C'))

const grandTotal = computed(() => (sectionA.value?.amount || 0) + (sectionB.value?.amount || 0))

const formatNumber = (num) => num?.toLocaleString() || '0'
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : '-'
const printClaim = () => window.print()
</script>

