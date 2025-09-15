<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Drug Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage drug formulary and pricing</p>
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
            @click="exportDrugs"
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
            Add Drug
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-pill</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Drugs</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.total_drugs }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Drugs</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.active_drugs }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-3 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-pause-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Inactive Drugs</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.inactive_drugs }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-4 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-clock-plus</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Recent Additions</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.recent_additions }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters and Search -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-4">
          <v-text-field
            v-model="searchQuery"
            label="Search drugs..."
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
            v-model="sortBy"
            :items="sortOptions"
            label="Sort by"
            variant="outlined"
            density="compact"
          />
          <v-select
            v-model="sortDirection"
            :items="sortDirectionOptions"
            label="Order"
            variant="outlined"
            density="compact"
          />
        </div>
      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          :headers="headers"
          :items="drugs"
          :loading="loading"
          :items-per-page="pagination.itemsPerPage"
          :page="pagination.page"
          :server-items-length="pagination.totalItems"
          item-key="id"
          class="tw-elevation-0"
          @update:page="handlePageChange"
          @update:items-per-page="handleItemsPerPageChange"
        >
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="item.status ? 'green' : 'red'"
              size="small"
              variant="flat"
            >
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template v-slot:item.drug_unit_price="{ item }">
            ₦{{ Number(item.drug_unit_price).toLocaleString() }}
          </template>

          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-2">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewDrug(item)"
              >
                <v-icon>mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editDrug(item)"
              >
                <v-icon>mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="red"
                @click="deleteDrug(item)"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Drug Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">{{ editingDrug ? 'Edit Drug' : 'Add New Drug' }}</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="drugForm" v-model="formValid">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="drugForm.nicare_code"
                label="NiCare Code"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="drugForm.drug_name"
                label="Drug Name"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="drugForm.drug_dosage_form"
                label="Dosage Form"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="drugForm.drug_strength"
                label="Strength"
                variant="outlined"
              />
              <v-text-field
                v-model="drugForm.drug_presentation"
                label="Presentation"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="drugForm.drug_unit_price"
                label="Unit Price"
                type="number"
                step="0.01"
                :rules="[rules.required, rules.positive]"
                variant="outlined"
                required
              />
            </div>
            <v-switch
              v-model="drugForm.status"
              label="Active"
              color="primary"
            />
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
            @click="saveDrug"
          >
            {{ editingDrug ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Import Drugs</span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <v-file-input
              v-model="importFile"
              label="Select Excel File"
              accept=".xlsx,.xls,.csv"
              variant="outlined"
              prepend-icon="mdi-file-upload"
              show-size
            />
            <v-alert
              type="info"
              variant="tonal"
            >
              Please use the template format. Download the template first if you haven't already.
            </v-alert>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn
            variant="text"
            @click="showImportDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            :loading="importing"
            :disabled="!importFile || importFile.length === 0"
            @click="importDrugs"
          >
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { drugAPI } from '../../utils/api.js';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);
const searchQuery = ref('');
const selectedStatus = ref('');
const sortBy = ref('created_at');
const sortDirection = ref('desc');
const showCreateDialog = ref(false);
const showImportDialog = ref(false);
const editingDrug = ref(null);
const formValid = ref(false);
const importFile = ref([]);

// Data
const drugs = ref([]);
const statistics = ref({
  total_drugs: 0,
  active_drugs: 0,
  inactive_drugs: 0,
  recent_additions: 0
});

const pagination = ref({
  page: 1,
  itemsPerPage: 15,
  totalItems: 0
});

// Form
const drugForm = ref({
  nicare_code: '',
  drug_name: '',
  drug_dosage_form: '',
  drug_strength: '',
  drug_presentation: '',
  drug_unit_price: '',
  status: true
});

// Options
const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
];

const sortOptions = [
  { title: 'Name', value: 'drug_name' },
  { title: 'Code', value: 'nicare_code' },
  { title: 'Price', value: 'drug_unit_price' },
  { title: 'Created Date', value: 'created_at' }
];

const sortDirectionOptions = [
  { title: 'Ascending', value: 'asc' },
  { title: 'Descending', value: 'desc' }
];

