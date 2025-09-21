<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Service Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage healthcare services, pricing, and PA requirements</p>
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
            @click="exportServices"
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
            Add Service
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
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Services</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.total_services }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6 tw-animate-slide-up tw-animate-stagger-2 tw-hover-lift">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active Services</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ statistics.active_services }}</p>
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
          :items="services"
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

          <template v-slot:item.level_of_care="{ item }">
            <v-chip
              :color="getLevelOfCareColor(item.level_of_care)"
              size="small"
              variant="flat"
            >
              {{ item.level_of_care }}
            </v-chip>
          </template>

          <template v-slot:item.price="{ item }">
            ₦{{ Number(item.price).toLocaleString() }}
          </template>

          <template v-slot:item.pa_required="{ item }">
            <v-chip
              :color="item.pa_required ? 'orange' : 'green'"
              size="small"
              variant="flat"
            >
              {{ item.pa_required ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template v-slot:item.referable="{ item }">
            <v-chip
              :color="item.referable ? 'blue' : 'grey'"
              size="small"
              variant="flat"
            >
              {{ item.referable ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-2">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewService(item)"
              >
                <v-icon>mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editService(item)"
              >
                <v-icon>mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="red"
                @click="deleteService(item)"
              >
                <v-icon>mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Service Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="900px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">{{ editingService ? 'Edit Service' : 'Add New Service' }}</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="serviceForm" v-model="formValid">
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="serviceForm.nicare_code"
                label="NiCare Code"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-select
                v-model="serviceForm.level_of_care"
                :items="levelOfCareOptions"
                label="Level of Care"
                :rules="[rules.required]"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="serviceForm.price"
                label="Price"
                type="number"
                step="0.01"
                :rules="[rules.required, rules.positive]"
                variant="outlined"
                required
              />
              <v-select
                v-model="serviceForm.service_group_id"
                :items="serviceGroupOptions"
                item-title="name"
                item-value="id"
                label="Service Group"
                :rules="[rules.required]"
                variant="outlined"
                required
                clearable
              />
            </div>
            <v-textarea
              v-model="serviceForm.service_description"
              label="Service Description"
              :rules="[rules.required]"
              variant="outlined"
              rows="3"
              required
            />
            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-4 tw-mt-4">
              <v-switch
                v-model="serviceForm.status"
                label="Active"
                color="primary"
              />
              <v-switch
                v-model="serviceForm.pa_required"
                label="PA Required"
                color="orange"
              />
              <v-switch
                v-model="serviceForm.referable"
                label="Referable"
                color="blue"
              />
            </div>
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
            @click="saveService"
          >
            {{ editingService ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="showImportDialog" max-width="600px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">Import Services</span>
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
            @click="importServices"
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
import { serviceAPI } from '../../utils/api.js';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const saving = ref(false);
const importing = ref(false);
const searchQuery = ref('');
const selectedStatus = ref('');
const selectedLevelOfCare = ref('');
const selectedGroup = ref('');
const selectedPARequired = ref('');
const showCreateDialog = ref(false);
const showImportDialog = ref(false);
const editingService = ref(null);
const formValid = ref(false);
const importFile = ref([]);

// Data
const services = ref([]);
const groupOptions = ref([]);
const serviceGroupOptions = ref([]);
const statistics = ref({
  total_services: 0,
  active_services: 0,
  inactive_services: 0,
  pa_required_count: 0,
  referable_count: 0
});

const pagination = ref({
  page: 1,
  itemsPerPage: 15,
  totalItems: 0
});

// Form
const serviceForm = ref({
  nicare_code: '',
  service_description: '',
  level_of_care: '',
  price: '',
  group: '',
  service_group_id: null,
  pa_required: false,
  referable: true,
  status: true
});

// Options
const statusOptions = [
  { title: 'Active', value: true },
  { title: 'Inactive', value: false }
];

const levelOfCareOptions = [
  { title: 'Primary', value: 'Primary' },
  { title: 'Secondary', value: 'Secondary' },
  { title: 'Tertiary', value: 'Tertiary' }
];

const paRequiredOptions = [
  { title: 'Required', value: true },
  { title: 'Not Required', value: false }
];

// Table headers
const headers = [
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Service Description', key: 'service_description', sortable: true },
  { title: 'Level of Care', key: 'level_of_care', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: 'Group', key: 'group', sortable: true },
  { title: 'PA Required', key: 'pa_required', sortable: true },
  { title: 'Referable', key: 'referable', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '150px' }
];

// Validation rules
const rules = {
  required: value => !!value || 'This field is required',
  positive: value => value > 0 || 'Value must be positive'
};

// Methods
const getLevelOfCareColor = (level) => {
  switch (level) {
    case 'Primary': return 'green';
    case 'Secondary': return 'orange';
    case 'Tertiary': return 'red';
    default: return 'grey';
  }
};

const loadStatistics = async () => {
  try {
    const response = await serviceAPI.getStatistics();
    if (response.data.success) {
      statistics.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load statistics:', err);
    error('Failed to load statistics');
  }
};

const loadGroups = async () => {
  try {
    const response = await serviceAPI.getGroups();
    if (response.data.success) {
      serviceGroupOptions.value = response.data.data;
      groupOptions.value = response.data.data.map(group => ({
        title: group.name,
        value: group.name
      }));
    }
  } catch (err) {
    console.error('Failed to load groups:', err);
  }
};

const loadServices = async () => {
  try {
    loading.value = true;
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value,
      level_of_care: selectedLevelOfCare.value,
      group: selectedGroup.value,
      pa_required: selectedPARequired.value,
      page: pagination.value.page,
      per_page: pagination.value.itemsPerPage
    };

    const response = await serviceAPI.getAll(params);
    if (response.data.success) {
      const data = response.data.data;
      services.value = data.data || [];
      pagination.value.totalItems = data.total || 0;
      pagination.value.page = data.current_page || 1;
    }
  } catch (err) {
    console.error('Failed to load services:', err);
    error('Failed to load services');
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  serviceForm.value = {
    nicare_code: '',
    service_description: '',
    level_of_care: '',
    price: '',
    group: '',
    service_group_id: null,
    pa_required: false,
    referable: true,
    status: true
  };
  editingService.value = null;
};

const closeDialog = () => {
  showCreateDialog.value = false;
  resetForm();
};

const saveService = async () => {
  try {
    saving.value = true;

    if (editingService.value) {
      await serviceAPI.update(editingService.value.id, serviceForm.value);
      success('Service updated successfully');
    } else {
      await serviceAPI.create(serviceForm.value);
      success('Service created successfully');
    }

    closeDialog();
    loadServices();
    loadStatistics();
  } catch (err) {
    console.error('Failed to save service:', err);
    error('Failed to save service');
  } finally {
    saving.value = false;
  }
};

const viewService = (service) => {
  success(`Viewing service: ${service.service_description}`);
};

const editService = (service) => {
  editingService.value = service;
  serviceForm.value = { ...service };
  showCreateDialog.value = true;
};

const deleteService = async (service) => {
  if (confirm(`Are you sure you want to delete this service?`)) {
    try {
      await serviceAPI.delete(service.id);
      success('Service deleted successfully');
      loadServices();
      loadStatistics();
    } catch (err) {
      console.error('Failed to delete service:', err);
      error('Failed to delete service');
    }
  }
};

const downloadTemplate = async () => {
  try {
    const response = await serviceAPI.downloadTemplate();
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'services_import_template.xlsx';
    link.click();
    window.URL.revokeObjectURL(url);
    success('Template downloaded successfully');
  } catch (err) {
    console.error('Failed to download template:', err);
    error('Failed to download template');
  }
};

const importServices = async () => {
  try {
    importing.value = true;
    const formData = new FormData();
    formData.append('file', importFile.value[0]);

    const response = await serviceAPI.import(formData);
    if (response.data.success) {
      const { imported_count, errors } = response.data.data;

      if (errors.length > 0) {
        console.warn('Import errors:', errors);
        error(`Import completed with ${errors.length} errors. Check console for details.`);
      } else {
        success(`Successfully imported ${imported_count} services`);
      }

      showImportDialog.value = false;
      importFile.value = [];
      loadServices();
      loadStatistics();
    }
  } catch (err) {
    console.error('Failed to import services:', err);
    error('Failed to import services');
  } finally {
    importing.value = false;
  }
};

const exportServices = async () => {
  try {
    const params = {
      search: searchQuery.value,
      status: selectedStatus.value,
      level_of_care: selectedLevelOfCare.value,
      group: selectedGroup.value
    };

    const response = await serviceAPI.export(params);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.download = `services_export_${new Date().toISOString().split('T')[0]}.xlsx`;
    link.click();
    window.URL.revokeObjectURL(url);
    success('Services exported successfully');
  } catch (err) {
    console.error('Failed to export services:', err);
    error('Failed to export services');
  }
};

const handlePageChange = (page) => {
  pagination.value.page = page;
  loadServices();
};

const handleItemsPerPageChange = (itemsPerPage) => {
  pagination.value.itemsPerPage = itemsPerPage;
  pagination.value.page = 1;
  loadServices();
};

// Watchers
watch([searchQuery, selectedStatus, selectedLevelOfCare, selectedGroup, selectedPARequired], () => {
  pagination.value.page = 1;
  loadServices();
}, { debounce: 300 });

// Lifecycle
onMounted(() => {
  loadStatistics();
  loadGroups();
  loadServices();
});
</script>
