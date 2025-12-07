<template>
  <AdminLayout>
    <div class="bundle-services-management-page">
      <v-container fluid>
      <!-- Page Header -->
      <v-row>
        <v-col cols="12">
          <h1 class="text-h4 mb-2">Bundle Services Management</h1>
          <p class="text-subtitle-1 text-grey">Manage service bundles and configurations</p>
        </v-col>
      </v-row>

      <!-- Statistics Cards -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card color="primary" dark>
            <v-card-text>
              <div class="text-h6">Total Bundles</div>
              <div class="text-h4">{{ statistics.total || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="success" dark>
            <v-card-text>
              <div class="text-h6">Active Bundles</div>
              <div class="text-h4">{{ statistics.active || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="warning" dark>
            <v-card-text>
              <div class="text-h6">Inactive Bundles</div>
              <div class="text-h4">{{ statistics.inactive || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="info" dark>
            <v-card-text>
              <div class="text-h6">Avg Price</div>
              <div class="text-h4">₦{{ Number(statistics.average_price || 0).toLocaleString() }}</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Filters and Actions -->
      <v-row class="mt-4">
        <v-col cols="12">
          <v-card>
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search bundles..."
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    outlined
                    dense
                    @input="debouncedSearch"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="statusFilter"
                    label="Status"
                    :items="statusOptions"
                    clearable
                    outlined
                    dense
                    @update:model-value="loadBundles"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-text-field
                    v-model="diagnosisFilter"
                    label="Filter by ICD-10"
                    clearable
                    outlined
                    dense
                    @input="debouncedSearch"
                  />
                </v-col>
                <v-col cols="12" md="2" class="d-flex align-center">
                  <v-btn color="secondary" @click="resetFilters" block>
                    <v-icon left>mdi-refresh</v-icon>
                    Reset
                  </v-btn>
                </v-col>
              </v-row>
              <v-row>
                <v-col cols="12" class="d-flex justify-end gap-2">
                  <v-btn color="primary" @click="showCreateDialog = true">
                    <v-icon left>mdi-plus</v-icon>
                    Add Bundle
                  </v-btn>
                  <v-btn color="success" @click="showImportDialog = true">
                    <v-icon left>mdi-upload</v-icon>
                    Import
                  </v-btn>
                  <v-btn color="info" @click="exportBundles">
                    <v-icon left>mdi-download</v-icon>
                    Export
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
            <v-card-text>
              <v-data-table
                :headers="headers"
                :items="bundles"
                :loading="loading"
                :items-per-page="15"
                :server-items-length="totalItems"
                @update:options="handleTableUpdate"
                class="elevation-0"
              >
                <template v-slot:item.code="{ item }">
                  <v-chip color="primary" variant="outlined" size="small">
                    {{ item.code }}
                  </v-chip>
                </template>

                <template v-slot:item.name="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.name }}</div>
                    <div class="text-caption text-grey">{{ item.description }}</div>
                  </div>
                </template>

                <template v-slot:item.diagnosis_icd10="{ item }">
                  <v-chip color="purple" variant="outlined" size="small">
                    {{ item.diagnosis_icd10 }}
                  </v-chip>
                </template>

                <template v-slot:item.fixed_price="{ item }">
                  <span class="font-weight-medium">₦{{ Number(item.fixed_price).toLocaleString() }}</span>
                </template>



                <template v-slot:item.components_count="{ item }">
                  <v-chip color="info" variant="outlined" size="small">
                    {{ item.components_count || 0 }} items
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="viewComponents(item)"
                    title="View Components"
                  >
                    <v-icon>mdi-eye</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="editBundle(item)"
                    title="Edit"
                  >
                    <v-icon>mdi-pencil</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    color="error"
                    @click="confirmDelete(item)"
                    title="Delete"
                  >
                    <v-icon>mdi-delete</v-icon>
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
          <span>{{ editMode ? 'Edit Bundle' : 'Add New Bundle' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="bundleForm">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.code"
                  label="Bundle Code *"
                  :rules="[v => !!v || 'Bundle code is required']"
                  outlined
                  dense
                  hint="e.g., MAL-001"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.name"
                  label="Bundle Name *"
                  :rules="[v => !!v || 'Bundle name is required']"
                  outlined
                  dense
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-textarea
                  v-model="formData.description"
                  label="Description"
                  outlined
                  dense
                  rows="3"
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.diagnosis_icd10"
                  label="Principal ICD-10 Diagnosis *"
                  :rules="[v => !!v || 'ICD-10 code is required']"
                  outlined
                  dense
                  hint="e.g., A00.0 for Cholera"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.fixed_price"
                  label="Fixed Price (₦) *"
                  type="number"
                  :rules="[v => !!v || 'Fixed price is required', v => v > 0 || 'Price must be greater than 0']"
                  outlined
                  dense
                  prefix="₦"
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.is_active"
                  label="Status *"
                  :items="[{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }]"
                  :rules="[v => v !== null || 'Status is required']"
                  outlined
                  dense
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveBundle" :loading="saving">
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Components Dialog -->
    <v-dialog v-model="showComponentsDialog" max-width="900px">
      <v-card>
        <v-card-title class="bg-info text-white">
          <span>Bundle Components - {{ selectedBundle?.name }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert type="info" class="mb-4">
            <strong>Bundle Code:</strong> {{ selectedBundle?.code }} |
            <strong>Fixed Price:</strong> ₦{{ Number(selectedBundle?.fixed_price || 0).toLocaleString() }} |
            <strong>ICD-10:</strong> {{ selectedBundle?.diagnosis_icd10 }}
          </v-alert>

          <v-data-table
            :headers="componentHeaders"
            :items="bundleComponents"
            :loading="loadingComponents"
            class="elevation-0"
          >
            <template v-slot:item.case_record="{ item }">
              <div>
                <div class="font-weight-medium">{{ item.case_record?.service_description }}</div>
                <div class="text-caption text-grey">{{ item.case_record?.nicare_code }}</div>
              </div>
            </template>

            <template v-slot:item.item_type="{ item }">
              <v-chip
                :color="getItemTypeColor(item.item_type)"
                text-color="white"
                size="small"
              >
                {{ item.item_type }}
              </v-chip>
            </template>

            <template v-slot:item.max_quantity="{ item }">
              <v-chip color="info" variant="outlined" size="small">
                {{ item.max_quantity }}
              </v-chip>
            </template>
          </v-data-table>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showComponentsDialog = false">Close</v-btn>
          <v-btn color="primary" @click="navigateToComponents">
            Manage Components
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>Import Bundles from Excel</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-file-input
            v-model="importFile"
            label="Select Excel file"
            accept=".xlsx,.xls"
            outlined
            dense
            prepend-icon="mdi-file-excel"
          />
          <v-alert type="info" class="mt-4">
            <div class="text-caption">
              <strong>Template columns:</strong> code, name, description, diagnosis_icd10, fixed_price, is_active
            </div>
          </v-alert>
          <v-btn
            color="secondary"
            block
            @click="downloadTemplate"
            class="mt-2"
          >
            <v-icon left>mdi-download</v-icon>
            Download Template
          </v-btn>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showImportDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="importBundles" :loading="importing">
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="400px">
      <v-card>
        <v-card-title class="bg-error text-white">
          <span>Confirm Delete</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <p>Are you sure you want to delete this bundle?</p>
          <p class="font-weight-bold">{{ bundleToDelete?.name }}</p>
          <v-alert type="warning" class="mt-2">
            This will also delete all associated components!
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteBundle" :loading="deleting">
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
import { useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import { debounce } from 'lodash';

const router = useRouter();
const { showSuccess, showError } = useToast();

// Reactive state
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const importing = ref(false);
const loadingComponents = ref(false);
const bundles = ref([]);
const bundleComponents = ref([]);
const statistics = ref({});
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref(null);
const diagnosisFilter = ref('');

const showCreateDialog = ref(false);
const showImportDialog = ref(false);
const showDeleteDialog = ref(false);
const showComponentsDialog = ref(false);
const editMode = ref(false);
const bundleForm = ref(null);
const bundleToDelete = ref(null);
const selectedBundle = ref(null);
const importFile = ref(null);

const formData = ref({
  code: '',
  name: '',
  description: '',
  diagnosis_icd10: '',
  fixed_price: 0,
  is_active: 1,
});

const headers = [
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Bundle Name', key: 'name', sortable: true },
  { title: 'ICD-10', key: 'diagnosis_icd10', sortable: true },
  { title: 'Fixed Price', key: 'fixed_price', sortable: true },
  { title: 'Components', key: 'components_count', sortable: true },
  { title: 'Status', key: 'is_active', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

const componentHeaders = [
  { title: 'Item', key: 'case_record', sortable: false },
  { title: 'Type', key: 'item_type', sortable: true },
  { title: 'Max Quantity', key: 'max_quantity', sortable: true },
];

const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
];

onMounted(async () => {
  await Promise.all([
    loadBundles(),
    loadStatistics(),
  ]);
});

// Load bundles with pagination and filters
const loadBundles = async (options = {}) => {
  loading.value = true;
  try {
    const params = {
      page: options.page || 1,
      per_page: options.itemsPerPage || 15,
      search: searchQuery.value,
      is_active: statusFilter.value,
      diagnosis_icd10: diagnosisFilter.value,
    };

    const response = await api.get('/api/service-bundles', { params });
    bundles.value = response.data.data || response.data;
    totalItems.value = response.data.total || bundles.value.length;
  } catch (err) {
    showError('Failed to load service bundles');
  } finally {
    loading.value = false;
  }
};

// Load statistics
const loadStatistics = async () => {
  try {
    const response = await api.get('/api/service-bundles/statistics');
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

// Load bundle components
const loadBundleComponents = async (bundleId) => {
  loadingComponents.value = true;
  try {
    const response = await api.get(`/api/bundle-components`, {
      params: { service_bundle_id: bundleId }
    });
    bundleComponents.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load bundle components');
  } finally {
    loadingComponents.value = false;
  }
};

// Debounced search
const debouncedSearch = debounce(() => {
  loadBundles();
}, 500);

// Handle table update (pagination, sorting)
const handleTableUpdate = (options) => {
  loadBundles(options);
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  diagnosisFilter.value = '';
  loadBundles();
};

// Get item type color
const getItemTypeColor = (itemType) => {
  const colors = {
    LAB: 'blue',
    DRUG: 'green',
    CONSULTATION: 'purple',
    PROCEDURE: 'orange',
    IMAGING: 'teal',
    OTHER: 'grey',
  };
  return colors[itemType] || 'grey';
};

// View components
const viewComponents = async (bundle) => {
  selectedBundle.value = bundle;
  await loadBundleComponents(bundle.id);
  showComponentsDialog.value = true;
};

// Navigate to components management
const navigateToComponents = () => {
  router.push({
    name: 'management-bundle-components',
    query: { bundle_id: selectedBundle.value.id }
  });
};


// Edit bundle
const editBundle = (bundle) => {
  editMode.value = true;
  formData.value = { ...bundle };
  showCreateDialog.value = true;
};

// Save bundle (create or update)
const saveBundle = async () => {
  if (!bundleForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/api/service-bundles/${formData.value.id}`, formData.value);
      showSuccess('Bundle updated successfully');
    } else {
      await api.post('/api/service-bundles', formData.value);
      showSuccess('Bundle created successfully');
    }
    closeDialog();
    await Promise.all([loadBundles(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to save bundle';
    showError(message);
  } finally {
    saving.value = false;
  }
};

// Close dialog
const closeDialog = () => {
  showCreateDialog.value = false;
  editMode.value = false;
  formData.value = {
    code: '',
    name: '',
    description: '',
    diagnosis_icd10: '',
    fixed_price: 0,
    is_active: 1,
  };
};

// Confirm delete
const confirmDelete = (bundle) => {
  bundleToDelete.value = bundle;
  showDeleteDialog.value = true;
};

// Delete bundle
const deleteBundle = async () => {
  deleting.value = true;
  try {
    await api.delete(`/api/service-bundles/${bundleToDelete.value.id}`);
    showSuccess('Bundle deleted successfully');
    showDeleteDialog.value = false;
    bundleToDelete.value = null;
    await Promise.all([loadBundles(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to delete bundle';
    showError(message);
  } finally {
    deleting.value = false;
  }
};

// Export bundles
const exportBundles = async () => {
  try {
    const params = {
      is_active: statusFilter.value,
      diagnosis_icd10: diagnosisFilter.value,
    };

    const response = await api.get('/api/service-bundles-export', {
      params,
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `service_bundles_export_${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Service bundles exported successfully');
  } catch (err) {
    showError('Failed to export service bundles');
  }
};

// Download template
const downloadTemplate = async () => {
  try {
    const response = await api.get('/api/service-bundles/download-template', {
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'service_bundles_import_template.xlsx');
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Template downloaded successfully');
  } catch (err) {
    showError('Failed to download template');
  }
};

// Import bundles
const importBundles = async () => {
  if (!importFile.value) {
    showError('Please select a file to import');
    return;
  }

  importing.value = true;
  try {
    const formDataObj = new FormData();
    formDataObj.append('file', importFile.value);

    await api.post('/api/service-bundles/import', formDataObj, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    showSuccess('Service bundles imported successfully');
    showImportDialog.value = false;
    importFile.value = null;
    await Promise.all([loadBundles(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to import service bundles';
    showError(message);
  } finally {
    importing.value = false;
  }
};
</script>

<style scoped>
.bundle-services-management-page {
  padding: 20px 0;
}
</style>

