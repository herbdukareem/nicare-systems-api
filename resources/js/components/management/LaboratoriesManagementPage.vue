<template>
  <AdminLayout>
    <div class="laboratories-management-page">
      <v-container fluid>
      <!-- Page Header -->
      <v-row>
        <v-col cols="12">
          <h1 class="text-h4 mb-2">Laboratories Management</h1>
          <p class="text-subtitle-1 text-grey">Manage laboratory test tariff items and pricing</p>
        </v-col>
      </v-row>

      <!-- Statistics Cards -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card color="primary" dark>
            <v-card-text>
              <div class="text-h6">Total Labs</div>
              <div class="text-h4">{{ statistics.total || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="success" dark>
            <v-card-text>
              <div class="text-h6">Active Labs</div>
              <div class="text-h4">{{ statistics.active || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="warning" dark>
            <v-card-text>
              <div class="text-h6">Inactive Labs</div>
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
                    label="Search labs..."
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
                    @update:model-value="loadLabs"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="levelOfCareFilter"
                    label="Level of Care"
                    :items="levelsOfCare"
                    clearable
                    outlined
                    dense
                    @update:model-value="loadLabs"
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
                    Add Lab Test
                  </v-btn>
                  <v-btn color="success" @click="showImportDialog = true">
                    <v-icon left>mdi-upload</v-icon>
                    Import
                  </v-btn>
                  <v-btn color="info" @click="exportLabs">
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
                :items="labs"
                :loading="loading"
                :items-per-page="15"
                :server-items-length="totalItems"
                @update:options="handleTableUpdate"
                class="elevation-0"
              >
                <template v-slot:item.nicare_code="{ item }">
                  <v-chip color="primary" variant="outlined" size="small">
                    {{ item.nicare_code }}
                  </v-chip>
                </template>

                <template v-slot:item.service_description="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.service_description }}</div>
                    <div class="text-caption text-grey">{{ item.level_of_care }}</div>
                  </div>
                </template>

                <template v-slot:item.price="{ item }">
                  <span class="font-weight-medium">₦{{ Number(item.price).toLocaleString() }}</span>
                </template>

                <template v-slot:item.pa_required="{ item }">
                  <v-chip
                    :color="item.pa_required ? 'orange' : 'grey'"
                    text-color="white"
                    size="small"
                  >
                    {{ item.pa_required ? 'Required' : 'Not Required' }}
                  </v-chip>
                </template>

                <template v-slot:item.status="{ item }">
                  <v-chip :color="item.status ? 'success' : 'grey'" size="small">
                    {{ item.status ? 'Active' : 'Inactive' }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="editLab(item)"
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
          <span>{{ editMode ? 'Edit Lab Test' : 'Add New Lab Test' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="labForm">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.nicare_code"
                  label="NiCare Code *"
                  :rules="[v => !!v || 'NiCare code is required']"
                  outlined
                  dense
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.level_of_care"
                  label="Level of Care *"
                  :items="levelsOfCare"
                  :rules="[v => !!v || 'Level of care is required']"
                  outlined
                  dense
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-textarea
                  v-model="formData.service_description"
                  label="Lab Test Description *"
                  :rules="[v => !!v || 'Description is required']"
                  outlined
                  dense
                  rows="3"
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model.number="formData.price"
                  label="Price (₦) *"
                  type="number"
                  :rules="[v => !!v || 'Price is required', v => v > 0 || 'Price must be greater than 0']"
                  outlined
                  dense
                  prefix="₦"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="formData.pa_required"
                  label="PA Required *"
                  :items="[{ title: 'Yes', value: true }, { title: 'No', value: false }]"
                  :rules="[v => v !== null || 'PA required is required']"
                  outlined
                  dense
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="formData.status"
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
          <v-btn color="primary" @click="saveLab" :loading="saving">
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>Import Labs from Excel</span>
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
              <strong>Template columns:</strong> nicare_code, service_description, level_of_care, price, pa_required, status
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
          <v-btn color="primary" @click="importLabs" :loading="importing">
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
          <p>Are you sure you want to delete this lab test?</p>
          <p class="font-weight-bold">{{ labToDelete?.service_description }}</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteLab" :loading="deleting">
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
const importing = ref(false);
const labs = ref([]);
const statistics = ref({});
const totalItems = ref(0);
const searchQuery = ref('');
const statusFilter = ref(null);
const levelOfCareFilter = ref(null);

const showCreateDialog = ref(false);
const showImportDialog = ref(false);
const showDeleteDialog = ref(false);
const editMode = ref(false);
const labForm = ref(null);
const labToDelete = ref(null);
const importFile = ref(null);

const formData = ref({
  nicare_code: '',
  service_description: '',
  level_of_care: '',
  price: 0,
  group: 'LABS',
  pa_required: false,
  status: 1,
});

const headers = [
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Lab Test Description', key: 'service_description', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: 'PA Required', key: 'pa_required', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
];

const levelsOfCare = ['Primary', 'Secondary', 'Tertiary'];

onMounted(async () => {
  await Promise.all([
    loadLabs(),
    loadStatistics(),
  ]);
});

// Load labs with pagination and filters
const loadLabs = async (options = {}) => {
  loading.value = true;
  try {
    const params = {
      page: options.page || 1,
      per_page: options.itemsPerPage || 15,
      search: searchQuery.value,
      status: statusFilter.value,
      level_of_care: levelOfCareFilter.value,
      group: 'LABS', // Filter by LABS group
    };

    const response = await api.get('/cases', { params });
    labs.value = response.data.data || response.data;
    totalItems.value = response.data.total || labs.value.length;
  } catch (err) {
    showError('Failed to load laboratory tests');
  } finally {
    loading.value = false;
  }
};

// Load statistics
const loadStatistics = async () => {
  try {
    const response = await api.get('/cases/statistics', {
      params: { group: 'LABS' }
    });
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

// Debounced search
const debouncedSearch = debounce(() => {
  loadLabs();
}, 500);

// Handle table update (pagination, sorting)
const handleTableUpdate = (options) => {
  loadLabs(options);
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  levelOfCareFilter.value = null;
  loadLabs();
};

// Edit lab
const editLab = (lab) => {
  editMode.value = true;
  formData.value = { ...lab };
  showCreateDialog.value = true;
};

// Save lab (create or update)
const saveLab = async () => {
  if (!labForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  try {
    // Ensure group is set to LABS
    formData.value.group = 'LABS';

    if (editMode.value) {
      await api.put(`/cases/${formData.value.id}`, formData.value);
      showSuccess('Lab test updated successfully');
    } else {
      await api.post('/cases', formData.value);
      showSuccess('Lab test created successfully');
    }
    closeDialog();
    await Promise.all([loadLabs(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to save lab test';
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
    nicare_code: '',
    service_description: '',
    level_of_care: '',
    price: 0,
    group: 'LABS',
    pa_required: false,
    status: 1,
  };
};


// Confirm delete
const confirmDelete = (lab) => {
  labToDelete.value = lab;
  showDeleteDialog.value = true;
};

// Delete lab
const deleteLab = async () => {
  deleting.value = true;
  try {
    await api.delete(`/api/cases/${labToDelete.value.id}`);
    showSuccess('Lab test deleted successfully');
    showDeleteDialog.value = false;
    labToDelete.value = null;
    await Promise.all([loadLabs(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to delete lab test';
    showError(message);
  } finally {
    deleting.value = false;
  }
};

// Export labs
const exportLabs = async () => {
  try {
    const response = await api.get('/api/cases-export', {
      params: { group: 'LABS' },
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `labs_export_${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Lab tests exported successfully');
  } catch (err) {
    showError('Failed to export lab tests');
  }
};

// Download template
const downloadTemplate = async () => {
  try {
    const response = await api.get('/api/cases/download-template', {
      params: { group: 'LABS' },
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'labs_import_template.xlsx');
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Template downloaded successfully');
  } catch (err) {
    showError('Failed to download template');
  }
};

// Import labs
const importLabs = async () => {
  if (!importFile.value) {
    showError('Please select a file to import');
    return;
  }

  importing.value = true;
  try {
    const formDataObj = new FormData();
    formDataObj.append('file', importFile.value);
    formDataObj.append('group', 'LABS');

    await api.post('/api/cases/import', formDataObj, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    showSuccess('Lab tests imported successfully');
    showImportDialog.value = false;
    importFile.value = null;
    await Promise.all([loadLabs(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to import lab tests';
    showError(message);
  } finally {
    importing.value = false;
  }
};
</script>

<style scoped>
.laboratories-management-page {
  padding: 20px 0;
}
</style>

