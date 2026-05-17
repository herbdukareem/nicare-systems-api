<template>
  <EnrolleeLayout>
    <div class="tw-mb-6">
      <h1 class="tw-text-2xl tw-font-bold tw-text-slate-900">Premium Plans</h1>
      <p class="tw-text-sm tw-text-slate-500">View available plans and renew your health coverage</p>
    </div>

    <!-- Current plan -->
    <div v-if="currentPlan" class="ep-current-plan tw-mb-6">
      <div class="tw-flex tw-items-center tw-gap-3 tw-mb-1">
        <v-icon color="white" size="20">mdi-shield-check</v-icon>
        <span class="tw-font-bold tw-text-white">Your Current Plan</span>
      </div>
      <div class="tw-text-white tw-text-lg tw-font-bold">{{ currentPlan.name }}</div>
      <div class="tw-text-white/75 tw-text-sm tw-mt-1">
        Coverage: {{ formatDate(enrolleeAuth.enrollee?.coverage_start_date) }}
        <template v-if="enrolleeAuth.enrollee?.coverage_end_date">
          → {{ formatDate(enrolleeAuth.enrollee.coverage_end_date) }}
        </template>
        <template v-else>· No Expiry</template>
      </div>
    </div>

    <!-- Plans grid -->
    <div v-if="loadingPlans" class="tw-flex tw-justify-center tw-py-16">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>

    <div v-else-if="plans.length" class="tw-grid sm:tw-grid-cols-2 lg:tw-grid-cols-3 tw-gap-6">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="ep-plan-card"
        :class="{ 'ep-plan-card--current': currentPlan?.id === plan.id }"
      >
        <div v-if="currentPlan?.id === plan.id" class="ep-plan-card__badge">Current Plan</div>

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
      </div>
    </div>

    <div v-else class="tw-text-center tw-py-16 tw-text-slate-500">
      <v-icon size="48" class="tw-mb-3">mdi-shield-off</v-icon>
      <p>No active plans available at this time.</p>
    </div>

    <!-- Renewal info dialog -->
    <AppModal v-model="infoDialog" title="Plan Renewal Information" icon="mdi-information" size="sm">
      <div class="tw-space-y-3">
        <p class="tw-text-slate-700">
          To renew your premium plan, please contact your NiCare agent or visit any accredited facility to purchase a new premium PIN.
        </p>
        <div class="tw-bg-blue-50 tw-rounded-xl tw-p-4">
          <div class="tw-font-semibold tw-text-slate-800 tw-mb-2">Contact NiCare</div>
          <div class="tw-text-sm tw-text-slate-600">
            <div class="tw-flex tw-items-center tw-gap-2 tw-mb-1">
              <v-icon size="16" color="primary">mdi-phone</v-icon> 08162653801
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
              <v-icon size="16" color="primary">mdi-web</v-icon> nicare.nigerstate.gov.ng
            </div>
          </div>
        </div>
        <p v-if="selectedPlan" class="tw-text-sm tw-text-slate-500">
          Selected plan: <strong>{{ selectedPlan.name }}</strong> — ₦{{ formatAmount(selectedPlan.amount) }}
        </p>
      </div>
      <template #actions>
        <v-btn variant="flat" color="primary" block rounded @click="infoDialog = false">
          Got it
        </v-btn>
      </template>
    </AppModal>
  </EnrolleeLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth';
import { enrolleePortalAPI } from '../../utils/enrolleeApi';
import EnrolleeLayout from './layout/EnrolleeLayout.vue';
import AppModal from '../common/AppModal.vue';

const enrolleeAuth = useEnrolleeAuthStore();
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
.ep-current-plan {
  background: linear-gradient(135deg, #0885ab, #0d3b6e);
  border-radius: 16px;
  padding: 20px 24px;
}

.ep-plan-card {
  background: white;
  border-radius: 20px;
  border: 2px solid #e2e8f0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: border-color 0.2s, box-shadow 0.2s, transform 0.2s;
  position: relative;
}
.ep-plan-card:hover {
  border-color: #0885ab;
  box-shadow: 0 8px 24px rgba(8,133,171,0.12);
  transform: translateY(-3px);
}
.ep-plan-card--current {
  border-color: #16a34a;
  box-shadow: 0 4px 16px rgba(22,163,74,0.12);
}
.ep-plan-card__badge {
  position: absolute;
  top: 12px;
  right: 12px;
  background: #16a34a;
  color: white;
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
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
