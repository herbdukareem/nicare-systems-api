<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Tariff Item Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage tariff items and pricing structure</p>
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
            @click="exportTariffItems"
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
            Add Tariff Item
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-1 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-format-list-bulleted</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Items</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.total_items }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Items</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.active_items }}</p>
            </div>
          </div>
        </div>

        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-3 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-pause-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Inactive Items</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.inactive_items }}</p>
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
        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-5 tw-gap-4">
          <v-text-field
            v-model="searchQuery"
            label="Search tariff items..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedCaseCategory"
            :items="caseCategories"
            item-title="name"
            item-value="id"
            label="Case Category"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedServiceType"
            :items="serviceTypes"
            item-title="name"
            item-value="id"
            label="Service Type"
            variant="outlined"
            density="compact"
            clearable
          />
          <v-select
            v-model="selectedCaseType"
            :items="caseTypes"
            item-title="name"
            item-value="id"
            label="Case Type"
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
        </div>
      </div>

      <!-- Data Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          :headers="headers"
          :items="tariffItems"
          :loading="loading"
          :items-per-page="pagination.itemsPerPage"
          :page="pagination.page"
          :server-items-length="pagination.totalItems"
          item-key="id"
          class="tw-elevation-0"
          @update:page="handlePageChange"
          @update:items-per-page="handleItemsPerPageChange"
        >
          <template #item.case_category="{ item }">
            {{ item.case_category?.name || 'N/A' }}
          </template>

          <template #item.service_type="{ item }">
            {{ item.service_type?.name || 'N/A' }}
          </template>

          <template #item.case_type="{ item }">
            <v-chip :color="item.case_type?.name === 'Surgical' ? 'red' : 'blue'" size="small" variant="flat">
              {{ item.case_type?.name || 'N/A' }}
            </v-chip>
          </template>

          <template #item.price="{ item }">
            ₦{{ Number(item.price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </template>

          <template #item.status="{ item }">
            <v-chip :color="item.status ? 'green' : 'red'" size="small" variant="flat">
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-space-x-2">
              <v-btn icon size="small" variant="text" @click="viewTariffItem(item)">
                <v-icon>mdi-eye</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" @click="editTariffItem(item)">
                <v-icon>mdi-pencil</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="red" @click="deleteTariffItem(item)">
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Tariff Item Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="800px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">{{ editingTariffItem ? 'Edit Tariff Item' : 'Add New Tariff Item' }}</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="formRef" v-model="formValid">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-select
                v-model="form.case_id"
                :items="caseCategories"
                item-title="name"
                item-value="id"
                label="Case Category"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-select
                v-model="form.service_type_id"
                :items="serviceTypes"
                item-title="name"
                item-value="id"
                label="Service Type"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-select
                v-model="form.case_type_id"
                :items="caseTypes"
                item-title="name"
                item-value="id"
                label="Case Type"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="form.price"
                label="Price"
                type="number"
                step="0.01"
                :rules="[rules.required, rules.positive]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="form.tariff_item"
                label="Tariff Item Description"
                :rules="[rules.required]"
                variant="outlined"
                required
                class="md:tw-col-span-2"
              />
            </div>
            <v-switch v-model="form.status" label="Active" color="primary" />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" :disabled="!formValid" @click="saveTariffItem">
            {{ editingTariffItem ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Import Tariff Items</span>
        </v-card-title>
        <v-card-text>
          <v-file-input
            :multiple="false"
            v-model="importFile"
            label="Select Excel file"
            accept=".xlsx,.xls,.csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv"
            prepend-icon="mdi-file-excel"
            variant="outlined"
            :rules="[rules.required]"
            clearable
            @update:modelValue="onFileSelected"
          />
          <v-alert v-if="importErrors.length > 0" type="warning" class="tw-mt-4">
            <div class="tw-font-semibold">Import completed with errors:</div>
            <ul class="tw-mt-2 tw-list-disc tw-list-inside">
              <li v-for="(error, index) in importErrors.slice(0, 10)" :key="index" class="tw-text-sm">
                {{ error }}
              </li>
            </ul>
            <div v-if="importErrors.length > 10" class="tw-text-sm tw-mt-2">
              ... and {{ importErrors.length - 10 }} more errors
            </div>
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeImportDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="importing" :disabled="!importFile" @click="handleImport">
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../layout/AdminLayout.vue';
import { tariffItemAPI, caseCategoryAPI, serviceTypeAPI, caseTypeAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const { success: showSuccess, error: showError } = useToast();

// Data
const tariffItems = ref([]);
const caseCategories = ref([]);
const serviceTypes = ref([]);
const caseTypes = ref([]);
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);
const showCreateDialog = ref(false);
const showImportDialog = ref(false);
const editingTariffItem = ref(null);
const formValid = ref(false);
const formRef = ref(null);
const importFile = ref(null);
const importErrors = ref([]);

// Search and filters
const searchQuery = ref('');
const selectedCaseCategory = ref(null);
const selectedServiceType = ref(null);
const selectedCaseType = ref(null);
const selectedStatus = ref(null);

// Pagination
const pagination = ref({
  page: 1,
  itemsPerPage: 15,
  totalItems: 0
});

// Statistics
const statistics = ref({
  total_items: 0,
  active_items: 0,
  inactive_items: 0,
  recent_additions: 0
});

// Form
const form = ref({
  case_id: null,
  service_type_id: null,
  tariff_item: '',
  price: 0,
  case_type_id: null,
  status: true
});

// Table headers
const headers = [
  { title: 'Case Category', key: 'case_category', sortable: false },
  { title: 'Service Type', key: 'service_type', sortable: false },
  { title: 'Tariff Item', key: 'tariff_item', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: 'Case Type', key: 'case_type', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
];

// Options
const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
];

// Validation rules
const rules = {
  required: value => !!value || 'This field is required',
  positive: value => value >= 0 || 'Value must be positive'
};

// Methods
const onFileSelected = (val) => {
  // v-file-input can emit File or File[]
  importFile.value = Array.isArray(val) ? (val[0] ?? null) : val
}

const validateImportFile = () => {
  if (!importFile.value) return 'Please select a file to import'
  const file = importFile.value
  if (!(file instanceof File)) return 'Invalid file object'

  const allowedMime = new Set([
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/csv',
    'application/vnd.ms-excel.sheet.macroEnabled.12',
  ])
  const allowedExt = /\.(xlsx|xls|csv)$/i
  if (!allowedMime.has(file.type) && !allowedExt.test(file.name)) {
    return 'Please select an Excel (.xlsx, .xls) or CSV file'
  }
  const maxSize = 10 * 1024 * 1024 // 10MB
  if (file.size > maxSize) return 'File size must be less than 10MB'
  return null
}

const fetchTariffItems = async () => {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage,
      search: searchQuery.value,
      case_category_id: selectedCaseCategory.value,
      service_type_id: selectedServiceType.value,
      case_type_id: selectedCaseType.value,
      status: selectedStatus.value
    };

    const response = await tariffItemAPI.getAll(params);

    if (response.data.success) {
      tariffItems.value = response.data.data.data;
      pagination.value.totalItems = response.data.data.total;
    }
  } catch (error) {
    showError('Failed to fetch tariff items');
    console.error('Error fetching tariff items:', error);
  } finally {
    loading.value = false;
  }
};

const fetchCaseCategories = async () => {
  try {
    const response = await caseCategoryAPI.getAll({ per_page: 1000 });
    if (response.data.success) {
      caseCategories.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error('Error fetching case categories:', error);
  }
};

const fetchServiceTypes = async () => {
  try {
    const response = await serviceTypeAPI.getAll({ per_page: 1000 });
    if (response.data.success) {
      serviceTypes.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error('Error fetching service types:', error);
  }
};

const fetchCaseTypes = async () => {
  try {
    const response = await caseTypeAPI.getAll({ per_page: 1000 });
    if (response.data.success) {
      caseTypes.value = response.data.data.data || response.data.data;
    }
  } catch (error) {
    console.error('Error fetching case types:', error);
  }
};

const fetchStatistics = async () => {
  try {
    const response = await tariffItemAPI.getStatistics();
    if (response.data.success) {
      statistics.value = response.data.data;
    }
  } catch (error) {
    console.error('Error fetching statistics:', error);
  }
};

const saveTariffItem = async () => {
  saving.value = true;
  try {
    let response;
    if (editingTariffItem.value) {
      response = await tariffItemAPI.update(editingTariffItem.value.id, form.value);
    } else {
      response = await tariffItemAPI.create(form.value);
    }

    if (response.data.success) {
      showSuccess(editingTariffItem.value ? 'Tariff item updated successfully' : 'Tariff item created successfully');
      closeDialog();
      fetchTariffItems();
      fetchStatistics();
    }
  } catch (error) {
    showError(error.response?.data?.message || 'Failed to save tariff item');
    console.error('Error saving tariff item:', error);
  } finally {
    saving.value = false;
  }
};

const editTariffItem = (item) => {
  editingTariffItem.value = item;
  form.value = {
    case_id: item.case_id,
    service_type_id: item.service_type_id,
    tariff_item: item.tariff_item,
    price: item.price,
    case_type_id: item.case_type_id,
    status: item.status
  };
  showCreateDialog.value = true;
};

const viewTariffItem = (item) => {
  // You can implement a view dialog or navigate to a detail page
  console.log('View tariff item:', item);
};

const deleteTariffItem = async (item) => {
  if (!confirm(`Are you sure you want to delete this tariff item?`)) {
    return;
  }

  try {
    const response = await tariffItemAPI.delete(item.id);
    if (response.data.success) {
      showSuccess('Tariff item deleted successfully');
      fetchTariffItems();
      fetchStatistics();
    }
  } catch (error) {
    showError('Failed to delete tariff item');
    console.error('Error deleting tariff item:', error);
  }
};

const closeDialog = () => {
  showCreateDialog.value = false;
  editingTariffItem.value = null;
  form.value = {
    case_id: null,
    service_type_id: null,
    tariff_item: '',
    price: 0,
    case_type_id: null,
    status: true
  };
};

const handleImport = async () => {
  const v = validateImportFile()
  if (v) return showError(v)

  importing.value = true
  importErrors.value = []

  try {
    const fd = new FormData()
    // include filename so backend frameworks infer extension
    fd.append('file', importFile.value, importFile.value.name)

    // optional metadata
    fd.append('import_type', 'tariff_items')
    fd.append('timestamp', new Date().toISOString())

    const response = await tariffItemAPI.import(fd)

    if (response?.data?.success) {
      const { imported_count = 0, errors = [] } = response.data.data || {}
      if (errors.length) {
        importErrors.value = errors
        showError(`Imported ${imported_count} items with ${errors.length} errors`)
      } else {
        showSuccess(`Successfully imported ${imported_count} tariff items`)
        closeImportDialog()
      }
      fetchTariffItems()
      fetchStatistics()
    } else {
      showError(response?.data?.message || 'Import failed')
    }
  } catch (err) {
    console.error('Error importing tariff items:', err)
    if (err.response?.data?.message) showError(err.response.data.message)
    else if (err.response?.data?.errors) showError(Object.values(err.response.data.errors).flat().join(', '))
    else if (err.code === 'NETWORK_ERROR') showError('Network error. Please check your connection.')
    else if (err.request) showError('No response from server. Please try again.')
    else showError('Failed to import tariff items. Please try again.')
  } finally {
    importing.value = false
  }
}


const closeImportDialog = () => {
  showImportDialog.value = false;
  importFile.value = null;
  importErrors.value = [];
};

const downloadTemplate = async () => {
  try {
    const response = await tariffItemAPI.downloadTemplate();
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `tariff_items_template_${new Date().getTime()}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Template downloaded successfully');
  } catch (error) {
    showError('Failed to download template');
    console.error('Error downloading template:', error);
  }
};

const exportTariffItems = async () => {
  try {
    const params = {
      search: searchQuery.value,
      case_category_id: selectedCaseCategory.value,
      service_type_id: selectedServiceType.value,
      case_type_id: selectedCaseType.value,
      status: selectedStatus.value
    };

    const response = await tariffItemAPI.export(params);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `tariff_items_export_${new Date().getTime()}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Tariff items exported successfully');
  } catch (error) {
    showError('Failed to export tariff items');
    console.error('Error exporting tariff items:', error);
  }
};

const handlePageChange = (page) => {
  pagination.value.page = page;
  fetchTariffItems();
};

const handleItemsPerPageChange = (itemsPerPage) => {
  pagination.value.itemsPerPage = itemsPerPage;
  pagination.value.page = 1;
  fetchTariffItems();
};

// Watchers
watch([searchQuery, selectedCaseCategory, selectedServiceType, selectedCaseType, selectedStatus], () => {
  pagination.value.page = 1;
  fetchTariffItems();
});

// Lifecycle
onMounted(() => {
  fetchTariffItems();
  fetchCaseCategories();
  fetchServiceTypes();
  fetchCaseTypes();
  fetchStatistics();
});
</script>

<style scoped>
.tw-hover-lift {
  transition: transform 0.2s ease-in-out;
}

.tw-hover-lift:hover {
  transform: translateY(-2px);
}

.tw-animate-fade-in-up {
  animation: fadeInUp 0.5s ease-out;
}

.tw-animate-slide-up {
  animation: slideUp 0.5s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.tw-animate-stagger-1 {
  animation-delay: 0.1s;
}

.tw-animate-stagger-2 {
  animation-delay: 0.2s;
}

.tw-animate-stagger-3 {
  animation-delay: 0.3s;
}

.tw-animate-stagger-4 {
  animation-delay: 0.4s;
}
</style>


