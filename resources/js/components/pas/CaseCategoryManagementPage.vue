<template>
  <AdminLayout>
    <div class="case-category-management">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h2 class="text-h4 font-weight-bold">Case Categories</h2>
              <p class="text-subtitle-1 text-grey-darken-1">Manage medical case categories</p>
            </div>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              @click="openCreateDialog"
            >
              Add Category
            </v-btn>
          </div>
        </v-col>
      </v-row>

      <!-- Filters -->
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Search categories..."
            variant="outlined"
            density="compact"
            clearable
            @input="debouncedSearch"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
            @update:model-value="loadCategories"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            v-model="perPage"
            :items="perPageOptions"
            label="Per Page"
            variant="outlined"
            density="compact"
            @update:model-value="loadCategories"
          />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center">
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
        <v-data-table-server
          v-model:items-per-page="perPage"
          v-model:page="currentPage"
          :headers="headers"
          :items="categories"
          :items-length="totalItems"
          :loading="loading"
          :search="search"
          class="elevation-1"
          @update:options="loadCategories"
        >
          <template #item.status="{ item }">
            <v-chip
              :color="item.status ? 'success' : 'error'"
              size="small"
              variant="flat"
            >
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <v-tooltip text="View Details">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-eye"
                    size="small"
                    variant="text"
                    @click="viewCategory(item)"
                  />
                </template>
              </v-tooltip>

              <v-tooltip text="Edit">
                <template #activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    @click="editCategory(item)"
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
                    @click="deleteCategory(item)"
                  />
                </template>
              </v-tooltip>
            </div>
          </template>
        </v-data-table-server>
      </v-card>

      <!-- Create/Edit Dialog -->
      <v-dialog v-model="dialog" max-width="600px" persistent>
        <v-card>
          <v-card-title class="text-h5">
            {{ isEditing ? 'Edit' : 'Create' }} Case Category
          </v-card-title>

          <v-card-text>
            <v-form ref="form" v-model="formValid">
              <v-row>
                <v-col cols="12">
                  <v-text-field
                    v-model="formData.name"
                    label="Category Name *"
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
                    :error-messages="validationErrors.description"
                    variant="outlined"
                    rows="3"
                    counter="500"
                  />
                </v-col>

                <v-col cols="12">
                  <v-switch
                    v-model="formData.status"
                    label="Active Status"
                    color="primary"
                    inset
                  />
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn
              variant="text"
              @click="closeDialog"
            >
              Cancel
            </v-btn>
            <v-btn
              color="primary"
              :loading="saving"
              :disabled="!formValid"
              @click="saveCategory"
            >
              {{ isEditing ? 'Update' : 'Create' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- View Dialog -->
      <v-dialog v-model="viewDialog" max-width="700px">
        <v-card v-if="selectedCategory">
          <v-card-title class="text-h5">
            Case Category Details
          </v-card-title>

          <v-card-text>
            <v-row>
              <v-col cols="12" md="6">
                <v-list-item>
                  <v-list-item-title class="text-subtitle-2">Name</v-list-item-title>
                  <v-list-item-subtitle>{{ selectedCategory.name }}</v-list-item-subtitle>
                </v-list-item>
              </v-col>

              <v-col cols="12" md="6">
                <v-list-item>
                  <v-list-item-title class="text-subtitle-2">Status</v-list-item-title>
                  <v-list-item-subtitle>
                    <v-chip
                      :color="selectedCategory.status ? 'success' : 'error'"
                      size="small"
                      variant="flat"
                    >
                      {{ selectedCategory.status ? 'Active' : 'Inactive' }}
                    </v-chip>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-col>

              <v-col cols="12">
                <v-list-item>
                  <v-list-item-title class="text-subtitle-2">Description</v-list-item-title>
                  <v-list-item-subtitle>{{ selectedCategory.description || 'No description provided' }}</v-list-item-subtitle>
                </v-list-item>
              </v-col>

              <v-col cols="12" md="6">
                <v-list-item>
                  <v-list-item-title class="text-subtitle-2">Created</v-list-item-title>
                  <v-list-item-subtitle>{{ formatDate(selectedCategory.created_at) }}</v-list-item-subtitle>
                </v-list-item>
              </v-col>

              <v-col cols="12" md="6">
                <v-list-item>
                  <v-list-item-title class="text-subtitle-2">Last Updated</v-list-item-title>
                  <v-list-item-subtitle>{{ formatDate(selectedCategory.updated_at) }}</v-list-item-subtitle>
                </v-list-item>
              </v-col>
            </v-row>
          </v-card-text>

          <v-card-actions>
            <v-spacer />
            <v-btn
              variant="text"
              @click="viewDialog = false"
            >
              Close
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirmation Dialog -->
      <v-dialog v-model="deleteDialog" max-width="400px">
        <v-card>
          <v-card-title class="text-h5">Confirm Delete</v-card-title>
          <v-card-text>
            Are you sure you want to delete the category "{{ categoryToDelete?.name }}"?
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
import { ref, reactive, onMounted } from 'vue'
import { caseCategoryAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { debounce } from 'lodash-es'
import AdminLayout from '../layout/AdminLayout.vue'

const { success: showSnackbar, error: showError } = useToast()

// Reactive data
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const categories = ref([])
const totalItems = ref(0)
const currentPage = ref(1)
const perPage = ref(15)
const search = ref('')
const statusFilter = ref('')

// Dialog states
const dialog = ref(false)
const viewDialog = ref(false)
const deleteDialog = ref(false)
const formValid = ref(false)
const isEditing = ref(false)
const selectedCategory = ref(null)
const categoryToDelete = ref(null)
const validationErrors = ref({})

// Form data
const formData = reactive({
  name: '',
  description: '',
  status: true
})

// Table headers
const headers = [
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '200px' }
]

// Options
const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
]

const perPageOptions = [10, 15, 25, 50, 100]

// Validation rules
const nameRules = [
  v => !!v || 'Category name is required',
  v => (v && v.length <= 255) || 'Name must be less than 255 characters'
]

// Computed
const debouncedSearch = debounce(() => {
  loadCategories()
}, 500)

// Methods
const loadCategories = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: perPage.value,
      search: search.value,
      status: statusFilter.value
    }

    const response = await caseCategoryAPI.getAll(params)
    if (response.data.success) {
      categories.value = response.data.data.data
      totalItems.value = response.data.data.total
    }
  } catch (error) {
    console.error('Error loading categories:', error)
    showSnackbar('Failed to load categories', 'error')
  } finally {
    loading.value = false
  }
}

