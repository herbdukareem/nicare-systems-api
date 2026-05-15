<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Analytics" subtitle="System insights, trends, and performance metrics" icon="mdi-chart-line" icon-color="primary">
        <v-select
          v-model="period"
          :items="periodOptions"
          variant="outlined"
          density="compact"
          hide-details
          style="width: 160px"
          @update:model-value="fetchData"
        />
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="fetchData">Refresh</v-btn>
      </AppPageHeader>

      <!-- KPI Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <AppStatCard label="New Enrollees" :value="kpis.new_enrollees" icon="mdi-account-plus" color="blue" :loading="loading" :change="kpis.enrollees_change" />
        <AppStatCard label="Referrals Approved" :value="kpis.referrals_approved" icon="mdi-file-check" color="green" :loading="loading" :change="kpis.referrals_change" />
        <AppStatCard label="Claims Value" :value="kpis.claims_value" icon="mdi-cash-multiple" color="orange" :loading="loading" :currency="true" />
        <AppStatCard label="Avg. Processing Time" :value="kpis.avg_processing_days + ' days'" icon="mdi-timer" color="purple" :loading="loading" />
      </div>

      <!-- Charts Row 1 -->
      <div class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-2 tw-gap-4">
        <!-- Enrollee Trend -->
        <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2 tw-flex tw-items-center tw-justify-between">
            <span class="tw-text-base tw-font-semibold">Enrollee Trend</span>
            <v-chip size="x-small" color="primary" variant="outlined">{{ period }}</v-chip>
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-p-4">
            <div v-if="loading" class="tw-h-64 tw-flex tw-items-center tw-justify-center">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <LineChart v-else :data="enrolleeChartData" :height="256" />
          </v-card-text>
        </v-card>

        <!-- Claims Trend -->
        <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2 tw-flex tw-items-center tw-justify-between">
            <span class="tw-text-base tw-font-semibold">Claims Activity</span>
            <v-chip size="x-small" color="orange" variant="outlined">{{ period }}</v-chip>
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-p-4">
            <div v-if="loading" class="tw-h-64 tw-flex tw-items-center tw-justify-center">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <BarChart v-else :data="claimsChartData" :height="256" />
          </v-card-text>
        </v-card>
      </div>

      <!-- Charts Row 2 -->
      <div class="tw-grid tw-grid-cols-1 xl:tw-grid-cols-3 tw-gap-4">
        <!-- Facility Distribution -->
        <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2">
            <span class="tw-text-base tw-font-semibold">Facilities by Type</span>
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-p-4 tw-flex tw-justify-center">
            <div v-if="loading" class="tw-h-56 tw-flex tw-items-center tw-justify-center tw-w-full">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <DoughnutChart v-else :data="facilityTypeData" :height="224" />
          </v-card-text>
        </v-card>

        <!-- Top Facilities by Referrals -->
        <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl xl:tw-col-span-2" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2">
            <span class="tw-text-base tw-font-semibold">Top Facilities by Referrals</span>
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-p-4">
            <div v-if="loading" class="tw-h-56 tw-flex tw-items-center tw-justify-center">
              <v-progress-circular indeterminate color="primary" />
            </div>
            <div v-else-if="topFacilities.length === 0">
              <AppEmptyState icon="mdi-hospital" title="No data available" description="Facility performance data will appear here." :icon-size="48" />
            </div>
            <div v-else class="tw-space-y-3">
              <div
                v-for="(fac, i) in topFacilities"
                :key="fac.name"
                class="tw-flex tw-items-center tw-gap-3"
              >
                <div class="tw-w-5 tw-text-xs tw-font-bold tw-text-gray-400 tw-text-right">{{ i + 1 }}</div>
                <div class="tw-flex-1 tw-min-w-0">
                  <div class="tw-flex tw-justify-between tw-mb-1">
                    <span class="tw-text-sm tw-font-medium tw-truncate">{{ fac.name }}</span>
                    <span class="tw-text-sm tw-font-bold tw-text-primary tw-ml-2 tw-shrink-0">{{ fac.count }}</span>
                  </div>
                  <v-progress-linear
                    :model-value="(fac.count / (topFacilities[0]?.count || 1)) * 100"
                    height="6"
                    rounded
                    color="primary"
                  />
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Referral Status Distribution -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2">
          <span class="tw-text-base tw-font-semibold">Referral Status Distribution</span>
        </v-card-title>
        <v-divider />
        <v-card-text class="tw-p-4">
          <div v-if="loading" class="tw-h-64 tw-flex tw-items-center tw-justify-center">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <BarChart v-else :data="referralStatusData" :height="256" />
        </v-card-text>
      </v-card>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppStatCard from '../common/AppStatCard.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import LineChart from '../charts/LineChart.vue';
