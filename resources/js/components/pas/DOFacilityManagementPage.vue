<template>
  <AdminLayout>
    <div class="do-facility-management">
      <v-container fluid>
        <!-- Header -->
        <div class="d-flex justify-space-between align-center mb-6">
          <div>
            <h1 class="text-h4 font-weight-bold text-primary">Desk Officer Facility Assignments</h1>
            <p class="text-subtitle-1 text-grey-600 mt-2">Assign facilities to desk officers for management</p>
          </div>
          <v-btn
            color="primary"
            size="large"
            prepend-icon="mdi-plus"
            @click="openCreateDialog"
            elevation="2"
          >
            Assign Facility
          </v-btn>
        </div>

        <!-- Filters -->
        <v-row class="mb-4">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search assignments..."
              variant="outlined"
              density="compact"
              clearable
              @input="debouncedSearch"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="userFilter"
              :items="deskOfficers"
              item-title="name"
              item-value="id"
              label="Filter by Desk Officer"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="loadAssignments"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="facilityFilter"
              :items="facilities"
              item-title="name"
              item-value="id"
              label="Filter by Facility"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="loadAssignments"
            />
          </v-col>
          <v-col cols="12" md="2">
            <v-btn
              color="secondary"
              variant="outlined"
              block
              @click="refreshData"
              :loading="loading"
            >
              <v-icon>mdi-refresh</v-icon>
              Refresh
            </v-btn>
          </v-col>
        </v-row>

        <!-- Data Table -->
        <v-card elevation="2" class="rounded-lg">
          <v-data-table
            :headers="headers"
            :items="assignments"
            :loading="loading"
            :items-per-page="perPage"
            :server-items-length="totalItems"
            :page="currentPage"
            @update:page="currentPage = $event; loadAssignments()"
            @update:items-per-page="perPage = $event; loadAssignments()"
            class="elevation-0"
            item-key="id"
          >
            <template #item.user="{ item }">
              <div class="d-flex align-center py-2">
                <v-avatar size="32" color="primary" class="mr-3">
                  <span class="text-white text-caption font-weight-bold">
                    {{ item.user.name.charAt(0).toUpperCase() }}
                  </span>
                </v-avatar>
                <div>
                  <div class="font-weight-medium">{{ item.user.name }}</div>
                  <div class="text-caption text-grey-600">{{ item.user.email }}</div>
                </div>
              </div>
            </template>

            <template #item.facility="{ item }">
              <div>
                <div class="font-weight-medium">{{ item.facility.name }}</div>
                <div class="text-caption text-grey-600">{{ item.facility.hcp_code }}</div>
                <v-chip
                  :color="item.facility.ownership === 'Public' ? 'green' : 'blue'"
                  size="x-small"
                  variant="flat"
                  class="mt-1"
                >
                  {{ item.facility.ownership }}
                </v-chip>
              </div>
            </template>

            <template #item.location="{ item }">
              <div v-if="item.facility.lga || item.facility.ward">
                <div class="text-caption">{{ item.facility.lga?.name || 'N/A' }}</div>
                <div class="text-caption text-grey-600">{{ item.facility.ward?.name || 'N/A' }}</div>
              </div>
              <span v-else class="text-grey-400">N/A</span>
            </template>

            <template #item.assigned_at="{ item }">
              {{ formatDate(item.assigned_at) }}
            </template>

            <template #item.actions="{ item }">
              <div class="d-flex gap-1">
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  color="primary"
                  @click="viewAssignment(item)"
                >
                  <v-icon>mdi-eye</v-icon>
                  <v-tooltip activator="parent">View Details</v-tooltip>
                </v-btn>
                <v-btn
                  icon="mdi-pencil"
                  size="small"
                  variant="text"
                  color="warning"
                  @click="editAssignment(item)"
                >
                  <v-icon>mdi-pencil</v-icon>
                  <v-tooltip activator="parent">Edit Assignment</v-tooltip>
                </v-btn>
                <v-btn
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="deleteAssignment(item)"
                >
                  <v-icon>mdi-delete</v-icon>
                  <v-tooltip activator="parent">Remove Assignment</v-tooltip>
                </v-btn>
              </div>
            </template>

            <template #no-data>
              <div class="text-center py-8">
                <v-icon size="64" color="grey-400" class="mb-4">mdi-clipboard-text-off</v-icon>
                <div class="text-h6 text-grey-600 mb-2">No assignments found</div>
                <div class="text-body-2 text-grey-500">Start by assigning facilities to desk officers</div>
              </div>
            </template>
          </v-data-table>
        </v-card>

        <!-- Create/Edit Dialog -->
        <v-dialog v-model="dialog" max-width="600px" persistent>
          <v-card class="rounded-lg">
            <v-card-title class="d-flex align-center pa-6 bg-primary">
              <v-icon color="white" class="mr-3">mdi-hospital-marker</v-icon>
              <span class="text-h6 text-white font-weight-bold">
                {{ isEditing ? 'Edit' : 'Create' }} Facility Assignment
              </span>
            </v-card-title>

            <v-form ref="formRef" v-model="formValid" @submit.prevent="saveAssignment">
              <v-card-text class="pa-6">
                <v-row>
                  <v-col cols="12">
                    <v-select
                      v-model="formData.user_id"
                      :items="deskOfficers"
                      item-title="name"
                      item-value="id"
                      label="Desk Officer *"
                      :rules="userRules"
                      :error-messages="validationErrors.user_id"
                      variant="outlined"
                      required
                    >
                      <template #item="{ props, item }">
                        <v-list-item v-bind="props">
                          <template #prepend>
                            <v-avatar size="32" color="primary">
                              <span class="text-white text-caption">
                                {{ item.raw.name.charAt(0).toUpperCase() }}
                              </span>
                            </v-avatar>
                          </template>
                          <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                          <v-list-item-subtitle>{{ item.raw.email }}</v-list-item-subtitle>
                        </v-list-item>
                      </template>
                    </v-select>
                  </v-col>

                  <v-col cols="12">
                    <v-select
                      v-model="formData.facility_id"
                      :items="facilities"
                      item-title="name"
                      item-value="id"
                      label="Facility *"
                      :rules="facilityRules"
                      :error-messages="validationErrors.facility_id"
                      variant="outlined"
                      required
                    >
                      <template #item="{ props, item }">
                        <v-list-item v-bind="props">
                          <v-list-item-title>{{ item.raw.name }}</v-list-item-title>
                          <v-list-item-subtitle>
                            {{ item.raw.hcp_code }} • {{ item.raw.ownership }}
                          </v-list-item-subtitle>
                        </v-list-item>
                      </template>
                    </v-select>
                  </v-col>
                </v-row>
              </v-card-text>

              <v-card-actions class="pa-6 pt-0">
                <v-spacer />
                <v-btn
                  variant="outlined"
                  @click="closeDialog"
                  :disabled="saving"
                >
                  Cancel
                </v-btn>
                <v-btn
                  type="submit"
                  color="primary"
                  :loading="saving"
                  :disabled="!formValid"
                >
                  {{ isEditing ? 'Update' : 'Assign' }}
                </v-btn>
              </v-card-actions>
            </v-form>
          </v-card>
        </v-dialog>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { doFacilityAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { debounce } from 'lodash-es'
import AdminLayout from '../layout/AdminLayout.vue'

const { success: showSnackbar, error: showError } = useToast()

// Reactive data
const loading = ref(false)
const saving = ref(false)
const assignments = ref([])
const deskOfficers = ref([])
const facilities = ref([])
const totalItems = ref(0)
const currentPage = ref(1)
const perPage = ref(15)
const search = ref('')
const userFilter = ref('')
const facilityFilter = ref('')

// Dialog states
const dialog = ref(false)
const formValid = ref(false)
const isEditing = ref(false)
const selectedAssignment = ref(null)
const validationErrors = ref({})

// Form data
const formData = reactive({
  user_id: '',
  facility_id: ''
})

// Table headers
const headers = [
  { title: 'Desk Officer', key: 'user', sortable: false },
  { title: 'Facility', key: 'facility', sortable: false },
  { title: 'Location', key: 'location', sortable: false },
  { title: 'Assigned Date', key: 'assigned_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' }
]

// Validation rules
const userRules = [
  v => !!v || 'Desk Officer is required'
]

const facilityRules = [
  v => !!v || 'Facility is required'
]

// Debounced search
const debouncedSearch = debounce(() => {
  currentPage.value = 1
  loadAssignments()
}, 300)

// Lifecycle
onMounted(() => {
  loadInitialData()
})

// Methods
const loadInitialData = async () => {
  await Promise.all([
    loadDeskOfficers(),
    loadFacilities(),
    loadAssignments()
  ])
}

const loadAssignments = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: search.value,
      user_id: userFilter.value,
      facility_id: facilityFilter.value
    }

    const response = await doFacilityAPI.getAll(params)
    if (response.data.success) {
      assignments.value = response.data.data.data
      totalItems.value = response.data.data.total
    }
  } catch (error) {
    console.error('Error loading assignments:', error)
    showSnackbar('Failed to load assignments', 'error')
  } finally {
    loading.value = false
  }
}

