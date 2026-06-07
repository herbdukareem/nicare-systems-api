<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Enrollment Approval"
        subtitle="Verify NIN, compare provider data, and approve only after the approval decision is fully auditable."
        kicker="Enrollment"
        icon="mdi-account-check-outline"
      >
        <v-select
          v-model="limit"
          :items="[25, 50, 75, 100]"
          label="Batch size"
          density="compact"
          variant="outlined"
          hide-details
          class="tw-w-32"
        />
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadBatch">
          Load Batch
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-4 md:tw-grid-cols-4">
        <AppCard muted>
          <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Loaded</p>
          <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-slate-950">{{ rows.length }}</p>
        </AppCard>
        <AppCard muted>
          <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Ready To Approve</p>
          <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-emerald-700">{{ readyCount }}</p>
        </AppCard>
        <AppCard muted>
          <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Approved This Batch</p>
          <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-blue-700">{{ approvedCount }}</p>
        </AppCard>
        <AppCard muted>
          <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500">Needs Attention</p>
          <p class="tw-mt-2 tw-text-2xl tw-font-semibold tw-text-amber-700">{{ attentionCount }}</p>
        </AppCard>
      </div>

      <AppCard title="Approval Filters" icon="mdi-filter-outline" tone="primary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-5">
          <v-select v-model="filters.programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Phase" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding" density="compact" variant="outlined" clearable />
        </div>
      </AppCard>

      <AppAlert
        v-if="!loading && rows.length === 0"
        tone="info"
        title="No pending enrollees"
        message="No pending approval records matched the current batch filters."
      />

      <div class="tw-space-y-4">
        <AppCard
          v-for="row in rows"
          :key="row.id"
          :title="row.full_name || row.name || `Enrollee #${row.id}`"
          :subtitle="row.enrollee_id || 'Pending ID assignment'"
          icon="mdi-account-box-outline"
          :tone="row.local_status === 'approved' ? 'success' : row.local_status === 'failed' ? 'danger' : 'primary'"
        >
          <template #actions>
            <AppStatusBadge :status="row.local_status === 'approved' ? 'approved' : row.status_label || 'pending'" :label="row.local_status === 'approved' ? 'Approved' : row.status_label || 'Pending'" size="sm" />
            <AppStatusBadge :status="row.nin_verification_status" :label="ninStatusLabel(row.nin_verification_status)" size="sm" />
          </template>

          <div class="tw-space-y-4">
            <div class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-5">
              <Info label="Phone" :value="row.phone || 'N/A'" />
              <Info label="NIN" :value="row.nin || 'Not provided'" />
              <Info label="Programme" :value="row.insurance_programme?.name || 'N/A'" />
              <Info label="Facility" :value="row.facility?.name || 'N/A'" />
              <Info label="Funding" :value="row.funding_type?.name || 'N/A'" />
              <Info label="Benefactor" :value="row.benefactor?.name || 'N/A'" />
              <Info label="Enrollment date">
                <DateDisplay :value="row.enrollment_date || row.created_at" format="medium" />
              </Info>
              <Info label="Duplicate review" :value="row.is_possible_duplicate ? 'Resolve before approval' : 'Clear'" />
              <Info label="Premium plan" :value="row.premium_plan?.name || 'N/A'" />
              <Info label="Payment" :value="row.premium_plan?.payment_required ? 'Required' : 'Not required'" />
            </div>

            <AppAlert
              v-if="row.local_error"
              tone="danger"
              title="Action failed"
              :message="row.local_error"
            />

            <AppAlert
              v-if="requiresVerification(row)"
              tone="warning"
              title="NIN verification required"
              message="This enrollee has a NIN. Verify it before approval so the approving officer can compare and choose which values to retain."
            />

            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn
                v-if="row.nin"
                variant="outlined"
                color="primary"
                prepend-icon="mdi-card-account-details-outline"
                :loading="verifyingId === row.id"
                @click="verifyNin(row)"
              >
                {{ row.nin_verification_status === 'verified' ? 'Re-verify NIN' : 'Verify NIN' }}
              </v-btn>
              <v-btn
                v-if="row.comparison.length"
                variant="text"
                color="primary"
                :prepend-icon="expandedRowId === row.id ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                @click="expandedRowId = expandedRowId === row.id ? null : row.id"
              >
                {{ expandedRowId === row.id ? 'Hide comparison' : 'Show comparison' }}
              </v-btn>
              <v-btn
                color="primary"
                prepend-icon="mdi-check-decagram-outline"
                :loading="approvingId === row.id"
                :disabled="cannotApprove(row)"
                @click="openApproveDialog(row)"
              >
                Approve
              </v-btn>
            </div>

            <div v-if="expandedRowId === row.id && row.comparison.length" class="tw-space-y-4 tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
              <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-start lg:tw-justify-between">
                <div>
                  <h3 class="tw-text-base tw-font-semibold tw-text-slate-900">NIN Comparison</h3>
                  <p class="tw-text-sm tw-text-slate-500">
                    Verified by {{ row.nin_verification_provider || 'configured provider' }}
                    <span v-if="row.nin_verified_at">on <DateDisplay :value="row.nin_verified_at" format="medium" /></span>
                  </p>
                </div>
                <v-select
                  v-model="row.mergeStrategy"
                  :items="mergeStrategies"
                  item-title="label"
                  item-value="value"
                  label="Merge strategy"
                  density="compact"
                  variant="outlined"
                  class="lg:tw-w-64"
                />
              </div>

              <div class="tw-grid tw-gap-4 lg:tw-grid-cols-[1fr_auto]">
                <div class="tw-overflow-x-auto">
                  <table class="tw-min-w-full tw-text-sm">
                    <thead>
                      <tr class="tw-border-b tw-border-slate-200">
                        <th class="tw-px-3 tw-py-2 tw-text-left tw-font-semibold tw-text-slate-700">Field</th>
                        <th class="tw-px-3 tw-py-2 tw-text-left tw-font-semibold tw-text-slate-700">Provided Data</th>
                        <th class="tw-px-3 tw-py-2 tw-text-left tw-font-semibold tw-text-slate-700">Verified Data</th>
                        <th class="tw-px-3 tw-py-2 tw-text-left tw-font-semibold tw-text-slate-700">Decision</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="field in row.comparison" :key="`${row.id}-${field.field}`" class="tw-border-b tw-border-slate-100">
                        <td class="tw-px-3 tw-py-3 tw-font-medium tw-text-slate-900">{{ field.label }}</td>
                        <td class="tw-px-3 tw-py-3 tw-text-slate-600">{{ field.provided || 'N/A' }}</td>
                        <td class="tw-px-3 tw-py-3 tw-text-slate-600">{{ field.verified || 'N/A' }}</td>
                        <td class="tw-px-3 tw-py-3">
                          <template v-if="row.mergeStrategy === 'manual'">
                            <v-select
                              v-model="row.fieldSelection[field.field]"
                              :items="decisionOptions"
                              item-title="label"
                              item-value="value"
                              density="compact"
                              variant="outlined"
                              hide-details
                              class="tw-min-w-40"
                            />
                          </template>
                          <template v-else>
                            <AppStatusBadge
                              :status="resolvedDecision(row, field.field)"
                              :label="resolvedDecisionLabel(row, field.field)"
                              size="sm"
                            />
                          </template>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <div v-if="row.providerPhoto" class="tw-flex tw-justify-center">
                  <div class="tw-overflow-hidden tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-p-2">
                    <img :src="row.providerPhoto" alt="Verified NIN profile photo" class="tw-h-40 tw-w-40 tw-object-cover" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </AppCard>
      </div>

      <AppConfirmDialog
        :model-value="approvalDialogOpen"
        title="Approve enrollee"
        :message="approvalDialogMessage"
        :warning="approvalDialogWarning"
        confirm-text="Approve enrollee"
        @update:modelValue="approvalDialogOpen = $event"
        @cancel="closeApproveDialog"
        @confirm="confirmApprove"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const Info = defineComponent({
  name: 'ApprovalInfo',
  props: {
    label: { type: String, required: true },
    value: { type: [String, Number], default: '' },
  },
  setup(props, { slots }) {
    return () => h('div', { class: 'tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-3' }, [
      h('p', { class: 'tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.15em] tw-text-slate-500' }, props.label),
      h('div', { class: 'tw-mt-2 tw-text-sm tw-font-medium tw-text-slate-900' }, slots.default ? slots.default() : (props.value || 'N/A')),
    ])
  },
})

