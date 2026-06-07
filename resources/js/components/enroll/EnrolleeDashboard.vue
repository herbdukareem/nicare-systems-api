<template>
  <EnrolleeLayout>
    <!-- Page header -->
    <div class="tw-mb-6">
      <h1 class="tw-text-2xl tw-font-bold tw-text-slate-900">
        Welcome, {{ firstName }}
      </h1>
      <p class="tw-text-sm tw-text-slate-500">Here's your health insurance overview</p>
    </div>

    <div v-if="loading" class="tw-flex tw-justify-center tw-py-16">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <template v-else-if="enrollee">
      <!-- Status banner -->
      <v-alert
        :type="coverageAlertType"
        variant="tonal"
        class="tw-mb-6"
        :icon="coverageAlertIcon"
        rounded="lg"
      >
        <strong>{{ coverageStatusLabel }}</strong>
        <span v-if="enrollee.coverage_end_date" class="tw-ml-2 tw-text-sm">
          — expires {{ formatDate(enrollee.coverage_end_date) }}
        </span>
      </v-alert>

      <!-- Stat cards -->
      <div class="tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-4 tw-mb-6">
        <div v-for="stat in statCards" :key="stat.label" class="ep-stat-card">
          <div class="ep-stat-card__icon" :style="{ background: stat.iconBg }">
            <v-icon :color="stat.iconColor" size="22">{{ stat.icon }}</v-icon>
          </div>
          <div class="ep-stat-card__value" :class="stat.valueClass">{{ stat.value }}</div>
          <div class="ep-stat-card__label">{{ stat.label }}</div>
        </div>
      </div>

      <!-- Two column layout -->
      <div class="tw-grid md:tw-grid-cols-2 tw-gap-6">
        <!-- Personal Details -->
        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="20">mdi-account-details</v-icon>
            <span class="ep-card__title">Personal Details</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-field" v-for="f in personalFields" :key="f.label">
              <span class="ep-field__label">{{ f.label }}</span>
              <span class="ep-field__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </div>

        <!-- Coverage Info -->
        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="20">mdi-shield-check</v-icon>
            <span class="ep-card__title">Coverage & Plan</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-field" v-for="f in coverageFields" :key="f.label">
              <span class="ep-field__label">{{ f.label }}</span>
              <span class="ep-field__value">{{ f.value || '—' }}</span>
            </div>
          </div>
          <div class="ep-card__footer">
            <v-btn color="primary" variant="tonal" size="small" rounded @click="$router.push('/enroll/plans')">
              <v-icon start size="16">mdi-refresh</v-icon> Renew Plan
            </v-btn>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="ep-card md:tw-col-span-2">
          <div class="ep-card__head">
            <v-icon color="primary" size="20">mdi-lightning-bolt</v-icon>
            <span class="ep-card__title">Quick Actions</span>
          </div>
          <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-3 tw-p-4">
            <div v-for="action in quickActions" :key="action.label" class="ep-action" @click="action.onClick">
              <div class="ep-action__icon" :style="{ background: action.bg }">
                <v-icon :color="action.color" size="24">{{ action.icon }}</v-icon>
              </div>
              <span class="ep-action__label">{{ action.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="tw-text-center tw-py-16 tw-text-slate-500">
      <v-icon size="48" class="tw-mb-3">mdi-account-alert</v-icon>
      <p>Could not load your profile. Please refresh.</p>
    </div>
  </EnrolleeLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { useOrganizationSettings } from '../../composables/useOrganizationSettings';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';

const router = useRouter();
const enrolleeAuth = useEnrolleeAuthStore();
const { settings: org, fetchSettings } = useOrganizationSettings();

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

const coverageAlertType = computed(() => hasCoverage.value ? 'success' : (isActive.value ? 'warning' : 'error'));
const coverageAlertIcon = computed(() => hasCoverage.value ? 'mdi-shield-check' : 'mdi-shield-alert');
const coverageStatusLabel = computed(() => {
  if (hasCoverage.value) return 'Your health coverage is active';
  if (isActive.value) return 'Your coverage has expired — please renew your plan';
  return `Account status: ${statusLabel.value}`;
});

const statCards = computed(() => [
  {
    icon: 'mdi-card-account-details',
    iconBg: '#eff6ff',
    iconColor: '#2563eb',
    value: enrollee.value?.enrollee_id || '—',
    label: 'Enrollee ID',
  },
  {
    icon: 'mdi-hospital-building',
    iconBg: '#f0fdf4',
    iconColor: '#16a34a',
    value: enrollee.value?.facility?.name || '—',
    label: 'Primary Facility',
  },
  {
    icon: 'mdi-shield-star',
    iconBg: '#faf5ff',
    iconColor: '#9333ea',
    value: enrollee.value?.premium_plan?.name || enrollee.value?.benefit_package?.name || '—',
    label: 'Current Plan',
  },
  {
    icon: 'mdi-account-check',
    iconBg: isActive.value ? '#f0fdf4' : '#fff7ed',
    iconColor: isActive.value ? '#16a34a' : '#ea580c',
    value: statusLabel.value,
    label: 'Account Status',
    valueClass: isActive.value ? 'tw-text-green-600' : 'tw-text-orange-500',
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
.ep-stat-card {
  background: white;
  border-radius: 16px;
  padding: 20px 16px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ep-stat-card__icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: grid;
  place-items: center;
}
.ep-stat-card__value {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ep-stat-card__label {
  font-size: 12px;
  color: #64748b;
}

.ep-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06);
  overflow: hidden;
}
.ep-card__head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}
.ep-card__title {
  font-size: 15px;
  font-weight: 700;
  color: #0f172a;
}
.ep-card__body {
  padding: 12px 20px;
}
.ep-card__footer {
  padding: 12px 20px;
  border-top: 1px solid #f1f5f9;
  background: #f8fafc;
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
