<template>
  <v-card class="tw-mt-4">
    <v-card-title class="tw-flex tw-items-center tw-justify-between">
      <span>Admission History</span>
      <div class="tw-flex tw-gap-2">
        <v-text-field
          v-model="search"
          density="compact"
          label="Search"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          hide-details
          class="tw-w-64"
          @update:modelValue="debouncedFetch"
        />
        <v-select
          v-model="filters.discharge_type"
          :items="dischargeTypes"
          label="Discharge Type"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          class="tw-w-48"
          @update:modelValue="fetchHistory"
        />
      </div>
    </v-card-title>

    <v-card-text>
      <v-data-table-server
        :headers="headers"
        :items="admissions"
        :items-length="totalItems"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :page="page"
        @update:page="onPageChange"
        @update:items-per-page="onItemsPerPageChange"
      >
        <template #item.enrollee="{ item }">
          <div class="tw-flex tw-items-center tw-gap-2">
            <v-avatar size="32" color="grey">
              <span class="tw-text-xs tw-text-white">{{ getInitials(item.enrollee?.full_name) }}</span>
            </v-avatar>
            <div>
              <div class="tw-font-medium">{{ item.enrollee?.full_name }}</div>
              <div class="tw-text-sm tw-text-gray-500">{{ item.enrollee?.nicare_number }}</div>
            </div>
          </div>
        </template>

        <template #item.dates="{ item }">
          <div>
            <div class="tw-text-sm"><span class="tw-text-gray-500">In:</span> {{ formatDate(item.admission_date) }}</div>
            <div class="tw-text-sm"><span class="tw-text-gray-500">Out:</span> {{ formatDate(item.discharge_date) }}</div>
            <v-chip size="x-small" color="info" variant="outlined" class="tw-mt-1">{{ item.length_of_stay }} days</v-chip>
          </div>
        </template>

        <template #item.diagnosis="{ item }">
          <div>
            <v-chip size="small" color="info" variant="outlined">{{ item.principal_diagnosis_code }}</v-chip>
            <div class="tw-text-sm tw-text-gray-600 tw-mt-1 tw-max-w-48 tw-truncate">
              {{ item.principal_diagnosis_description }}
            </div>
          </div>
        </template>

        <template #item.discharge_type="{ item }">
          <v-chip :color="getDischargeColor(item.discharge_type)" size="small">
            {{ formatDischargeType(item.discharge_type) }}
          </v-chip>
        </template>

        <template #item.bundle="{ item }">
          <div v-if="item.bundle">
            <v-chip size="small" color="success" variant="outlined">{{ item.bundle.bundle_code }}</v-chip>
            <div class="tw-text-sm tw-text-gray-600">{{ item.bundle.bundle_name }}</div>
          </div>
          <v-chip v-else size="small" color="grey" variant="outlined">N/A</v-chip>
        </template>

        <template #item.actions="{ item }">
          <v-btn size="small" variant="text" color="primary" @click="viewDetails(item)">
            <v-icon>mdi-eye</v-icon>
          </v-btn>
          <v-btn v-if="item.claim" size="small" variant="text" color="info" @click="viewClaim(item)">
            <v-icon>mdi-file-document</v-icon>
          </v-btn>
        </template>
      </v-data-table-server>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { claimsAutomationAPI } from '@/js/utils/api'
import { debounce } from 'lodash-es'

const router = useRouter()

const loading = ref(false)
const admissions = ref([])
const search = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const totalItems = ref(0)

const filters = ref({ discharge_type: null })

const dischargeTypes = [
  { title: 'Normal', value: 'normal' },
  { title: 'Transfer', value: 'transfer' },
  { title: 'Against Advice', value: 'against_advice' },
  { title: 'Deceased', value: 'deceased' }
]

const headers = [
  { title: 'Patient', key: 'enrollee', sortable: true },
  { title: 'Facility', key: 'facility.name', sortable: true },
  { title: 'Admission/Discharge', key: 'dates', sortable: false },
  { title: 'Diagnosis', key: 'diagnosis', sortable: false },
  { title: 'Discharge Type', key: 'discharge_type', sortable: true },
  { title: 'Bundle', key: 'bundle', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
]

const fetchHistory = async () => {
  loading.value = true
  try {
    const params = {
      status: 'discharged',
      page: page.value,
      per_page: itemsPerPage.value,
      search: search.value || undefined,
      discharge_type: filters.value.discharge_type || undefined
    }
    const res = await claimsAutomationAPI.getAdmissionHistory(params)
    admissions.value = res.data.data || []
    totalItems.value = res.data.meta?.total || 0
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const debouncedFetch = debounce(fetchHistory, 300)

const onPageChange = (p) => { page.value = p; fetchHistory() }
const onItemsPerPageChange = (pp) => { itemsPerPage.value = pp; page.value = 1; fetchHistory() }

const getInitials = (name) => name?.split(' ').map(n => n[0]).join('').substring(0, 2) || '?'
const formatDate = (date) => date ? new Date(date).toLocaleDateString() : '-'
const formatDischargeType = (type) => type?.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) || '-'
const getDischargeColor = (type) => ({ normal: 'success', transfer: 'info', against_advice: 'warning', deceased: 'error' }[type] || 'grey')

const viewDetails = (item) => router.push(`/claims/automation/admissions/${item.id}`)
const viewClaim = (item) => router.push(`/claims/automation/process/${item.claim?.id}`)

const refresh = () => fetchHistory()
defineExpose({ refresh })

onMounted(fetchHistory)
</script>

