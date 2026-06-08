<template>
  <EnrolleeLayout>
    <AppPageHeader class="tw-mb-6" title="Premium Plans" subtitle="View available plans and renew your health coverage" kicker="Enrollee portal" icon="mdi-shield-star-outline" />

    <!-- Current plan -->
    <AppCard v-if="currentPlan" class="tw-mb-6" title="Your Current Plan" icon="mdi-shield-check" tone="success" muted>
      <div class="tw-flex tw-items-center tw-gap-3 tw-mb-1">
        <v-icon color="success" size="20">mdi-shield-check</v-icon>
        <span class="tw-font-bold tw-text-slate-900">Current coverage</span>
      </div>
      <div class="tw-text-lg tw-font-bold tw-text-slate-950">{{ currentPlan.name }}</div>
      <div class="tw-mt-1 tw-text-sm tw-text-slate-600">
        Coverage: {{ formatDate(enrolleeAuth.enrollee?.coverage_start_date) }}
        <template v-if="enrolleeAuth.enrollee?.coverage_end_date">
          → {{ formatDate(enrolleeAuth.enrollee.coverage_end_date) }}
        </template>
        <template v-else>· No Expiry</template>
      </div>
    </AppCard>

    <!-- Plans grid -->
    <div v-if="loadingPlans" class="tw-grid tw-gap-4 sm:tw-grid-cols-2 lg:tw-grid-cols-3">
      <AppSkeleton v-for="index in 3" :key="index" type="article, actions" />
    </div>

    <div v-else-if="plans.length" class="tw-grid sm:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
      <AppCard
        v-for="plan in plans"
        :key="plan.id"
        class="ep-plan-card"
        :class="{ 'ep-plan-card--current': currentPlan?.id === plan.id }"
        :tone="currentPlan?.id === plan.id ? 'success' : 'primary'"
        :padded="false"
        hover
        full-height
      >
        <AppBadge v-if="currentPlan?.id === plan.id" class="ep-plan-card__badge" label="Current Plan" tone="success" size="sm" />

        <div class="ep-plan-card__head">
          <v-icon color="primary" size="28" class="tw-mb-2">mdi-shield-star</v-icon>
          <h3 class="ep-plan-card__name">{{ plan.name }}</h3>
          <div class="ep-plan-card__amount">
            ₦{{ formatAmount(plan.amount) }}
            <span class="ep-plan-card__period">/ {{ plan.duration_label || 'year' }}</span>
          </div>
        </div>

        <div class="ep-plan-card__body">
          <p v-if="plan.description" class="ep-plan-card__desc">{{ plan.description }}</p>

          <div class="ep-plan-card__features">
            <div v-if="plan.max_dependants" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              Up to {{ plan.max_dependants }} dependant(s)
            </div>
            <div v-if="plan.duration_months" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              {{ plan.duration_months }} month(s) coverage
            </div>
            <div v-if="plan.benefit_package?.name" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              {{ plan.benefit_package.name }}
            </div>
            <div class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              Access to accredited facilities
            </div>
          </div>
        </div>

        <div class="ep-plan-card__footer">
          <v-btn
            :color="currentPlan?.id === plan.id ? 'success' : 'primary'"
            :variant="currentPlan?.id === plan.id ? 'tonal' : 'flat'"
            block
            rounded
            @click="selectPlan(plan)"
          >
            <v-icon start size="18">
              {{ currentPlan?.id === plan.id ? 'mdi-check-circle' : 'mdi-arrow-right-circle' }}
            </v-icon>
            {{ currentPlan?.id === plan.id ? 'Currently Active' : 'Select Plan' }}
          </v-btn>
        </div>
      </AppCard>
    </div>

    <AppEmptyState v-else icon="mdi-shield-off-outline" title="No active plans available" description="There are no premium plans available for renewal at this time." />

    <!-- Renewal info dialog -->
    <AppModal v-model="infoDialog" title="Plan Renewal Information" icon="mdi-information" size="sm">
      <div class="tw-space-y-3">
        <p class="tw-text-slate-700">
          To renew your premium plan, start a new premium application through the public enrollment flow or contact {{ org.scheme_name }} support if you need help matching the right plan.
        </p>
        <div class="tw-bg-blue-50 tw-rounded-xl tw-p-4">
          <div class="tw-font-semibold tw-text-slate-800 tw-mb-2">Contact {{ org.scheme_name }}</div>
          <div class="tw-text-sm tw-text-slate-600">
            <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
              <v-icon size="16" color="primary">mdi-phone</v-icon> {{ org.hotline }}
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
              <v-icon size="16" color="primary">mdi-web</v-icon> {{ org.website }}
            </div>
          </div>
        </div>
        <p v-if="selectedPlan" class="tw-text-sm tw-text-slate-500">
          Selected plan: <strong>{{ selectedPlan.name }}</strong> — ₦{{ formatAmount(selectedPlan.amount) }}
        </p>
      </div>
      <template #actions>
        <v-btn variant="outlined" color="primary" rounded @click="infoDialog = false">
          Close
        </v-btn>
        <v-btn variant="flat" color="primary" rounded @click="$router.push('/enroll/start')">
          Start Renewal
        </v-btn>
      </template>
    </AppModal>
  </EnrolleeLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { enrolleePortalAPI } from '../../utils/enrolleeApi';
import { useOrganizationSettings } from '../../composables/useOrganizationSettings';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';
import AppModal from '../common/AppModal.vue';
import AppBadge from '../common/AppBadge.vue';
import AppCard from '../common/AppCard.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppSkeleton from '../common/AppSkeleton.vue';

const enrolleeAuth = useEnrolleeAuthStore();
const { settings: org, fetchSettings } = useOrganizationSettings();
const loadingPlans = ref(false);
const plans = ref([]);
const infoDialog = ref(false);
const selectedPlan = ref(null);

const currentPlan = computed(() => enrolleeAuth.enrollee?.premium_plan || enrolleeAuth.enrollee?.benefit_package || null);

const formatAmount = (a) => Number(a || 0).toLocaleString('en-NG');
const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

const selectPlan = (plan) => {
  selectedPlan.value = plan;
  infoDialog.value = true;
};

onMounted(async () => {
  fetchSettings();
  loadingPlans.value = true;
  try {
    const res = await enrolleePortalAPI.plans();
    plans.value = res.data.data || [];
  } catch {
    plans.value = [];
  } finally {
    loadingPlans.value = false;
  }
});
</script>

<style scoped>
.ep-plan-card {
  overflow: hidden;
  display: flex;
  flex-direction: column;
  position: relative;
}
.ep-plan-card--current {
  border-color: #16a34a;
}
.ep-plan-card__badge {
  position: absolute;
  top: 12px;
  right: 12px;
}
.ep-plan-card__head {
  padding: 28px 24px 20px;
  border-bottom: 1px solid #f1f5f9;
  text-align: center;
  background: #f8fafc;
}
.ep-plan-card__name {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  margin-bottom: 8px;
}
.ep-plan-card__amount {
  font-size: 26px;
  font-weight: 800;
  color: #0885ab;
}
.ep-plan-card__period {
  font-size: 14px;
  font-weight: 400;
  color: #64748b;
}
.ep-plan-card__body {
  padding: 20px 24px;
  flex: 1;
}
.ep-plan-card__desc {
  font-size: 13px;
  color: #64748b;
  margin-bottom: 16px;
  line-height: 1.6;
}
.ep-plan-card__features {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.ep-plan-feat {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #374151;
}
.ep-plan-card__footer {
  padding: 16px 24px;
  border-top: 1px solid #f1f5f9;
}
</style>
