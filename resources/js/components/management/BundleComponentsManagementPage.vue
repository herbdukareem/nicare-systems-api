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
                    item-title="name"
                    item-value="id"
                    clearable
                    outlined
                    dense
                    @update:model-value="loadComponents"
                  />
                </v-col>
                <v-col cols="12" md="3">
                  <v-select
                    v-model="itemTypeFilter"
                    label="Item Type"
                    :items="itemTypes"
                    clearable
                    outlined
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
                    Add Component
                  </v-btn>
                  <v-btn color="success" @click="showBulkAddDialog = true">
                    <v-icon left>mdi-plus-box-multiple</v-icon>
                    Bulk Add
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
                    <div class="font-weight-medium">{{ item.service_bundle?.name }}</div>
                    <div class="text-caption text-grey">{{ item.service_bundle?.code }}</div>
                  </div>
                </template>

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
    <v-dialog v-model="showCreateDialog" max-width="700px" persistent>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <span>{{ editMode ? 'Edit Component' : 'Add New Component' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="componentForm">
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.service_bundle_id"
                  label="Service Bundle *"
                  :items="bundles"
                  item-title="name"
                  item-value="id"
                  :rules="[v => !!v || 'Service bundle is required']"
                  outlined
                  dense
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>{{ item.raw.name }}</template>
                      <template v-slot:subtitle>{{ item.raw.code }} - ₦{{ Number(item.raw.fixed_price).toLocaleString() }}</template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.item_type"
                  label="Item Type *"
                  :items="itemTypes"
                  :rules="[v => !!v || 'Item type is required']"
                  outlined
                  dense
                  @update:model-value="onItemTypeChange"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.max_quantity"
                  label="Max Quantity *"
                  type="number"
                  :rules="[v => !!v || 'Max quantity is required', v => v > 0 || 'Must be greater than 0']"
                  outlined
                  dense
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.case_record_id"
                  label="Service/Drug/Lab Item *"
                  :items="caseRecords"
                  item-title="service_description"
                  item-value="id"
                  :rules="[v => !!v || 'Item is required']"
                  :loading="loadingCaseRecords"
                  outlined
                  dense
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>{{ item.raw.service_description }}</template>
                      <template v-slot:subtitle>{{ item.raw.nicare_code }} - ₦{{ Number(item.raw.price).toLocaleString() }}</template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveComponent" :loading="saving">
            {{ editMode ? 'Update' : 'Create' }}
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
                  item-title="name"
                  item-value="id"
                  :rules="[v => !!v || 'Service bundle is required']"
                  outlined
                  dense
                />
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-select
                  v-model="bulkFormData.item_type"
                  label="Item Type *"
                  :items="itemTypes"
                  :rules="[v => !!v || 'Item type is required']"
                  outlined
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
                  outlined
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
                  outlined
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
const editMode = ref(false);
const componentForm = ref(null);
const bulkForm = ref(null);
const componentToDelete = ref(null);

const formData = ref({
  service_bundle_id: null,
  case_record_id: null,
  max_quantity: 1,
  item_type: '',
});

const bulkFormData = ref({
  service_bundle_id: null,
  item_type: '',
  selected_items: [],
  default_max_quantity: 1,
});

const headers = [
  { title: 'Bundle', key: 'service_bundle', sortable: true },
  { title: 'Item', key: 'case_record', sortable: true },
  { title: 'Item Type', key: 'item_type', sortable: true },
  { title: 'Max Quantity', key: 'max_quantity', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false },
];

const itemTypes = ['LAB', 'DRUG', 'CONSULTATION', 'PROCEDURE', 'IMAGING', 'OTHER'];

onMounted(async () => {
  await Promise.all([
    loadComponents(),
    loadBundles(),
    loadStatistics(),
  ]);
});

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

    const response = await api.get('/api/bundle-components', { params });
    components.value = response.data.data || response.data;
    totalItems.value = response.data.total || components.value.length;
  } catch (err) {
    showError('Failed to load bundle components');
  } finally {
    loading.value = false;
  }
};

// Load bundles
const loadBundles = async () => {
  try {
    const response = await api.get('/api/service-bundles');
    bundles.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load bundles', err);
  }
};

// Load case records based on item type
const loadCaseRecords = async (itemType) => {
  loadingCaseRecords.value = true;
  try {
    const params = {};

    // Filter by group based on item type
    if (itemType === 'LAB') {
      params.group = 'LABS';
    } else if (itemType === 'DRUG') {
      params.group = 'DRUGS';
    }

    const response = await api.get('/api/cases', { params });
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
    const response = await api.get('/api/bundle-components/statistics');
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

// On item type change
const onItemTypeChange = (itemType) => {
  formData.value.case_record_id = null;
  caseRecords.value = [];
  if (itemType) {
    loadCaseRecords(itemType);
  }
};

// On bulk item type change
const onBulkItemTypeChange = (itemType) => {
  bulkFormData.value.selected_items = [];
  caseRecords.value = [];
  if (itemType) {
    loadCaseRecords(itemType);
  }
};


// Edit component
const editComponent = (component) => {
  editMode.value = true;
  formData.value = { ...component };
  // Load case records for the item type
  if (component.item_type) {
    loadCaseRecords(component.item_type);
  }
  showCreateDialog.value = true;
};

// Save component (create or update)
const saveComponent = async () => {
  if (!componentForm.value.validate()) {
    showError('Please fill in all required fields');
    return;
  }

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/api/bundle-components/${formData.value.id}`, formData.value);
      showSuccess('Component updated successfully');
    } else {
      await api.post('/api/bundle-components', formData.value);
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

    await api.post('/api/bundle-components/bulk', payload);
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
    max_quantity: 1,
    item_type: '',
  };
  caseRecords.value = [];
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
    await api.delete(`/api/bundle-components/${componentToDelete.value.id}`);
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

    const response = await api.get('/api/bundle-components-export', {
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
</script>

<style scoped>
.bundle-components-management-page {
  padding: 20px 0;
}
</style>

