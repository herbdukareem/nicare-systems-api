<template>
  <EnrolleeLayout>
    <AppPageHeader
      class="tw-mb-6"
      title="Premium Plans"
      subtitle="Choose a plan and renew your health coverage from the enrollee portal"
      kicker="Enrollee portal"
      icon="mdi-shield-star-outline"
    />

    <AppAlert
      v-if="statusMessage"
      class="tw-mb-5"
      :tone="renewalReady ? 'success' : 'info'"
      title="Renewal status"
      :message="statusMessage"
    />

    <AppCard v-if="currentPlan" class="tw-mb-6" title="Your Current Plan" icon="mdi-shield-check" tone="success" muted>
      <div class="tw-flex tw-items-center tw-gap-3 tw-mb-1">
        <v-icon color="success" size="20">mdi-shield-check</v-icon>
        <span class="tw-font-bold tw-text-slate-900">Current coverage</span>
      </div>
      <div class="tw-text-lg tw-font-bold tw-text-slate-950">{{ currentPlan.name }}</div>
      <div class="tw-mt-1 tw-text-sm tw-text-slate-600">
        Coverage: {{ formatDate(enrolleeAuth.enrollee?.coverage_start_date) }}
        <template v-if="enrolleeAuth.enrollee?.coverage_end_date">
          -> {{ formatDate(enrolleeAuth.enrollee.coverage_end_date) }}
        </template>
        <template v-else>· No Expiry</template>
      </div>
    </AppCard>

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
        <AppBadge
          v-if="currentPlan?.id === plan.id"
          class="ep-plan-card__badge"
          label="Current Plan"
          tone="success"
          size="sm"
        />

        <div class="ep-plan-card__head">
          <v-icon color="primary" size="28" class="tw-mb-2">mdi-shield-star</v-icon>
          <h3 class="ep-plan-card__name">{{ plan.name }}</h3>
          <div class="ep-plan-card__amount">
            ₦{{ formatAmount(plan.amount) }}
            <span class="ep-plan-card__period">/ {{ plan.duration_label || durationLabel(plan) }}</span>
          </div>
        </div>

        <div class="ep-plan-card__body">
          <p v-if="plan.description" class="ep-plan-card__desc">{{ plan.description }}</p>

          <div class="ep-plan-card__features">
            <div v-if="plan.max_dependants || plan.maximum_dependants" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              Up to {{ plan.max_dependants || plan.maximum_dependants }} dependant(s)
            </div>
            <div v-if="plan.duration_months || plan.duration_days" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              {{ durationDetail(plan) }}
            </div>
            <div v-if="plan.benefit_package?.name" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              {{ plan.benefit_package.name }}
            </div>
            <div v-if="plan.funding_type?.name" class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              Funding: {{ plan.funding_type.name }}
            </div>
            <div class="ep-plan-feat">
              <v-icon size="16" color="green">mdi-check</v-icon>
              {{ plan.payment_required ? 'Secure online checkout' : 'Instant renewal' }}
            </div>
          </div>
        </div>

        <div class="ep-plan-card__footer tw-space-y-2">
          <v-btn
            color="primary"
            variant="flat"
            block
            rounded
            :loading="submitting && selectedPlan?.id === plan.id"
            @click="selectPlan(plan)"
          >
            <v-icon start size="18">
              {{ currentPlan?.id === plan.id ? 'mdi-refresh-circle' : 'mdi-arrow-right-circle' }}
            </v-icon>
            {{ currentPlan?.id === plan.id ? 'Renew This Plan' : 'Switch & Renew' }}
          </v-btn>

          <v-btn
            v-if="purchaseReference && selectedPlan?.id === plan.id"
            block
            rounded
            variant="outlined"
            color="primary"
            :loading="verifying"
            @click="verifyRenewal"
          >
            Verify Payment
          </v-btn>
        </div>
      </AppCard>
    </div>

    <AppEmptyState
      v-else
      icon="mdi-shield-off-outline"
      title="No active plans available"
      description="There are no premium plans available for renewal at this time."
    />

    <AppModal v-model="confirmDialog" title="Confirm Renewal" icon="mdi-refresh-circle" size="sm" :loading="submitting">
      <div class="tw-space-y-3">
        <p class="tw-text-slate-700">
          You are about to renew
          <strong>{{ selectedPlan?.name }}</strong>
          for
          <strong>₦{{ formatAmount(selectedPlan?.amount) }}</strong>.
        </p>
        <p class="tw-text-sm tw-text-slate-600">
          {{ selectedPlan?.payment_required
            ? 'A secure payment checkout will open. After payment, verify the transaction here to activate your renewed coverage.'
            : 'This plan does not require online payment. Renewal will be applied immediately.' }}
        </p>
      </div>
      <template #actions>
        <v-btn variant="outlined" color="primary" rounded :disabled="submitting" @click="confirmDialog = false">
          Cancel
        </v-btn>
        <v-btn variant="flat" color="primary" rounded :loading="submitting" @click="startRenewal">
          Continue
        </v-btn>
      </template>
    </AppModal>
  </EnrolleeLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useEnrolleeAuthStore } from '../../stores/enrolleeAuth'
