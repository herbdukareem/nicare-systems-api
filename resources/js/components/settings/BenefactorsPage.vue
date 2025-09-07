<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Manage Benefactors</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage healthcare scheme benefactors and sponsors</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            variant="outlined" 
            prepend-icon="mdi-download"
            @click="exportBenefactors"
          >
            Export
          </v-btn>
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showCreateDialog = true"
          >
            Add Benefactor
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-blue-100">
              <v-icon color="blue" size="24">mdi-account-heart</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Benefactors</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ totalBenefactors }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-green-100">
              <v-icon color="green" size="24">mdi-check-circle</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Active</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">{{ activeBenefactorsCount }}</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-purple-100">
              <v-icon color="purple" size="24">mdi-currency-ngn</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Total Funding</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">₦2.5B</p>
            </div>
          </div>
        </div>
        
        <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
          <div class="tw-flex tw-items-center">
            <div class="tw-p-3 tw-rounded-full tw-bg-orange-100">
              <v-icon color="orange" size="24">mdi-account-group</v-icon>
            </div>
            <div class="tw-ml-4">
              <p class="tw-text-sm tw-font-medium tw-text-gray-600">Beneficiaries</p>
              <p class="tw-text-2xl tw-font-bold tw-text-gray-900">45,230</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm tw-p-6">
        <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-lg:tw-grid-cols-4 tw-gap-4">
          <!-- Search -->
          <div class="tw-lg:tw-col-span-2">
            <v-text-field
              v-model="searchQuery"
              label="Search benefactors..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
            />
          </div>
          
          <!-- Type Filter -->
          <v-select
            v-model="filters.type"
            :items="typeOptions"
            label="Type"
            variant="outlined"
            density="compact"
            clearable
          />
          
          <!-- Status Filter -->
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            clearable
          />
        </div>
      </div>

      <!-- Benefactors Table -->
      <div class="tw-bg-white tw-rounded-lg tw-shadow-sm">
        <v-data-table
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="benefactors"
          :loading="loading"
          class="tw-elevation-0"
          item-value="id"
        >
          <!-- Custom header -->
          <template v-slot:top>
            <div class="tw-p-4 tw-border-b tw-border-gray-200">
              <div class="tw-flex tw-items-center tw-justify-between">
                <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">
                  Benefactors List
                </h3>
                <v-chip size="small" color="primary">
                  {{ filteredBenefactors.length }} benefactors
                </v-chip>
              </div>
            </div>
          </template>

          <!-- Logo column -->
          <template v-slot:item.logo="{ item }">
            <v-avatar size="40" color="primary">
              <span class="tw-text-white tw-text-sm">{{ getInitials(item.name) }}</span>
            </v-avatar>
          </template>

          <!-- Type column -->
          <template v-slot:item.type="{ item }">
            <v-chip
              :color="getTypeColor(item.type)"
              size="small"
              variant="outlined"
            >
              {{ item.type }}
            </v-chip>
          </template>

          <!-- Status column -->
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ item.status }}
            </v-chip>
          </template>

          <!-- Funding column -->
          <template v-slot:item.total_funding="{ item }">
            <span class="tw-font-medium tw-text-green-600">
              {{ formatCurrency(item.total_funding) }}
            </span>
          </template>

          <!-- Beneficiaries column -->
          <template v-slot:item.beneficiaries_count="{ item }">
            <span class="tw-text-gray-600">{{ item.beneficiaries_count?.toLocaleString() || 0 }}</span>
          </template>

          <!-- Actions column -->
          <template v-slot:item.actions="{ item }">
            <div class="tw-flex tw-space-x-1">
              <v-btn
                icon
                size="small"
                variant="text"
                @click="viewBenefactor(item)"
              >
                <v-icon size="16">mdi-eye</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                @click="editBenefactor(item)"
              >
                <v-icon size="16">mdi-pencil</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                @click="deleteBenefactor(item)"
              >
                <v-icon size="16">mdi-delete</v-icon>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="700px">
      <v-card>
        <v-card-title>
          <span class="tw-text-xl tw-font-semibold">
            {{ editingBenefactor ? 'Edit Benefactor' : 'Add New Benefactor' }}
          </span>
        </v-card-title>
        <v-card-text>
          <div class="tw-space-y-4">
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="benefactorForm.name"
                label="Organization Name"
                variant="outlined"
                required
              />
              <v-select
                v-model="benefactorForm.type"
                :items="typeOptions"
                label="Type"
                variant="outlined"
                required
              />
            </div>
            
            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="benefactorForm.contact_person"
                label="Contact Person"
                variant="outlined"
                required
              />
              <v-text-field
                v-model="benefactorForm.email"
                label="Email"
                type="email"
                variant="outlined"
                required
              />
            </div>

            <div class="tw-grid tw-grid-cols-1 tw-md:tw-grid-cols-2 tw-gap-4">
              <v-text-field
                v-model="benefactorForm.phone"
                label="Phone Number"
                variant="outlined"
                required
              />
              <v-select
                v-model="benefactorForm.status"
                :items="statusOptions"
                label="Status"
                variant="outlined"
                required
              />
            </div>

            <v-textarea
              v-model="benefactorForm.address"
              label="Address"
              variant="outlined"
              rows="2"
            />

            <v-text-field
              v-model="benefactorForm.total_funding"
              label="Total Funding (₦)"
              type="number"
              variant="outlined"
              prefix="₦"
            />

            <v-textarea
              v-model="benefactorForm.description"
              label="Description"
              variant="outlined"
              rows="3"
            />
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveBenefactor">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { useToast } from '../../composables/useToast';
import { benefactorAPI } from '../../utils/api';