const { success, error } = useToast()

const rows = ref([])
const loading = ref(false)
const verifyingId = ref(null)
const approvingId = ref(null)
const limit = ref(50)
const expandedRowId = ref(null)
const approvalDialogOpen = ref(false)
const approvalTarget = ref(null)

const metadata = reactive({
  insurance_programmes: [],
  facilities: [],
  benefactors: [],
  enrollment_phases: [],
  funding_types: [],
})

const filters = reactive({
  programme_id: null,
  facility_id: null,
  benefactor_id: null,
  enrollment_phase_id: null,
  funding_type_id: null,
})

const mergeStrategies = [
  { label: 'Keep provided data', value: 'keep_provided' },
  { label: 'Prefer verified NIN data', value: 'prefer_verified' },
  { label: 'Choose field by field', value: 'manual' },
]

const decisionOptions = [
  { label: 'Keep provided', value: 'provided' },
  { label: 'Use verified', value: 'verified' },
]

const approvedCount = computed(() => rows.value.filter((row) => row.local_status === 'approved').length)
const readyCount = computed(() => rows.value.filter((row) => !cannotApprove(row)).length)
const attentionCount = computed(() => rows.value.filter((row) => row.local_status === 'failed' || requiresVerification(row) || row.is_possible_duplicate).length)

