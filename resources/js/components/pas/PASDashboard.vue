<template>
  <AdminLayout>
    <div class="qds-page-shell">
      <AppPageHeader title="Pre-Authorisation System" icon="mdi-shield-check-outline" icon-color="primary">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadStatistics">Refresh</v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
        <AppStatCard compact label="Total Referrals" :value="stats.totalReferrals" icon="mdi-file-send-outline" color="primary" :loading="loading" />
        <AppStatCard compact label="Pending Referrals" :value="stats.pendingReferrals" icon="mdi-clock-outline" color="warning" :loading="loading" />
        <AppStatCard compact label="PA Codes" :value="stats.totalPaCodes" icon="mdi-shield-edit-outline" color="info" :loading="loading" />
        <AppStatCard compact label="Document Rules" :value="stats.totalDocuments" icon="mdi-file-document-multiple-outline" color="success" :loading="loading" />
      </div>

      <div v-if="loadError">
        <AppErrorState title="PAS data unavailable" :message="loadError">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-refresh" @click="loadStatistics">Retry</v-btn>
        </AppErrorState>
      </div>

      <div class="tw-grid tw-grid-cols-1 tw-gap-4 xl:tw-grid-cols-[1.1fr_.9fr]">
        <AppCard title="PAS Workbench" icon="mdi-view-grid-outline" tone="primary">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <button
              v-for="card in filteredNavigationCards"
              :key="card.route"
              type="button"
              class="qds-card qds-hover-lift tw-text-left"
              @click="navigateTo(card.route)"
            >
              <div class="tw-flex tw-items-start tw-gap-3 tw-p-4">
                <div class="qds-icon-shell" :class="card.toneClass">
                  <v-icon size="20">{{ card.icon }}</v-icon>
                </div>
                <div class="tw-min-w-0">
                  <p class="tw-font-semibold tw-text-slate-900">{{ card.title }}</p>
                  <p class="tw-mt-0.5 tw-text-xs tw-text-slate-500">{{ card.description }}</p>
                  <div class="tw-mt-3">
                    <AppBadge v-if="card.badge" :label="card.badge" :tone="card.badgeTone || 'neutral'" size="sm" />
                  </div>
                </div>
              </div>
            </button>
          </div>
        </AppCard>

        <AppCard title="Recent Referrals" icon="mdi-history" tone="info">
          <div v-if="recentReferrals.length" class="tw-space-y-2">
            <div
              v-for="referral in recentReferrals"
              :key="referral.id"
              class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2"
            >
              <div class="tw-min-w-0">
                <p class="tw-font-medium tw-text-slate-900">{{ referral.referral_code || referral.utn || `Referral #${referral.id}` }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ referral.enrollee?.first_name }} {{ referral.enrollee?.last_name }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ referral.referring_facility?.name || referral.referringFacility?.name || 'Referring facility unavailable' }}</p>
              </div>
              <div class="tw-flex tw-flex-col tw-items-end tw-gap-2">
                <ReferralStatusBadge :status="referral.status" size="sm" />
                <DateDisplay :value="referral.created_at" format="short" />
              </div>
            </div>
          </div>
          <AppEmptyState
            v-else
            icon="mdi-file-send-outline"
            title="No referrals yet"
            description="Approved, pending, and rejected referrals will appear here as PAS activity starts flowing."
          />
        </AppCard>
      </div>

      <div class="tw-grid tw-grid-cols-1 tw-gap-4 xl:tw-grid-cols-2">
        <AppCard title="Referral Mix" icon="mdi-chart-donut" tone="warning">
          <div class="tw-grid tw-grid-cols-2 tw-gap-2 md:tw-grid-cols-4">
            <div v-for="item in referralMix" :key="item.label" class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">{{ item.label }}</p>
              <p class="tw-mt-1.5 tw-text-xl tw-font-semibold tw-text-slate-950">{{ item.value }}</p>
            </div>
          </div>
        </AppCard>

        <AppCard title="Documentation Coverage" icon="mdi-file-check-outline" tone="success">
          <div class="tw-grid tw-grid-cols-1 tw-gap-2 sm:tw-grid-cols-2">
            <div class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Referral Rules</p>
              <p class="tw-mt-1.5 tw-text-xl tw-font-semibold tw-text-slate-950">{{ stats.referralDocuments }}</p>
            </div>
            <div class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-3">
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">PA Code Rules</p>
              <p class="tw-mt-1.5 tw-text-xl tw-font-semibold tw-text-slate-950">{{ stats.paDocuments }}</p>
            </div>
          </div>
        </AppCard>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import AdminLayout from '../layout/AdminLayout.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppErrorState from '../common/AppErrorState.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import DateDisplay from '../common/DateDisplay.vue'
