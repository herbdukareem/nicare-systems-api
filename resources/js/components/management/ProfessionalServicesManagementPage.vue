<template>
  <AdminLayout>
    <template #header>
      <div class="d-flex align-center justify-space-between">
        <div>
          <h1 class="text-h4 font-weight-bold mb-2">Professional Services Management</h1>
          <Breadcrumb :items="breadcrumbItems" />
        </div>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="openCreateDialog"
          size="large"
        >
          Add Professional Service
        </v-btn>
      </div>
    </template>

    <!-- Statistics Cards -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="primary" size="40" class="mr-3">mdi-medical-bag</v-icon>
              <div>
                <div class="text-caption text-grey">Total Services</div>
                <div class="text-h5 font-weight-bold">{{ statistics.total || 0 }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="success" size="40" class="mr-3">mdi-check-circle</v-icon>
              <div>
                <div class="text-caption text-grey">Active Services</div>
                <div class="text-h5 font-weight-bold">{{ statistics.active || 0 }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="warning" size="40" class="mr-3">mdi-shield-check</v-icon>
              <div>
                <div class="text-caption text-grey">Requires PA</div>
                <div class="text-h5 font-weight-bold">{{ statistics.pa_required || 0 }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="info" size="40" class="mr-3">mdi-hospital-building</v-icon>
              <div>
                <div class="text-caption text-grey">Specialties</div>
                <div class="text-h5 font-weight-bold">{{ statistics.specialties || 0 }}</div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters Card -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="searchQuery"
              label="Search"
              placeholder="Search by code, name, or description"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="specialtyFilter"
              :items="specialtyOptions"
              label="Filter by Specialty"
              prepend-inner-icon="mdi-filter"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="levelOfCareFilter"
              :items="levelOfCareOptions"
              label="Level of Care"
              prepend-inner-icon="mdi-hospital-box"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="paRequiredFilter"
              :items="paRequiredOptions"
              label="PA Required"
              prepend-inner-icon="mdi-shield-check"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />
          </v-col>
          <v-col cols="12" md="1">
            <v-btn
              color="secondary"
              variant="outlined"
              @click="resetFilters"
              block
              height="40"
            >
              Reset
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Data Table -->
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <span>Professional Services</span>
        <v-chip color="primary" variant="outlined">
          {{ totalItems }} Total
        </v-chip>
      </v-card-title>
      <v-data-table
        :headers="headers"
        :items="services"
        :loading="loading"
        :items-per-page="itemsPerPage"
        :server-items-length="totalItems"
        @update:options="loadServices"
        class="elevation-0"
      >
        <template #item.nicare_code="{ item }">
          <v-chip size="small" color="primary" variant="outlined">
            {{ item.nicare_code }}
          </v-chip>
        </template>

        <template #item.group="{ item }">
          <v-chip size="small" :color="getSpecialtyColor(item.group)" variant="tonal">
            {{ item.group }}
          </v-chip>
        </template>

        <template #item.level_of_care="{ item }">
          <v-chip size="small" :color="getLevelColor(item.level_of_care)" variant="flat">
            {{ item.level_of_care }}
          </v-chip>
        </template>

        <template #item.price="{ item }">
          <span class="font-weight-bold">₦{{ formatNumber(item.price) }}</span>
        </template>

        <template #item.pa_required="{ item }">
          <v-chip
            size="small"
            :color="item.pa_required ? 'warning' : 'success'"
            variant="flat"
          >
            <v-icon start size="small">
              {{ item.pa_required ? 'mdi-shield-check' : 'mdi-check' }}
            </v-icon>
            {{ item.pa_required ? 'Required' : 'Not Required' }}
          </v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip
            size="small"
            :color="item.status ? 'success' : 'error'"
            variant="flat"
          >
            {{ item.status ? 'Active' : 'Inactive' }}
          </v-chip>
        </template>

        <template #item.actions="{ item }">
          <v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            color="primary"
            @click="openEditDialog(item)"
          />
          <v-btn
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          />
        </template>

        <template #bottom>
          <div class="text-center pa-4">
            <v-pagination
              v-model="currentPage"
              :length="totalPages"
              :total-visible="7"
              @update:model-value="loadServices"
            />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="800px" persistent>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span class="text-h5">{{ isEditing ? 'Edit' : 'Add' }} Professional Service</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="form" v-model="formValid">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.nicare_code"
                  label="NiCare Code *"
                  placeholder="e.g., NGSCHS/GCons/P/0001"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  prepend-inner-icon="mdi-barcode"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.group"
                  :items="specialtyOptions"
                  label="Specialty/Service Group *"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  prepend-inner-icon="mdi-medical-bag"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="formData.service_description"
                  label="Service Description *"
                  placeholder="Enter detailed description of the service"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  rows="3"
                  prepend-inner-icon="mdi-text"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="formData.level_of_care"
                  :items="levelOfCareOptions"
                  label="Level of Care *"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  prepend-inner-icon="mdi-hospital-box"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model.number="formData.price"
                  label="Unit Price (₦) *"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required, rules.positiveNumber]"
                  prepend-inner-icon="mdi-currency-ngn"
                  min="0"
                  step="0.01"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="formData.status"
                  :items="statusOptions"
                  label="Status *"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  prepend-inner-icon="mdi-check-circle"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-switch
                  v-model="formData.pa_required"
                  label="Requires Pre-Authorization (PA)"
                  color="warning"
                  hide-details
                  inset
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-switch
                  v-model="formData.referable"
                  label="Referable to Higher Level"
                  color="info"
                  hide-details
                  inset
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer />
          <v-btn
            color="grey"
            variant="outlined"
            @click="closeDialog"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            @click="saveService"
            :loading="saving"
            :disabled="!formValid"
          >
            {{ isEditing ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="500px">
      <v-card>
        <v-card-title class="bg-error text-white">
          <v-icon start>mdi-alert</v-icon>
          Confirm Delete
        </v-card-title>
        <v-card-text class="pt-4">
          <p class="text-body-1">
            Are you sure you want to delete this professional service?
          </p>
          <v-alert type="warning" variant="tonal" class="mt-3">
            <strong>{{ serviceToDelete?.nicare_code }}</strong> - {{ serviceToDelete?.service_description }}
          </v-alert>
          <p class="text-caption text-grey mt-3">
            This action cannot be undone. The service will be soft-deleted and can be restored later.
          </p>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer />
          <v-btn
            color="grey"
            variant="outlined"
            @click="deleteDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            @click="deleteService"
            :loading="deleting"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import Breadcrumb from '@/js/components/common/Breadcrumb.vue'
import api from '@/js/utils/api'
import { useToast } from '@/js/composables/useToast'

const { showSuccess, showError } = useToast()
const router = useRouter()

// Breadcrumb
const breadcrumbItems = ref([
  { title: 'Dashboard', to: '/dashboard' },
  { title: 'Management', to: '/management' },
  { title: 'Professional Services', to: '/management/professional-services' }
])

// Data
const services = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const dialog = ref(false)
const deleteDialog = ref(false)
const formValid = ref(false)
const form = ref(null)
const isEditing = ref(false)
const serviceToDelete = ref(null)

// Pagination
const currentPage = ref(1)
const itemsPerPage = ref(15)
const totalItems = ref(0)
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value))

// Filters
const searchQuery = ref('')
const specialtyFilter = ref(null)
const levelOfCareFilter = ref(null)
const paRequiredFilter = ref(null)

// Statistics
const statistics = ref({
  total: 0,
  active: 0,
  pa_required: 0,
  specialties: 0
})

// Form Data
const formData = ref({
  nicare_code: '',
  service_description: '',
  group: '',
  level_of_care: 'Primary',
  price: 0,
  pa_required: false,
  referable: true,
  status: true
})

// Table Headers
const headers = [
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Service Description', key: 'service_description', sortable: true },
  { title: 'Specialty', key: 'group', sortable: true },
  { title: 'Level of Care', key: 'level_of_care', sortable: true },
  { title: 'Unit Price', key: 'price', sortable: true },
  { title: 'PA Required', key: 'pa_required', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
]

// Options
const specialtyOptions = [
  'GENERAL CONSULTATION',
  'PAEDIATRICS',
  'INTERNAL MEDICINE (PRV)',
  'OBSTETRICS & GYNAECOLOGY',
  'SURGERY',
  'EMERGENCY SERVICES',
  'ORTHOPAEDICS',
  'OPHTHALMOLOGY',
  'ENT (EAR, NOSE & THROAT)',
  'DERMATOLOGY',
  'PSYCHIATRY',
  'DENTAL SERVICES',
  'PHYSIOTHERAPY',
  'ANAESTHESIA',
  'CARDIOLOGY',
  'NEUROLOGY'
]

const levelOfCareOptions = ['Primary', 'Secondary', 'Tertiary']

const paRequiredOptions = [
  { title: 'PA Required', value: true },
  { title: 'No PA Required', value: false }
]

const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
]

// Validation Rules
const rules = {
  required: value => !!value || 'This field is required',
  positiveNumber: value => value >= 0 || 'Must be a positive number'
}

// Watch filters
watch([searchQuery, specialtyFilter, levelOfCareFilter, paRequiredFilter], () => {
  currentPage.value = 1
  loadServices()
}, { debounce: 500 })

// Methods
const loadServices = async (options = {}) => {
  loading.value = true
  try {
    const params = {
      page: options.page || currentPage.value,
      per_page: options.itemsPerPage || itemsPerPage.value,
      search: searchQuery.value,
      group: specialtyFilter.value,
      level_of_care: levelOfCareFilter.value,
      pa_required: paRequiredFilter.value,
      // Filter to only professional services (exclude LABS, RADIOLOGY, PHARMACY)
      exclude_groups: 'LABS,RADIOLOGY,PHARMACY'
    }

    const response = await api.get('/api/cases', { params })

    services.value = response.data.data
    totalItems.value = response.data.total
    currentPage.value = response.data.current_page
  } catch (error) {
    console.error('Error loading professional services:', error)
    showError('Failed to load professional services')
  } finally {
    loading.value = false
  }
}

const loadStatistics = async () => {
  try {
    const response = await api.get('/api/cases-statistics', {
      params: {
        exclude_groups: 'LABS,RADIOLOGY,PHARMACY'
      }
    })
    statistics.value = response.data
  } catch (error) {
    console.error('Error loading statistics:', error)
  }
}

const openCreateDialog = () => {
  isEditing.value = false
  formData.value = {
    nicare_code: '',
    service_description: '',
    group: '',
    level_of_care: 'Primary',
    price: 0,
    pa_required: false,
    referable: true,
    status: true
  }
  dialog.value = true
}

const openEditDialog = (service) => {
  isEditing.value = true
  formData.value = {
    id: service.id,
    nicare_code: service.nicare_code,
    service_description: service.service_description,
    group: service.group,
    level_of_care: service.level_of_care,
    price: service.price,
    pa_required: service.pa_required,
    referable: service.referable,
    status: service.status
  }
  dialog.value = true
}

const closeDialog = () => {
  dialog.value = false
  form.value?.reset()
}

const saveService = async () => {
  if (!formValid.value) return

  saving.value = true
  try {
    if (isEditing.value) {
      await api.put(`/api/cases/${formData.value.id}`, formData.value)
      showSuccess('Professional service updated successfully')
    } else {
      await api.post('/api/cases', formData.value)
      showSuccess('Professional service created successfully')
    }

    closeDialog()
    loadServices()
    loadStatistics()
  } catch (error) {
    console.error('Error saving professional service:', error)
    showError(error.response?.data?.message || 'Failed to save professional service')
  } finally {
    saving.value = false
  }
}

const confirmDelete = (service) => {
  serviceToDelete.value = service
  deleteDialog.value = true
}

const deleteService = async () => {
  if (!serviceToDelete.value) return

  deleting.value = true
  try {
    await api.delete(`/api/cases/${serviceToDelete.value.id}`)
    showSuccess('Professional service deleted successfully')
    deleteDialog.value = false
    serviceToDelete.value = null
    loadServices()
    loadStatistics()
  } catch (error) {
    console.error('Error deleting professional service:', error)
    showError('Failed to delete professional service')
  } finally {
    deleting.value = false
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  specialtyFilter.value = null
  levelOfCareFilter.value = null
  paRequiredFilter.value = null
}

// Helper Functions
const formatNumber = (value) => {
  return new Intl.NumberFormat('en-NG', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

const getSpecialtyColor = (specialty) => {
  const colors = {
    'GENERAL CONSULTATION': 'blue',
    'PAEDIATRICS': 'pink',
    'INTERNAL MEDICINE (PRV)': 'purple',
    'OBSTETRICS & GYNAECOLOGY': 'teal',
    'SURGERY': 'red',
    'EMERGENCY SERVICES': 'orange',
    'ORTHOPAEDICS': 'brown',
    'OPHTHALMOLOGY': 'cyan',
    'ENT (EAR, NOSE & THROAT)': 'indigo',
    'DERMATOLOGY': 'lime',
    'PSYCHIATRY': 'deep-purple',
    'DENTAL SERVICES': 'light-blue',
    'PHYSIOTHERAPY': 'green',
    'ANAESTHESIA': 'amber',
    'CARDIOLOGY': 'deep-orange',
    'NEUROLOGY': 'blue-grey'
  }
  return colors[specialty] || 'grey'
}

const getLevelColor = (level) => {
  const colors = {
    'Primary': 'success',
    'Secondary': 'warning',
    'Tertiary': 'error'
  }
  return colors[level] || 'grey'
}

// Lifecycle
onMounted(() => {
  loadServices()
  loadStatistics()
})
</script>

<style scoped>
.v-data-table {
  font-size: 0.875rem;
}
</style>