import BarChart from '../charts/BarChart.vue';
import DoughnutChart from '../charts/DoughnutChart.vue';
import { dashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();
const loading = ref(false);
const period = ref('Last 6 Months');
const periodOptions = ['Last 30 Days', 'Last 3 Months', 'Last 6 Months', 'This Year', 'Last Year'];
const rawData = ref(null);

const kpis = ref({ new_enrollees: 0, referrals_approved: 0, claims_value: 0, avg_processing_days: 0, enrollees_change: null, referrals_change: null });
const topFacilities = ref([]);

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const enrolleeChartData = computed(() => {
  const d = rawData.value;
  const labels = d?.enrollee_chart?.labels ?? MONTHS.slice(0, 6);
  return {
    labels,
    datasets: [{
      label: 'New Enrollees',
      data: d?.enrollee_chart?.data ?? Array(labels.length).fill(0),
      borderColor: '#3B82F6',
      backgroundColor: 'rgba(59,130,246,0.1)',
      fill: true,
    }],
  };
});

const claimsChartData = computed(() => {
  const d = rawData.value;
  const labels = d?.claims_chart?.labels ?? MONTHS.slice(0, 6);
  return {
    labels,
    datasets: [
      { label: 'Submitted', data: d?.claims_chart?.submitted ?? Array(labels.length).fill(0), backgroundColor: 'rgba(251,146,60,0.7)' },
      { label: 'Approved', data: d?.claims_chart?.approved ?? Array(labels.length).fill(0), backgroundColor: 'rgba(34,197,94,0.7)' },
      { label: 'Paid', data: d?.claims_chart?.paid ?? Array(labels.length).fill(0), backgroundColor: 'rgba(59,130,246,0.7)' },
    ],
  };
});

const facilityTypeData = computed(() => {
  const d = rawData.value?.facility_distribution;
  return {
    labels: d?.labels ?? ['Primary', 'Secondary', 'Tertiary'],
    datasets: [{
      data: d?.data ?? [60, 30, 10],
      backgroundColor: ['#3B82F6', '#10B981', '#F59E0B'],
    }],
  };
});

const referralStatusData = computed(() => {
  const d = rawData.value;
  const labels = d?.referral_chart?.labels ?? MONTHS.slice(0, 6);
  return {
    labels,
    datasets: [
      { label: 'Approved', data: d?.referral_chart?.approved ?? Array(labels.length).fill(0), backgroundColor: 'rgba(34,197,94,0.7)' },
      { label: 'Pending', data: d?.referral_chart?.pending ?? Array(labels.length).fill(0), backgroundColor: 'rgba(251,191,36,0.7)' },
      { label: 'Denied', data: d?.referral_chart?.denied ?? Array(labels.length).fill(0), backgroundColor: 'rgba(239,68,68,0.7)' },
    ],
  };
});

async function fetchData() {
  loading.value = true;
  try {
    const [overviewRes, chartRes] = await Promise.allSettled([
      dashboardAPI.getOverview(),
      dashboardAPI.getChartData(),
    ]);

    if (overviewRes.status === 'fulfilled') {
      const d = overviewRes.value.data?.data ?? overviewRes.value.data;
      kpis.value = {
        new_enrollees: d?.new_enrollees ?? d?.enrollees?.new ?? d?.total_enrollees ?? 0,
        referrals_approved: d?.approved_referrals ?? d?.referrals?.approved ?? 0,
        claims_value: d?.claims_value ?? d?.claims?.total_value ?? 0,
        avg_processing_days: d?.avg_processing_days ?? 3,
        enrollees_change: d?.enrollees_change ?? null,
        referrals_change: d?.referrals_change ?? null,
      };
      topFacilities.value = d?.top_facilities ?? [];
    }

    if (chartRes.status === 'fulfilled') {
      rawData.value = chartRes.value.data?.data ?? chartRes.value.data;
    }
  } catch {
    showToast('Failed to load analytics data', 'error');
  } finally {
    loading.value = false;
  }
}

onMounted(fetchData);
</script>
