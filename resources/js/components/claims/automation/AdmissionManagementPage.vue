<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Admission Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage patient admissions for episode-of-care tracking</p>
        </div>
        <v-btn color="primary" @click="showCreateDialog = true">
          <v-icon left>mdi-plus</v-icon>
          New Admission
        </v-btn>
      </div>

      <!-- Tabs for Active/History -->
      <v-tabs v-model="activeTab" color="primary">
        <v-tab value="active">
          <v-icon left>mdi-hospital-building</v-icon>
          Active Admissions
          <v-chip class="tw-ml-2" size="small" color="primary">{{ activeAdmissions.length }}</v-chip>
        </v-tab>
        <v-tab value="history">
          <v-icon left>mdi-history</v-icon>
          Admission History
        </v-tab>
      </v-tabs>

      <!-- Active Admissions Tab -->
      <v-window v-model="activeTab">
        <v-window-item value="active">
          <v-card class="tw-mt-4">
            <v-card-text>
              <v-data-table
                :headers="admissionHeaders"
                :items="activeAdmissions"
                :loading="loading"
                :items-per-page="10"
              >
                <template #item.enrollee="{ item }">
                  <div class="tw-flex tw-items-center tw-gap-2">
                    <v-avatar size="32" color="primary">
                      <span class="tw-text-xs tw-text-white">{{ getInitials(item.enrollee?.full_name) }}</span>
                    </v-avatar>
                    <div>
                      <div class="tw-font-medium">{{ item.enrollee?.full_name }}</div>
                      <div class="tw-text-sm tw-text-gray-500">{{ item.enrollee?.nicare_number }}</div>
                    </div>
                  </div>
                </template>

                <template #item.diagnosis="{ item }">
                  <div>
                    <v-chip size="small" color="info" variant="outlined">{{ item.principal_diagnosis_code }}</v-chip>
                    <div class="tw-text-sm tw-text-gray-600 tw-mt-1">{{ item.principal_diagnosis_description }}</div>
                  </div>
                </template>

                <template #item.admission_date="{ item }">
                  <div>
                    <div class="tw-font-medium">{{ formatDate(item.admission_date) }}</div>
                    <div class="tw-text-sm tw-text-gray-500">{{ getDaysAdmitted(item.admission_date) }} days</div>
                  </div>
                </template>

                <template #item.bundle_status="{ item }">
                  <v-chip :color="item.has_bundle_assigned ? 'success' : 'warning'" size="small">
                    {{ item.has_bundle_assigned ? 'Bundle Assigned' : 'Pending' }}
                  </v-chip>
                </template>

                <template #item.actions="{ item }">
                  <v-btn size="small" color="primary" variant="text" @click="viewAdmission(item)">
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn size="small" color="warning" variant="text" @click="openDischargeDialog(item)">
                    <v-icon>mdi-hospital</v-icon>
                  </v-btn>
                  <v-btn size="small" color="info" variant="text" @click="openClaimBuilder(item)">
                    <v-icon>mdi-file-document-plus</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-window-item>

        <v-window-item value="history">
          <AdmissionHistory ref="historyRef" />
        </v-window-item>
      </v-window>

      <!-- Create Admission Dialog -->
      <CreateAdmissionDialog 
        v-model="showCreateDialog" 
        @created="onAdmissionCreated" 
      />

      <!-- Discharge Dialog -->
      <DischargePatientDialog
        v-model="showDischargeDialog"
        :admission="selectedAdmission"
        @discharged="onPatientDischarged"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import { claimsAutomationAPI } from '@/js/utils/api'
import CreateAdmissionDialog from './components/CreateAdmissionDialog.vue'
import DischargePatientDialog from './components/DischargePatientDialog.vue'
import AdmissionHistory from './components/AdmissionHistory.vue'

const router = useRouter()

// State
const loading = ref(false)
const activeTab = ref('active')
const admissions = ref([])
const showCreateDialog = ref(false)
const showDischargeDialog = ref(false)
const selectedAdmission = ref(null)
const historyRef = ref(null)

// Computed
const activeAdmissions = computed(() => admissions.value.filter(a => a.status === 'active'))

// Table headers
const admissionHeaders = [
  { title: 'Patient', key: 'enrollee', sortable: true },
  { title: 'Facility', key: 'facility.name', sortable: true },
  { title: 'Diagnosis', key: 'diagnosis', sortable: false },
  { title: 'Admitted', key: 'admission_date', sortable: true },
  { title: 'Bundle Status', key: 'bundle_status', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
]

// Methods
const fetchActiveAdmissions = async () => {
  loading.value = true
  try {
    const response = await claimsAutomationAPI.getAdmissionHistory({ status: 'active' })
    admissions.value = response.data.data || []
  } catch (error) {
    console.error('Failed to fetch admissions:', error)
  } finally {
    loading.value = false
  }
}

const getInitials = (name) => name?.split(' ').map(n => n[0]).join('').substring(0, 2) || '?'
const formatDate = (date) => new Date(date).toLocaleDateString()
const getDaysAdmitted = (date) => Math.ceil((new Date() - new Date(date)) / (1000 * 60 * 60 * 24))

const viewAdmission = (admission) => router.push(`/claims/automation/admissions/${admission.id}`)
const openDischargeDialog = (admission) => { selectedAdmission.value = admission; showDischargeDialog.value = true }
const openClaimBuilder = (admission) => router.push(`/claims/automation/process?admission_id=${admission.id}`)

const onAdmissionCreated = () => { showCreateDialog.value = false; fetchActiveAdmissions() }
const onPatientDischarged = () => { showDischargeDialog.value = false; fetchActiveAdmissions(); historyRef.value?.refresh() }

onMounted(fetchActiveAdmissions)
</script>

