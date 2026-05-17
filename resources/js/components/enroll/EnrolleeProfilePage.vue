<template>
  <EnrolleeLayout>
    <div class="tw-mb-6">
      <h1 class="tw-text-2xl tw-font-bold tw-text-slate-900">My Profile</h1>
      <p class="tw-text-sm tw-text-slate-500">Your personal and enrollment information</p>
    </div>

    <div v-if="loading" class="tw-flex tw-justify-center tw-py-16">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <template v-else-if="enrollee">
      <!-- Profile header card -->
      <div class="ep-profile-hero tw-mb-6">
        <div class="ep-profile-hero__avatar">
          <img
            v-if="enrollee.image_url"
            :src="enrollee.image_url"
            :alt="enrollee.full_name"
            class="ep-profile-hero__img"
          />
          <v-icon v-else size="56" color="rgba(255,255,255,0.8)">mdi-account-circle</v-icon>
        </div>
        <div class="ep-profile-hero__info">
          <h2 class="ep-profile-hero__name">{{ enrollee.full_name }}</h2>
          <div class="ep-profile-hero__id">{{ enrollee.enrollee_id }}</div>
          <div class="tw-flex tw-gap-2 tw-mt-2 tw-flex-wrap">
            <v-chip size="small" :color="statusColor" label>{{ statusLabel }}</v-chip>
            <v-chip size="small" color="primary" variant="tonal" label v-if="enrollee.premium_plan?.name || enrollee.benefit_package?.name">
              {{ enrollee.premium_plan?.name || enrollee.benefit_package?.name }}
            </v-chip>
          </div>
        </div>
      </div>

      <!-- Details sections -->
      <div class="tw-grid md:tw-grid-cols-2 tw-gap-6">
        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="18">mdi-account</v-icon>
            <span class="ep-card__title">Personal Information</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-row" v-for="f in personalSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </div>

        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="18">mdi-shield-check</v-icon>
            <span class="ep-card__title">Enrollment & Coverage</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-row" v-for="f in enrollmentSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </div>

        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="18">mdi-map-marker</v-icon>
            <span class="ep-card__title">Location Details</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-row" v-for="f in locationSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </div>

        <div class="ep-card">
          <div class="ep-card__head">
            <v-icon color="primary" size="18">mdi-hospital-building</v-icon>
            <span class="ep-card__title">Facility & Programme</span>
          </div>
          <div class="ep-card__body">
            <div class="ep-row" v-for="f in facilitySection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="tw-flex tw-flex-wrap tw-gap-3 tw-mt-6">
        <v-btn color="primary" variant="tonal" rounded @click="$router.push('/enroll/change-password')">
          <v-icon start>mdi-lock-reset</v-icon> Change Password
        </v-btn>
        <v-btn color="primary" variant="tonal" rounded @click="refresh">
          <v-icon start>mdi-refresh</v-icon> Refresh Profile
        </v-btn>
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
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';

const enrolleeAuth = useEnrolleeAuthStore();
const loading = ref(false);
const enrollee = computed(() => enrolleeAuth.enrollee);

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const statusColor = computed(() => ({ 0: 'warning', 1: 'success', 2: 'error', 3: 'warning', 4: 'error' }[enrollee.value?.status] ?? 'default'));
const statusLabel = computed(() => ({ 0: 'Pending', 1: 'Active', 2: 'Rejected', 3: 'Suspended', 4: 'Expired' }[enrollee.value?.status] ?? 'Unknown'));

const personalSection = computed(() => [
  { label: 'First Name',      value: enrollee.value?.first_name },
  { label: 'Middle Name',     value: enrollee.value?.middle_name },
  { label: 'Last Name',       value: enrollee.value?.last_name },
  { label: 'Date of Birth',   value: fmt(enrollee.value?.date_of_birth) },
  { label: 'Gender',          value: enrollee.value?.sex === 1 ? 'Male' : enrollee.value?.sex === 2 ? 'Female' : null },
  { label: 'Marital Status',  value: ({ 1: 'Single', 2: 'Married', 3: 'Divorced', 4: 'Widowed' })[enrollee.value?.marital_status] },
  { label: 'Phone',           value: enrollee.value?.phone },
  { label: 'Email',           value: enrollee.value?.email },
  { label: 'NIN',             value: enrollee.value?.nin ? '••••••••••••' + enrollee.value.nin.slice(-3) : null },
]);

const enrollmentSection = computed(() => [
  { label: 'Enrollee ID',     value: enrollee.value?.enrollee_id },
  { label: 'Status',          value: statusLabel.value },
  { label: 'Plan',            value: enrollee.value?.premium_plan?.name || enrollee.value?.benefit_package?.name },
  { label: 'Enrolled On',     value: fmt(enrollee.value?.enrollment_date || enrollee.value?.created_at) },
  { label: 'Approved On',     value: fmt(enrollee.value?.approval_date) },
  { label: 'Coverage Start',  value: fmt(enrollee.value?.coverage_start_date) },
  { label: 'Coverage End',    value: enrollee.value?.coverage_end_date ? fmt(enrollee.value.coverage_end_date) : 'No Expiry' },
]);

const locationSection = computed(() => [
  { label: 'Address',         value: enrollee.value?.address },
  { label: 'LGA',             value: enrollee.value?.lga?.name },
  { label: 'Ward',            value: enrollee.value?.ward?.name },
  { label: 'Village/Town',    value: enrollee.value?.village },
]);

const facilitySection = computed(() => [
  { label: 'Primary Facility',    value: enrollee.value?.facility?.name },
  { label: 'Insurance Programme', value: enrollee.value?.insurance_programme?.name },
  { label: 'Funding Type',        value: enrollee.value?.funding_type?.name },
  { label: 'Benefactor',          value: enrollee.value?.benefactor?.name },
]);

const refresh = async () => {
  loading.value = true;
  await enrolleeAuth.fetchMe();
  loading.value = false;
};

onMounted(async () => {
  if (!enrollee.value) await refresh();
});
</script>

<style scoped>
.ep-profile-hero {
  background: linear-gradient(135deg, #0885ab, #0d3b6e);
  border-radius: 20px;
  padding: 28px 32px;
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
}
.ep-profile-hero__avatar {
  width: 88px;
  height: 88px;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  border: 3px solid rgba(255,255,255,0.3);
  display: grid;
  place-items: center;
  flex-shrink: 0;
  overflow: hidden;
}
.ep-profile-hero__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.ep-profile-hero__name {
  font-size: 22px;
  font-weight: 800;
  color: white;
  margin-bottom: 4px;
}
.ep-profile-hero__id {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  font-family: monospace;
}

.ep-card {
  background: white;
  border-radius: 16px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  overflow: hidden;
}
.ep-card__head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 20px;
  border-bottom: 1px solid #f1f5f9;
  background: #f8fafc;
}
.ep-card__title {
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
}
.ep-card__body { padding: 4px 20px 12px; }

.ep-row {
  display: flex;
  justify-content: space-between;
  padding: 9px 0;
  border-bottom: 1px solid #f8fafc;
  gap: 12px;
}
.ep-row:last-child { border-bottom: none; }
.ep-row__label {
  font-size: 13px;
  color: #64748b;
  flex-shrink: 0;
}
.ep-row__value {
  font-size: 13px;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
  word-break: break-word;
}
</style>
