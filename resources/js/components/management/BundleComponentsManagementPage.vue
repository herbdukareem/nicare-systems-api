<template>
  <AdminLayout>
    <div class="bundle-components-management-page">
      <v-container fluid>
      <!-- Page Header -->
      <v-row>
        <v-col cols="12">
          <h1 class="text-h4 mb-2">Bundle Components Management</h1>
          <p class="text-subtitle-1 text-grey">Manage components within service bundles</p>
        </v-col>
      </v-row>

      <!-- Statistics Cards -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card color="primary" dark>
            <v-card-text>
              <div class="text-h6">Total Components</div>
              <div class="text-h4">{{ statistics.total || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="success" dark>
            <v-card-text>
              <div class="text-h6">Active Bundles</div>
              <div class="text-h4">{{ statistics.active_bundles || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="info" dark>
            <v-card-text>
              <div class="text-h6">Lab Items</div>
              <div class="text-h4">{{ statistics.lab_items || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card color="warning" dark>
            <v-card-text>
              <div class="text-h6">Drug Items</div>
              <div class="text-h4">{{ statistics.drug_items || 0 }}</div>
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
                  <v-autocomplete
                    v-model="bundleFilter"
                    label="Filter by Bundle"
                    :items="bundles"
                    item-title="service_description"
                    item-value="id"
                    clearable
                    variant="outlined"
                    dense
                    @update:model-value="loadComponents"
                  >
                    <template v-slot:item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template v-slot:title>{{ item.raw.service_description }}</template>
                        <template v-slot:subtitle>
                          {{ item.raw.case_name }}
                        </template>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="itemTypeFilter"
                    label="Item Type"
                    :items="itemTypes"
                    clearable
                    variant="outlined"
                    dense
                    @update:model-value="loadComponents"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search..."
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    variant="outlined"
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
                  <v-btn color="primary" @click="openCreateDialog">
                    <v-icon left>mdi-plus</v-icon>
                    Add Component
                  </v-btn>
                  <v-btn color="success" @click="showBulkAddDialog = true">
                    <v-icon left>mdi-plus-box-multiple</v-icon>
                    Bulk Add
                  </v-btn>
                  <v-btn color="secondary" @click="openImportDialog">
                    <v-icon left>mdi-upload</v-icon>
                    Import
                  </v-btn>
                  <v-btn color="info" @click="exportComponents">
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
                :items="components"
                :loading="loading"
                :items-per-page="15"
                :server-items-length="totalItems"
                @update:options="handleTableUpdate"
                class="elevation-0"
              >
                <template v-slot:item.service_bundle="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.service_bundle?.case_name || item.service_bundle?.service_description }}</div>
                    <div class="text-caption text-grey">{{ item.service_bundle?.nicare_code }}</div>
                  </div>
                </template>

                <template v-slot:item.case_record="{ item }">
                  <div>
                    <div class="font-weight-medium">{{ item.case_record?.service_description }}</div>
                    <div class="text-caption text-grey">{{ item.case_record?.nicare_code }}</div>
                  </div>
                </template>



                <template v-slot:item.max_quantity="{ item }">
                  <v-chip color="info" variant="outlined" size="small">
                    {{ item.max_quantity }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    variant="text"
                    size="small"
                    @click="editComponent(item)"
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
    <v-dialog v-model="showCreateDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>{{ editMode ? 'Edit Component' : 'Add Component to Bundle' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="componentForm">
            <v-row>

              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.service_bundle_id"
                  label="Service Bundle *"
                  :items="bundles"
                  item-title="service_description"
                  item-value="id"
                  :rules="[v => !!v || 'Service bundle is required']"
                  variant="outlined"
                  density="comfortable"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>{{ item.raw.case_name }}</template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="12">

                <v-select
                  v-model="caseTypeFilter"
                  label="Filter by Case Type"
                  :items="caseTypes"
                  item-title="text"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  @update:model-value="loadCaseRecords"
                  clearable
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.case_record_id"
                  label="Case Record / Service *"
                  :items="availableCaseRecords"
                  item-title="service_description"
                  item-value="id"
                  :loading="loadingCaseRecords"
                  :rules="[v => !!v || 'Case record is required']"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  :hint="formData.service_bundle_id ? `${availableCaseRecords.length} available (${addedCaseRecordIds.length} already added)` : 'Select a bundle first'"
                  persistent-hint
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>{{ item.raw.service_description }}</template>
                      <template v-slot:subtitle>
                        {{ item.raw.nicare_code }} | {{ item.raw.case_name }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-text-field
                  v-model.number="formData.max_quantity"
                  label="Maximum Quantity *"
                  type="number"
                  :rules="[v => !!v || 'Max quantity is required', v => v > 0 || 'Must be greater than 0']"
                  variant="outlined"
                  density="comfortable"
                  hint="Maximum quantity covered by the bundle's fixed price"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" variant="elevated" @click="saveComponent" :loading="saving">
            {{ editMode ? 'Update Component' : 'Add Component' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Add Dialog -->
    <v-dialog v-model="showBulkAddDialog" max-width="900px" persistent>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>Bulk Add Components</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="bulkForm">
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="bulkFormData.service_bundle_id"
                  label="Service Bundle *"
                  :items="bundles"
                  item-title="service_description"
                  item-value="id"
                  :rules="[v => !!v || 'Service bundle is required']"
                  variant="outlined"
                  dense
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>{{ item.raw.service_description }}</template>
                      <template v-slot:subtitle>
                        {{ item.raw.case_name }} | ₦{{ Number(item.raw.bundle_price || item.raw.price || 0).toLocaleString() }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-select
                  v-model="bulkFormData.item_type"
                  label="Item Type *"
                  :items="itemTypes"
                  :rules="[v => !!v || 'Item type is required']"
                     variant="outlined"
                  dense
                  @update:model-value="onBulkItemTypeChange"
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="bulkFormData.selected_items"
                  label="Select Multiple Items *"
                  :items="caseRecords"
                  item-title="service_description"
                  item-value="id"
                  :rules="[v => v && v.length > 0 || 'At least one item is required']"
                  :loading="loadingCaseRecords"
                  multiple
                  chips
                  variant="outlined"
                  dense
                >
                  <template v-slot:chip="{ props, item }">
                    <v-chip v-bind="props" closable>
                      {{ item.title }}
                    </v-chip>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-text-field
                  v-model.number="bulkFormData.default_max_quantity"
                  label="Default Max Quantity for All *"
                  type="number"
                  :rules="[v => !!v || 'Default max quantity is required', v => v > 0 || 'Must be greater than 0']"
                     variant="outlined"
                  dense
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="closeBulkDialog">Cancel</v-btn>
          <v-btn color="primary" @click="bulkAddComponents" :loading="saving">
            Add All
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
          <p>Are you sure you want to delete this component?</p>
          <p class="font-weight-bold">{{ componentToDelete?.case_record?.service_description }}</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteComponent" :loading="deleting">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="importDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="bg-grey-lighten-4">
          <span class="text-h6">Import Bundle Components</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert variant="outlined" type="info" class="mb-4">
            <div class="text-body-2">
              <strong>Import Instructions:</strong>
              <ul class="mt-2">
                <li>Download the template file first</li>
                <li>Fill in the required fields:
                  <ul>
                    <li><strong>Bundle NiCare Code</strong> - The NiCare code of the bundle</li>
                    <li><strong>Component NiCare Code</strong> - The NiCare code of the component (drug, lab, etc.)</li>
                    <li><strong>Max Quantity</strong> - Maximum quantity covered by the bundle</li>
                    <li><strong>Item Type</strong> - DRUG, LABORATORY, RADIOLOGY, etc.</li>
                  </ul>
                </li>
                <li>Upload the completed file</li>
                <li>Supported formats: .xlsx, .xls, .csv</li>
              </ul>
            </div>
          </v-alert>

          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-download"
            block
            class="mb-4"
            @click="downloadTemplate"
            :loading="downloadingTemplate"
          >
            Download Template
          </v-btn>

          <v-file-input
            v-model="importFile"
            label="Select File"
            accept=".xlsx,.xls,.csv"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-file-excel"
            show-size
            clearable
          ></v-file-input>

          <v-alert v-if="importErrors.length > 0" type="error" class="mt-4">
            <div class="text-body-2">
              <strong>Import Errors:</strong>
              <ul class="mt-2">
                <li v-for="(error, index) in importErrors" :key="index">{{ error }}</li>
              </ul>
            </div>
          </v-alert>

          <v-alert v-if="importSuccess" type="success" class="mt-4">
            {{ importSuccessMessage }}
          </v-alert>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer></v-spacer>
          <v-btn text @click="closeImportDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            @click="importComponents"
            :loading="importing"
            :disabled="!importFile"
          >
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '../layout/AdminLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import { debounce } from 'lodash';

const { showSuccess, showError } = useToast();
const route = useRoute();

// Reactive state
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const loadingCaseRecords = ref(false);
const components = ref([]);
const bundles = ref([]);
const caseRecords = ref([]);
const statistics = ref({});
const totalItems = ref(0);
const searchQuery = ref('');
const bundleFilter = ref(null);
const itemTypeFilter = ref(null);

const showCreateDialog = ref(false);
const showBulkAddDialog = ref(false);
const showDeleteDialog = ref(false);
const importDialog = ref(false);
const editMode = ref(false);
const componentForm = ref(null);
const bulkForm = ref(null);
const importFile = ref(null);
const importErrors = ref([]);
const importSuccess = ref(false);
const importSuccessMessage = ref('');
const importing = ref(false);
const downloadingTemplate = ref(false);
const componentToDelete = ref(null);

const formData = ref({
  service_bundle_id: null,
  case_record_id: null,
  item_type: '',
  max_quantity: 1,
});

const bulkFormData = ref({
  service_bundle_id: null,
  item_type: '',
  selected_items: [],
  default_max_quantity: 1,
});

const caseTypeFilter = ref('');
const caseTypes = ref([
  { value: '', text: 'All Types' },
  { value: 'DRUG', text: 'Drug' },
  { value: 'LABORATORY', text: 'Laboratory' },
  { value: 'PROFESSIONAL_SERVICE', text: 'Professional Service' },
  { value: 'RADIOLOGY', text: 'Radiology' },
  { value: 'CONSULTATION', text: 'Consultation' },
  { value: 'CONSUMABLE', text: 'Consumable' }
]);

// Get already added case record IDs for the selected bundle
const addedCaseRecordIds = computed(() => {
  if (!formData.value.service_bundle_id) return [];
  return components.value
    .filter(c => c.service_bundle_id === formData.value.service_bundle_id)
    .map(c => c.case_record_id);
});

// Filter available case records to exclude already added ones (except when editing)
const availableCaseRecords = computed(() => {
  if (editMode.value) return caseRecords.value;
  return caseRecords.value.filter(cr => !addedCaseRecordIds.value.includes(cr.id));
});

const headers = [
  { title: 'Bundle', key: 'service_bundle', sortable: true },
  { title: 'Item', key: 'case_record', sortable: true },
  { title: 'Max Quantity', key: 'max_quantity', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

const itemTypes = ['LAB', 'DRUG', 'CONSULTATION', 'PROCEDURE', 'IMAGING', 'OTHER'];

onMounted(async () => {
  await loadBundles();
  applyRouteBundle();
  await Promise.all([
    loadComponents(),
    loadStatistics(),
  ]);
});

// Sync bundle filter from route (e.g., "Manage Components" button)
const applyRouteBundle = () => {
  const bundleId = route.query.bundle_id;
  if (!bundleId) return;

  const parsed = Number(bundleId);
  bundleFilter.value = parsed;
  formData.value.service_bundle_id = parsed;
  bulkFormData.value.service_bundle_id = parsed;
};

// Load components with pagination and filters
const loadComponents = async (options = {}) => {
  loading.value = true;
  try {
    const params = {
      page: options.page || 1,
      per_page: options.itemsPerPage || 15,
      search: searchQuery.value,
      service_bundle_id: bundleFilter.value,
      item_type: itemTypeFilter.value,
    };

    const response = await api.get('/bundle-components', { params });
    components.value = response.data.data || response.data;
    totalItems.value = response.data.total || components.value.length;
  } catch (err) {
    showError('Failed to load bundle components');
  } finally {
    loading.value = false;
  }
};

// Load bundles (case records where is_bundle = true)
const loadBundles = async () => {
  try {
    const response = await api.get('/cases', {
      params: {
        is_bundle: true,
        per_page: 1000
      }
    });
    bundles.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load bundles', err);
  }
};

// Load case records (only non-bundle items: is_bundle = false) with optional case type filter
const loadCaseRecords = async () => {
  loadingCaseRecords.value = true;
  try {
    const params = {
      is_bundle: false, // Only load FFS services (non-bundle items)
      per_page: 100000
    };

    // Filter by case type if selected
    if (caseTypeFilter.value && caseTypeFilter.value !== '') {
      params.detail_type = caseTypeFilter.value.toLocaleLowerCase();
    }

    const response = await api.get('/cases', { params });
    caseRecords.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load case records', err);
  } finally {
    loadingCaseRecords.value = false;
  }
};

// Load statistics
const loadStatistics = async () => {
  try {
    const response = await api.get('/bundle-components/statistics');
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

// Debounced search
const debouncedSearch = debounce(() => {
  loadComponents();
}, 500);

// Handle table update (pagination, sorting)
const handleTableUpdate = (options) => {
  loadComponents(options);
};

// Reset filters
const resetFilters = () => {
  searchQuery.value = '';
  bundleFilter.value = null;
  itemTypeFilter.value = null;
  loadComponents();
};

// Open create dialog
const openCreateDialog = async () => {
  await loadCaseRecords();
  showCreateDialog.value = true;
};

// Keep form bundle selections in sync with current filter (when not editing)
watch(bundleFilter, (val) => {
  if (!editMode.value) {
    formData.value.service_bundle_id = val;
  }
  bulkFormData.value.service_bundle_id = val;
});

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

// Edit component
const editComponent = (component) => {
  editMode.value = true;
  formData.value = { ...component };
  showCreateDialog.value = true;
};
// case type from detail_type
const getCaseType = (detail_type) => {
  console.log('detail_type:', detail_type);

  let case_type = null;

  switch (detail_type) {
    case 'App\\Models\\DrugDetail':
      case_type = 'drug';
      break;

    case 'App\\Models\\RadiologyDetail':
      case_type = 'radiology';
      break;

    case 'App\\Models\\ConsultationDetail':
        case_type = 'consultation';
      break;
    case 'App\\Models\\ConsumableDetail':
        case_type = 'consumable';
      break;
    case 'App\\Models\\LaboratoryDetail':
      case_type = 'laboratory';
      break;

    default:
      case_type = detail_type;
      break;
  }

  console.log('case_type:', case_type);
  return case_type;
};


// Save component (create or update)
const saveComponent = async () => {
  if (!componentForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  // add item_type to formData.value from case records
  const caseRecord = caseRecords.value.find(c => c.id === formData.value.case_record_id);
  formData.value.item_type = getCaseType(caseRecord?.detail_type) || 'OTHER';
  try {
    if (editMode.value) {
      await api.put(`/bundle-components/${formData.value.id}`, formData.value);
      showSuccess('Component updated successfully');
    } else {
      await api.post('/bundle-components', formData.value);
      showSuccess('Component created successfully');
    }
    closeDialog();
    await Promise.all([loadComponents(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to save component';
    showError(message);
  } finally {
    saving.value = false;
  }
};

// Bulk add components
const bulkAddComponents = async () => {
  if (!bulkForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  try {
    const payload = {
      service_bundle_id: bulkFormData.value.service_bundle_id,
      components: bulkFormData.value.selected_items.map(itemId => ({
        case_record_id: itemId,
        item_type: bulkFormData.value.item_type,
        max_quantity: bulkFormData.value.default_max_quantity,
      })),
    };

    await api.post('/bundle-components/bulk', payload);
    showSuccess(`${bulkFormData.value.selected_items.length} components added successfully`);
    closeBulkDialog();
    await Promise.all([loadComponents(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to bulk add components';
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
    service_bundle_id: null,
    case_record_id: null,
    item_type: '',
    max_quantity: 1,
  };
};

// Close bulk dialog
const closeBulkDialog = () => {
  showBulkAddDialog.value = false;
  bulkFormData.value = {
    service_bundle_id: null,
    item_type: '',
    selected_items: [],
    default_max_quantity: 1,
  };
  caseRecords.value = [];
};

// Confirm delete
const confirmDelete = (component) => {
  componentToDelete.value = component;
  showDeleteDialog.value = true;
};

// Delete component
const deleteComponent = async () => {
  deleting.value = true;
  try {
    await api.delete(`/bundle-components/${componentToDelete.value.id}`);
    showSuccess('Component deleted successfully');
    showDeleteDialog.value = false;
    componentToDelete.value = null;
    await Promise.all([loadComponents(), loadStatistics()]);
  } catch (err) {
    const message = err.response?.data?.message || 'Failed to delete component';
    showError(message);
  } finally {
    deleting.value = false;
  }
};

// Export components
const exportComponents = async () => {
  try {
    const params = {
      service_bundle_id: bundleFilter.value,
      item_type: itemTypeFilter.value,
    };

    const response = await api.get('/bundle-components-export', {
      params,
      responseType: 'blob'
    });
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `bundle_components_export_${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    showSuccess('Bundle components exported successfully');
  } catch (err) {
    showError('Failed to export bundle components');
  }
};

// Import functions
const openImportDialog = () => {
  importDialog.value = true;
  importFile.value = null;
  importErrors.value = [];
  importSuccess.value = false;
  importSuccessMessage.value = '';
};

const closeImportDialog = () => {
  importDialog.value = false;
  importFile.value = null;
  importErrors.value = [];
  importSuccess.value = false;
  importSuccessMessage.value = '';
};

const downloadTemplate = async () => {
  try {
    downloadingTemplate.value = true;
    const response = await api.get('/bundle-components-template', {
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'bundle_components_template.xlsx');
    document.body.appendChild(link);
    link.click();
    link.remove();

    showSuccess('Template downloaded successfully');
  } catch (err) {
    showError('Failed to download template');
  } finally {
    downloadingTemplate.value = false;
  }
};

const importComponents = async () => {
  // Check if file is selected (handle both array and single file)
  const file = Array.isArray(importFile.value) ? importFile.value[0] : importFile.value;

  if (!file) {
    showError('Please select a file to import');
    return;
  }

  try {
    importing.value = true;
    importErrors.value = [];
    importSuccess.value = false;

    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post('/bundle-components-import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      importSuccess.value = true;
      importSuccessMessage.value = response.data.message;

      if (response.data.data.errors && response.data.data.errors.length > 0) {
        importErrors.value = response.data.data.errors;
      }

      // Reload components after successful import
      await loadComponents();

      // Close dialog after 2 seconds if no errors
      if (importErrors.value.length === 0) {
        setTimeout(() => {
          closeImportDialog();
        }, 2000);
      }
    } else {
      importErrors.value = [response.data.message];
    }
  } catch (err) {
    if (err.response?.data?.data?.errors) {
      importErrors.value = err.response.data.data.errors;
    } else {
      importErrors.value = [err.response?.data?.message || 'Failed to import bundle components'];
    }
  } finally {
    importing.value = false;
  }
};
</script>

<style scoped>
.bundle-components-management-page {
  padding: 20px 0;
}
</style>