import ReferralStatusBadge from '../common/ReferralStatusBadge.vue'
import { documentRequirementAPI, pasAPI } from '../../utils/api'
import { useAuthStore } from '../../stores/auth'
import { useToast } from '../../composables/useToast'

const router = useRouter()
const authStore = useAuthStore()
const { error } = useToast()

const loading = ref(false)
const loadError = ref('')
const referrals = ref([])
const paCodes = ref([])
const documentRules = ref([])

const stats = computed(() => ({
  totalReferrals: referrals.value.length,
  pendingReferrals: referrals.value.filter((item) => String(item.status).toLowerCase() === 'pending').length,
  totalPaCodes: paCodes.value.length,
  totalDocuments: documentRules.value.length,
  referralDocuments: documentRules.value.filter((item) => item.request_type === 'referral').length,
  paDocuments: documentRules.value.filter((item) => item.request_type === 'pa_code').length,
}))

const recentReferrals = computed(() => referrals.value.slice(0, 6))
const referralMix = computed(() => ([
  { label: 'Pending', value: referrals.value.filter((item) => String(item.status).toLowerCase() === 'pending').length },
  { label: 'Approved', value: referrals.value.filter((item) => String(item.status).toLowerCase() === 'approved').length },
  { label: 'Rejected', value: referrals.value.filter((item) => String(item.status).toLowerCase() === 'rejected').length },
  { label: 'Validated', value: referrals.value.filter((item) => item.utn_validated).length },
]))

const navigationCards = [
  {
    title: 'Submit Referral',
    description: 'Create a new PAS referral using the secured referral submission route.',
    icon: 'mdi-account-arrow-right-outline',
    route: '/claims/referral-request',
    permissions: ['referrals.create', 'referrals.submit'],
    toneClass: 'qds-tone-primary',
  },
  {
    title: 'Referral Management',
    description: 'Review referral details, approve, reject, and print generated PAS references.',
    icon: 'mdi-file-document-check-outline',
    route: '/pas/referral-management',
    permissions: ['referrals.view'],
    toneClass: 'qds-tone-info',
  },
  {
    title: 'Validate UTN',
    description: 'Use the UTN validation workflow available to PAS and desk officer users.',
    icon: 'mdi-barcode-scan',
    route: '/pas/validate-utn',
    permissions: ['utn.validate'],
    toneClass: 'qds-tone-warning',
  },
  {
    title: 'FU-PA Approval',
    description: 'Approve or reject follow-up PA requests from facilities using the live PA code routes.',
    icon: 'mdi-check-decagram-outline',
    route: '/pas/fu-pa-approval',
    permissions: ['pa_codes.approve', 'pa_codes.reject'],
    toneClass: 'qds-tone-success',
  },
  {
    title: 'Document Requirements',
    description: 'Maintain the referral and PA code document rules backing PAS compliance checks.',
    icon: 'mdi-file-document-multiple-outline',
    route: '/document-requirements',
    permissions: ['documents.manage', 'documents.requirements.manage'],
    toneClass: 'qds-tone-secondary',
  },
]

const filteredNavigationCards = computed(() => navigationCards.filter((card) => card.permissions.some((permission) => authStore.hasPermission(permission))))

const loadStatistics = async () => {
  loading.value = true
  loadError.value = ''

  try {
    const [referralResponse, paCodeResponse, documentResponse] = await Promise.all([
      pasAPI.getReferrals({ per_page: 50, with: 'enrollee,referringFacility' }),
      pasAPI.getPACodes({ per_page: 50 }),
      documentRequirementAPI.getAll({ per_page: 200 }),
    ])

    referrals.value = referralResponse.data?.data?.data || referralResponse.data?.data || []
    paCodes.value = paCodeResponse.data?.data?.data || paCodeResponse.data?.data || []
    documentRules.value = documentResponse.data?.data?.data || documentResponse.data?.data || []
  } catch (err) {
    loadError.value = err?.response?.data?.message || 'Unable to load PAS dashboard data.'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

const navigateTo = (path) => {
  if (!path) return
  router.push(path)
}

loadStatistics()
</script>
