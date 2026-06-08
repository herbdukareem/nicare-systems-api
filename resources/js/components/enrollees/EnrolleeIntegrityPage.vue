<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="NIN & Duplicates" icon="mdi-shield-account-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadAll">
          Refresh
        </v-btn>
        <v-btn size="small" variant="outlined" prepend-icon="mdi-filter-remove-outline" @click="resetFilters">
          Reset
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-3 xl:tw-grid-cols-6">
        <button type="button" class="tw-text-left" @click="applyQuickFilter('all')">
          <AppStatCard compact label="Total Lives" icon="mdi-account-group-outline" color="primary" :value="summary.total" :loading="loading" />
        </button>
        <button type="button" class="tw-text-left" @click="applyQuickFilter('with_nin')">
          <AppStatCard compact label="With NIN" icon="mdi-card-account-details-outline" color="success" :value="summary.with_nin" :loading="loading" />
        </button>
        <button type="button" class="tw-text-left" @click="applyQuickFilter('without_nin')">
          <AppStatCard compact label="Without NIN" icon="mdi-card-account-details-off-outline" color="warning" :value="summary.without_nin" :loading="loading" />
        </button>
        <button type="button" class="tw-text-left" @click="applyQuickFilter('duplicate_nin')">
          <AppStatCard compact label="Duplicate NIN" icon="mdi-content-duplicate" color="danger" :value="summary.duplicate_nin_records" :loading="loading" />
        </button>
        <button type="button" class="tw-text-left" @click="applyQuickFilter('flagged_duplicates')">
          <AppStatCard compact label="Flagged Duplicates" icon="mdi-alert-decagram-outline" color="secondary" :value="summary.possible_duplicates" :loading="loading" />
        </button>
        <div>
          <AppStatCard compact label="Open Flags" icon="mdi-flag-outline" color="info" :value="summary.unresolved_duplicate_flags" :loading="loading" />
        </div>
      </div>

      <AppFilterBar :active-count="activeFilterCount" :cols="5" :advanced-cols="3" @clear="resetFilters">
        <template #actions>
          <v-btn size="small" color="primary" variant="flat" :loading="loading" @click="applyFilters">
            Load
          </v-btn>
        </template>

        <v-text-field
          v-model="filters.search"
          label="Search"
          placeholder="Name, phone, email"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-text-field
          v-model="filters.enrollee_id"
          label="NICARE ID"
          placeholder="NG..."
          density="compact"
          variant="outlined"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-text-field
          v-model="filters.nin"
          label="NIN"
          placeholder="Filter by NIN"
          density="compact"
          variant="outlined"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-autocomplete
          v-model="filters.ward_id"
          :items="metadata.wards"
          item-title="name"
          item-value="id"
          label="Ward"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
        <v-select
          v-model="filters.nin_state"
          :items="ninStateOptions"
          item-title="title"
          item-value="value"
          label="NIN State"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />

        <template #advanced>
          <v-select
            v-model="filters.funding_type_id"
            :items="metadata.funding_types"
            item-title="name"
            item-value="id"
            label="Funding Type"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
          <v-autocomplete
            v-model="filters.benefactor_id"
            :items="filteredBenefactors"
            item-title="name"
            item-value="id"
            label="Benefactor"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
          <v-select
            v-model="filters.duplicate_filter"
            :items="duplicateFilterOptions"
            item-title="title"
            item-value="value"
            label="Duplicate Filter"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            item-title="title"
            item-value="value"
            label="Status"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
        </template>

        <template #tags>
          <AppBadge v-if="filters.enrollee_id" :label="`NICARE ID: ${filters.enrollee_id}`" tone="primary" size="sm" />
          <AppBadge v-if="filters.nin" :label="`NIN: ${filters.nin}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.ward_id" :label="`Ward: ${selectedWardName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.funding_type_id" :label="`Funding: ${selectedFundingName}`" tone="info" size="sm" />
          <AppBadge v-if="filters.benefactor_id" :label="`Benefactor: ${selectedBenefactorName}`" tone="info" size="sm" />
          <AppBadge v-if="filters.nin_state" :label="`NIN State: ${selectedNinStateLabel}`" tone="warning" size="sm" />
          <AppBadge v-if="filters.duplicate_filter" :label="`Duplicate Filter: ${selectedDuplicateFilterLabel}`" tone="danger" size="sm" />
          <AppBadge v-if="filters.status !== null && filters.status !== ''" :label="`Status: ${selectedStatusLabel}`" tone="warning" size="sm" />
        </template>
      </AppFilterBar>

      <AppAlert v-if="loadError" tone="danger" :message="loadError" />

      <AppBulkActions :count="selectedEnrolleeIds.length" title="Bulk enrollee status change">
        <v-select
          v-model="bulkForm.status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="New status"
          density="compact"
          variant="outlined"
          hide-details
          class="tw-min-w-[180px]"
        />
        <v-text-field
          v-model="bulkForm.comment"
          label="Comment"
          density="compact"
          variant="outlined"
          hide-details
          class="tw-min-w-[220px]"
        />
        <v-btn color="primary" variant="flat" prepend-icon="mdi-content-save-outline" :loading="bulkSaving" @click="applyBulkStatus">
          Apply Status
        </v-btn>
      </AppBulkActions>

      <AppCard title="Enrollee Records" icon="mdi-account-search-outline" tone="primary" :padded="false">
        <template #actions>
          <span class="tw-text-xs tw-text-slate-500">{{ matchingRecordsLabel }}</span>
        </template>

        <AppDataTable
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="enrollees"
          :loading="loading"
          :items-length="meta.total"
          :per-page-options="[25, 50, 100]"
          item-value="id"
          show-select
          v-model:model-value="selectedEnrolleeIds"
          class="tw-rounded-none tw-border-0"
        >
          <template #item.enrollee_id="{ item }">
            <button class="tw-text-left tw-font-semibold tw-text-cyan-700 hover:tw-underline" @click="openProfile(item.id)">
              {{ item.enrollee_id }}
            </button>
          </template>
          <template #item.full_name="{ item }">
            <div class="tw-font-medium tw-text-slate-900">{{ item.full_name || item.name || 'N/A' }}</div>
          </template>
          <template #item.nin="{ item }">
            <div class="tw-space-y-1">
              <div class="tw-font-medium tw-text-slate-900">{{ item.nin || 'Not provided' }}</div>
              <AppBadge
                v-if="item.has_duplicate_nin"
                :label="`Duplicate NIN (${item.duplicate_nin_count})`"
                tone="danger"
                size="sm"
              />
            </div>
          </template>
          <template #item.nin_verification="{ item }">
            <AppBadge :label="item.nin_verification_label || 'Not Verified'" :tone="ninTone(item.nin_verification_status)" size="sm" />
          </template>
          <template #item.duplicate_flags="{ item }">
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge
                v-if="item.is_possible_duplicate"
                label="Flagged"
                tone="warning"
                size="sm"
              />
              <AppBadge
                v-if="item.duplicate_reviewed"
                label="Reviewed"
                tone="success"
                size="sm"
              />
              <span v-if="!item.is_possible_duplicate && !item.duplicate_reviewed" class="tw-text-sm tw-text-slate-500">Clear</span>
            </div>
          </template>
          <template #item.ward="{ item }">{{ relationName(item.ward) || 'N/A' }}</template>
          <template #item.funding="{ item }">
            <FundingTypeBadge v-if="relationName(item.funding_type)" :label="relationName(item.funding_type)" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.benefactor="{ item }">
            <BenefactorBadge v-if="relationName(item.benefactor)" :label="relationName(item.benefactor)" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.status="{ item }">
            <EnrolleeStatusBadge :status="item.status_label" :label="item.status_label" size="sm" />
          </template>
          <template #item.created_at="{ item }">
            <DateDisplay :value="item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-items-center tw-justify-end tw-gap-1">
              <v-btn icon size="small" variant="text" title="Open enrollee" @click="openProfile(item.id)">
                <v-icon size="18">mdi-open-in-new</v-icon>
              </v-btn>
              <v-btn
                v-if="canVerifyNin && item.nin"
                icon
                size="small"
                variant="text"
                color="primary"
                title="Verify NIN"
                @click="verifyNin(item)"
              >
                <v-icon size="18">mdi-card-account-details-star-outline</v-icon>
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              :title="hasLoaded ? 'No enrollee records matched these filters' : 'No enrollee records loaded yet'"
              :description="hasLoaded
                ? 'Try switching between duplicate NIN, with NIN, and without NIN filters.'
                : 'Load the latest integrity records to review NIN coverage and duplicate risks.'"
              icon="mdi-account-search-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppCard title="Open Duplicate Flags" icon="mdi-flag-variant-outline" tone="secondary" :padded="false">
        <AppDataTable
          :headers="duplicateHeaders"
          :items="duplicateFlags"
          :loading="duplicateFlagsLoading"
          item-value="id"
          class="tw-rounded-none tw-border-0"
        >
          <template #item.flagged_pair="{ item }">
            <div class="tw-space-y-1">
              <div class="tw-font-medium tw-text-slate-900">{{ duplicateName(item.enrollee) }}</div>
              <div class="tw-text-xs tw-text-slate-500">{{ item.enrollee?.enrollee_id || 'No enrollee ID' }}</div>
              <div class="tw-text-xs tw-text-slate-400">Matched with {{ duplicateName(item.matched_enrollee || item.matchedEnrollee) }}</div>
            </div>
          </template>
          <template #item.match_type="{ item }">
            <AppBadge :label="item.match_type || 'Possible duplicate'" tone="warning" size="sm" />
          </template>
          <template #item.flagged_at="{ item }">
            <DateDisplay :value="item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-flex-wrap tw-justify-end tw-gap-2">
              <v-btn
                v-if="canResolveDuplicates"
                size="x-small"
                color="success"
                variant="flat"
                :loading="resolvingFlagId === item.id"
                @click="resolveDuplicate(item, 'confirmed_unique')"
              >
                Mark Unique
              </v-btn>
              <v-btn
                v-if="canResolveDuplicates"
                size="x-small"
                color="warning"
                variant="outlined"
                :loading="resolvingFlagId === item.id"
                @click="resolveDuplicate(item, 'confirmed_duplicate')"
              >
                Confirm Duplicate
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              title="No open duplicate flags"
              description="All currently flagged duplicate records have been reviewed."
              icon="mdi-check-decagram-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppBulkActions from '../common/AppBulkActions.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import BenefactorBadge from '../common/BenefactorBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import EnrolleeStatusBadge from '../common/EnrolleeStatusBadge.vue'
