<template>
  <AdminLayout>
    <div class="pas-dashboard">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="mb-6">
            <h1 class="text-h4 font-weight-bold">Pre-Authorization System (PAS)</h1>
            <p class="text-subtitle-1 text-grey">Manage pre-authorizations, referrals, and PA codes</p>
          </div>
        </v-col>
      </v-row>

      <!-- Statistics Cards -->
      <v-row>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Total PA Requests</p>
                  <h3 class="text-h5">{{ statistics.total_pa_requests || 0 }}</h3>
                </div>
                <v-icon size="40" color="primary">mdi-file-check</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Pending Approvals</p>
                  <h3 class="text-h5">{{ statistics.pending_approvals || 0 }}</h3>
                </div>
                <v-icon size="40" color="warning">mdi-clock-alert</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Active Referrals</p>
                  <h3 class="text-h5">{{ statistics.active_referrals || 0 }}</h3>
                </div>
                <v-icon size="40" color="info">mdi-hospital-box</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Document Requirements</p>
                  <h3 class="text-h5">{{ statistics.total_documents || 0 }}</h3>
                </div>
                <v-icon size="40" color="success">mdi-file-document-multiple</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Navigation Cards -->
      <v-row class="mt-4">
        <v-col cols="12">
          <h2 class="text-h6 mb-4">PAS Management Tools</h2>
        </v-col>

        <v-col cols="12" md="6" lg="4" v-for="card in navigationCards" :key="card.route">
          <v-card
            class="navigation-card"
            hover
            @click="navigateTo(card.route)"
            :disabled="card.disabled"
          >
            <v-card-text class="pa-6">
              <div class="d-flex align-start">
                <v-avatar :color="card.color" size="56" class="mr-4">
                  <v-icon size="32" color="white">{{ card.icon }}</v-icon>
                </v-avatar>
                <div class="flex-grow-1">
                  <h3 class="text-h6 mb-2">{{ card.title }}</h3>
                  <p class="text-body-2 text-grey">{{ card.description }}</p>
                  <v-chip
                    v-if="card.badge"
                    :color="card.badgeColor"
                    size="small"
                    class="mt-2"
                  >
                    {{ card.badge }}
                  </v-chip>
                </div>
                <v-icon color="grey-lighten-1">mdi-chevron-right</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/js/composables/useToast';
import api from '@/js/utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const router = useRouter();
const { error: showError } = useToast();

const statistics = ref({
  total_pa_requests: 0,
  pending_approvals: 0,
  active_referrals: 0,
  total_documents: 0,
});

const navigationCards = ref([
   {
    title: 'Submit Referral to PAS',
    description: 'Submit referrals on behalf of primary facilities',
    icon: 'mdi-hospital-box-outline',
    color: 'primary',
    route: '/pas/referrals',
    // badge: 'Admin Only',
    badgeColor: 'primary',
    roles: ['admin', 'Super Admin', 'claims_officer'],
  },
  {
    title: 'Referral Management',
    description: 'View, approve, reject, and print referrals',
    icon: 'mdi-file-document-check',
    color: 'info',
    route: '/pas/referral-management',
  },
  {
    title: 'Request FU-PA Code',
    description: 'Request Follow-Up PA Code for FFS services not in bundle',
    icon: 'mdi-file-plus',
    color: 'success',
    route: '/pas/fu-pa-request',
  },
  {
    title: 'FU-PA Code Approval',
    description: 'Approve or reject FU-PA Code requests from facilities',
    icon: 'mdi-check-decagram',
    color: 'warning',
    route: '/pas/fu-pa-approval',
  },
  {
    title: 'Document Requirements',
    description: 'Manage document requirements for referrals and PA codes',
    icon: 'mdi-file-document-multiple',
    color: 'primary',
    route: '/document-requirements',
  },
]);

onMounted(async () => {
  await loadStatistics();
});

const loadStatistics = async () => {
  try {
    const response = await api.get('/api/pas/statistics');
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

const navigateTo = (route) => {
  if (!route) return;
  router.push(route);
};
</script>

<style scoped>
.stat-card {
  transition: transform 0.2s;
}

.stat-card:hover {
  transform: translateY(-4px);
}

.navigation-card {
  cursor: pointer;
  transition: all 0.3s;
  border: 1px solid rgba(0, 0, 0, 0.12);
}

.navigation-card:hover:not([disabled]) {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.navigation-card[disabled] {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>