import { enrolleePortalAPI } from '../../utils/enrolleeApi'
import { useToast } from '../../composables/useToast'
import EnrolleeLayout from './layout/EnrolleeLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppModal from '../common/AppModal.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppSkeleton from '../common/AppSkeleton.vue'

const route = useRoute()
const enrolleeAuth = useEnrolleeAuthStore()
const { error, success } = useToast()

const loadingPlans = ref(false)
const submitting = ref(false)
const verifying = ref(false)
const plans = ref([])
const confirmDialog = ref(false)
const selectedPlan = ref(null)
const purchaseReference = ref('')
const statusMessage = ref('')
const renewalReady = ref(false)

const currentPlan = computed(() => enrolleeAuth.enrollee?.premium_plan || null)

const formatAmount = (amount) => Number(amount || 0).toLocaleString('en-NG')
const formatDate = (date) => (date ? new Date(date).toLocaleDateString('en-NG', { day: 'numeric', month: 'short', year: 'numeric' }) : '—')

const durationLabel = (plan) => {
  if (plan?.duration_label) return plan.duration_label
  if (Number(plan?.duration_days || 0) >= 365) return 'year'
  return 'plan'
}

const durationDetail = (plan) => {
  if (plan?.duration_months) return `${plan.duration_months} month(s) coverage`
  if (plan?.duration_days) return `${plan.duration_days} day(s) coverage`
  return 'Coverage included'
}

const openCheckout = (url) => {
  if (!url) return
  const popup = window.open(url, 'enrollee-renewal-checkout', 'width=520,height=760,noopener,noreferrer')
  if (!popup) window.location.href = url
}

const loadPlans = async () => {
  loadingPlans.value = true
  try {
    const response = await enrolleePortalAPI.plans()
    plans.value = response.data.data || []
  } catch {
    plans.value = []
  } finally {
    loadingPlans.value = false
  }
}

const selectPlan = (plan) => {
  selectedPlan.value = plan
  confirmDialog.value = true
}

const startRenewal = async () => {
  if (!selectedPlan.value) return

  submitting.value = true
  try {
    const response = await enrolleePortalAPI.renewPlan({
      premium_plan_id: selectedPlan.value.id,
    })

    const payload = response.data?.data || {}
    purchaseReference.value = payload.purchase?.payment_reference || ''
    renewalReady.value = !!payload.renewed

    if (payload.enrollee) {
      enrolleeAuth.enrollee = payload.enrollee
      localStorage.setItem('enrollee', JSON.stringify(payload.enrollee))
    } else {
      await enrolleeAuth.fetchMe()
    }

    if (payload.requires_payment) {
      statusMessage.value = `Renewal ${purchaseReference.value} was created. Complete payment, then verify it here to activate your plan.`
      openCheckout(payload.checkout?.authorization_url)
    } else {
      statusMessage.value = `Your ${selectedPlan.value.name} renewal has been applied successfully.`
      success('Premium plan renewed successfully.')
    }

    confirmDialog.value = false
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to start premium renewal.')
  } finally {
    submitting.value = false
  }
}

const verifyRenewal = async () => {
  if (!purchaseReference.value) return

  verifying.value = true
  try {
    const response = await enrolleePortalAPI.verifyRenewal(purchaseReference.value)
    const payload = response.data?.data || {}

    renewalReady.value = !!payload.renewed
    if (payload.enrollee) {
      enrolleeAuth.enrollee = payload.enrollee
      localStorage.setItem('enrollee', JSON.stringify(payload.enrollee))
    } else {
      await enrolleeAuth.fetchMe()
    }

    statusMessage.value = renewalReady.value
      ? `Payment confirmed. Your ${payload.enrollee?.premium_plan?.name || selectedPlan.value?.name || 'premium plan'} renewal is now active.`
      : `Payment is still ${payload.verification?.status || 'pending'}.`

    if (renewalReady.value) {
      success('Premium plan renewed successfully.')
    }
  } catch (err) {
    error(err?.response?.data?.message || 'Unable to verify the renewal payment.')
  } finally {
    verifying.value = false
  }
}

onMounted(async () => {
  await Promise.all([
    loadPlans(),
    enrolleeAuth.fetchMe(),
  ])

  purchaseReference.value = String(route.query.payment_reference || '')
  if (purchaseReference.value && route.query.checkout_return) {
    await verifyRenewal()
  }
})
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