import FundingTypeBadge from '../common/FundingTypeBadge.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const { success, error } = useToast()
const auth = useAuthStore()

const loading = ref(false)
const duplicateFlagsLoading = ref(false)
const bulkSaving = ref(false)
const resolvingFlagId = ref(null)
const hasLoaded = ref(false)
const loadError = ref('')
const page = ref(1)
const perPage = ref(50)
const selectedEnrolleeIds = ref([])
const enrollees = ref([])
const duplicateFlags = ref([])

const meta = reactive({ total: 0, from: null, to: null })
const summary = reactive({
  total: 0,
  with_nin: 0,
  without_nin: 0,
  duplicate_nin_records: 0,
  possible_duplicates: 0,
  unresolved_duplicate_flags: 0,
})

const metadata = reactive({
  wards: [],
  funding_types: [],
  benefactors: [],
})

const filters = reactive({
  search: '',
  enrollee_id: '',
  nin: '',
  ward_id: null,
  funding_type_id: null,
  benefactor_id: null,
  nin_state: null,
  duplicate_filter: null,
  status: null,
})

const bulkForm = reactive({
  status: null,
  comment: '',
})

const canVerifyNin = computed(() => auth.hasPermission('enrollee.nin.verify') || auth.hasPermission('enrollee.approve'))
const canManageStatuses = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollees.edit') || auth.hasPermission('enrollee.approve'))
const canResolveDuplicates = computed(() => canManageStatuses.value || canVerifyNin.value)

