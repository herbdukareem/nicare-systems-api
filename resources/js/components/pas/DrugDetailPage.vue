<template>
  <div class="tw-min-h-screen tw-bg-gray-50">
    <!-- Header -->
    <div class="tw-bg-white tw-shadow-sm tw-border-b">
      <div class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8">
        <div class="tw-flex tw-items-center tw-justify-between tw-h-16">
          <div class="tw-flex tw-items-center tw-space-x-4">
            <v-btn
              icon
              variant="text"
              @click="$router.go(-1)"
            >
              <v-icon>mdi-arrow-left</v-icon>
            </v-btn>
            <div>
              <h1 class="tw-text-xl tw-font-semibold tw-text-gray-900">Drug Details</h1>
              <p class="tw-text-sm tw-text-gray-600">{{ drug?.drug_name || 'Loading...' }}</p>
            </div>
          </div>
          <div class="tw-flex tw-items-center tw-space-x-3">
            <v-btn
              v-if="drug"
              color="primary"
              variant="outlined"
              @click="editDrug"
            >
              <v-icon left>mdi-pencil</v-icon>
              Edit Drug
            </v-btn>
            <v-chip
              v-if="drug"
              :color="drug.status ? 'green' : 'red'"
              size="small"
              variant="flat"
            >
              {{ drug.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="tw-flex tw-justify-center tw-items-center tw-h-64">
      <v-progress-circular indeterminate color="primary" size="64" />
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8 tw-py-8">
      <v-alert type="error" variant="tonal">
        {{ error }}
      </v-alert>
    </div>

    <!-- Drug Details -->
    <div v-else-if="drug" class="tw-max-w-7xl tw-mx-auto tw-px-4 sm:tw-px-6 lg:tw-px-8 tw-py-8">
      <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-8">
        
        <!-- Main Content -->
        <div class="lg:tw-col-span-2 tw-space-y-6">
          
          <!-- Drug Information -->
          <v-card>
            <v-card-title class="tw-bg-blue-50 tw-text-blue-800">
              <v-icon class="tw-mr-2">mdi-pill</v-icon>
              Drug Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-6">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">NiCare Code</label>
                  <p class="tw-text-lg tw-font-mono tw-text-gray-900">{{ drug.nicare_code }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Drug Name</label>
                  <p class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ drug.drug_name }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Dosage Form</label>
                  <p class="tw-text-gray-900">{{ drug.drug_dosage_form }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Strength</label>
                  <p class="tw-text-gray-900">{{ drug.drug_strength || 'Not specified' }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Presentation</label>
                  <p class="tw-text-gray-900">{{ drug.drug_presentation }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Unit Price</label>
                  <p class="tw-text-lg tw-font-semibold tw-text-green-600">₦{{ Number(drug.drug_unit_price).toLocaleString() }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Usage Statistics (if available) -->
          <v-card v-if="statistics">
            <v-card-title class="tw-bg-green-50 tw-text-green-800">
              <v-icon class="tw-mr-2">mdi-chart-line</v-icon>
              Usage Statistics
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-6">
                <div class="tw-text-center">
                  <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ statistics.total_prescriptions || 0 }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Total Prescriptions</p>
                </div>
                <div class="tw-text-center">
                  <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ statistics.monthly_usage || 0 }}</p>
                  <p class="tw-text-sm tw-text-gray-600">This Month</p>
                </div>
                <div class="tw-text-center">
                  <p class="tw-text-2xl tw-font-bold tw-text-purple-600">{{ statistics.average_monthly || 0 }}</p>
                  <p class="tw-text-sm tw-text-gray-600">Monthly Average</p>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </div>

        <!-- Sidebar -->
        <div class="tw-space-y-6">
          
          <!-- Status & Metadata -->
          <v-card>
            <v-card-title class="tw-bg-gray-50">
              Status & Information
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-space-y-4">
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Status</label>
                  <div class="tw-mt-1">
                    <v-chip
                      :color="drug.status ? 'green' : 'red'"
                      size="small"
                      variant="flat"
                    >
                      {{ drug.status ? 'Active' : 'Inactive' }}
                    </v-chip>
                  </div>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Created Date</label>
                  <p class="tw-text-gray-900">{{ formatDate(drug.created_at) }}</p>
                </div>
                <div>
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Last Updated</label>
                  <p class="tw-text-gray-900">{{ formatDate(drug.updated_at) }}</p>
                </div>
                <div v-if="drug.creator">
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Created By</label>
                  <p class="tw-text-gray-900">{{ drug.creator.name }}</p>
                </div>
                <div v-if="drug.updater">
                  <label class="tw-text-sm tw-font-medium tw-text-gray-700">Last Updated By</label>
                  <p class="tw-text-gray-900">{{ drug.updater.name }}</p>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Actions -->
          <v-card>
            <v-card-title class="tw-bg-gray-50">
              Actions
            </v-card-title>
            <v-card-text class="tw-p-6">
              <div class="tw-space-y-3">
                <v-btn
                  color="blue"
                  variant="outlined"
                  block
                  @click="editDrug"
                >
                  <v-icon left>mdi-pencil</v-icon>
                  Edit Drug
                </v-btn>
                <v-btn
                  :color="drug.status ? 'orange' : 'green'"
                  variant="outlined"
                  block
                  @click="toggleStatus"
                >
                  <v-icon left>{{ drug.status ? 'mdi-pause' : 'mdi-play' }}</v-icon>
                  {{ drug.status ? 'Deactivate' : 'Activate' }}
                </v-btn>
                <v-btn
                  color="red"
                  variant="outlined"
                  block
                  @click="deleteDrug"
                >
                  <v-icon left>mdi-delete</v-icon>
                  Delete Drug
                </v-btn>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from '../../composables/useToast';
import { drugAPI } from '../../utils/api.js';

const route = useRoute();
const router = useRouter();
const { success, error: showError } = useToast();

// Reactive data
const drug = ref(null);
const loading = ref(false);
const error = ref(null);
const statistics = ref(null);

// Methods
const fetchDrug = async () => {
  try {
    loading.value = true;
    error.value = null;

    const drugId = route.params.drugId;
    const response = await drugAPI.getById(drugId);

    if (response.data.success) {
      drug.value = response.data.data;
    } else {
      error.value = response.data.message || 'Drug not found';
    }
  } catch (err) {
    console.error('Error fetching drug:', err);
    error.value = 'Failed to load drug details';
    showError('Failed to load drug details');
  } finally {
    loading.value = false;
  }
};

const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

const editDrug = () => {
  router.push(`/drugs/${drug.value.id}/edit`);
};

const toggleStatus = async () => {
  const action = drug.value.status ? 'deactivate' : 'activate';
  if (confirm(`Are you sure you want to ${action} this drug?`)) {
    try {
      const updatedData = { ...drug.value, status: !drug.value.status };
      const response = await drugAPI.update(drug.value.id, updatedData);
      
      if (response.data.success) {
        success(`Drug ${action}d successfully`);
        fetchDrug(); // Refresh data
      } else {
        showError(response.data.message || `Failed to ${action} drug`);
      }
    } catch (err) {
      console.error(`Error ${action}ing drug:`, err);
      showError(`Failed to ${action} drug`);
    }
  }
};

const deleteDrug = async () => {
  if (confirm('Are you sure you want to delete this drug? This action cannot be undone.')) {
    try {
      const response = await drugAPI.delete(drug.value.id);
      if (response.data.success) {
        success('Drug deleted successfully');
        router.push('/drugs');
      } else {
        showError(response.data.message || 'Failed to delete drug');
      }
    } catch (err) {
      console.error('Error deleting drug:', err);
      showError('Failed to delete drug');
    }
  }
};

onMounted(() => {
  fetchDrug();
});
</script>