// Table headers
const headers = [
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Drug Name', key: 'drug_name', sortable: true },
  { title: 'Dosage Form', key: 'drug_dosage_form', sortable: true },
  { title: 'Strength', key: 'drug_strength', sortable: false },
  { title: 'Presentation', key: 'drug_presentation', sortable: false },
  { title: 'Unit Price', key: 'drug_unit_price', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' }
];

// Validation rules
const rules = {
  required: value => !!value || 'This field is required',
  positive: value => value > 0 || 'Value must be positive'
};

// Methods
const loadStatistics = async () => {
  try {
    const response = await drugAPI.getStatistics();
    if (response.data.success) {
      statistics.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load statistics:', err);
    error('Failed to load statistics');
  }
};

const loadDrugs = async () => {
  try {
    loading.value = true;
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value,
      sort_by: sortBy.value,
      sort_direction: sortDirection.value,
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage
    };

    const response = await drugAPI.getAll(params);
    if (response.data.success) {
      const data = response.data.data;
      drugs.value = data.data || [];
      pagination.value.totalItems = data.total || 0;
      pagination.value.page = data.current_page || 1;
    }
  } catch (err) {
    console.error('Failed to load drugs:', err);
    error('Failed to load drugs');
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  drugForm.value = {
    nicare_code: '',
    drug_name: '',
    drug_dosage_form: '',
    drug_strength: '',
    drug_presentation: '',
    drug_unit_price: '',
    status: true
  };
  editingDrug.value = null;
};

const closeDialog = () => {
  showCreateDialog.value = false;
  resetForm();
};

const saveDrug = async () => {
  try {
    saving.value = true;

    if (editingDrug.value) {
      await drugAPI.update(editingDrug.value.id, drugForm.value);
      success('Drug updated successfully');
    } else {
      await drugAPI.create(drugForm.value);
      success('Drug created successfully');
    }

    closeDialog();
    loadDrugs();
    loadStatistics();
  } catch (err) {
    console.error('Failed to save drug:', err);
    error('Failed to save drug');
  } finally {
    saving.value = false;
  }
};

const viewDrug = (drug) => {
  // Implement view functionality
  success(`Viewing drug: ${drug.drug_name}`);
};

const editDrug = (drug) => {
  editingDrug.value = drug;
  drugForm.value = { ...drug };
  showCreateDialog.value = true;
};

const deleteDrug = async (drug) => {
  if (confirm(`Are you sure you want to delete ${drug.drug_name}?`)) {
    try {
      await drugAPI.delete(drug.id);
      success('Drug deleted successfully');
      loadDrugs();
      loadStatistics();
    } catch (err) {
      console.error('Failed to delete drug:', err);
      error('Failed to delete drug');
    }
  }
};

const downloadTemplate = async () => {
  try {
    const response = await drugAPI.downloadTemplate();
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'drugs_import_template.xlsx';
    link.click();
    window.URL.revokeObjectURL(url);
    success('Template downloaded successfully');
  } catch (err) {
    console.error('Failed to download template:', err);
    error('Failed to download template');
  }
};

const importDrugs = async () => {
  try {
    importing.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value[0]);

    const response = await drugAPI.import(formData);
    if (response.data.success) {
      const { imported_count, errors } = response.data.data;

      if (errors.length > 0) {
        console.warn('Import errors:', errors);
        error(`Import completed with ${errors.length} errors. Check console for details.`);
      } else {
        success(`Successfully imported ${imported_count} drugs`);
      }

      showImportDialog.value = false;
      importFile.value = [];
      loadDrugs();
      loadStatistics();
    }
  } catch (err) {
    console.error('Failed to import drugs:', err);
    error('Failed to import drugs');
  } finally {
    importing.value = false;
  }
};

const exportDrugs = async () => {
  try {
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value
    };

    const response = await drugAPI.export(params);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = `drugs_export_${new Date().toISOString().split('T')[0]}.xlsx`;
    link.click();
    window.URL.revokeObjectURL(url);
    success('Drugs exported successfully');
  } catch (err) {
    console.error('Failed to export drugs:', err);
    error('Failed to export drugs');
  }
};

const handlePageChange = (page) => {
  pagination.value.page = page;
  loadDrugs();
};

const handleItemsPerPageChange = (itemsPerPage) => {
  pagination.value.itemsPerPage = itemsPerPage;
  pagination.value.page = 1;
  loadDrugs();
};

// Watchers
watch([searchQuery, selectedStatus, sortBy, sortDirection], () => {
  pagination.value.page = 1;
  loadDrugs();
}, { debounce: 300 });

// Lifecycle
onMounted(() => {
  loadStatistics();
  loadDrugs();
});
</script>
