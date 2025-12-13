<template>
  <AdminLayout>
    <div class="do-dashboard">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-4">
          <v-col cols="12">
            <div class="d-flex justify-space-between align-center">
              <div>
                <h1 class="text-h4 font-weight-bold">
                  <v-icon size="32" color="primary" class="mr-2">mdi-desk</v-icon>
                  Desk Officer Dashboard
                </h1>
                <p class="text-subtitle-1 text-grey mt-2">
                  Manage assigned facilities, referrals, and PA codes
                </p>
              </div>
              <v-btn
                color="primary"
                variant="outlined"
                prepend-icon="mdi-refresh"
                @click="refreshData"
                :loading="loading"
              >
                Refresh
              </v-btn>
            </div>
          </v-col>
        </v-row>

        <!-- Stats Cards -->
        <v-row class="mb-4">
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Assigned Facilities</p>
                    <h3 class="text-h5 font-weight-bold text-primary">{{ stats.assignedFacilities }}</h3>
                  </div>
                  <v-icon size="40" color="primary">mdi-hospital-building</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Pending Referrals</p>
                    <h3 class="text-h5 font-weight-bold text-warning">{{ stats.pendingReferrals }}</h3>
                  </div>
                  <v-icon size="40" color="warning">mdi-file-document-alert</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">Pending PA Codes</p>
                    <h3 class="text-h5 font-weight-bold text-info">{{ stats.pendingPACodes }}</h3>
                  </div>
                  <v-icon size="40" color="info">mdi-shield-alert</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
          <v-col cols="12" md="3">
            <v-card class="stat-card">
              <v-card-text>
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <p class="text-caption text-grey mb-1">UTN Validations</p>
                    <h3 class="text-h5 font-weight-bold text-success">{{ stats.utnValidations }}</h3>
                  </div>
                  <v-icon size="40" color="success">mdi-check-circle</v-icon>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Assigned Facilities -->
        <v-row class="mb-4">
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-grey-lighten-4">
                <v-icon start>mdi-hospital-building</v-icon>
                Assigned Facilities
              </v-card-title>
              <v-card-text>
                <v-data-table
                  :headers="facilityHeaders"
                  :items="facilities"
                  :loading="loading"
                  class="elevation-0"
                >
                  <template v-slot:item.level_of_care="{ item }">
                    <v-chip :color="getLevelColor(item.level_of_care)" size="small">
                      {{ item.level_of_care }}
                    </v-chip>
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-btn
                      color="primary"
                      size="small"
                      variant="text"
                      @click="viewFacilityDetails(item)"
                    >
                      View Details
                    </v-btn>
                  </template>

                  <template v-slot:no-data>
                    <div class="text-center py-8">
                      <v-icon size="64" color="grey-lighten-2">mdi-hospital-building-outline</v-icon>
                      <p class="text-h6 text-grey mt-4">No facilities assigned</p>
                      <p class="text-body-2 text-grey">Contact admin to assign facilities to your account</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Quick Actions -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-title class="bg-grey-lighten-4">
                <v-icon start>mdi-lightning-bolt</v-icon>
                Quick Actions
              </v-card-title>
              <v-card-text>
                <v-row>
                  <v-col cols="12" md="4" v-for="action in quickActions" :key="action.title">
                    <v-card
                      class="action-card"
                      :color="action.color"
                      variant="tonal"
                      @click="navigateTo(action.route)"
                      hover
                    >
                      <v-card-text class="text-center">
                        <v-icon size="48" :color="action.color" class="mb-3">{{ action.icon }}</v-icon>
                        <h4 class="text-h6 mb-2">{{ action.title }}</h4>
                        <p class="text-body-2">{{ action.description }}</p>
                      </v-card-text>
                    </v-card>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { doDashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';
import AdminLayout from '../layout/AdminLayout.vue';

const router = useRouter();
const { success: showSuccess, error: showError } = useToast();

// State
const loading = ref(false);
const facilities = ref([]);
const overview = ref({
  assignedFacilities: 0,
  pendingReferrals: 0,
  pendingPACodes: 0,
  utnValidations: 0,
});

// Computed stats
const stats = computed(() => ({
  assignedFacilities: overview.value.total_facilities || 0,
  pendingReferrals: overview.value.pending_referrals || 0,
  pendingPACodes: overview.value.total_pa_codes || 0,
  utnValidations: overview.value.pending_utn_validations || 0,
}));

// Table headers
const facilityHeaders = [
  { title: 'Facility Name', value: 'name', key: 'name' },
  { title: 'Level of Care', value: 'level_of_care', key: 'level_of_care' },
  { title: 'LGA', value: 'lga.name', key: 'lga' },
  { title: 'Ward', value: 'ward.name', key: 'ward' },
  { title: 'Actions', value: 'actions', key: 'actions', sortable: false },
];

// Quick actions
const quickActions = [
  {
    title: 'Validate UTN',
    description: 'Validate UTN for approved referrals',
    icon: 'mdi-shield-check',
    color: 'primary',
    route: '/pas/validate-utn',
  },
  {
    title: 'View Referrals',
    description: 'View referrals for assigned facilities',
    icon: 'mdi-file-document-multiple',
    color: 'info',
    route: '/do/assigned-referrals',
  },
  {
    title: 'FU-PA Code Management',
    description: 'View and manage FU-PA code requests',
    icon: 'mdi-shield-check',
    color: 'success',
    route: '/pas/facility-pa-codes',
  },
   {
    title: 'Admit Patient',
    description: 'View and manage Admissions',
    icon: 'mdi-hospital',
    color: 'success',
    route: '/facility/admissions',
  },
   {
    title: 'Out-Patient Claim submission',
    description: 'View and manage Out Patient Claim submission',
    icon: 'mdi-hospital',
    color: 'error',
    route: '/facility/admissions',
  },
   {
    title: 'Submit Claim',
    description: 'Submit a new claim for processing',
    icon: 'mdi-file-document-plus',
    color: 'primary',
    route: '/facility/claims/submit',
  },
];

// Fetch overview data
const fetchOverview = async () => {
  loading.value = true;
  try {
      const response = await doDashboardAPI.getOverview();
      console.log
      const data = response.data?.data || response.data || {};
      overview.value = data.stats;
      facilities.value =data.assigned_facilities || [];
    
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch dashboard data');
    console.error(err);
  } finally {
    loading.value = false;
  }
};

// Refresh data
const refreshData = async () => {
  await fetchOverview();
  showSuccess('Dashboard data refreshed');
};

// Get level color
const getLevelColor = (level) => {
  const colors = {
    'Primary': 'green',
    'Secondary': 'blue',
    'Tertiary': 'purple',
  };
  return colors[level] || 'grey';
};

// View facility details
const viewFacilityDetails = (facility) => {
  // Navigate to facility details or show dialog
  console.log('View facility:', facility);
};

// Navigate to route
const navigateTo = (route) => {
  router.push(route);
};

// Lifecycle
onMounted(() => {
  fetchOverview();
});
</script>

<style scoped>
.do-dashboard {
  padding: 20px 0;
}

.stat-card {
  border-left: 4px solid;
  transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-card:nth-child(1) {
  border-left-color: rgb(var(--v-theme-primary));
}

.stat-card:nth-child(2) {
  border-left-color: rgb(var(--v-theme-warning));
}

.stat-card:nth-child(3) {
  border-left-color: rgb(var(--v-theme-info));
}

.stat-card:nth-child(4) {
  border-left-color: rgb(var(--v-theme-success));
}

.action-card {
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
}

.action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}
</style>

