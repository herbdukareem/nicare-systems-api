<template>
  <AdminLayout>
    <div class="management-dashboard">
      <v-container fluid>
      <!-- Header -->
      <v-row>
        <v-col cols="12">
          <div class="mb-6">
            <h1 class="text-h4 font-weight-bold">Management Module</h1>
            <p class="text-subtitle-1 text-grey">Manage tariff items, bundles, and system configurations</p>
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
                  <p class="text-caption text-grey mb-1">Total Drugs</p>
                  <h3 class="text-h5">{{ statistics.total_drugs || 0 }}</h3>
                </div>
                <v-icon size="40" color="green">mdi-pill</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Laboratory Tests</p>
                  <h3 class="text-h5">{{ statistics.total_labs || 0 }}</h3>
                </div>
                <v-icon size="40" color="blue">mdi-test-tube</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Service Bundles</p>
                  <h3 class="text-h5">{{ statistics.total_bundles || 0 }}</h3>
                </div>
                <v-icon size="40" color="purple">mdi-package-variant</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card class="stat-card">
            <v-card-text>
              <div class="d-flex justify-space-between align-center">
                <div>
                  <p class="text-caption text-grey mb-1">Bundle Components</p>
                  <h3 class="text-h5">{{ statistics.total_components || 0 }}</h3>
                </div>
                <v-icon size="40" color="orange">mdi-puzzle</v-icon>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Navigation Cards -->
      <v-row class="mt-4">
        <v-col cols="12">
          <h2 class="text-h6 mb-4">Management Tools</h2>
        </v-col>

        <v-col cols="12" md="6" lg="3" v-for="card in navigationCards" :key="card.route">
          <v-card
            class="navigation-card"
            hover
            @click="navigateTo(card.route)"
            height="100%"
          >
            <v-card-text class="pa-6 d-flex flex-column" style="height: 100%;">
              <div class="text-center mb-4">
                <v-avatar :color="card.color" size="64">
                  <v-icon size="36" color="white">{{ card.icon }}</v-icon>
                </v-avatar>
              </div>
              <div class="text-center flex-grow-1">
                <h3 class="text-h6 mb-2">{{ card.title }}</h3>
                <p class="text-body-2 text-grey">{{ card.description }}</p>
              </div>
              <div class="text-center mt-4">
                <v-btn :color="card.color" variant="text" size="small">
                  Manage
                  <v-icon end>mdi-arrow-right</v-icon>
                </v-btn>
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
import { useToast } from '../../composables/useToast';
import api from '../../utils/api';
import AdminLayout from '../layout/AdminLayout.vue';

const router = useRouter();
const { showError } = useToast();

const statistics = ref({
  total_drugs: 0,
  total_labs: 0,
  total_bundles: 0,
  total_components: 0,
});

const navigationCards = ref([
  {
    title: 'Case Management',
    description: 'Manage case records, service tariffs, and bundles',
    icon: 'mdi-file-document-multiple-outline',
    color: 'indigo',
    route: '/management/cases',
  },
    {
    title: 'Bundle Components',
    description: 'Manage bundle components and configurations',
    icon: 'mdi-package-variant',
    color: 'purple',
    route: '/management/bundle-components',
  },
]);

onMounted(async () => {
  await loadStatistics();
});

const loadStatistics = async () => {
  try {
    const [drugsRes, labsRes, bundlesRes, componentsRes] = await Promise.allSettled([
      api.get('/api/drugs/statistics'),
      api.get('/api/cases/statistics', { params: { group: 'LABS' } }),
      api.get('/api/service-bundles/statistics'),
      api.get('/api/bundle-components/statistics'),
    ]);

    if (drugsRes.status === 'fulfilled') {
      statistics.value.total_drugs = drugsRes.value.data.data?.total || drugsRes.value.data.total || 0;
    }
    if (labsRes.status === 'fulfilled') {
      statistics.value.total_labs = labsRes.value.data.data?.total || labsRes.value.data.total || 0;
    }
    if (bundlesRes.status === 'fulfilled') {
      statistics.value.total_bundles = bundlesRes.value.data.data?.total || bundlesRes.value.data.total || 0;
    }
    if (componentsRes.status === 'fulfilled') {
      statistics.value.total_components = componentsRes.value.data.data?.total || componentsRes.value.data.total || 0;
    }
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
</style>