const statusOptions = [
  { title: 'Pending Approval', value: 0 },
  { title: 'Approved', value: 1 },
  { title: 'Rejected', value: 2 },
  { title: 'Suspended', value: 3 },
  { title: 'Inactive', value: 4 },
]

const ninStateOptions = [
  { title: 'With NIN', value: 'with_nin' },
  { title: 'Without NIN', value: 'without_nin' },
]

const duplicateFilterOptions = [
  { title: 'Duplicate NIN Only', value: 'duplicate_nin_only' },
  { title: 'Flagged Duplicate Records', value: 'duplicate_flag_only' },
]

const headers = [
  { title: 'NICARE ID', key: 'enrollee_id', sortable: false },
  { title: 'Full Name', key: 'full_name', sortable: false },
  { title: 'NIN', key: 'nin', sortable: false },
  { title: 'NIN Verification', key: 'nin_verification', sortable: false },
  { title: 'Duplicate Review', key: 'duplicate_flags', sortable: false },
  { title: 'Ward', key: 'ward', sortable: false },
  { title: 'Funding Type', key: 'funding', sortable: false },
  { title: 'Benefactor', key: 'benefactor', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created', key: 'created_at', sortable: false },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const duplicateHeaders = [
  { title: 'Flagged Pair', key: 'flagged_pair', sortable: false },
  { title: 'Match Type', key: 'match_type', sortable: false },
  { title: 'Flagged At', key: 'flagged_at', sortable: false },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const responseItems = (response) => {
  const root = response?.data || {}
  return root.data?.data?.data || root.data?.data || root.data || []
}

const responseMeta = (response) => {
  const root = response?.data || {}
  return root.data?.meta || root.meta || root.data?.data?.meta || {}
}

const relationName = (value) => value?.name || value?.full_name || null

const filteredBenefactors = computed(() => {
  const linked = metadata.benefactors.filter((benefactor) => {
    const id = benefactor.funding_type_id || benefactor.funding_type?.id
    return filters.funding_type_id && Number(id) === Number(filters.funding_type_id)
  })

  return filters.funding_type_id && linked.length ? linked : metadata.benefactors
})

const selectedWardName = computed(() => metadata.wards.find((item) => Number(item.id) === Number(filters.ward_id))?.name || 'Selected')
const selectedFundingName = computed(() => metadata.funding_types.find((item) => Number(item.id) === Number(filters.funding_type_id))?.name || 'Selected')
const selectedBenefactorName = computed(() => metadata.benefactors.find((item) => Number(item.id) === Number(filters.benefactor_id))?.name || 'Selected')
const selectedNinStateLabel = computed(() => ninStateOptions.find((item) => item.value === filters.nin_state)?.title || 'Selected')
const selectedDuplicateFilterLabel = computed(() => duplicateFilterOptions.find((item) => item.value === filters.duplicate_filter)?.title || 'Selected')
const selectedStatusLabel = computed(() => statusOptions.find((item) => item.value === filters.status)?.title || 'Selected')
const activeFilterCount = computed(() => Object.keys(activeFilterParams()).length)
const matchingRecordsLabel = computed(() => `${Number(meta.total || 0).toLocaleString()} matching record(s)`)

const normalizeMetadata = (payload) => {
  metadata.wards = payload.wards || []
  metadata.funding_types = payload.funding_types || []
  metadata.benefactors = payload.benefactors || []
}

const ninTone = (status) => ({
  verified: 'success',
  failed: 'danger',
  not_provided: 'warning',
  not_started: 'secondary',
}[status] || 'secondary')

const duplicateName = (record) => {
  const firstName = record?.first_name || ''
  const middleName = record?.middle_name || ''
  const lastName = record?.last_name || ''
  return [firstName, middleName, lastName].filter(Boolean).join(' ') || record?.enrollee_id || 'Unknown enrollee'
}

const activeFilterParams = () => {
  const params = {
    search: filters.search,
    enrollee_id: filters.enrollee_id,
    nin: filters.nin,
    ward_id: filters.ward_id,
    funding_type_id: filters.funding_type_id,
    benefactor_id: filters.benefactor_id,
    nin_state: filters.nin_state,
    status: filters.status,
  }

  if (filters.duplicate_filter === 'duplicate_nin_only') {
    params.duplicate_nin_only = true
  }

  if (filters.duplicate_filter === 'duplicate_flag_only') {
    params.duplicate_flag_only = true
  }

  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })

  return params
}