const loadDeskOfficers = async () => {
  try {
    const response = await doFacilityAPI.getDeskOfficers()
    if (response.data.success) {
      deskOfficers.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading desk officers:', error)
    showSnackbar('Failed to load desk officers', 'error')
  }
}

const loadFacilities = async () => {
  try {
    const response = await doFacilityAPI.getFacilities()
    if (response.data.success) {
      facilities.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading facilities:', error)
    showSnackbar('Failed to load facilities', 'error')
  }
}

const refreshData = () => {
  search.value = ''
  userFilter.value = ''
  facilityFilter.value = ''
  currentPage.value = 1
  loadAssignments()
}

const openCreateDialog = () => {
  isEditing.value = false
  resetForm()
  dialog.value = true
}

const viewAssignment = (assignment) => {
  // Could open a detailed view dialog
  console.log('View assignment:', assignment)
}

const editAssignment = (assignment) => {
  isEditing.value = true
  formData.user_id = assignment.user_id
  formData.facility_id = assignment.facility_id
  selectedAssignment.value = assignment
  validationErrors.value = {}
  dialog.value = true
}

const deleteAssignment = (assignment) => {
  if (confirm('Are you sure you want to remove this facility assignment?')) {
    removeAssignment(assignment.id)
  }
}

const removeAssignment = async (id) => {
  try {
    const response = await doFacilityAPI.delete(id)
    if (response.data.success) {
      showSnackbar('Assignment removed successfully', 'success')
      loadAssignments()
    }
  } catch (error) {
    console.error('Error removing assignment:', error)
    showSnackbar('Failed to remove assignment', 'error')
  }
}

const resetForm = () => {
  formData.user_id = ''
  formData.facility_id = ''
  selectedAssignment.value = null
  validationErrors.value = {}
}

const closeDialog = () => {
  dialog.value = false
  resetForm()
}

const saveAssignment = async () => {
  if (!formValid.value) return

  saving.value = true
  try {
    let response
    if (isEditing.value) {
      response = await doFacilityAPI.update(selectedAssignment.value.id, formData)
    } else {
      response = await doFacilityAPI.create(formData)
    }

    if (response.data.success) {
      showSnackbar(
        `Assignment ${isEditing.value ? 'updated' : 'created'} successfully`,
        'success'
      )
      closeDialog()
      loadAssignments()
    }
  } catch (error) {
    console.error('Error saving assignment:', error)

    // Handle validation errors
    if (error.response?.status === 422 && error.response?.data?.errors) {
      validationErrors.value = error.response.data.errors
      const message = error.response?.data?.message || 'Validation failed'
      showSnackbar(message, 'error')
    } else {
      const message = error.response?.data?.message || 'Failed to save assignment'
      showSnackbar(message, 'error')
    }
  } finally {
    saving.value = false
  }
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