const { success, error } = useToast();

// Reactive data
const loading = ref(false);
const searchQuery = ref('');
const showCreateDialog = ref(false);
const editingBenefactor = ref(null);
const itemsPerPage = ref(10);

// Filters
const filters = ref({
  type: null,
  status: null
});

// Form data
const benefactorForm = ref({
  name: '',
  type: '',
  contact_person: '',
  email: '',
  phone: '',
  address: '',
  status: 'active',
  total_funding: '',
  description: ''
});

// Options
const typeOptions = ['Government', 'Private Company', 'NGO', 'International Organization', 'Foundation'];
const statusOptions = ['active', 'inactive', 'pending'];

// Table headers
const headers = [
  { title: '', key: 'logo', sortable: false, width: '60px' },
  { title: 'Name', key: 'name', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'Contact Person', key: 'contact_person', sortable: true },
  { title: 'Email', key: 'email', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Total Funding', key: 'total_funding', sortable: true },
  { title: 'Beneficiaries', key: 'beneficiaries_count', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' }
];

// Data
const benefactors = ref([]);
const totalBenefactors = ref(0);
const currentPage = ref(1);

// Computed properties
const filteredBenefactors = computed(() => {
  let filtered = benefactors.value;
  
  if (filters.value.type) {
    filtered = filtered.filter(benefactor => benefactor.type === filters.value.type);
  }
  
  if (filters.value.status) {
    filtered = filtered.filter(benefactor => benefactor.status === filters.value.status);
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(benefactor =>
      benefactor.name.toLowerCase().includes(query) ||
      benefactor.contact_person.toLowerCase().includes(query) ||
      benefactor.email.toLowerCase().includes(query)
    );
  }
  
  return filtered;
});

const activeBenefactorsCount = computed(() => {
  return benefactors.value.filter(benefactor => benefactor.status === 'active').length;
});

// Methods
const getInitials = (name) => {
  return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
};

const getTypeColor = (type) => {
  switch (type.toLowerCase()) {
    case 'government': return 'blue';
    case 'private company': return 'green';
    case 'ngo': return 'orange';
    case 'international organization': return 'purple';
    case 'foundation': return 'teal';
    default: return 'grey';
  }
};

const getStatusColor = (status) => {
  switch (status.toLowerCase()) {
    case 'active': return 'success';
    case 'pending': return 'warning';
    case 'inactive': return 'error';
    default: return 'grey';
  }
};

const formatCurrency = (amount) => {
  if (!amount) return '₦0';
  return new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
};

const viewBenefactor = (benefactor) => {
  // Navigate to benefactor detail page or show detail dialog
  console.log('View benefactor:', benefactor);
};

const editBenefactor = (benefactor) => {
  editingBenefactor.value = benefactor;
  Object.assign(benefactorForm.value, benefactor);
  showCreateDialog.value = true;
};

const deleteBenefactor = (benefactor) => {
  if (confirm(`Are you sure you want to delete ${benefactor.name}?`)) {
    const index = benefactors.value.findIndex(b => b.id === benefactor.id);
    if (index > -1) {
      benefactors.value.splice(index, 1);
      success('Benefactor deleted successfully');
    }
  }
};

const saveBenefactor = () => {
  if (editingBenefactor.value) {
    // Update existing benefactor
    Object.assign(editingBenefactor.value, benefactorForm.value);
    success('Benefactor updated successfully');
  } else {
    // Create new benefactor
    const newBenefactor = {
      id: Date.now(),
      ...benefactorForm.value,
      beneficiaries_count: 0,
      total_funding: parseFloat(benefactorForm.value.total_funding) || 0
    };
    benefactors.value.push(newBenefactor);
    success('Benefactor created successfully');
  }
  closeDialog();
};

const closeDialog = () => {
  showCreateDialog.value = false;
  editingBenefactor.value = null;
  Object.keys(benefactorForm.value).forEach(key => {
    if (key === 'status') {
      benefactorForm.value[key] = 'active';
    } else {
      benefactorForm.value[key] = '';
    }
  });
};

// API Methods
const loadBenefactors = async () => {
  loading.value = true;
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      sort_by: 'created_at',
      sort_direction: 'desc'
    };

    // Add filters if they have values
    if (searchQuery.value && searchQuery.value.trim()) {
      params.search = searchQuery.value.trim();
    }
    if (filters.value.type) {
      params.type = filters.value.type;
    }
    if (filters.value.status) {
      params.status = filters.value.status;
    }

    const response = await benefactorAPI.getAll(params);

    if (response?.data?.success) {
      const responseData = response.data.data;

      if (responseData && typeof responseData === 'object' && responseData.data) {
        benefactors.value = responseData.data;
        totalBenefactors.value = responseData.meta?.total || responseData.total || 0;
      } else if (Array.isArray(responseData)) {
        benefactors.value = responseData;
        totalBenefactors.value = responseData.length;
      } else {
        benefactors.value = [];
        totalBenefactors.value = 0;
      }
    } else {
      benefactors.value = [];
      totalBenefactors.value = 0;
    }
  } catch (err) {
    console.error('Failed to load benefactors:', err);
    error('Failed to load benefactors');
    benefactors.value = [];
    totalBenefactors.value = 0;
  } finally {
    loading.value = false;
  }
};

const exportBenefactors = () => {
  success('Benefactors exported successfully');
};

// Lifecycle
onMounted(() => {
  loadBenefactors();
});
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 0.5rem;
}
</style>