const approvalDialogMessage = computed(() => {
  if (!approvalTarget.value) return 'Approve this enrollee?'
  return `Approve ${approvalTarget.value.full_name || approvalTarget.value.name || 'this enrollee'} and activate coverage using the current approval workflow.`
})

const approvalDialogWarning = computed(() => {
  if (!approvalTarget.value) return ''
  if (!approvalTarget.value.nin) {
    return 'This enrollee does not have a NIN. Approval will continue with a "NIN Not Provided" verification status.'
  }

  if (approvalTarget.value.mergeStrategy === 'manual') {
    return 'Manual merge choices will be recorded in the enrollee verification metadata before approval.'
  }

  return 'The selected NIN merge strategy will be stored in the audit trail and enrollee verification metadata.'
})

const apiItems = (response) => response?.data?.data?.data || response?.data?.data || []

const ninStatusLabel = (status) => ({
  verified: 'Verified',
  failed: 'Verification Failed',
  not_provided: 'NIN Not Provided',
  not_started: 'Not Verified',
}[status] || 'Not Verified')

const normalizeProviderPhoto = (photo) => {
  if (!photo) return ''
  return String(photo).startsWith('data:') ? photo : `data:image/jpeg;base64,${photo}`
}

const defaultFieldSelection = (comparison = []) => {
  return comparison.reduce((carry, field) => {
    carry[field.field] = field.recommended_source || 'provided'
    return carry
  }, {})
}

const normalizeRow = (row) => {
  const comparison = row.nin_verification?.data?.comparison || row.nin_verification_data?.comparison || []
  const providerData = row.nin_verification?.data?.provider_data || row.nin_verification_data?.provider_data || {}
  const storedSelection = row.nin_verification?.meta?.approval_selection?.fields || row.nin_verification_meta?.approval_selection?.fields || {}

  return {
    ...row,
    local_status: row.local_status || 'pending',
    local_error: '',
    mergeStrategy: row.nin ? (comparison.some((field) => !field.matches) ? 'manual' : 'keep_provided') : 'keep_provided',
    fieldSelection: { ...defaultFieldSelection(comparison), ...storedSelection },
    comparison,
    providerData,
    providerPhoto: normalizeProviderPhoto(providerData.photo),
  }
}

