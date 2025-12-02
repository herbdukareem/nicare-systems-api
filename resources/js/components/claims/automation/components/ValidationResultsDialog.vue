<template>
  <v-dialog v-model="dialog" max-width="600">
    <v-card>
      <v-card-title :class="headerClass">
        <v-icon class="tw-mr-2">{{ headerIcon }}</v-icon>
        Validation Results
      </v-card-title>

      <v-card-text class="tw-p-4">
        <!-- Overall Status -->
        <div class="tw-text-center tw-py-4 tw-mb-4 tw-border-b">
          <v-icon :color="isValid ? 'success' : 'error'" size="64">
            {{ isValid ? 'mdi-check-circle' : 'mdi-alert-circle' }}
          </v-icon>
          <div class="tw-text-xl tw-font-semibold tw-mt-2">
            {{ isValid ? 'Claim is Valid' : 'Validation Issues Found' }}
          </div>
          <div class="tw-text-gray-500">
            {{ isValid ? 'The claim passed all validation checks' : `${errorCount} error(s), ${warningCount} warning(s)` }}
          </div>
        </div>

        <!-- Validation Details -->
        <div v-if="results?.checks?.length" class="tw-space-y-2">
          <div
            v-for="(check, index) in results.checks"
            :key="index"
            class="tw-flex tw-items-start tw-gap-3 tw-p-3 tw-rounded"
            :class="getCheckBgClass(check.status)"
          >
            <v-icon :color="getCheckColor(check.status)" size="small">
              {{ getCheckIcon(check.status) }}
            </v-icon>
            <div class="tw-flex-1">
              <div class="tw-font-medium">{{ check.name }}</div>
              <div class="tw-text-sm tw-text-gray-600">{{ check.message }}</div>
            </div>
          </div>
        </div>

        <!-- Summary Stats -->
        <div v-if="results" class="tw-mt-4 tw-pt-4 tw-border-t tw-grid tw-grid-cols-3 tw-gap-4 tw-text-center">
          <div>
            <div class="tw-text-2xl tw-font-bold tw-text-green-600">{{ passedCount }}</div>
            <div class="tw-text-sm tw-text-gray-500">Passed</div>
          </div>
          <div>
            <div class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ warningCount }}</div>
            <div class="tw-text-sm tw-text-gray-500">Warnings</div>
          </div>
          <div>
            <div class="tw-text-2xl tw-font-bold tw-text-red-600">{{ errorCount }}</div>
            <div class="tw-text-sm tw-text-gray-500">Errors</div>
          </div>
        </div>

        <!-- Recommendations -->
        <div v-if="!isValid && results?.recommendations?.length" class="tw-mt-4">
          <div class="tw-font-medium tw-mb-2">Recommendations:</div>
          <ul class="tw-list-disc tw-pl-5 tw-text-sm tw-text-gray-600 tw-space-y-1">
            <li v-for="(rec, idx) in results.recommendations" :key="idx">{{ rec }}</li>
          </ul>
        </div>
      </v-card-text>

      <v-card-actions class="tw-px-6 tw-pb-4">
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Close</v-btn>
        <v-btn v-if="isValid" color="success">
          <v-icon left>mdi-send</v-icon>
          Submit Claim
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  results: Object
})
const emit = defineEmits(['update:modelValue'])

const dialog = ref(props.modelValue)
watch(() => props.modelValue, (val) => dialog.value = val)
watch(dialog, (val) => emit('update:modelValue', val))

const isValid = computed(() => props.results?.is_valid ?? false)
const errorCount = computed(() => props.results?.checks?.filter(c => c.status === 'error')?.length || 0)
const warningCount = computed(() => props.results?.checks?.filter(c => c.status === 'warning')?.length || 0)
const passedCount = computed(() => props.results?.checks?.filter(c => c.status === 'passed')?.length || 0)

const headerClass = computed(() => isValid.value 
  ? 'tw-bg-green-50 tw-text-green-800' 
  : 'tw-bg-red-50 tw-text-red-800'
)
const headerIcon = computed(() => isValid.value ? 'mdi-check-decagram' : 'mdi-alert-decagram')

const getCheckColor = (status) => ({ passed: 'success', warning: 'warning', error: 'error' }[status] || 'grey')
const getCheckIcon = (status) => ({ passed: 'mdi-check-circle', warning: 'mdi-alert', error: 'mdi-close-circle' }[status] || 'mdi-help-circle')
const getCheckBgClass = (status) => ({ passed: 'tw-bg-green-50', warning: 'tw-bg-orange-50', error: 'tw-bg-red-50' }[status] || 'tw-bg-gray-50')
</script>

