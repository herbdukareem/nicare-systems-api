<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div class="tw-flex tw-items-center tw-gap-2">
          <v-btn icon variant="text" @click="$router.go(-1)">
            <v-icon>mdi-arrow-left</v-icon>
          </v-btn>
          <div>
            <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Admission Details</h1>
            <p class="tw-text-gray-600 tw-mt-1">View admission information and linked claims</p>
          </div>
        </div>
        <div class="tw-flex tw-gap-2">
          <v-btn v-if="admission?.status === 'active'" color="warning" @click="showDischargeDialog = true">
            <v-icon left>mdi-hospital</v-icon>
            Discharge Patient
          </v-btn>
          <v-btn color="primary" @click="createClaim">
            <v-icon left>mdi-file-document-plus</v-icon>
            Create Claim
          </v-btn>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="tw-flex tw-justify-center tw-py-12">
        <v-progress-circular indeterminate size="64" color="primary" />
      </div>

      <!-- Main Content -->
      <div v-else-if="admission" class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-6">
        <!-- Left Column: Details -->
        <div class="lg:tw-col-span-2 tw-space-y-6">
          <!-- Patient Info Card -->
          <v-card>
            <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
              <v-icon class="tw-mr-2">mdi-account</v-icon>
              Patient Information
            </v-card-title>
            <v-card-text class="tw-p-4">
              <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 tw-gap-4">
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Patient Name</div>
                  <div class="tw-font-medium">{{ admission.enrollee?.full_name }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">NiCare Number</div>
                  <div class="tw-font-mono">{{ admission.enrollee?.nicare_number }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Facility</div>
                  <div class="tw-font-medium">{{ admission.facility?.name }}</div>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Admission Details Card -->
          <v-card>
            <v-card-title class="tw-bg-indigo-50 tw-text-indigo-800">
              <v-icon class="tw-mr-2">mdi-hospital-building</v-icon>
              Admission Details
            </v-card-title>
            <v-card-text class="tw-p-4">
              <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-3 tw-gap-4">
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Admission Date</div>
                  <div class="tw-font-medium">{{ formatDate(admission.admission_date) }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Discharge Date</div>
                  <div class="tw-font-medium">{{ admission.discharge_date ? formatDate(admission.discharge_date) : 'Active' }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Length of Stay</div>
                  <div class="tw-font-medium">{{ admission.length_of_stay || calculateLOS() }} days</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Admission Type</div>
                  <v-chip size="small" :color="admission.admission_type === 'emergency' ? 'error' : 'info'">
                    {{ admission.admission_type }}
                  </v-chip>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Ward Type</div>
                  <div class="tw-font-medium">{{ admission.ward_type }}</div>
                </div>
                <div>
                  <div class="tw-text-sm tw-text-gray-500">Attending Physician</div>
                  <div class="tw-font-medium">{{ admission.attending_physician_name }}</div>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Diagnosis Card -->
          <v-card>
            <v-card-title class="tw-bg-purple-50 tw-text-purple-800">
              <v-icon class="tw-mr-2">mdi-stethoscope</v-icon>
              Principal Diagnosis
            </v-card-title>
            <v-card-text class="tw-p-4">
              <div class="tw-flex tw-items-center tw-gap-4">
                <v-chip color="purple" variant="outlined" size="large">
                  {{ admission.principal_diagnosis_code }}
                </v-chip>
                <div>
                  <div class="tw-font-medium">{{ admission.principal_diagnosis_description }}</div>
                  <div v-if="admission.admission_reason" class="tw-text-sm tw-text-gray-500 tw-mt-1">
                    {{ admission.admission_reason }}
                  </div>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Linked Claims -->
          <v-card>
            <v-card-title class="tw-bg-green-50 tw-text-green-800">
              <v-icon class="tw-mr-2">mdi-file-document-multiple</v-icon>
              Linked Claims
            </v-card-title>
            <v-card-text class="tw-p-0">
              <v-list v-if="admission.claims?.length">
                <v-list-item v-for="claim in admission.claims" :key="claim.id" @click="viewClaim(claim)">
                  <template #prepend>
                    <v-icon color="green">mdi-file-document</v-icon>
                  </template>
                  <v-list-item-title>Claim #{{ claim.claim_number || claim.id }}</v-list-item-title>
                  <v-list-item-subtitle>{{ formatDate(claim.created_at) }}</v-list-item-subtitle>
                  <template #append>
                    <v-chip :color="getStatusColor(claim.status)" size="small">{{ claim.status }}</v-chip>
                  </template>
                </v-list-item>
              </v-list>
              <div v-else class="tw-p-6 tw-text-center tw-text-gray-500">No claims linked yet</div>
            </v-card-text>
          </v-card>
        </div>

        <!-- Right Column: Summary & Actions -->
        <div class="tw-space-y-6">
          <!-- Status Card -->
          <v-card>
            <v-card-text class="tw-text-center tw-py-6">
              <v-chip :color="admission.status === 'active' ? 'success' : 'grey'" size="large">
                {{ admission.status === 'active' ? 'Active Admission' : 'Discharged' }}
              </v-chip>
              <div v-if="admission.bundle" class="tw-mt-4">
                <div class="tw-text-sm tw-text-gray-500">Assigned Bundle</div>
                <div class="tw-font-semibold tw-text-lg">{{ admission.bundle.bundle_name }}</div>
                <div class="tw-text-2xl tw-font-bold tw-text-green-600">₦{{ formatNumber(admission.bundle.tariff_amount) }}</div>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>

      <!-- Discharge Dialog -->
      <DischargePatientDialog v-model="showDischargeDialog" :admission="admission" @discharged="onDischarged" />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import DischargePatientDialog from './components/DischargePatientDialog.vue'
import { claimsAutomationAPI } from '@/js/utils/api'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const admission = ref(null)
const showDischargeDialog = ref(false)

const fetchAdmission = async () => {
  loading.value = true
  try {
    const res = await claimsAutomationAPI.getAdmissionHistory({ id: route.params.id })
    admission.value = res.data.data?.[0] || res.data.data
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const formatDate = (date) => date ? new Date(date).toLocaleDateString() : '-'
const formatNumber = (num) => num?.toLocaleString() || '0'
const calculateLOS = () => Math.ceil((new Date() - new Date(admission.value?.admission_date)) / (1000 * 60 * 60 * 24))
const getStatusColor = (status) => ({ draft: 'grey', submitted: 'info', claim_approved: 'success', paid: 'success' }[status] || 'grey')

const viewClaim = (claim) => router.push(`/claims/automation/process/${claim.id}`)
const createClaim = () => router.push(`/claims/automation/process?admission_id=${admission.value?.id}`)
const onDischarged = () => { showDischargeDialog.value = false; fetchAdmission() }

onMounted(fetchAdmission)
</script>