const loadMetadata = async () => {
  const response = await premiumAPI.metadata()
  Object.assign(metadata, response.data?.data || {})
}

const loadBatch = async () => {
  loading.value = true

  try {
    const params = { ...filters, limit: limit.value, random: true }
    Object.keys(params).forEach((key) => {
      if (params[key] === null || params[key] === '') delete params[key]
    })

    const response = await enrolleeAPI.pendingApproval(params)
    rows.value = apiItems(response).map(normalizeRow)
    expandedRowId.value = null
  } catch (err) {
    error(err.response?.data?.message || 'Could not load the pending approval batch')
  } finally {
    loading.value = false
  }
}

const requiresVerification = (row) => !!row.nin && row.nin_verification_status !== 'verified'
const cannotApprove = (row) => row.local_status === 'approved' || row.is_possible_duplicate || requiresVerification(row)

const resolvedDecision = (row, field) => {
  if (row.mergeStrategy === 'manual') {
    return row.fieldSelection[field] || 'provided'
  }

  return row.mergeStrategy === 'prefer_verified' ? 'verified' : 'provided'
}

const resolvedDecisionLabel = (row, field) => {
  return resolvedDecision(row, field) === 'verified' ? 'Use verified data' : 'Keep provided data'
}

const verifyNin = async (row) => {
  verifyingId.value = row.id
  row.local_error = ''

  try {
    const response = await enrolleeAPI.verifyNin(row.id, { consent: true })
    const enrollee = response.data?.data?.enrollee || response.data?.data?.data?.enrollee
    const verification = response.data?.data?.verification || response.data?.data?.data?.verification

    Object.assign(row, normalizeRow({
      ...row,
      ...enrollee,
      nin_verification_status: verification?.status || enrollee?.nin_verification_status,
      nin_verification_provider: verification?.provider_name || enrollee?.nin_verification_provider,
      nin_verified_at: verification?.verified_at || enrollee?.nin_verified_at,
      nin_verification_data: {
        provider_data: verification?.provider_data || {},
        comparison: verification?.comparison || [],
      },
    }))

    expandedRowId.value = row.id
    success(`NIN verified for ${row.full_name || row.name}`)
  } catch (err) {
    row.local_error = err.response?.data?.message || 'NIN verification failed'
    row.nin_verification_status = 'failed'
    error(row.local_error)
  } finally {
    verifyingId.value = null
  }
}

const openApproveDialog = (row) => {
  approvalTarget.value = row
  approvalDialogOpen.value = true
}

const closeApproveDialog = () => {
  approvalDialogOpen.value = false
  approvalTarget.value = null
}

const approvalPayload = (row) => {
  const payload = {
    nin_merge_strategy: row.mergeStrategy,
  }

  if (row.mergeStrategy === 'manual') {
    payload.nin_field_selection = row.fieldSelection
  }

  return payload
}

const confirmApprove = async () => {
  if (!approvalTarget.value) return

  const row = approvalTarget.value
  approvingId.value = row.id
  row.local_error = ''

  try {
    const response = await enrolleeAPI.approve(row.id, approvalPayload(row))
    Object.assign(row, normalizeRow({
      ...row,
      ...(response.data?.data || {}),
      local_status: 'approved',
    }))
    success(`${row.full_name || row.name} approved`)
    closeApproveDialog()
  } catch (err) {
    row.local_status = 'failed'
    row.local_error = err.response?.data?.message || 'Approval failed'
    error(row.local_error)
  } finally {
    approvingId.value = null
  }
}

onMounted(async () => {
  await loadMetadata()
  await loadBatch()
})
</script>