const loadMetadata = async () => {
  try {
    const response = await premiumAPI.metadata()
    normalizeMetadata(response.data.data || {})
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load enrollee integrity filters')
  }
}

const loadSummary = async () => {
  const response = await enrolleeAPI.integritySummary(activeFilterParams())
  Object.assign(summary, {
    total: 0,
    with_nin: 0,
    without_nin: 0,
    duplicate_nin_records: 0,
    possible_duplicates: 0,
    unresolved_duplicate_flags: 0,
  }, response.data?.data?.summary || {})
}

const loadEnrollees = async () => {
  const response = await enrolleeAPI.getAll({
    ...activeFilterParams(),
    page: page.value,
    per_page: perPage.value,
    include_summary: false,
    include_duplicate_nin: true,
  })

  enrollees.value = responseItems(response)
  Object.assign(meta, { total: 0, from: null, to: null }, responseMeta(response))
  hasLoaded.value = true
}

const loadDuplicateFlags = async () => {
  duplicateFlagsLoading.value = true
  try {
    const response = await enrolleeAPI.getDuplicateFlags({ per_page: 50 })
    duplicateFlags.value = responseItems(response)
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load duplicate flags')
  } finally {
    duplicateFlagsLoading.value = false
  }
}

const loadAll = async () => {
  loading.value = true
  loadError.value = ''
  try {
    await Promise.all([
      loadSummary(),
      loadEnrollees(),
      loadDuplicateFlags(),
    ])
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Failed to load enrollee integrity records'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  page.value = 1
  loadAll()
}

const applyQuickFilter = (preset) => {
  if (preset === 'all') {
    filters.nin_state = null
    filters.duplicate_filter = null
  }

  if (preset === 'with_nin') {
    filters.nin_state = 'with_nin'
    filters.duplicate_filter = null
  }

  if (preset === 'without_nin') {
    filters.nin_state = 'without_nin'
    filters.duplicate_filter = null
  }

  if (preset === 'duplicate_nin') {
    filters.nin_state = null
    filters.duplicate_filter = 'duplicate_nin_only'
  }

  if (preset === 'flagged_duplicates') {
    filters.nin_state = null
    filters.duplicate_filter = 'duplicate_flag_only'
  }

  applyFilters()
}

const resetFilters = () => {
  Object.assign(filters, {
    search: '',
    enrollee_id: '',
    nin: '',
    ward_id: null,
    funding_type_id: null,
    benefactor_id: null,
    nin_state: null,
    duplicate_filter: null,
    status: null,
  })

  selectedEnrolleeIds.value = []
  bulkForm.status = null
  bulkForm.comment = ''
  page.value = 1
  loadAll()
}

const applyBulkStatus = async () => {
  if (!canManageStatuses.value) {
    error('You do not have permission to update enrollee statuses.')
    return
  }

  if (!selectedEnrolleeIds.value.length) {
    error('Select at least one enrollee before applying a bulk status update.')
    return
  }

  if (bulkForm.status === null || bulkForm.status === '') {
    error('Choose the new status to apply.')
    return
  }

  bulkSaving.value = true
  try {
    const response = await enrolleeAPI.bulkUpdateStatus({
      enrollee_ids: selectedEnrolleeIds.value,
      status: bulkForm.status,
      comment: bulkForm.comment || null,
    })

    success(response.data?.message || 'Enrollee statuses updated successfully.')
    selectedEnrolleeIds.value = []
    bulkForm.status = null
    bulkForm.comment = ''
    await loadAll()
  } catch (err) {
    error(err.response?.data?.message || 'Could not update the selected enrollees.')
  } finally {
    bulkSaving.value = false
  }
}

const resolveDuplicate = async (flag, resolution) => {
  resolvingFlagId.value = flag.id
  try {
    const response = await enrolleeAPI.resolveDuplicateFlag(flag.id, { resolution })
    success(response.data?.message || 'Duplicate flag resolved successfully.')
    await loadAll()
  } catch (err) {
    error(err.response?.data?.message || 'Could not resolve the duplicate flag.')
  } finally {
    resolvingFlagId.value = null
  }
}

const verifyNin = async (item) => {
  try {
    const response = await enrolleeAPI.verifyNin(item.id, { consent: true })
    success(response.data?.message || 'NIN verified successfully.')
    await loadAll()
  } catch (err) {
    error(err.response?.data?.message || 'Could not verify this NIN.')
  }
}

const openProfile = (id) => {
  router.push(`/enrollees/${id}`)
}

watch(() => filters.funding_type_id, () => {
  if (filters.benefactor_id && !filteredBenefactors.value.some((benefactor) => Number(benefactor.id) === Number(filters.benefactor_id))) {
    filters.benefactor_id = null
  }
})

watch([page, perPage], () => {
  if (hasLoaded.value) {
    loadAll()
  }
})

onMounted(async () => {
  await loadMetadata()
  await loadAll()
})
</script>
