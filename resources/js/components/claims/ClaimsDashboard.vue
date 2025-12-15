<template>
  <AdminLayout>
    <div class="claims-dashboard">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="mb-6">
            <h1 class="text-h4 font-weight-bold">Claims Module</h1>
            <p class="text-subtitle-1 text-grey">Manage referrals, claims submission, and review</p>
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
                  <p class="text-caption text-grey mb-1">Total Claims</p>
                  <h3 class="text-h5">{{ statistics.total_claims || 0 }}</h3>
                </div>
                <v-icon size="40" color="primary">mdi-file-document-multiple</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Pending Review</p>
                  <h3 class="text-h5">{{ statistics.pending_claims || 0 }}</h3>
                </div>
                <v-icon size="40" color="warning">mdi-clock-outline</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Approved Claims</p>
                  <h3 class="text-h5">{{ statistics.approved_claims || 0 }}</h3>
                </div>
                <v-icon size="40" color="success">mdi-check-circle</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Total Referrals</p>
                  <h3 class="text-h5">{{ statistics.total_referrals || 0 }}</h3>
                </div>
                <v-icon size="40" color="info">mdi-hospital-box</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Navigation Cards -->
      <v-row class="mt-4">
        <v-col cols="12">
          <h2 class="text-h6 mb-4">Quick Actions</h2>
        </v-col>

        <v-col cols="12" md="6" lg="4" v-for="card in filteredNavigationCards" :key="card.route">
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
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from '@/js/composables/useToast';
import { useAuthStore } from '@/js/stores/auth';
import api from '@/js/utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const router = useRouter();
const authStore = useAuthStore();
const { error: showError } = useToast();

const statistics = ref({
  total_claims: 0,
  pending_claims: 0,
  approved_claims: 0,
  total_referrals: 0,
});

const navigationCards = ref([
  {
    title: 'Submit Referral to PAS',
    description: 'Submit referrals on behalf of primary facilities to Pre-Authorization System',
    icon: 'mdi-hospital-box-outline',
    color: 'primary',
    route: '/claims/referrals',
    badge: 'Admin Only',
    badgeColor: 'primary',
    roles: ['admin', 'Super Admin', 'claims_officer'],
  },
  {
    title: 'Review Claims',
    description: 'Review  submitted claims',
    icon: 'mdi-file-check',
    color: 'warning',
    route: '/claims/review',
    roles: ['admin', 'Super Admin', 'claim_reviewer', 'claim_confirmer', 'claim_approver'],
  },
   {
    title: 'Claims Approval',
    description: 'Approve submitted claims',
    icon: 'mdi-file-check',
    color: 'warning',
    route: '/claims/approval',
    roles: ['admin', 'Super Admin', 'claim_reviewer', 'claim_confirmer', 'claim_approver'],
  },
  {
    title: 'Payment Batches',
    description: 'Authorize and process payment batches',
    icon: 'mdi-cash-multiple',
    color: 'success',
    route: '/claims/payment-batches',
    roles: ['admin', 'Super Admin', 'claims_officer', 'claim_approver'],
  },
  {
    title: 'Claims History',
    description: 'View claims history and reports',
    icon: 'mdi-history',
    color: 'purple',
    route: '/claims/history',
  },
  {
    title: 'Admission Management',
    description: 'Manage patient admissions for episode-of-care tracking',
    icon: 'mdi-bed-empty',
    color: 'teal',
    route: '/claims/automation/admissions',
  },
  {
    title: 'Claims Processing',
    description: 'Process claims with bundle classification and FFS top-ups',
    icon: 'mdi-cog-outline',
    color: 'orange',
    route: '/claims/automation/process',
  },
]);

// Filter navigation cards based on user roles
const filteredNavigationCards = computed(() => {
  const userRoles = authStore.userRoles.map(role => role.name);

  return navigationCards.value.filter(card => {
    // If card has no role restrictions, show it to everyone
    if (!card.roles || card.roles.length === 0) {
      return true;
    }

    // Check if user has any of the required roles
    return card.roles.some(role => userRoles.includes(role));
  });
});

onMounted(async () => {
  await loadStatistics();
});

const loadStatistics = async () => {
  try {
    const response = await api.get('/api/claims/statistics');
    statistics.value = response.data.data || response.data;
  } catch (err) {
    console.error('Failed to load statistics', err);
  }
};

const navigateTo = (route) => {
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

.navigation-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.navigation-card:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>