const refreshData = () => {
  search.value = ''
  statusFilter.value = ''
  currentPage.value = 1
  loadCategories()
}

const openCreateDialog = () => {
  isEditing.value = false
  resetForm()
  dialog.value = true
}

const editCategory = (category) => {
  isEditing.value = true
  formData.name = category.name
  formData.description = category.description || ''
  formData.status = category.status
  selectedCategory.value = category
  dialog.value = true
}

const viewCategory = (category) => {
  selectedCategory.value = category
  viewDialog.value = true
}

const resetForm = () => {
  formData.name = ''
  formData.description = ''
  formData.status = true
  selectedCategory.value = null
  validationErrors.value = {}
}

const closeDialog = () => {
  dialog.value = false
  resetForm()
}

const saveCategory = async () => {
  if (!formValid.value) return

  saving.value = true
  try {
    let response
    if (isEditing.value) {
      response = await caseCategoryAPI.update(selectedCategory.value.id, formData)
    } else {
      response = await caseCategoryAPI.create(formData)
    }

    if (response.data.success) {
      showSnackbar(
        `Category ${isEditing.value ? 'updated' : 'created'} successfully`,
        'success'
      )
      closeDialog()
      loadCategories()
    }
  } catch (error) {
    console.error('Error saving category:', error)

    // Handle validation errors
    if (error.response?.status === 422 && error.response?.data?.errors) {
      validationErrors.value = error.response.data.errors
      const message = error.response?.data?.message || 'Validation failed'
      showSnackbar(message, 'error')
    } else {
      const message = error.response?.data?.message || 'Failed to save category'
      showSnackbar(message, 'error')
    }
  } finally {
    saving.value = false
  }
}

const toggleStatus = async (category) => {
  try {
    const response = await caseCategoryAPI.toggleStatus(category.id)
    if (response.data.success) {
      showSnackbar('Category status updated successfully', 'success')
      loadCategories()
    }
  } catch (error) {
    console.error('Error toggling status:', error)
    showSnackbar('Failed to update category status', 'error')
  }
}

const deleteCategory = (category) => {
  categoryToDelete.value = category
  deleteDialog.value = true
}

const confirmDelete = async () => {
  if (!categoryToDelete.value) return

  deleting.value = true
  try {
    const response = await caseCategoryAPI.delete(categoryToDelete.value.id)
    if (response.data.success) {
      showSnackbar('Category deleted successfully', 'success')
      deleteDialog.value = false
      categoryToDelete.value = null
      loadCategories()
    }
  } catch (error) {
    console.error('Error deleting category:', error)
    const message = error.response?.data?.message || 'Failed to delete category'
    showSnackbar(message, 'error')
  } finally {
    deleting.value = false
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

// Lifecycle
onMounted(() => {
  loadCategories()
})
</script>
