<template>
  <EnrolleeLayout>
    <AppPageHeader
      class="tw-mb-6"
      :title="`Welcome, ${firstName}`"
      subtitle="Here is your health insurance overview"
      kicker="Enrollee portal"
      icon="mdi-view-dashboard-outline"
    />

    <div v-if="loading" class="tw-grid tw-gap-4 md:tw-grid-cols-2">
      <AppSkeleton v-for="index in 4" :key="index" type="article" />
    </div>

    <template v-else-if="enrollee">
      <!-- Status banner -->
      <AppAlert
        :tone="coverageAlertTone"
        :title="coverageStatusLabel"
        class="tw-mb-6"
        :icon="coverageAlertIcon"
      >
        <span v-if="enrollee.coverage_end_date" class="tw-ml-2 tw-text-sm">
          — expires {{ formatDate(enrollee.coverage_end_date) }}
        </span>
      </AppAlert>

      <!-- Stat cards -->
      <div class="tw-grid tw-grid-cols-1 sm:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-4 tw-mb-6">
        <AppMetricCard v-for="stat in statCards" :key="stat.label" class="ep-metric-card" :title="stat.label" :helper="stat.helper" :icon="stat.icon" :tone="stat.tone" hover>
          <template #value>
            <div class="ep-metric-card__value" :class="{ 'ep-metric-card__value--id': stat.isId }" :title="stat.value">
              {{ stat.value }}
            </div>
          </template>
          <template #aside>
            <EnrolleeStatusBadge v-if="stat.isStatus" :status="enrollee.status" :label="stat.value" size="sm" />
            <v-btn v-else-if="stat.action" icon size="small" variant="tonal" :color="stat.tone" :aria-label="stat.actionLabel" @click="stat.action">
              <v-icon size="17">{{ stat.actionIcon }}</v-icon>
              <v-tooltip activator="parent">{{ stat.actionLabel }}</v-tooltip>
            </v-btn>
          </template>
        </AppMetricCard>
      </div>

      <!-- Two column layout -->
      <div class="tw-grid md:tw-grid-cols-2 tw-gap-6">
        <!-- Personal Details -->
        <AppCard title="Personal Details" icon="mdi-account-details" full-height>
          <div>
            <div class="ep-field" v-for="f in personalFields" :key="f.label">
              <span class="ep-field__label">{{ f.label }}</span>
              <span class="ep-field__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>

        <!-- Coverage Info -->
        <AppCard title="Coverage & Plan" icon="mdi-shield-check" full-height>
          <template #actions>
            <v-btn color="primary" variant="tonal" size="small" @click="$router.push('/enroll/plans')">
              <v-icon start size="16">mdi-refresh</v-icon> Renew Plan
            </v-btn>
          </template>
          <div>
            <div class="ep-field" v-for="f in coverageFields" :key="f.label">
              <span class="ep-field__label">{{ f.label }}</span>
              <span class="ep-field__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>

        <!-- Quick Actions -->
        <AppCard class="md:tw-col-span-2" title="Quick Actions" icon="mdi-lightning-bolt">
          <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-3">
            <div v-for="action in quickActions" :key="action.label" class="ep-action" @click="action.onClick">
              <div class="ep-action__icon" :style="{ background: action.bg }">
                <v-icon :color="action.color" size="24">{{ action.icon }}</v-icon>
              </div>
              <span class="ep-action__label">{{ action.label }}</span>
            </div>
          </div>
        </AppCard>
      </div>
    </template>

    <AppErrorState v-else title="Could not load your profile" message="Please refresh the page or sign in again." />
  </EnrolleeLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { useOrganizationSettings } from '../../composables/useOrganizationSettings';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';
import AppAlert from '../common/AppAlert.vue';
import AppCard from '../common/AppCard.vue';
import AppErrorState from '../common/AppErrorState.vue';
import AppMetricCard from '../common/AppMetricCard.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppSkeleton from '../common/AppSkeleton.vue';
import EnrolleeStatusBadge from '../common/EnrolleeStatusBadge.vue';
import { useToast } from '../../composables/useToast';

const router = useRouter();
const enrolleeAuth = useEnrolleeAuthStore();
const { settings: org, fetchSettings } = useOrganizationSettings();
const { success } = useToast();

const loading = ref(false);
const enrollee = computed(() => enrolleeAuth.enrollee);
const firstName = computed(() => enrollee.value?.first_name || 'Enrollee');

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const statusLabel = computed(() => {
  const s = enrollee.value?.status;
  return { 0: 'Pending', 1: 'Active', 2: 'Rejected', 3: 'Suspended', 4: 'Expired' }[s] ?? 'Unknown';
});

const isActive = computed(() => enrollee.value?.status === 1);
const hasCoverage = computed(() => {
  if (!enrollee.value?.coverage_start_date) return false;
  const end = enrollee.value?.coverage_end_date;
  return !end || new Date(end) >= new Date();
});

