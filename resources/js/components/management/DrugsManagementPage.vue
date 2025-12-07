<template>
  <AdminLayout>
    <!-- Page Header -->
    <v-card flat class="mb-4">
      <v-card-title class="d-flex align-center justify-space-between bg-grey-lighten-4">
        <div class="d-flex align-center">
          <v-icon size="32" color="primary" class="mr-3">mdi-pill</v-icon>
          <div>
            <div class="text-h5 font-weight-bold">Drugs Management</div>
            <div class="text-caption text-grey-darken-1">Manage pharmaceutical items and drug details</div>
          </div>
        </div>
        <v-chip variant="outlined" color="primary">
          <v-icon start>mdi-database</v-icon>
          {{ statistics.total || 0 }} Total Drugs
        </v-chip>
      </v-card-title>
    </v-card>

    <!-- Statistics Cards -->
    <v-row class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Total Drugs</div>
                <div class="text-h5 font-weight-bold">{{ statistics.total || 0 }}</div>
              </div>
              <v-icon size="40" color="primary">mdi-pill-multiple</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Prescription Only</div>
                <div class="text-h5 font-weight-bold text-warning">{{ statistics.prescription_required || 0 }}</div>
              </div>
              <v-icon size="40" color="warning">mdi-file-document-edit</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Controlled Drugs</div>
                <div class="text-h5 font-weight-bold text-error">{{ statistics.controlled_drug || 0 }}</div>
              </div>
              <v-icon size="40" color="error">mdi-shield-alert</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">NAFDAC Approved</div>
                <div class="text-h5 font-weight-bold text-success">{{ statistics.nafdac_approved || 0 }}</div>
              </div>
              <v-icon size="40" color="success">mdi-check-decagram</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters and Actions -->
    <v-card elevation="1" class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              label="Search"
              placeholder="Search by name, code, or manufacturer"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @input="debouncedSearch"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.dosage_form"
              label="Dosage Form"
              :items="dosageForms"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchDrugs"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.prescription_required"
              label="Prescription"
              :items="prescriptionOptions"
              item-title="text"
              item-value="value"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchDrugs"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.controlled_drug"
              label="Controlled"
              :items="controlledOptions"
              item-title="text"
              item-value="value"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchDrugs"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3" class="d-flex gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openAddDialog">
              Add Drug
            </v-btn>
            <v-btn color="success" variant="outlined" prepend-icon="mdi-upload" @click="openImportDialog">
              Import
            </v-btn>
            <v-btn color="info" variant="outlined" prepend-icon="mdi-download" @click="exportDrugs">
              Export
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Drugs Table -->
    <v-card elevation="1">
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="drugs"
          :loading="loading"
          :items-per-page="pagination.per_page"
          hide-default-footer
          class="elevation-0"
        >
          <template #item.generic_name="{ item }">
            <div>
              <div class="font-weight-medium">{{ item.generic_name }}</div>
              <div class="text-caption text-grey">{{ item.brand_name || 'No brand' }}</div>
            </div>
          </template>

          <template #item.strength="{ item }">
            <span class="font-weight-medium">{{ item.strength }} {{ item.unit }}</span>
          </template>

          <template #item.dosage_form="{ item }">
            <v-chip size="small" variant="outlined" color="primary">
              {{ item.dosage_form }}
            </v-chip>
          </template>

          <template #item.prescription_required="{ item }">
            <v-chip size="small" :color="item.prescription_required ? 'warning' : 'grey'" variant="flat">
              {{ item.prescription_required ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.controlled_substance="{ item }">
            <v-chip size="small" :color="item.controlled_substance ? 'error' : 'grey'" variant="flat">
              {{ item.controlled_substance ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.nafdac_number="{ item }">
            <v-chip size="small" :color="item.nafdac_number ? 'success' : 'grey'" variant="flat">
              {{ item.nafdac_number ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <v-btn icon size="small" variant="text" color="info" @click="viewDrug(item)">
                <v-icon size="20">mdi-eye</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="primary" @click="editDrug(item)">
                <v-icon size="20">mdi-pencil</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="error" @click="confirmDelete(item)">
                <v-icon size="20">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>

        <!-- Pagination -->
        <div class="d-flex justify-space-between align-center mt-4">
          <div class="text-caption text-grey">
            Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}
            to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
            of {{ pagination.total }} entries
          </div>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="7"
            @update:model-value="fetchDrugs"
          ></v-pagination>
        </div>
      </v-card-text>
    </v-card>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { debounce } from 'lodash';
import AdminLayout from '@/js/components/layout/AdminLayout.vue';
import api from '@/js/utils/api';

// State
const loading = ref(false);
const drugs = ref([]);
const showDialog = ref(false);
const dialogMode = ref('create'); // 'create' or 'edit'
const drugToDelete = ref(null);
const showDeleteDialog = ref(false);

const statistics = reactive({
  total: 0,
  prescription_required: 0,
  controlled_drug: 0,
  nafdac_approved: 0
});

const filters = reactive({
  search: '',
  dosage_form: null,
  prescription_required: null,
  controlled_drug: null,
  per_page: 15
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

const form = reactive({
  id: null,
  generic_name: '',
  brand_name: '',
  dosage_form: '',
  strength: '',
  route_of_administration: '',
  manufacturer: '',
  drug_class: '',
  indications: '',
  contraindications: '',
  side_effects: '',
  storage_conditions: '',
  prescription_required: false,
  controlled_substance: false,
  nafdac_number: '',
  expiry_date: null
});

const headers = [
  { title: 'Generic Name / Brand', key: 'generic_name', sortable: true },
  { title: 'Strength', key: 'strength', sortable: true },
  { title: 'Dosage Form', key: 'dosage_form', sortable: true },
  { title: 'Route', key: 'route_of_administration', sortable: false },
  { title: 'Manufacturer', key: 'manufacturer', sortable: true },
  { title: 'Prescription', key: 'prescription_required', sortable: true },
  { title: 'Controlled', key: 'controlled_substance', sortable: true },
  { title: 'NAFDAC', key: 'nafdac_number', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
];

const dosageForms = [
  'Tablet', 'Capsule', 'Syrup', 'Suspension', 'Injection', 'Cream', 'Ointment',
  'Gel', 'Drops', 'Inhaler', 'Suppository', 'Patch', 'Powder', 'Solution'
];

const routesOfAdministration = [
  'Oral', 'Intravenous', 'Intramuscular', 'Subcutaneous', 'Topical',
  'Rectal', 'Inhalation', 'Sublingual', 'Transdermal', 'Ophthalmic', 'Otic', 'Nasal'
];

const prescriptionOptions = [
  { text: 'Prescription Required', value: true },
  { text: 'Over the Counter', value: false }
];

const controlledOptions = [
  { text: 'Controlled Drug', value: true },
  { text: 'Not Controlled', value: false }
];

// Methods
const fetchDrugs = async () => {
  loading.value = true;
  try {
    const params = {
      ...filters,
      page: pagination.current_page,
      per_page: pagination.per_page
    };

    const response = await api.get('/drug-details', { params });

    if (response.data.success) {
      drugs.value = response.data.data;
      pagination.total = response.data.total;
      pagination.current_page = response.data.current_page;
      pagination.last_page = response.data.last_page;
      pagination.per_page = response.data.per_page;
    }
  } catch (error) {
    console.error('Error fetching drugs:', error);
  } finally {
    loading.value = false;
  }
};

const fetchStatistics = async () => {
  try {
    const response = await api.get('/drug-details-statistics');
    if (response.data.success) {
      Object.assign(statistics, response.data.data);
    }
  } catch (error) {
    console.error('Error fetching statistics:', error);
  }
};

const debouncedSearch = debounce(() => {
  pagination.current_page = 1;
  fetchDrugs();
}, 500);

const openAddDialog = () => {
  dialogMode.value = 'create';
  resetForm();
  showDialog.value = true;
};

const editDrug = (drug) => {
  dialogMode.value = 'edit';
  Object.assign(form, drug);
  showDialog.value = true;
};

const viewDrug = (drug) => {
  // TODO: Implement view dialog
  console.log('View drug:', drug);
};

const confirmDelete = (drug) => {
  drugToDelete.value = drug;
  showDeleteDialog.value = true;
};

const deleteDrug = async () => {
  try {
    const response = await api.delete(`/drug-details/${drugToDelete.value.id}`);
    if (response.data.success) {
      showDeleteDialog.value = false;
      drugToDelete.value = null;
      fetchDrugs();
      fetchStatistics();
    }
  } catch (error) {
    console.error('Error deleting drug:', error);
  }
};

const saveDrug = async () => {
  try {
    let response;
    if (dialogMode.value === 'edit') {
      response = await api.put(`/drug-details/${form.id}`, form);
    } else {
      response = await api.post('/drug-details', form);
    }

    if (response.data.success) {
      showDialog.value = false;
      resetForm();
      fetchDrugs();
      fetchStatistics();
    }
  } catch (error) {
    console.error('Error saving drug:', error);
  }
};

const resetForm = () => {
  Object.assign(form, {
    id: null,
    generic_name: '',
    brand_name: '',
    dosage_form: '',
    strength: '',
    route_of_administration: '',
    manufacturer: '',
    drug_class: '',
    indications: '',
    contraindications: '',
    side_effects: '',
    storage_conditions: '',
    prescription_required: false,
    controlled_substance: false,
    nafdac_number: '',
    expiry_date: null
  });
};

const openImportDialog = () => {
  // TODO: Implement import
  console.log('Import drugs');
};

const exportDrugs = () => {
  // TODO: Implement export
  console.log('Export drugs');
};

onMounted(() => {
  fetchDrugs();
  fetchStatistics();
});
</script>

<style scoped>
.gap-2 {
  gap: 0.5rem;
}
</style>

