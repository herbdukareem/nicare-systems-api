<template>
  <EnrolleeLayout>
    <AppPageHeader class="tw-mb-6" title="My Profile" subtitle="Your personal and enrollment information" kicker="Enrollee portal" icon="mdi-account-outline" />

    <div v-if="loading" class="tw-grid tw-gap-4 md:tw-grid-cols-2">
      <AppSkeleton v-for="index in 4" :key="index" type="article" />
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
            <EnrolleeStatusBadge :status="enrollee.status" :label="statusLabel" />
            <AppBadge v-if="enrollee.premium_plan?.name || enrollee.benefit_package?.name" :label="enrollee.premium_plan?.name || enrollee.benefit_package?.name" tone="info" />
          </div>
        </div>
      </div>

      <!-- Details sections -->
      <div class="tw-grid md:tw-grid-cols-2 tw-gap-6">
        <AppCard title="Personal Information" icon="mdi-account" full-height>
          <div>
            <div class="ep-row" v-for="f in personalSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>

        <AppCard title="Enrollment & Coverage" icon="mdi-shield-check" full-height>
          <div>
            <div class="ep-row" v-for="f in enrollmentSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>

        <AppCard title="Location Details" icon="mdi-map-marker" full-height>
          <div>
            <div class="ep-row" v-for="f in locationSection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>

        <AppCard title="Facility & Programme" icon="mdi-hospital-building" full-height>
          <div>
            <div class="ep-row" v-for="f in facilitySection" :key="f.label">
              <span class="ep-row__label">{{ f.label }}</span>
              <span class="ep-row__value">{{ f.value || '—' }}</span>
            </div>
          </div>
        </AppCard>
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

    <AppErrorState v-else title="Could not load your profile" message="Please refresh the profile or sign in again.">
      <v-btn color="primary" variant="outlined" @click="refresh">Retry</v-btn>
    </AppErrorState>
  </EnrolleeLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';
import AppBadge from '../common/AppBadge.vue';
import AppCard from '../common/AppCard.vue';
import AppErrorState from '../common/AppErrorState.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppSkeleton from '../common/AppSkeleton.vue';
import EnrolleeStatusBadge from '../common/EnrolleeStatusBadge.vue';

const enrolleeAuth = useEnrolleeAuthStore();
const loading = ref(false);
const enrollee = computed(() => enrolleeAuth.enrollee);

const fmt = (d) => d ? new Date(d).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

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
