<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Reports" subtitle="Generate and download system reports" icon="mdi-file-chart" icon-color="primary">
        <v-btn variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="fetchData">Refresh</v-btn>
      </AppPageHeader>

      <!-- Summary Stats -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4">
        <AppStatCard label="Total Enrollees" :value="stats.total_enrollees" icon="mdi-account-group" color="blue" :loading="loading" />
        <AppStatCard label="Active Facilities" :value="stats.active_facilities" icon="mdi-hospital-building" color="green" :loading="loading" />
        <AppStatCard label="Total Referrals" :value="stats.total_referrals" icon="mdi-file-send" color="orange" :loading="loading" />
        <AppStatCard label="Paid Claims" :value="stats.paid_claims" icon="mdi-cash-check" color="teal" :loading="loading" />
      </div>

      <!-- Report Categories -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-3 tw-gap-4">
        <v-card
          v-for="report in reportCategories"
          :key="report.key"
          class="tw-border tw-border-gray-100 tw-rounded-xl tw-shadow-sm hover:tw-shadow-md tw-transition-all tw-duration-200 tw-cursor-pointer"
          elevation="0"
          @click="openReport(report)"
        >
          <v-card-text class="tw-p-6">
            <div class="tw-flex tw-items-start tw-gap-4">
              <div :class="`tw-p-3 tw-rounded-xl tw-bg-${report.color}-50 tw-shrink-0`">
                <v-icon :color="report.color" size="28">{{ report.icon }}</v-icon>
              </div>
              <div class="tw-flex-1 tw-min-w-0">
                <h3 class="tw-font-semibold tw-text-gray-900 tw-text-base">{{ report.title }}</h3>
                <p class="tw-text-sm tw-text-gray-500 tw-mt-1">{{ report.description }}</p>
                <div class="tw-flex tw-flex-wrap tw-gap-1 tw-mt-3">
                  <v-chip
                    v-for="fmt in report.formats"
                    :key="fmt"
                    size="x-small"
                    variant="outlined"
                    color="primary"
                  >{{ fmt }}</v-chip>
                </div>
              </div>
              <v-icon color="grey-lighten-1" size="20">mdi-chevron-right</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </div>

      <!-- Recent Activity -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-card-title class="tw-px-6 tw-pt-5 tw-pb-2 tw-flex tw-items-center tw-gap-2">
          <v-icon size="20" color="primary">mdi-history</v-icon>
          <span class="tw-text-base tw-font-semibold">Recent System Activity</span>
        </v-card-title>
        <v-divider />
        <v-card-text class="tw-p-0">
          <div v-if="loading" class="tw-py-12 tw-flex tw-justify-center">
            <v-progress-circular indeterminate color="primary" />
          </div>
          <div v-else-if="activities.length === 0">
            <AppEmptyState icon="mdi-history" title="No recent activity" description="No activity records available." />
          </div>
          <v-list v-else density="compact" lines="two">
            <v-list-item
              v-for="act in activities.slice(0, 10)"
              :key="act.id"
              class="tw-border-b tw-border-gray-50 last:tw-border-b-0"
            >
              <template #prepend>
                <v-avatar :color="act.color || 'primary'" size="36">
                  <v-icon size="18" color="white">{{ act.icon || 'mdi-circle-small' }}</v-icon>
                </v-avatar>
              </template>
              <v-list-item-title class="tw-text-sm tw-font-medium">{{ act.title || act.description }}</v-list-item-title>
              <v-list-item-subtitle class="tw-text-xs">{{ act.subtitle || act.time }}</v-list-item-subtitle>
              <template #append>
                <span class="tw-text-xs tw-text-gray-400">{{ formatRelative(act.created_at || act.time) }}</span>
              </template>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>

      <!-- Report Generate Dialog -->
      <AppModal v-model="reportDialog" :title="selectedReport?.title ?? 'Generate Report'" size="sm">
        <div class="tw-space-y-4">
          <p class="tw-text-sm tw-text-gray-600">{{ selectedReport?.description }}</p>
          <v-text-field label="Start Date" type="date" variant="outlined" density="compact" v-model="reportForm.date_from" />
          <v-text-field label="End Date" type="date" variant="outlined" density="compact" v-model="reportForm.date_to" />
          <v-select
            label="Format"
            :items="selectedReport?.formats ?? []"
            v-model="reportForm.format"
            variant="outlined"
            density="compact"
          />
        </div>
        <template #actions>
          <v-btn variant="outlined" @click="reportDialog = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" prepend-icon="mdi-download" :loading="generating" @click="generateReport">Generate</v-btn>
        </template>
      </AppModal>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppStatCard from '../common/AppStatCard.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import AppModal from '../common/AppModal.vue';
import { dashboardAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();

const loading = ref(false);
const generating = ref(false);
const reportDialog = ref(false);
const selectedReport = ref(null);
const activities = ref([]);
const stats = ref({ total_enrollees: 0, active_facilities: 0, total_referrals: 0, paid_claims: 0 });
const reportForm = ref({ date_from: '', date_to: '', format: 'PDF' });

const reportCategories = [
  { key: 'enrollees', title: 'Enrollee Report', description: 'List of all enrolled beneficiaries, status, and coverage details.', icon: 'mdi-account-group', color: 'blue', formats: ['PDF', 'Excel', 'CSV'] },
  { key: 'facilities', title: 'Facility Report', description: 'Healthcare facility listings, accreditation, and performance data.', icon: 'mdi-hospital-building', color: 'green', formats: ['PDF', 'Excel'] },
  { key: 'referrals', title: 'Referrals Report', description: 'Pre-authorization referral activity, approvals, denials, and UTN validations.', icon: 'mdi-file-send', color: 'orange', formats: ['PDF', 'Excel', 'CSV'] },
  { key: 'claims', title: 'Claims Report', description: 'Claims submitted, reviewed, approved, and paid by facility or period.', icon: 'mdi-cash-multiple', color: 'teal', formats: ['PDF', 'Excel'] },
  { key: 'payments', title: 'Payment Report', description: 'Payment batches processed, amounts disbursed, and outstanding balances.', icon: 'mdi-bank-transfer', color: 'indigo', formats: ['PDF', 'Excel'] },
  { key: 'premium', title: 'Premium & Coverage Report', description: 'PIN sales, coverage activations, and premium collection summaries.', icon: 'mdi-shield-account', color: 'purple', formats: ['PDF', 'Excel', 'CSV'] },
  { key: 'financial', title: 'Financial Summary', description: 'High-level financial performance including revenue, claims ratio, and capitation.', icon: 'mdi-chart-bar', color: 'red', formats: ['PDF', 'Excel'] },
  { key: 'audit', title: 'Audit Trail Report', description: 'System activity log for compliance and governance purposes.', icon: 'mdi-clipboard-text-clock', color: 'grey', formats: ['PDF', 'CSV'] },
  { key: 'capitation', title: 'Capitation Report', description: 'Monthly capitation calculations, disbursements, and facility summaries.', icon: 'mdi-receipt', color: 'amber', formats: ['PDF', 'Excel'] },
];

async function fetchData() {
  loading.value = true;
  try {
    const [overviewRes, activityRes] = await Promise.allSettled([
      dashboardAPI.getOverview(),
      dashboardAPI.getRecentActivities(),
    ]);
    if (overviewRes.status === 'fulfilled') {
      const d = overviewRes.value.data?.data ?? overviewRes.value.data;
      stats.value = {
        total_enrollees: d?.total_enrollees ?? d?.enrollees?.total ?? 0,
        active_facilities: d?.active_facilities ?? d?.facilities?.active ?? 0,
        total_referrals: d?.total_referrals ?? d?.referrals?.total ?? 0,
        paid_claims: d?.paid_claims ?? d?.claims?.paid ?? 0,
      };
    }
    if (activityRes.status === 'fulfilled') {
      const d = activityRes.value.data?.data ?? activityRes.value.data;
      activities.value = Array.isArray(d) ? d : (d?.activities ?? d?.data ?? []);
    }
  } catch {
    // non-critical
  } finally {
    loading.value = false;
  }
}

function openReport(report) {
  selectedReport.value = report;
  reportForm.value = { date_from: '', date_to: '', format: report.formats[0] };
  reportDialog.value = true;
}

async function generateReport() {
  generating.value = true;
  try {
    showToast(`${selectedReport.value.title} generation coming soon`, 'info');
    reportDialog.value = false;
  } finally {
    generating.value = false;
  }
}

function formatRelative(d) {
  if (!d) return '';
  const diff = Date.now() - new Date(d).getTime();
  const m = Math.floor(diff / 60000);
  if (m < 1) return 'just now';
  if (m < 60) return `${m}m ago`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h}h ago`;
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short' });
}

onMounted(fetchData);
</script>
