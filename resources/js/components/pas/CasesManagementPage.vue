<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Case Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage healthcare cases, pricing, and PA requirements</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn
            color="success"
            variant="outlined"
            prepend-icon="mdi-download"
            @click="downloadTemplate"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Template
          </v-btn>
          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-upload"
            @click="showImportDialog = true"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Import
          </v-btn>
          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-download"
            @click="exportCases"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Export
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-plus"
            @click="showCreateDialog = true"
            class="tw-hover-lift tw-transition-all tw-duration-300 tw-shadow-lg"
          >
            Add Case
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-medical-bag</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Cases</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.total_cases }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Cases</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.active_cases }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-3 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-shield-check</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">PA Required</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.pa_required_count }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-4 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-transfer</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Referable</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.referable_count }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-5 tw-gap-4">
          <v-text-field
            v-model="searchQuery"
            label="Search services..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedStatus"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedLevelOfCare"
            :items="levelOfCareOptions"
            label="Level of Care"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedGroup"
            :items="groupOptions"
            label="Group"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedPARequired"
            :items="paRequiredOptions"
            label="PA Required"
            variant="outlined"
            density="compact"
            clearable
          />
        </div>
      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          :headers="headers"
          :items="cases"
          :loading="loading"
          :items-per-page="pagination.itemsPerPage"
          :page="pagination.page"
          :server-items-length="pagination.totalItems"
          item-key="id"
          class="tw-elevation-0"
          @update:page="handlePageChange"
          @update:items-per-page="handleItemsPerPageChange"
        >
          <template #item.status="{ item }">
            <v-chip :color="item.status ? 'green' : 'red'" size="small" variant="flat">
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.level_of_care="{ item }">
            <v-chip :color="getLevelOfCareColor(item.level_of_care)" size="small" variant="flat">
              {{ item.level_of_care }}
            </v-chip>
          </template>

          <template #item.price="{ item }">
            ₦{{ Number(item.price ?? 0).toLocaleString() }}
          </template>

          <template #item.pa_required="{ item }">
            <v-chip :color="item.pa_required ? 'orange' : 'green'" size="small" variant="flat">
              {{ item.pa_required ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.referable="{ item }">
            <v-chip :color="item.referable ? 'blue' : 'grey'" size="small" variant="flat">
              {{ item.referable ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-space-x-2">
              <v-btn icon size="small" variant="text" @click="viewCase(item)">
                <v-icon>mdi-eye</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" @click="editCase(item)">
                <v-icon>mdi-pencil</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="red" @click="deleteCase(item)">
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Case Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="900px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">{{ editingCase ? 'Edit Case' : 'Add New Case' }}</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="caseFormRef" v-model="formValid">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="caseForm.nicare_code"
                label="NiCare Code"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-select
                v-model="caseForm.level_of_care"
                :items="levelOfCareOptions"
                item-title="title"
                item-value="value"
                label="Level of Care"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="caseForm.price"
                label="Price"
                type="number"
                step="0.01"
                :rules="[rules.required, rules.positive]"
                variant="outlined"
                required
              />
              <v-select
                v-model="caseForm.case_group_id"
                :items="caseGroupOptions"
                item-title="name"
                item-value="id"
                label="Case Group"
                :rules="[rules.required]"
                variant="outlined"
                required
                clearable
              />
            </div>
            <v-textarea
              v-model="caseForm.case_description"
              label="Case Description"
              :rules="[rules.required]"
              variant="outlined"
              rows="3"
              required
            />
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4 tw-mt-4">
              <v-switch v-model="caseForm.status" label="Active" color="primary" />
              <v-switch v-model="caseForm.pa_required" label="PA Required" color="orange" />
              <v-switch v-model="caseForm.referable" label="Referable" color="blue" />
            </div>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!formValid" @click="saveCase">
            {{ editingCase ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Import Cases</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <!-- IMPORTANT: normalize to a single File in state -->
            <v-file-input
              :model-value="importFileRaw"
              @update:modelValue="onImportFileUpdate"
              label="Select Excel/CSV File"
              accept=".xlsx,.xls,.csv"
              variant="outlined"
              prepend-icon="mdi-file-upload"
              show-size
              :clearable="true"
            />
            <v-alert type="info" variant="tonal">
              Please use the template format. Download the template first if you haven't already.
            </v-alert>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeImportDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            :loading="importing"
            :disabled="!canImport"
            @click="importCases"
          >
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { useToast } from '../../composables/useToast'
import { caseAPI } from '../../utils/api.js'

// Toast
const { success, error } = useToast()

// State
const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const showCreateDialog = ref(false)
const showImportDialog = ref(false)
const editingCase = ref(null)
const formValid = ref(false)

// Filters
const searchQuery = ref('')
const selectedStatus = ref('')
const selectedLevelOfCare = ref('')
const selectedGroup = ref('')
const selectedPARequired = ref('')

// Table + stats
const cases = ref([])
const groupOptions = ref([])
const caseGroupOptions = ref([])
const statistics = ref({
  total_cases: 0,
  active_cases: 0,
  inactive_cases: 0,
  pa_required_count: 0,
  referable_count: 0
})
const pagination = ref({
  page: 1,
  itemsPerPage: 15,
  totalItems: 0
})

// Form model
const caseForm = ref({
  nicare_code: '',
  case_description: '',
  level_of_care: '',
  price: '',
  group: '',
  case_group_id: null,
  pa_required: false,
  referable: true,
  status: true
})

// Options
const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
]
const levelOfCareOptions = [
  { title: 'Primary', value: 'Primary' },
  { title: 'Secondary', value: 'Secondary' },
  { title: 'Tertiary', value: 'Tertiary' }
]
const paRequiredOptions = [
  { title: 'Required', value: true },
  { title: 'Not Required', value: false }
]

// Headers
const headers = [
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Case Description', key: 'case_description', sortable: true },
  { title: 'Level of Care', key: 'level_of_care', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: 'Group', key: 'group', sortable: true },
  { title: 'PA Required', key: 'pa_required', sortable: true },
  { title: 'Referable', key: 'referable', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' }
]

// Validation rules
const rules = {
  required: v => (!!v || v === false) || 'This field is required',
  positive: v => Number(v) > 0 || 'Value must be positive'
}

// Helpers
const getLevelOfCareColor = level => {
  switch (level) {
    case 'Primary': return 'green'
    case 'Secondary': return 'orange'
    case 'Tertiary': return 'red'
    default: return 'grey'
  }
}

// -------- Import handling (FIXED) --------
// We keep two refs:
// - importFileRaw: what v-file-input binds to (File or File[] or null)
// - importFile: always a single File or null (normalized)
const importFileRaw = ref(null) // File | File[] | null
const importFile = ref(null)     // File | null

const onImportFileUpdate = (val) => {
  importFileRaw.value = val
  // Normalize any shape to a single File or null
  if (Array.isArray(val)) {
    importFile.value = val[0] ?? null
  } else {
    importFile.value = val ?? null
  }
}

const validateImportFile = () => {
  if (!importFile.value) return 'No file selected'
  const file = importFile.value
  const allowedTypes = [
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'text/csv', 'application/csv', 'application/vnd.ms-excel.sheet.macroEnabled.12'
  ]
  // Some browsers send empty type for .csv; accept by extension as fallback
  const nameOk = /\.(xlsx|xls|csv)$/i.test(file.name || '')
  const typeOk = file.type ? allowedTypes.includes(file.type) : nameOk
  return typeOk ? null : 'Please select an Excel (.xlsx/.xls) or CSV file'
}

const canImport = computed(() => !!importFile.value && !importing.value)

const closeImportDialog = () => {
  showImportDialog.value = false
  importFileRaw.value = null
  importFile.value = null
}

// -------- Data loads --------
const loadStatistics = async () => {
  try {
    const res = await caseAPI.getStatistics()
    if (res?.data?.success) statistics.value = res.data.data
  } catch (e) {
    console.error('Failed to load statistics:', e)
    error('Failed to load statistics')
  }
}

const loadGroups = async () => {
  try {
    const res = await caseAPI.getGroups()
    if (res?.data?.success) {
      caseGroupOptions.value = res.data.data || []
      groupOptions.value = (res.data.data || []).map(g => ({ title: g.name, value: g.name }))
    }
  } catch (e) {
    console.error('Failed to load groups:', e)
  }
}

const loadCases = async () => {
  try {
    loading.value = true
    const params = {
      search: (searchQuery.value || '').trim(),
      status: selectedStatus.value,
      level_of_care: selectedLevelOfCare.value,
      group: selectedGroup.value,
      pa_required: selectedPARequired.value,
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage
    }
    const res = await caseAPI.getAll(params)
    if (res?.data?.success) {
      const data = res.data.data
      cases.value = data.data || []
      pagination.value.totalItems = data.total || 0
      pagination.value.page = data.current_page || 1
    }
  } catch (e) {
    console.error('Failed to load cases:', e)
    error('Failed to load cases')
  } finally {
    loading.value = false
  }
}

// -------- CRUD --------
const resetForm = () => {
  caseForm.value = {
    nicare_code: '',
    case_description: '',
    level_of_care: '',
    price: '',
    group: '',
    case_group_id: null,
    pa_required: false,
    referable: true,
    status: true
  }
  editingCase.value = null
}

const closeDialog = () => {
  showCreateDialog.value = false
  resetForm()
}

const saveCase = async () => {
  try {
    saving.value = true
    if (editingCase.value) {
      await caseAPI.update(editingCase.value.id, caseForm.value)
      success('Case updated successfully')
    } else {
      await caseAPI.create(caseForm.value)
      success('Case created successfully')
    }
    closeDialog()
    await Promise.all([loadCases(), loadStatistics()])
  } catch (e) {
    console.error('Failed to save case:', e)
    error('Failed to save case')
  } finally {
    saving.value = false
  }
}

const viewCase = (row) => {
  success(`Viewing case: ${row.case_description}`)
}

const editCase = (row) => {
  editingCase.value = row
  caseForm.value = { ...row }
  showCreateDialog.value = true
}

const deleteCase = async (row) => {
  if (!confirm('Are you sure you want to delete this case?')) return
  try {
    await caseAPI.delete(row.id)
    success('Case deleted successfully')
    await Promise.all([loadCases(), loadStatistics()])
  } catch (e) {
    console.error('Failed to delete case:', e)
    error('Failed to delete case')
  }
}

// -------- Download helpers --------
const blobDownload = (blob, fallbackName) => {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = fallbackName
  a.click()
  URL.revokeObjectURL(url)
}

const getFilenameFromDisposition = (disposition, fallback) => {
  if (!disposition) return fallback
  const m = /filename\*?=(?:UTF-8'')?["']?([^"';]+)["']?/i.exec(disposition)
  return m ? decodeURIComponent(m[1]) : fallback
}

// -------- Export/Template --------
const downloadTemplate = async () => {
  try {
    const res = await caseAPI.downloadTemplate()
    const cd = res.headers?.['content-disposition']
    const name = getFilenameFromDisposition(cd, 'cases_import_template.xlsx')
    blobDownload(res.data, name)
    success('Template downloaded successfully')
  } catch (e) {
    console.error('Failed to download template:', e)
    error('Failed to download template')
  }
}

const exportCases = async () => {
  try {
    const params = {
      search: (searchQuery.value || '').trim(),
      status: selectedStatus.value,
      level_of_care: selectedLevelOfCare.value,
      group: selectedGroup.value
    }
    const res = await caseAPI.export(params)
    const cd = res.headers?.['content-disposition']
    const name = getFilenameFromDisposition(
      cd,
      `cases_export_${new Date().toISOString().split('T')[0]}.xlsx`
    )
    blobDownload(res.data, name)
    success('Cases exported successfully')
  } catch (e) {
    console.error('Failed to export cases:', e)
    error('Failed to export cases')
  }
}

// -------- Import (FIXED) --------
const importCases = async () => {
  const vErr = validateImportFile()
  if (vErr) {
    error(vErr)
    return
  }
  try {
    importing.value = true
    const fd = new FormData()
    // If your backend expects another name (e.g., 'import_file'), change the key here.
    fd.append('file', importFile.value)

    const res = await caseAPI.import(fd)
    if (res?.data?.success) {
      const { imported_count = 0, errors = [] } = res.data.data || {}
      if (errors.length) {
        console.warn('Import errors:', errors)
        error(`Imported ${imported_count} with ${errors.length} error(s). Check console for details.`)
      } else {
        success(`Successfully imported ${imported_count} cases`)
      }
      closeImportDialog()
      await Promise.all([loadCases(), loadStatistics()])
    } else {
      error('Import failed — invalid server response')
    }
  } catch (e) {
    console.error('Failed to import cases:', e)
    error('Failed to import cases')
  } finally {
    importing.value = false
  }
}

// -------- Pagination --------
const handlePageChange = (page) => {
  pagination.value.page = page
  loadCases()
}
const handleItemsPerPageChange = (itemsPerPage) => {
  pagination.value.itemsPerPage = itemsPerPage
  pagination.value.page = 1
  loadCases()
}

// -------- Debounced filter watch (FIXED) --------
let filterTimer = null
watch([searchQuery, selectedStatus, selectedLevelOfCare, selectedGroup, selectedPARequired], () => {
  if (filterTimer) clearTimeout(filterTimer)
  filterTimer = setTimeout(() => {
    pagination.value.page = 1
    loadCases()
  }, 300)
})

// -------- Lifecycle --------
onMounted(() => {
  loadStatistics()
  loadGroups()
  loadCases()
})
</script>
