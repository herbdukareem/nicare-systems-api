<template>
  <AdminLayout>
    <div class="document-requirements-page">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="d-flex justify-space-between align-center mb-4">
            <div>
              <h1 class="text-h4">Document Requirements Management</h1>
              <p class="text-subtitle-1 text-grey">Manage document requirements for referrals and PA codes</p>
            </div>
            <v-btn color="primary" @click="openCreateDialog">
              <v-icon left>mdi-plus</v-icon>
              Add Requirement
            </v-btn>
          </div>
        </v-col>
      </v-row>

      <!-- Statistics Cards -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Total Requirements</p>
                  <h3 class="text-h5">{{ statistics.total || 0 }}</h3>
                </div>
                <v-icon size="40" color="primary">mdi-file-document-multiple</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Active Requirements</p>
                  <h3 class="text-h5">{{ statistics.active || 0 }}</h3>
                </div>
                <v-icon size="40" color="success">mdi-check-circle</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Referral Documents</p>
                  <h3 class="text-h5">{{ statistics.referral_docs || 0 }}</h3>
                </div>
                <v-icon size="40" color="info">mdi-hospital-box</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">PA Documents</p>
                  <h3 class="text-h5">{{ statistics.pa_docs || 0 }}</h3>
                </div>
                <v-icon size="40" color="warning">mdi-file-check</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Filters -->
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search requirements..."
                    prepend-inner-icon="mdi-magnify"
                    outlined
                    dense
                    clearable
                    @input="debouncedSearch"
                    hint="Search by name or description"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="statusFilter"
                    label="Status"
                    :items="statusOptions"
                    outlined
                    dense
                    clearable
                    @update:modelValue="loadRequirements"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="documentTypeFilter"
                    label="Document Type"
                    :items="documentTypes"
                    outlined
                    dense
                    clearable
                    @update:modelValue="loadRequirements"
                  />
                </v-col>
                <v-col cols="12" md="2">
                  <v-btn color="secondary" block @click="resetFilters">
                    <v-icon left>mdi-refresh</v-icon>
                    Reset
                  </v-btn>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Data Table -->
      <v-row class="mt-4">
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex justify-space-between align-center">
              <span>Document Requirements List</span>
              <div>
                <v-btn
                  icon
                  variant="text"
                  @click="loadRequirements"
                  :loading="loading"
                  title="Refresh"
                >
                  <v-icon>mdi-refresh</v-icon>
                </v-btn>
                <v-btn
                  icon
                  variant="text"
                  @click="exportRequirements"
                  title="Export to Excel"
                >
                  <v-icon>mdi-download</v-icon>
                </v-btn>
              </div>
            </v-card-title>
            <v-card-text>
              <v-data-table
                :headers="headers"
                :items="requirements"
                :loading="loading"
                :items-per-page="15"
                :server-items-length="totalItems"
                @update:options="handleTableUpdate"
                class="elevation-0"
              >
                <template v-slot:item.requirement_name="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.requirement_name }}</div>
                    <div class="text-caption text-grey">{{ item.description }}</div>
                  </div>
                </template>

                <template v-slot:item.document_type="{ item }">
                  <v-chip :color="getDocumentTypeColor(item.document_type)" variant="outlined" size="small">
                    {{ item.document_type }}
                  </v-chip>
                </template>

                <template v-slot:item.is_mandatory="{ item }">
                  <v-chip :color="item.is_mandatory ? 'error' : 'grey'" size="small">
                    {{ item.is_mandatory ? 'Mandatory' : 'Optional' }}
                  </v-chip>
                </template>

                <template v-slot:item.is_active="{ item }">
                  <v-chip :color="item.is_active ? 'success' : 'grey'" size="small">
                    {{ item.is_active ? 'Active' : 'Inactive' }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    size="small"
                    variant="text"
                    @click="editRequirement(item)"
                    title="Edit"
                  >
                    <v-icon size="small">mdi-pencil</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    size="small"
                    variant="text"
                    color="error"
                    @click="confirmDelete(item)"
                    title="Delete"
                  >
                    <v-icon size="small">mdi-delete</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="800px" persistent>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>{{ editMode ? 'Edit Requirement' : 'Add New Requirement' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="requirementForm">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.requirement_name"
                  label="Requirement Name *"
                  outlined
                  dense
                  :rules="[v => !!v || 'Requirement name is required']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.document_type"
                  label="Document Type *"
                  :items="documentTypes"
                  outlined
                  dense
                  :rules="[v => !!v || 'Document type is required']"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="formData.description"
                  label="Description"
                  outlined
                  dense
                  rows="3"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-switch
                  v-model="formData.is_mandatory"
                  label="Mandatory"
                  color="error"
                  hide-details
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-switch
                  v-model="formData.is_active"
                  label="Active"
                  color="success"
                  hide-details
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model.number="formData.display_order"
                  label="Display Order"
                  type="number"
                  outlined
                  dense
                  hint="Order in which this requirement appears"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveRequirement" :loading="saving">
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="500px">
      <v-card>
        <v-card-title class="bg-error text-white">
          <span>Confirm Delete</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <p>Are you sure you want to delete this requirement?</p>
          <p class="font-weight-bold">{{ requirementToDelete?.requirement_name }}</p>
          <v-alert type="warning" class="mt-4">
            This action cannot be undone.
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteRequirement" :loading="deleting">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '../layout/AdminLayout.vue';
import { ref, onMounted } from 'vue';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import { debounce } from 'lodash';

const { showSuccess, showError } = useToast();

// Reactive state
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const requirements = ref([]);
const statistics = ref({});
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref(null);
const documentTypeFilter = ref(null);

const showCreateDialog = ref(false);
const showDeleteDialog = ref(false);
const editMode = ref(false);
const requirementForm = ref(null);
const requirementToDelete = ref(null);

const formData = ref({
  requirement_name: '',
  description: '',
  document_type: '',
  is_mandatory: false,
  is_active: true,
  display_order: 0,
});

const headers = [
  { title: 'Requirement Name', key: 'requirement_name', sortable: true },
  { title: 'Document Type', key: 'document_type', sortable: true },
  { title: 'Mandatory', key: 'is_mandatory', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Display Order', key: 'display_order', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
];

const documentTypes = [
  { title: 'Referral', value: 'REFERRAL' },
  { title: 'Pre-Authorization', value: 'PA' },
  { title: 'Both', value: 'BOTH' },
];

onMounted(async () => {
  await Promise.all([
    loadRequirements(),
    loadStatistics(),
  ]);
});

// Load requirements with pagination and filters
const loadRequirements = async (options = {}) => {
  loading.value = true;
  try {
    const params = {
      page: options.page || 1,
      per_page: options.itemsPerPage || 15,
      search: searchQuery.value,
      is_active: statusFilter.value,
      document_type: documentTypeFilter.value,
    };

    const response = await api.get('/api/document-requirements', { params });
    requirements.value = response.data.data || response.data;
    totalItems.value = response.data.total || requirements.value.length;
  } catch (err) {
    showError('Failed to load document requirements');
  } finally {
    loading.value = false;
  }
};

// Load statistics
const loadStatistics = async () => {
  try {
    const response = await api.get('/api/document-requirements/statistics');
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

// Debounced search
const debouncedSearch = debounce(() => {
  loadRequirements();
}, 500);

// Handle table update (pagination, sorting)
const handleTableUpdate = (options) => {
  loadRequirements(options);
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  documentTypeFilter.value = null;
  loadRequirements();
};

// Open create dialog
const openCreateDialog = () => {
  editMode.value = false;
  formData.value = {
    requirement_name: '',
    description: '',
    document_type: '',
    is_mandatory: false,
    is_active: true,
    display_order: 0,
  };
  showCreateDialog.value = true;
};

// Edit requirement
const editRequirement = (requirement) => {
  editMode.value = true;
  formData.value = { ...requirement };
  showCreateDialog.value = true;
};

// Save requirement (create or update)
const saveRequirement = async () => {
  if (!requirementForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/api/document-requirements/${formData.value.id}`, formData.value);
      showSuccess('Requirement updated successfully');
    } else {
      await api.post('/api/document-requirements', formData.value);
      showSuccess('Requirement created successfully');
    }
    closeDialog();
    await Promise.all([loadRequirements(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to save requirement';
    showError(message);
  } finally {
    saving.value = false;
  }
};

// Confirm delete
const confirmDelete = (requirement) => {
  requirementToDelete.value = requirement;
  showDeleteDialog.value = true;
};

// Delete requirement
const deleteRequirement = async () => {
  deleting.value = true;
  try {
    await api.delete(`/api/document-requirements/${requirementToDelete.value.id}`);
    showSuccess('Requirement deleted successfully');
    showDeleteDialog.value = false;
    requirementToDelete.value = null;
    await Promise.all([loadRequirements(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to delete requirement';
    showError(message);
  } finally {
    deleting.value = false;
  }
};

// Close dialog
const closeDialog = () => {
  showCreateDialog.value = false;
  editMode.value = false;
  formData.value = {
    requirement_name: '',
    description: '',
    document_type: '',
    is_mandatory: false,
    is_active: true,
    display_order: 0,
  };
};

// Export requirements
const exportRequirements = async () => {
  try {
    const response = await api.get('/api/document-requirements-export', {
      responseType: 'blob',
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `document-requirements-${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Requirements exported successfully');
  } catch (err) {
    showError('Failed to export requirements');
  }
};

// Get document type color
const getDocumentTypeColor = (type) => {
  const colors = {
    REFERRAL: 'info',
    PA: 'warning',
    BOTH: 'purple',
  };
  return colors[type] || 'grey';
};
</script>

