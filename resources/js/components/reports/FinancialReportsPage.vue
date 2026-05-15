<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Financial Reports" subtitle="Revenue, claims costs, and financial performance" icon="mdi-currency-usd" icon-color="green">
        <v-text-field
          v-model="filters.year"
          label="Year"
          type="number"
          min="2020"
          :max="currentYear"
          variant="outlined"
          density="compact"
          hide-details
          style="width: 120px"
          @update:model-value="fetchData"
        />
        <v-btn variant="outlined" prepend-icon="mdi-download" @click="exportReport">Export PDF</v-btn>
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="fetchData">Refresh</v-btn>
      </AppPageHeader>

      <!-- Financial KPIs -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <AppStatCard
          label="Total Premium Collected"
          :value="financials.total_premium"
          icon="mdi-cash-multiple"
          color="green"
          :loading="loading"
          :currency="true"
        />
        <AppStatCard
          label="Total Claims Paid"
          :value="financials.total_claims_paid"
          icon="mdi-cash-check"
          color="blue"
          :loading="loading"
          :currency="true"
        />
        <AppStatCard
          label="Claims Loss Ratio"
          :value="financials.claims_ratio + '%'"
          icon="mdi-chart-donut"
          color="orange"
          :loading="loading"
        />
        <AppStatCard
          label="Net Balance"
          :value="financials.net_balance"
          icon="mdi-bank"
          color="purple"
          :loading="loading"
          :currency="true"
        />
      </div>

      <!-- Monthly Breakdown Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2 tw-flex tw-items-center tw-gap-2">
          <v-icon color="primary" size="20">mdi-table</v-icon>
          <span class="tw-text-base tw-font-semibold">Monthly Breakdown – {{ filters.year }}</span>
        </v-card-title>
        <v-divider />
        <v-data-table
          :headers="headers"
          :items="monthlyData"
          :loading="loading"
          hide-default-footer
          :items-per-page="12"
          class="tw-rounded-xl"
        >
          <template #item.premium="{ item }">
            {{ formatCurrency(item.premium) }}
          </template>
          <template #item.claims_paid="{ item }">
            {{ formatCurrency(item.claims_paid) }}
          </template>
          <template #item.claims_ratio="{ item }">
            <v-chip
              :color="item.claims_ratio > 80 ? 'error' : item.claims_ratio > 60 ? 'warning' : 'success'"
              size="small"
              variant="flat"
            >{{ item.claims_ratio }}%</v-chip>
          </template>
          <template #item.net="{ item }">
            <span :class="item.net >= 0 ? 'tw-text-green-600' : 'tw-text-red-600'" class="tw-font-semibold">
              {{ formatCurrency(item.net) }}
            </span>
          </template>

          <template #no-data>
            <AppEmptyState icon="mdi-chart-bar" title="No financial data" description="Financial report data will appear here once available." />
          </template>

          <template #body.append>
            <tr v-if="monthlyData.length > 0" class="tw-bg-gray-50 tw-font-bold">
              <td class="tw-px-4 tw-py-3 tw-text-sm">Total</td>
              <td class="tw-px-4 tw-py-3 tw-text-sm">{{ formatCurrency(totals.premium) }}</td>
              <td class="tw-px-4 tw-py-3 tw-text-sm">{{ formatCurrency(totals.claims_paid) }}</td>
              <td class="tw-px-4 tw-py-3 tw-text-sm">{{ totals.avg_ratio }}%</td>
              <td :class="['tw-px-4 tw-py-3 tw-text-sm', totals.net >= 0 ? 'tw-text-green-600' : 'tw-text-red-600']">
                {{ formatCurrency(totals.net) }}
              </td>
            </tr>
          </template>
        </v-data-table>
      </v-card>

      <!-- Revenue vs Claims Chart -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2">
          <span class="tw-text-base tw-font-semibold">Revenue vs Claims – {{ filters.year }}</span>
        </v-card-title>
        <v-divider />
        <v-card-text class="tw-p-4">
          <div v-if="loading" class="tw-h-72 tw-flex tw-items-center tw-justify-center">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <BarChart v-else :data="revenueVsClaimsData" :height="288" />
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
import BarChart from '../charts/BarChart.vue';
import { dashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();
const loading = ref(false);
const currentYear = new Date().getFullYear();
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const filters = ref({ year: currentYear });
const financials = ref({ total_premium: 0, total_claims_paid: 0, claims_ratio: 0, net_balance: 0 });
const monthlyData = ref([]);

const headers = [
  { title: 'Month', key: 'month', width: 100 },
  { title: 'Premium Collected', key: 'premium', minWidth: 160 },
  { title: 'Claims Paid', key: 'claims_paid', minWidth: 160 },
  { title: 'Claims Ratio', key: 'claims_ratio', width: 130 },
  { title: 'Net Balance', key: 'net', minWidth: 140 },
];

const totals = computed(() => {
  const prem = monthlyData.value.reduce((s, r) => s + (r.premium || 0), 0);
  const claims = monthlyData.value.reduce((s, r) => s + (r.claims_paid || 0), 0);
  const avgRatio = prem > 0 ? Math.round((claims / prem) * 100) : 0;
  return { premium: prem, claims_paid: claims, avg_ratio: avgRatio, net: prem - claims };
});

const revenueVsClaimsData = computed(() => ({
  labels: MONTHS,
  datasets: [
    { label: 'Premium Collected', data: monthlyData.value.map(r => r.premium ?? 0), backgroundColor: 'rgba(34,197,94,0.7)' },
    { label: 'Claims Paid', data: monthlyData.value.map(r => r.claims_paid ?? 0), backgroundColor: 'rgba(239,68,68,0.7)' },
  ],
}));

async function fetchData() {
  loading.value = true;
  try {
    const res = await dashboardAPI.getOverview();
    const d = res.data?.data ?? res.data;
    financials.value = {
      total_premium: d?.total_premium ?? 0,
      total_claims_paid: d?.total_claims_paid ?? 0,
      claims_ratio: d?.claims_ratio ?? 0,
      net_balance: d?.net_balance ?? ((d?.total_premium ?? 0) - (d?.total_claims_paid ?? 0)),
    };
    monthlyData.value = (d?.monthly_breakdown ?? MONTHS.map((month, i) => ({
      month,
      premium: d?.monthly_premium?.[i] ?? 0,
      claims_paid: d?.monthly_claims?.[i] ?? 0,
      claims_ratio: 0,
      net: 0,
    }))).map(r => ({ ...r, claims_ratio: r.premium > 0 ? Math.round((r.claims_paid / r.premium) * 100) : 0, net: (r.premium || 0) - (r.claims_paid || 0) }));
  } catch {
    showToast('Failed to load financial data', 'error');
  } finally {
    loading.value = false;
  }
}

function exportReport() {
  showToast('Export functionality coming soon', 'info');
}

function formatCurrency(v) {
  return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(v || 0);
}

onMounted(fetchData);
</script>
