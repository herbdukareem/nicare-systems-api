<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <div class="tw-flex tw-items-center tw-gap-2">
            <v-btn icon variant="text" @click="$router.go(-1)">
              <v-icon>mdi-arrow-left</v-icon>
            </v-btn>
            <div>
              <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Claims Processing</h1>
              <p class="tw-text-gray-600 tw-mt-1">Build claims with bundle classification and FFS top-ups</p>
            </div>
          </div>
        </div>
        <div class="tw-flex tw-gap-2">
          <v-btn variant="outlined" @click="refreshClaim" :loading="loading">
            <v-icon left>mdi-refresh</v-icon>
            Refresh
          </v-btn>
          <v-btn color="primary" @click="showPreview = true" :disabled="!claim">
            <v-icon left>mdi-eye</v-icon>
            Preview Claim
          </v-btn>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-py-12">
        <v-progress-circular indeterminate size="64" color="primary" />
      </div>

      <!-- Main Content -->
      <div v-else-if="claim" class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-6">
        <!-- Left Column: Claim Details & Treatments -->
        <div class="lg:tw-col-span-2 tw-space-y-6">
          <!-- Admission & Patient Info Card -->
          <v-card>
            <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
              <v-icon class="tw-mr-2">mdi-account-details</v-icon>
              Patient & Admission Details
            </v-card-title>
            <v-card-text class="tw-p-4">
              <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4">
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Patient</div>
                  <div class="tw-font-medium">{{ claim.enrollee?.full_name }}</div>
                  <div class="tw-text-sm tw-text-gray-500">{{ claim.enrollee?.nicare_number }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Facility</div>
                  <div class="tw-font-medium">{{ claim.facility?.name }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Admission Date</div>
                  <div class="tw-font-medium">{{ formatDate(claim.admission?.admission_date) }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Status</div>
                  <v-chip :color="getStatusColor(claim.status)" size="small">{{ claim.status }}</v-chip>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Diagnoses Card -->
          <DiagnosesPanel :claim="claim" @diagnosis-added="refreshClaim" />

          <!-- Treatments/Services Card -->
          <TreatmentsPanel :claim="claim" @updated="refreshClaim" />
        </div>

        <!-- Right Column: Actions & Alerts -->
        <div class="tw-space-y-6">
          <!-- Claim Summary Card -->
          <ClaimSummaryCard :claim="claim" />

          <!-- Bundle Assignment Card -->
          <BundleAssignmentCard :claim="claim" @process="processClaim" :processing="processing" />

          <!-- Actions Card -->
          <v-card>
            <v-card-title class="tw-text-lg">Actions</v-card-title>
            <v-card-text class="tw-space-y-3">
              <v-btn block color="success" @click="validateClaim" :loading="validating">
                <v-icon left>mdi-check-decagram</v-icon>
                Validate Claim
              </v-btn>
            </v-card-text>
          </v-card>
        </div>
      </div>

      <!-- No Claim State -->
      <v-alert v-else type="warning" variant="tonal">
        No claim selected. Please select or create a claim from an admission.
      </v-alert>

      <!-- Claim Preview Dialog -->
      <ClaimPreviewDialog v-model="showPreview" :claim="claim" />

      <!-- Validation Results Dialog -->
      <ValidationResultsDialog v-model="showValidation" :results="validationResults" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import { claimsAutomationAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'

// Sub-components
import DiagnosesPanel from './components/DiagnosesPanel.vue'
import TreatmentsPanel from './components/TreatmentsPanel.vue'
import ClaimSummaryCard from './components/ClaimSummaryCard.vue'
import BundleAssignmentCard from './components/BundleAssignmentCard.vue'
import ClaimPreviewDialog from './components/ClaimPreviewDialog.vue'
import ValidationResultsDialog from './components/ValidationResultsDialog.vue'

const route = useRoute()
const toast = useToast()

// State
const loading = ref(false)
const claim = ref(null)
const processing = ref(false)
const validating = ref(false)

const showPreview = ref(false)
const showValidation = ref(false)

const validationResults = ref(null)

// Fetch claim data
const fetchClaim = async () => {
  const claimId = route.params.id || route.query.claim_id
  if (!claimId) return

  loading.value = true
  try {
    const res = await claimsAutomationAPI.getClaimPreview(claimId)
    claim.value = res.data.data
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load claim', life: 3000 })
  } finally { loading.value = false }
}

const refreshClaim = () => fetchClaim()

const processClaim = async () => {
  if (!claim.value) return
  processing.value = true
  try {
    await claimsAutomationAPI.processClaim(claim.value.id)
    toast.add({ severity: 'success', summary: 'Success', detail: 'Claim processed successfully', life: 3000 })
    await refreshClaim()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Processing failed', life: 5000 })
  } finally { processing.value = false }
}

const validateClaim = async () => {
  if (!claim.value) return
  validating.value = true
  try {
    const res = await claimsAutomationAPI.validateClaim(claim.value.id)
    validationResults.value = res.data.data
    showValidation.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Validation failed', life: 5000 })
  } finally { validating.value = false }
}

const formatDate = (date) => date ? new Date(date).toLocaleDateString() : '-'
const getStatusColor = (status) => ({
  draft: 'grey', submitted: 'info', doctor_review: 'warning', claim_approved: 'success', paid: 'success'
}[status] || 'grey')

onMounted(fetchClaim)
</script>