const coverageAlertTone = computed(() => hasCoverage.value ? 'success' : (isActive.value ? 'warning' : 'danger'));
const coverageAlertIcon = computed(() => hasCoverage.value ? 'mdi-shield-check' : 'mdi-shield-alert');
const coverageStatusLabel = computed(() => {
  if (hasCoverage.value) return 'Your health coverage is active';
  if (isActive.value) return 'Your coverage has expired — please renew your plan';
  return `Account status: ${statusLabel.value}`;
});

const copyEnrolleeId = async () => {
  await navigator.clipboard.writeText(enrollee.value?.enrollee_id || '');
  success('Enrollee ID copied.');
};

const statCards = computed(() => [
  {
    icon: 'mdi-card-account-details',
    tone: 'primary',
    value: enrollee.value?.enrollee_id || '—',
    label: 'Enrollee ID',
    helper: 'Your unique NiCare reference',
    isId: true,
    action: copyEnrolleeId,
    actionIcon: 'mdi-content-copy',
    actionLabel: 'Copy enrollee ID',
  },
  {
    icon: 'mdi-hospital-building',
    tone: 'success',
    value: enrollee.value?.facility?.name || '—',
    label: 'Primary Facility',
    helper: enrollee.value?.facility?.name ? 'Assigned healthcare provider' : 'No facility assigned yet',
  },
  {
    icon: 'mdi-shield-star',
    tone: 'secondary',
    value: enrollee.value?.premium_plan?.name || enrollee.value?.benefit_package?.name || '—',
    label: 'Current Plan',
    helper: hasCoverage.value ? 'Coverage is currently active' : 'Review or renew your coverage',
    action: () => router.push('/enroll/plans'),
    actionIcon: 'mdi-arrow-right',
    actionLabel: 'View premium plans',
  },
  {
    icon: 'mdi-account-check',
    tone: isActive.value ? 'success' : 'warning',
    value: statusLabel.value,
    label: 'Account Status',
    helper: isActive.value ? 'Your account is approved' : 'Approval or review may be required',
    isStatus: true,
  },
]);

const personalFields = computed(() => [
  { label: 'Full Name',       value: enrollee.value?.full_name },
  { label: 'Date of Birth',   value: formatDate(enrollee.value?.date_of_birth) },
  { label: 'Gender',          value: enrollee.value?.sex === 1 ? 'Male' : enrollee.value?.sex === 2 ? 'Female' : null },
  { label: 'Phone',           value: enrollee.value?.phone },
  { label: 'Email',           value: enrollee.value?.email },
  { label: 'LGA',             value: enrollee.value?.lga?.name },
  { label: 'Ward',            value: enrollee.value?.ward?.name },
]);

const coverageFields = computed(() => [
  { label: 'Plan',            value: enrollee.value?.premium_plan?.name || enrollee.value?.benefit_package?.name },
  { label: 'Programme',       value: enrollee.value?.insurance_programme?.name },
  { label: 'Coverage Start',  value: formatDate(enrollee.value?.coverage_start_date) },
  { label: 'Coverage End',    value: enrollee.value?.coverage_end_date ? formatDate(enrollee.value.coverage_end_date) : 'No Expiry' },
  { label: 'Approved On',     value: formatDate(enrollee.value?.approval_date) },
  { label: 'Facility',        value: enrollee.value?.facility?.name },
]);

const quickActions = computed(() => [
  { icon: 'mdi-refresh-circle', label: 'Renew Plan',       bg: '#eff6ff', color: '#2563eb', onClick: () => router.push('/enroll/plans') },
  { icon: 'mdi-account-edit',   label: 'Edit Profile',     bg: '#f0fdf4', color: '#16a34a', onClick: () => router.push('/enroll/profile') },
  { icon: 'mdi-lock-reset',     label: 'Change Password',  bg: '#faf5ff', color: '#9333ea', onClick: () => router.push('/enroll/change-password') },
  { icon: 'mdi-phone-outline',  label: 'Contact Agency',   bg: '#fff7ed', color: '#ea580c', onClick: () => window.open(`tel:${org.value.hotline}`) },
]);

onMounted(async () => {
  fetchSettings();
  if (!enrollee.value) {
    loading.value = true;
    await enrolleeAuth.fetchMe();
    loading.value = false;
  }
});
</script>

<style scoped>
.ep-metric-card :deep(.qds-card-padding) {
  padding-top: 0.75rem;
}

.ep-metric-card :deep(.tw-items-end) {
  align-items: center;
}

.ep-metric-card__value {
  display: -webkit-box;
  overflow: hidden;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.35;
  color: #0f172a;
}

.ep-metric-card__value--id {
  display: block;
  overflow: hidden;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.84rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ep-field {
  display: flex;
  justify-content: space-between;
  padding: 9px 0;
  border-bottom: 1px solid #f1f5f9;
  gap: 12px;
}
.ep-field:last-child { border-bottom: none; }
.ep-field__label {
  font-size: 13px;
  color: #64748b;
  flex-shrink: 0;
}
.ep-field__value {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
}

.ep-action {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 16px 8px;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s;
}
.ep-action:hover { border-color: #0885ab; transform: translateY(-2px); }
.ep-action__icon {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: grid;
  place-items: center;
}
.ep-action__label {
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  text-align: center;
}
</style>
