<template>
  <AdminLayout>
    <div class="document-requirements-management">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h2 class="text-h4 font-weight-bold">Document Requirements</h2>
              <p class="text-subtitle-1 text-grey-darken-1">Manage document requirements for referrals and PA codes</p>
            </div>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              @click="openCreateDialog"
            >
              Add Requirement
            </v-btn>
          </div>
        </v-col>
      </v-row>

      <!-- Filters -->
      <v-row class="mb-4">
        <v-col cols="12" md="3">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Search requirements..."
            variant="outlined"
            density="compact"
            clearable
            @input="debouncedSearch"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="requestTypeFilter"
            :items="requestTypeOptions"
            label="Request Type"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="loadRequirements"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="loadRequirements"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="requiredFilter"
            :items="requiredOptions"
            label="Required"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="loadRequirements"
          />
        </v-col>
        <v-col cols="12" md="2" class="d-flex align-center">
          <v-btn
            variant="outlined"
            prepend-icon="mdi-refresh"
            @click="refreshData"
          >
            Refresh
          </v-btn>
        </v-col>
      </v-row>

      <!-- Data Table -->
      <v-card>
        <v-data-table
          :headers="headers"
          :items="filteredRequirements"
          :loading="loading"
          :search="search"
          class="elevation-1"
        >
          <template #item.request_type="{ item }">
            <v-chip
              :color="item.request_type === 'referral' ? 'primary' : 'secondary'"
              size="small"
              variant="flat"
            >
              {{ item.request_type === 'referral' ? 'Referral' : 'PA Code' }}
            </v-chip>
          </template>

          <template #item.is_required="{ item }">
            <v-chip
              :color="item.is_required ? 'error' : 'info'"
              size="small"
              variant="flat"
            >
              {{ item.is_required ? 'Required' : 'Optional' }}
            </v-chip>
          </template>

          <template #item.status="{ item }">
            <v-chip
              :color="item.status ? 'success' : 'grey'"
              size="small"
              variant="flat"
            >
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.allowed_file_types="{ item }">
            <span class="text-caption">{{ item.allowed_file_types || 'Any' }}</span>
          </template>

          <template #item.max_file_size_mb="{ item }">
            <span>{{ item.max_file_size_mb || 10 }} MB</span>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <v-tooltip text="Edit">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    @click="editRequirement(item)"
                  />
                </template>
              </v-tooltip>

              <v-tooltip :text="item.status ? 'Deactivate' : 'Activate'">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    :icon="item.status ? 'mdi-toggle-switch' : 'mdi-toggle-switch-off'"
                    size="small"
                    variant="text"
                    :color="item.status ? 'warning' : 'success'"
                    @click="toggleStatus(item)"
                  />
                </template>
              </v-tooltip>

              <v-tooltip text="Delete">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                    @click="deleteRequirement(item)"
                  />
                </template>
              </v-tooltip>
            </div>
          </template>
        </v-data-table>
      </v-card>

      <!-- Create/Edit Dialog -->
      <v-dialog v-model="dialog" max-width="700px" persistent>
        <v-card>
          <v-card-title class="text-h5">
            {{ isEditing ? 'Edit' : 'Create' }} Document Requirement
          </v-card-title>

          <v-card-text>
            <v-form ref="form" v-model="formValid">
              <v-row>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="formData.request_type"
                    :items="requestTypeSelectOptions"
                    item-title="title"
                    item-value="value"
                    label="Request Type *"
                    :rules="[v => !!v || 'Request type is required']"
                    variant="outlined"
                    required
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="formData.document_type"
                    label="Document Type Code *"
                    hint="E.g., referral_letter, medical_report"
                    :rules="documentTypeRules"
                    :error-messages="validationErrors.document_type"
                    variant="outlined"
                    required
                  />
                </v-col>

                <v-col cols="12">
                  <v-text-field
                    v-model="formData.name"
                    label="Display Name *"
                    :rules="nameRules"
                    :error-messages="validationErrors.name"
                    variant="outlined"
                    required
                  />
                </v-col>

                <v-col cols="12">
                  <v-textarea
                    v-model="formData.description"
                    label="Description"
                    variant="outlined"
                    rows="2"
                    counter="500"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="formData.allowed_file_types"
                    label="Allowed File Types"
                    hint="E.g., pdf,jpg,png"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="formData.max_file_size_mb"
                    label="Max File Size (MB)"
                    type="number"
                    :min="1"
                    :max="50"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="4">
                  <v-text-field
                    v-model.number="formData.display_order"
                    label="Display Order"
                    type="number"
                    :min="0"
                    variant="outlined"
                  />
                </v-col>

                <v-col cols="12" md="4">
                  <v-switch
                    v-model="formData.is_required"
                    label="Required Document"
                    color="error"
                    inset
                  />
                </v-col>

                <v-col cols="12" md="4">
                  <v-switch
                    v-model="formData.status"
                    label="Active"
                    color="success"
                    inset
                  />
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
            <v-btn
              color="primary"
              :loading="saving"
              :disabled="!formValid"
              @click="saveRequirement"
            >
              {{ isEditing ? 'Update' : 'Create' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteDialog" max-width="400px">
        <v-card>
          <v-card-title class="text-h5">Confirm Delete</v-card-title>
          <v-card-text>
            Are you sure you want to delete "{{ requirementToDelete?.name }}"?
            This action cannot be undone.
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
            <v-btn
              color="error"
              :loading="deleting"
              @click="confirmDelete"
            >
              Delete
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { documentRequirementAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { debounce } from 'lodash-es'
import AdminLayout from '../layout/AdminLayout.vue'

const { success: showSnackbar, error: showError } = useToast()

// Reactive data
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const requirements = ref([])
const search = ref('')
const requestTypeFilter = ref('')
const statusFilter = ref('')
const requiredFilter = ref('')

// Dialog states
const dialog = ref(false)
const deleteDialog = ref(false)
const formValid = ref(false)
const isEditing = ref(false)
const selectedRequirement = ref(null)
const requirementToDelete = ref(null)
const validationErrors = ref({})

// Form data
const formData = reactive({
  request_type: 'referral',
  document_type: '',
  name: '',
  description: '',
  is_required: false,
  allowed_file_types: 'pdf,jpg,png',
  max_file_size_mb: 10,
  display_order: 0,
  status: true
})

// Table headers
const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Document Type', key: 'document_type', sortable: true },
  { title: 'Request Type', key: 'request_type', sortable: true },
  { title: 'Required', key: 'is_required', sortable: true },
  { title: 'File Types', key: 'allowed_file_types', sortable: false },
  { title: 'Max Size', key: 'max_file_size_mb', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' }
]

// Options
const requestTypeOptions = [
  { title: 'All', value: '' },
  { title: 'Referral', value: 'referral' },
  { title: 'PA Code', value: 'pa_code' }
]

const requestTypeSelectOptions = [
  { title: 'Referral', value: 'referral' },
  { title: 'PA Code', value: 'pa_code' }
]

const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
]

const requiredOptions = [
  { title: 'All', value: '' },
  { title: 'Required', value: true },
  { title: 'Optional', value: false }
]

// Validation rules
const nameRules = [
  v => !!v || 'Display name is required',
  v => (v && v.length <= 255) || 'Name must be less than 255 characters'
]

const documentTypeRules = [
  v => !!v || 'Document type code is required',
  v => (v && v.length <= 50) || 'Code must be less than 50 characters',
  v => /^[a-z_]+$/.test(v) || 'Use lowercase letters and underscores only'
]

// Computed
const filteredRequirements = computed(() => {
  let result = requirements.value

  if (requestTypeFilter.value) {
    result = result.filter(r => r.request_type === requestTypeFilter.value)
  }

  if (statusFilter.value !== '' && statusFilter.value !== null) {
    result = result.filter(r => r.status === statusFilter.value)
  }

  if (requiredFilter.value !== '' && requiredFilter.value !== null) {
    result = result.filter(r => r.is_required === requiredFilter.value)
  }

  return result
})

const debouncedSearch = debounce(() => {
  loadRequirements()
}, 500)

// Methods
const loadRequirements = async () => {
  loading.value = true
  try {
    const params = {}
    if (requestTypeFilter.value) params.request_type = requestTypeFilter.value
    if (statusFilter.value !== '' && statusFilter.value !== null) params.status = statusFilter.value
    if (requiredFilter.value !== '' && requiredFilter.value !== null) params.is_required = requiredFilter.value

    const response = await documentRequirementAPI.getAll(params)
    if (response.data.success) {
      requirements.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading requirements:', error)
    showError('Failed to load document requirements')
  } finally {
    loading.value = false
  }
}

const refreshData = () => {
  search.value = ''
  requestTypeFilter.value = ''
  statusFilter.value = ''
  requiredFilter.value = ''
  loadRequirements()
}

const openCreateDialog = () => {
  isEditing.value = false
  resetForm()
  dialog.value = true
}

const editRequirement = (requirement) => {
  isEditing.value = true
  Object.assign(formData, {
    request_type: requirement.request_type,
    document_type: requirement.document_type,
    name: requirement.name,
    description: requirement.description || '',
    is_required: requirement.is_required,
    allowed_file_types: requirement.allowed_file_types || 'pdf,jpg,png',
    max_file_size_mb: requirement.max_file_size_mb || 10,
    display_order: requirement.display_order || 0,
    status: requirement.status
  })
  selectedRequirement.value = requirement
  dialog.value = true
}

const resetForm = () => {
  formData.request_type = 'referral'
  formData.document_type = ''
  formData.name = ''
  formData.description = ''
  formData.is_required = false
  formData.allowed_file_types = 'pdf,jpg,png'
  formData.max_file_size_mb = 10
  formData.display_order = 0
  formData.status = true
  selectedRequirement.value = null
  validationErrors.value = {}
}

const closeDialog = () => {
  dialog.value = false
  resetForm()
}

const saveRequirement = async () => {
  if (!formValid.value) return

  saving.value = true
  try {
    let response
    if (isEditing.value) {
      response = await documentRequirementAPI.update(selectedRequirement.value.id, formData)
    } else {
      response = await documentRequirementAPI.create(formData)
    }

    if (response.data.success) {
      showSnackbar(`Document requirement ${isEditing.value ? 'updated' : 'created'} successfully`)
      closeDialog()
      loadRequirements()
    }
  } catch (error) {
    console.error('Error saving requirement:', error)

    if (error.response?.status === 422 && error.response?.data?.errors) {
      validationErrors.value = error.response.data.errors
      const message = error.response?.data?.message || 'Validation failed'
      showError(message)
    } else {
      const message = error.response?.data?.message || 'Failed to save document requirement'
      showError(message)
    }
  } finally {
    saving.value = false
  }
}

const toggleStatus = async (requirement) => {
  try {
    const response = await documentRequirementAPI.toggleStatus(requirement.id)
    if (response.data.success) {
      showSnackbar('Status updated successfully')
      loadRequirements()
    }
  } catch (error) {
    console.error('Error toggling status:', error)
    showError('Failed to update status')
  }
}

const deleteRequirement = (requirement) => {
  requirementToDelete.value = requirement
  deleteDialog.value = true
}

const confirmDelete = async () => {
  if (!requirementToDelete.value) return

  deleting.value = true
  try {
    const response = await documentRequirementAPI.delete(requirementToDelete.value.id)
    if (response.data.success) {
      showSnackbar('Document requirement deleted successfully')
      deleteDialog.value = false
      requirementToDelete.value = null
      loadRequirements()
    }
  } catch (error) {
    console.error('Error deleting requirement:', error)
    const message = error.response?.data?.message || 'Failed to delete document requirement'
    showError(message)
  } finally {
    deleting.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadRequirements()
})
</script>
