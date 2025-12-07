<template>
  <div class="bundle-management-page">
    <v-container>
      <v-row>
        <v-col cols="12">
          <v-card>
            <v-card-title class="bg-primary text-white d-flex justify-space-between align-center">
              <div>
                <v-icon left>mdi-package-variant</v-icon>
                Bundle Management
              </div>
              <v-btn color="white" @click="showCreateDialog = true">
                <v-icon left>mdi-plus</v-icon>
                New Bundle
              </v-btn>
            </v-card-title>

            <v-card-text>
              <!-- Filters -->
              <v-row class="mb-4">
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="searchQuery"
                    label="Search by bundle name"
                    outlined
                    dense
                    prepend-icon="mdi-magnify"
                  />
                </v-col>
                <v-col cols="12" md="4">
                  <v-select
                    v-model="statusFilter"
                    label="Filter by Status"
                    :items="statusOptions"
                    outlined
                    dense
                    clearable
                  />
                </v-col>
              </v-row>

              <!-- Bundles Table -->
              <v-data-table
                :headers="headers"
                :items="filteredBundles"
                :loading="loading"
                class="elevation-1"
              >
                <template v-slot:item.status="{ item }">
                  <v-chip
                    :color="item.status === 'ACTIVE' ? 'green' : 'gray'"
                    text-color="white"
                    small
                  >
                    {{ item.status }}
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon
                    small
                    color="primary"
                    @click="editBundle(item)"
                  >
                    <v-icon>mdi-pencil</v-icon>
                  </v-btn>
                  <v-btn
                    icon
                    small
                    color="error"
                    @click="deleteBundle(item)"
                  >
                    <v-icon>mdi-delete</v-icon>
                  </v-btn>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Create/Edit Dialog -->
      <v-dialog v-model="showCreateDialog" max-width="600px">
        <v-card>
          <v-card-title>
            {{ editingBundle ? 'Edit Bundle' : 'Create New Bundle' }}
          </v-card-title>
          <v-card-text>
            <v-form ref="bundleForm">
              <v-text-field
                v-model="bundleData.name"
                label="Bundle Name"
                outlined
                required
                :rules="[v => !!v || 'Name is required']"
              />
              <v-textarea
                v-model="bundleData.description"
                label="Description"
                outlined
                rows="3"
              />
              <v-text-field
                v-model.number="bundleData.price"
                label="Bundle Price"
                type="number"
                outlined
                required
                :rules="[v => !!v || 'Price is required']"
              />
              <v-select
                v-model="bundleData.status"
                label="Status"
                :items="statusOptions"
                outlined
                required
              />
              <v-text-field
                v-model="bundleData.icd10_code"
                label="ICD-10 Code"
                outlined
              />
            </v-form>
          </v-card-text>
          <v-card-actions>
            <v-spacer></v-spacer>
            <v-btn color="secondary" @click="showCreateDialog = false">
              Cancel
            </v-btn>
            <v-btn color="primary" @click="saveBundle" :loading="loading">
              Save
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from '@/js/composables/useToast';
import api from '@/js/utils/api';

const { success: showSuccess, error: showError } = useToast();

const searchQuery = ref('');
const statusFilter = ref(null);
const showCreateDialog = ref(false);
const editingBundle = ref(null);
const bundleForm = ref(null);
const bundles = ref([]);
const loading = ref(false);

const statusOptions = [
  { title: 'Active', value: 'ACTIVE' },
  { title: 'Inactive', value: 'INACTIVE' },
];

const headers = [
  { title: 'Bundle Name', value: 'name' },
  { title: 'Price', value: 'price' },
  { title: 'ICD-10 Code', value: 'icd10_code' },
  { title: 'Status', value: 'status' },
  { title: 'Actions', value: 'actions' },
];

const bundleData = ref({
  name: '',
  description: '',
  price: 0,
  status: 'ACTIVE',
  icd10_code: '',
});

const filteredBundles = computed(() => {
  return bundles.value.filter(bundle => {
    const matchesSearch = !searchQuery.value || 
      bundle.name?.toLowerCase().includes(searchQuery.value.toLowerCase());
    
    const matchesStatus = !statusFilter.value || bundle.status === statusFilter.value;
    
    return matchesSearch && matchesStatus;
  });
});

onMounted(async () => {
  try {
    await fetchBundles();
  } catch (err) {
    showError('Failed to load bundles');
  }
});

const fetchBundles = async () => {
  loading.value = true;
  try {
    const response = await api.get('/api/bundles');
    bundles.value = response.data.data || response.data;
  } catch (err) {
    showError('Failed to load bundles');
  } finally {
    loading.value = false;
  }
};

const editBundle = (bundle) => {
  editingBundle.value = bundle;
  bundleData.value = { ...bundle };
  showCreateDialog.value = true;
};

const saveBundle = async () => {
  if (!bundleForm.value.validate()) return;

  loading.value = true;
  try {
    if (editingBundle.value) {
      await api.put(`/api/bundles/${editingBundle.value.id}`, bundleData.value);
      showSuccess('Bundle updated successfully');
    } else {
      await api.post('/api/bundles', bundleData.value);
      showSuccess('Bundle created successfully');
    }
    showCreateDialog.value = false;
    resetForm();
    await fetchBundles();
  } catch (err) {
    showError(err.message || 'Failed to save bundle');
  } finally {
    loading.value = false;
  }
};

const deleteBundle = async (bundle) => {
  if (!confirm('Are you sure you want to delete this bundle?')) return;

  loading.value = true;
  try {
    await api.delete(`/api/bundles/${bundle.id}`);
    showSuccess('Bundle deleted successfully');
    await fetchBundles();
  } catch (err) {
    showError('Failed to delete bundle');
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  editingBundle.value = null;
  bundleData.value = {
    name: '',
    description: '',
    price: 0,
    status: 'ACTIVE',
    icd10_code: '',
  };
};
</script>

<style scoped>
.bundle-management-page {
  padding: 20px 0;
}
</style>

