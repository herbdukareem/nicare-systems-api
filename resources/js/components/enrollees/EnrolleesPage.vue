<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Enrollees Management</h1>
          <p class="tw-text-sm tw-text-slate-500">Search, filter, review, and export enrollee records.</p>
        </div>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn variant="outlined" prepend-icon="mdi-filter-remove-outline" @click="resetFilters">Reset Filters</v-btn>
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-file-excel-outline" :loading="exporting" :disabled="!canExport" @click="exportExcel">
            Export Excel
          </v-btn>
          <v-btn color="primary" prepend-icon="mdi-account-plus-outline" to="/enrollees/demo-enrollment">New Enrollee</v-btn>
        </div>
      </div>

      <section class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-sm">
        <div class="tw-mb-4 tw-flex tw-flex-col tw-gap-1 sm:tw-flex-row sm:tw-items-end sm:tw-justify-between">
          <div>
            <h2 class="tw-text-sm tw-font-bold tw-text-slate-900">Filter Records</h2>
            <p class="tw-text-xs tw-text-slate-500">Choose at least one filter for faster loading on large enrollee records.</p>
          </div>
          <v-btn color="primary" prepend-icon="mdi-database-search-outline" :loading="loading" @click="applyFilters">Load Enrollees</v-btn>
        </div>

        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-4">
          <v-text-field v-model="filters.search" label="Search" placeholder="ID, name, NIN, phone, legacy ID" density="compact" variant="outlined" prepend-inner-icon="mdi-magnify" clearable hide-details @keyup.enter="applyFilters" />
          <v-autocomplete v-model="filters.lga_id" :items="metadata.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable hide-details />
          <v-autocomplete v-model="filters.ward_id" :items="filteredWards" item-title="name" item-value="id" label="Ward" density="compact" variant="outlined" clearable hide-details :disabled="!filters.lga_id && filteredWards.length === 0" />
          <v-autocomplete v-model="filters.facility_id" :items="filteredFacilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable hide-details />

          <v-select v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding Type" density="compact" variant="outlined" clearable hide-details />
          <v-autocomplete v-model="filters.benefactor_id" :items="filteredBenefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Enrollment Phase" density="compact" variant="outlined" clearable hide-details />
          <v-select v-model="filters.status" :items="statusOptions" item-title="title" item-value="value" label="Status" density="compact" variant="outlined" clearable hide-details />

          <v-select v-model="filters.date_field" :items="dateFieldOptions" item-title="title" item-value="value" label="Date Type" density="compact" variant="outlined" hide-details />
          <v-text-field v-model="filters.date_from" type="date" label="Date From" density="compact" variant="outlined" hide-details />
          <v-text-field v-model="filters.date_to" type="date" label="Date To" density="compact" variant="outlined" hide-details />
          <v-select v-model="filters.coverage_status" :items="coverageOptions" item-title="title" item-value="value" label="Coverage" density="compact" variant="outlined" clearable hide-details />
        </div>
      </section>

      <v-alert v-if="loadError" type="error" variant="tonal" closable @click:close="loadError = ''">
        {{ loadError }}
      </v-alert>

      <div v-if="hasLoaded" class="tw-grid tw-gap-3 md:tw-grid-cols-2 xl:tw-grid-cols-5">
        <div v-for="card in summaryCards" :key="card.label" class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4 tw-shadow-sm">
          <div class="tw-flex tw-items-start tw-justify-between tw-gap-3">
            <div>
              <p class="tw-text-xs tw-font-medium tw-uppercase tw-text-slate-500">{{ card.label }}</p>
              <p class="tw-mt-2 tw-text-2xl tw-font-bold tw-text-slate-950">{{ card.value }}</p>
              <p v-if="card.helper" class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ card.helper }}</p>
            </div>
            <v-icon :color="card.color" size="24">{{ card.icon }}</v-icon>
          </div>
        </div>
      </div>

      <section class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-shadow-sm">
        <div class="tw-flex tw-flex-col tw-gap-3 tw-border-b tw-border-slate-200 tw-p-4 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
          <div>
            <h2 class="tw-text-sm tw-font-bold tw-text-slate-900">Filtered Results</h2>
            <p class="tw-text-xs tw-text-slate-500">{{ hasLoaded ? showingText : 'Filters are ready. Click Load Enrollees to fetch records.' }}</p>
          </div>
          <div class="tw-flex tw-flex-wrap tw-gap-2">
            <v-btn size="small" variant="outlined" prepend-icon="mdi-account-check-outline" to="/enrollees/approval">Pending Approval</v-btn>
            <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :disabled="!hasLoaded" @click="loadEnrollees">Refresh</v-btn>
          </div>
        </div>

        <v-skeleton-loader v-if="loading && enrollees.length === 0" type="table" class="tw-m-4" />

        <div v-else class="enrollees-table-wrap">
          <AppDataTable
            :headers="headers"
            :items="enrollees"
            :loading="loading"
            :items-length="meta.total"
            :per-page-options="[25, 50, 100, 250]"
            v-model:page="page"
            v-model:items-per-page="perPage"
            item-value="id"
            hover
            @update:sort-by="handleSort"
          >
            <template #item.sn="{ index }">{{ serialNumber(index) }}</template>
            <template #item.enrollee="{ item }">
              <button class="tw-text-left tw-font-semibold tw-text-cyan-700 hover:tw-underline" @click="openDetails(item)">{{ item.enrollee_id }}</button>
              <div v-if="item.legacy_id" class="tw-text-xs tw-text-slate-500">Legacy: {{ item.legacy_id }}</div>
            </template>
            <template #item.full_name="{ item }">
              <div class="tw-font-medium tw-text-slate-900">{{ item.full_name || item.name || 'N/A' }}</div>
            </template>
            <template #item.lga="{ item }">{{ relationName(item.lga) || item.lga_name || 'N/A' }}</template>
            <template #item.ward="{ item }">{{ relationName(item.ward) || item.ward_name || 'N/A' }}</template>
            <template #item.facility="{ item }">{{ relationName(item.facility) || 'N/A' }}</template>
            <template #item.funding="{ item }">{{ relationName(item.funding_type) || 'N/A' }}</template>
            <template #item.benefactor="{ item }">{{ relationName(item.benefactor) || 'N/A' }}</template>
            <template #item.phase="{ item }">{{ relationName(item.enrollment_phase) || 'N/A' }}</template>
            <template #item.status="{ item }">
              <v-chip size="small" :color="statusColor(item.status)" variant="flat">{{ item.status_label || statusLabel(item.status) }}</v-chip>
            </template>
            <template #item.created_at="{ item }">{{ formatDate(item.created_at) || 'N/A' }}</template>
            <template #item.actions="{ item }">
              <v-menu location="bottom end">
                <template #activator="{ props }">
                  <v-btn icon size="small" variant="text" v-bind="props" title="Actions"><v-icon size="18">mdi-dots-vertical</v-icon></v-btn>
                </template>
                <v-list density="compact" min-width="190">
                  <v-list-item v-if="canView" prepend-icon="mdi-eye-outline" title="View Details" @click="openDetails(item)" />
                  <v-list-item v-if="canEdit" prepend-icon="mdi-pencil-outline" title="Edit" @click="openEdit(item)" />
                  <v-list-item v-if="canApprove && Number(item.status) === 0" prepend-icon="mdi-check-circle-outline" title="Approve" @click="approveEnrollee(item)" />
                  <v-list-item v-if="canReject && Number(item.status) !== 2" prepend-icon="mdi-close-circle-outline" title="Reject" @click="changeStatus(item, 2)" />
                  <v-list-item prepend-icon="mdi-card-account-details-outline" title="Print ID Card" @click="printIdCard(item)" />
                  <v-list-item v-if="canDelete" prepend-icon="mdi-delete-outline" title="Delete" class="tw-text-red-600" @click="deleteEnrollee(item)" />
                </v-list>
              </v-menu>
            </template>
            <template #no-data>
              <div class="tw-flex tw-flex-col tw-items-center tw-py-14 tw-text-center">
                <v-icon size="48" color="grey">mdi-account-search-outline</v-icon>
                <p class="tw-mt-3 tw-text-sm tw-font-semibold tw-text-slate-700">{{ hasLoaded ? 'No enrollees found for the selected filters.' : 'No records loaded yet.' }}</p>
                <p class="tw-mt-1 tw-max-w-md tw-text-xs tw-text-slate-500">
                  {{ hasLoaded ? 'Try changing the LGA, facility, funding type, or enrollment phase.' : 'Apply filters and click Load Enrollees.' }}
                </p>
              </div>
            </template>
          </AppDataTable>
        </div>
      </section>

      <v-navigation-drawer v-model="detailDrawer" location="right" temporary width="560">
        <div v-if="selected" class="tw-space-y-4 tw-p-5">
          <div class="tw-flex tw-items-start tw-justify-between">
            <div>
              <h2 class="tw-text-xl tw-font-bold tw-text-slate-950">{{ selected.full_name || selected.name }}</h2>
              <p class="tw-text-sm tw-text-slate-500">{{ selected.enrollee_id }}</p>
            </div>
            <v-btn icon variant="text" @click="detailDrawer = false"><v-icon>mdi-close</v-icon></v-btn>
          </div>
          <div class="tw-flex tw-gap-2">
            <v-btn size="small" color="primary" variant="outlined" :disabled="!canEdit" @click="openEdit(selected)"><v-icon start size="16">mdi-pencil</v-icon>Edit</v-btn>
            <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-card-account-details-outline" @click="printIdCard(selected)">Print ID Card</v-btn>
          </div>

          <section v-for="group in detailGroups" :key="group.title" class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
            <h3 class="tw-mb-3 tw-text-xs tw-font-bold tw-uppercase tw-text-slate-500">{{ group.title }}</h3>
            <div class="tw-grid tw-gap-3 sm:tw-grid-cols-2">
              <div v-for="row in group.rows" :key="row.label">
                <p class="tw-text-xs tw-text-slate-500">{{ row.label }}</p>
                <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ row.value || 'N/A' }}</p>
              </div>
            </div>
          </section>
        </div>
      </v-navigation-drawer>

      <AppModal v-model="editDialog" title="Edit Enrollee" :subtitle="selected ? (selected.full_name || selected.name) : ''" icon="mdi-account-edit-outline" size="lg" :loading="saving">
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="editDialog = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" prepend-icon="mdi-content-save" @click="saveEdit">Save Changes</v-btn>
        </template>

        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
          <v-text-field v-model="editForm.first_name" label="First name" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.middle_name" label="Middle name" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.last_name" label="Last name" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.nin" label="NIN" density="compact" variant="outlined" />
          <v-select v-model="editForm.sex" :items="sexOptions" item-title="title" item-value="value" label="Sex" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.date_of_birth" type="date" label="Date of birth" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.phone" label="Phone" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.email" label="Email" density="compact" variant="outlined" />
          <v-text-field v-model="editForm.occupation" label="Occupation" density="compact" variant="outlined" />
          <v-select v-model="editForm.lga_id" :items="metadata.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" />
          <v-select v-model="editForm.ward_id" :items="metadata.wards" item-title="name" item-value="id" label="Ward" density="compact" variant="outlined" />
          <v-select v-model="editForm.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" />
          <v-select v-model="editForm.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding type" density="compact" variant="outlined" />
          <v-select v-model="editForm.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="editForm.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Enrollment phase" density="compact" variant="outlined" clearable />
          <v-textarea v-model="editForm.address" label="Address" density="compact" variant="outlined" rows="2" class="md:tw-col-span-3" />
        </div>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppModal from '../common/AppModal.vue'
import AppDataTable from '../common/AppDataTable.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const { success, error } = useToast()
const auth = useAuthStore()

const canView = computed(() => auth.hasPermission('enrollees.view'))
const canEdit = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollee.update'))
const canApprove = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollee.approve'))
const canReject = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollee.reject'))
const canDelete = computed(() => auth.hasPermission('enrollees.delete'))
const canExport = computed(() => auth.hasPermission('enrollees.export'))

const metadata = reactive({ insurance_programmes: [], enrollee_categories: [], premium_plans: [], facilities: [], lgas: [], wards: [], funding_types: [], benefactors: [], enrollment_phases: [] })
const filters = reactive({ search: '', lga_id: null, ward_id: null, facility_id: null, funding_type_id: null, benefactor_id: null, enrollment_phase_id: null, status: null, coverage_status: null, date_field: 'created_at', date_from: '', date_to: '' })
const enrollees = ref([])
const selected = ref(null)
const detailDrawer = ref(false)
const editDialog = ref(false)
const editForm = reactive({})
const loading = ref(false)
const exporting = ref(false)
const saving = ref(false)
const hasLoaded = ref(false)
const loadError = ref('')
const page = ref(1)
const perPage = ref(50)
const sortBy = ref('created_at')
const sortDirection = ref('desc')
const meta = reactive({ total: 0, from: null, to: null })
const summary = reactive({ total: 0, approved: 0, pending: 0, active_coverage: 0 })

const statusOptions = [
  { title: 'Pending Approval', value: 0 },
  { title: 'Approved', value: 1 },
  { title: 'Rejected', value: 2 },
  { title: 'Active', value: 'active' },
  { title: 'Inactive', value: 4 },
]
const coverageOptions = [
  { title: 'Active Coverage', value: 'active' },
  { title: 'Expired Coverage', value: 'expired' },
  { title: 'No Expiry', value: 'no_expiry' },
  { title: 'Future Coverage', value: 'future' },
]
const dateFieldOptions = [
  { title: 'Created Date', value: 'created_at' },
  { title: 'Enrollment Date', value: 'enrollment_date' },
]
const sexOptions = [{ title: 'Male', value: 1 }, { title: 'Female', value: 2 }, { title: 'Other', value: 3 }]
const headers = [
  { title: 'S/N', key: 'sn', sortable: false, width: 72 },
  { title: 'Enrollee ID', key: 'enrollee', sortable: true },
  { title: 'Full Name', key: 'full_name', sortable: true },
  { title: 'NIN', key: 'nin', sortable: false },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Gender', key: 'gender', sortable: false },
  { title: 'LGA', key: 'lga', sortable: true },
  { title: 'Ward', key: 'ward', sortable: false },
  { title: 'Facility', key: 'facility', sortable: true },
  { title: 'Funding Type', key: 'funding', sortable: false },
  { title: 'Benefactor', key: 'benefactor', sortable: false },
  { title: 'Enrollment Phase', key: 'phase', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const responseNodes = (response) => {
  const root = response?.data || {}
  return [
    root.data?.data?.data,
    root.data?.data,
    root.data,
    root,
  ].filter(Boolean)
}
const responseItems = (response) => {
  for (const node of responseNodes(response)) {
    if (Array.isArray(node)) return node
    if (Array.isArray(node?.data)) return node.data
  }
  return []
}
const responseMeta = (response) => {
  const root = response?.data || {}
  const directMeta = root.data?.data?.meta || root.data?.meta || root.meta
  if (directMeta) return directMeta

  const paginator = responseNodes(response).find((node) => node && typeof node === 'object' && !Array.isArray(node) && 'total' in node)
  return paginator || {}
}
const responseSummary = (response) => {
  const root = response?.data || {}
  return root.data?.data?.summary || root.data?.summary || root.summary || {}
}
const formatNumber = (value) => Number(value || 0).toLocaleString()
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : null)
const relationName = (object) => object?.name || object?.full_name || null

const filteredWards = computed(() => metadata.wards.filter((ward) => !filters.lga_id || Number(ward.lga_id) === Number(filters.lga_id)))
const filteredFacilities = computed(() => metadata.facilities.filter((facility) => {
  if (filters.ward_id) return Number(facility.ward_id) === Number(filters.ward_id)
  if (filters.lga_id) return Number(facility.lga_id) === Number(filters.lga_id)
  return true
}))
const filteredBenefactors = computed(() => {
  const linked = metadata.benefactors.filter((benefactor) => {
    const id = benefactor.funding_type_id || benefactor.funding_type?.id
    return filters.funding_type_id && Number(id) === Number(filters.funding_type_id)
  })
  return filters.funding_type_id && linked.length ? linked : metadata.benefactors
})

const showingText = computed(() => meta.total ? `Showing ${formatNumber(meta.from)}-${formatNumber(meta.to)} of ${formatNumber(meta.total)}` : 'No matching records')
const summaryCards = computed(() => [
  { label: 'Total Matching Enrollees', value: formatNumber(summary.total), icon: 'mdi-account-group-outline', color: 'primary' },
  { label: 'Current Page Loaded', value: formatNumber(enrollees.value.length), helper: showingText.value, icon: 'mdi-table-row', color: 'cyan' },
  { label: 'Approved Enrollees', value: formatNumber(summary.approved), icon: 'mdi-check-decagram-outline', color: 'success' },
  { label: 'Pending Approval', value: formatNumber(summary.pending), icon: 'mdi-clock-outline', color: 'warning' },
  { label: 'Active Coverage', value: formatNumber(summary.active_coverage), icon: 'mdi-shield-check-outline', color: 'teal' },
])

const detailGroups = computed(() => {
  const e = selected.value || {}
  return [
    { title: 'Identity', rows: [
      { label: 'Legacy ID', value: e.legacy_id }, { label: 'NIN', value: e.nin }, { label: 'Gender', value: e.gender },
      { label: 'DOB', value: formatDate(e.date_of_birth) }, { label: 'Phone', value: e.phone }, { label: 'Email', value: e.email },
      { label: 'Address', value: e.address }, { label: 'Village', value: e.village }, { label: 'Occupation', value: e.occupation },
    ] },
    { title: 'Funding & Enrollment', rows: [
      { label: 'Funding Type', value: relationName(e.funding_type) }, { label: 'Benefactor', value: relationName(e.benefactor) },
      { label: 'Enrollment Phase', value: relationName(e.enrollment_phase) }, { label: 'Status', value: e.status_label },
      { label: 'Coverage', value: e.coverage_label }, { label: 'Created', value: formatDate(e.created_at) },
    ] },
    { title: 'Facility', rows: [
      { label: 'Facility', value: relationName(e.facility) }, { label: 'HCP Code', value: e.facility?.hcp_code },
      { label: 'LGA', value: relationName(e.lga) }, { label: 'Ward', value: relationName(e.ward) },
    ] },
  ]
})

const statusColor = (status) => ({ 0: 'warning', 1: 'success', 2: 'error', 3: 'orange', 4: 'grey' }[Number(status)] || 'grey')
const statusLabel = (status) => ({ 0: 'Pending Approval', 1: 'Approved', 2: 'Rejected', 3: 'Suspended', 4: 'Inactive' }[Number(status)] || 'Unknown')
const serialNumber = (index) => ((page.value - 1) * perPage.value) + index + 1

const normalizeMetadata = (data) => {
  metadata.insurance_programmes = data.insurance_programmes || data.programmes || []
  metadata.enrollee_categories = data.enrollee_categories || data.categories || []
  metadata.premium_plans = data.premium_plans || []
  metadata.facilities = data.facilities || []
  metadata.lgas = data.lgas || []
  metadata.wards = data.wards || []
  metadata.funding_types = data.funding_types || []
  metadata.benefactors = data.benefactors || []
  metadata.enrollment_phases = data.enrollment_phases || []
}

const activeFilterParams = () => {
  const params = { ...filters }
  Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null || params[key] === undefined) && delete params[key])
  if (params.status === 'active') {
    delete params.status
    params.coverage_status = 'active'
  }
  return params
}

const tableParams = () => ({
  ...activeFilterParams(),
  page: page.value,
  per_page: perPage.value,
  sort_by: sortBy.value,
  sort_direction: sortDirection.value,
})

const loadMetadata = async () => {
  try {
    const response = await premiumAPI.metadata()
    normalizeMetadata(response.data.data || {})
  } catch (e) {
    error(e.response?.data?.message || 'Failed to load enrollee filters')
  }
}

const applyFilters = () => {
  page.value = 1
  loadEnrollees()
}

const loadEnrollees = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await enrolleeAPI.getAll(tableParams())
    enrollees.value = responseItems(response)
    Object.assign(meta, { total: 0, from: null, to: null }, responseMeta(response))
    Object.assign(summary, { total: meta.total, approved: 0, pending: 0, active_coverage: 0 }, responseSummary(response))
    hasLoaded.value = true
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Failed to load enrollees'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

const resetFilters = () => {
  Object.assign(filters, { search: '', lga_id: null, ward_id: null, facility_id: null, funding_type_id: null, benefactor_id: null, enrollment_phase_id: null, status: null, coverage_status: null, date_field: 'created_at', date_from: '', date_to: '' })
  enrollees.value = []
  Object.assign(meta, { total: 0, from: null, to: null })
  Object.assign(summary, { total: 0, approved: 0, pending: 0, active_coverage: 0 })
  hasLoaded.value = false
}

const handleSort = (items) => {
  const sort = Array.isArray(items) ? items[0] : null
  sortBy.value = sort?.key || 'created_at'
  sortDirection.value = sort?.order || 'desc'
  if (hasLoaded.value) loadEnrollees()
}

const exportExcel = async () => {
  const params = activeFilterParams()
  if (Object.keys(params).length === 0 && !window.confirm('No filters are selected. Export all enrollees? This may take a while.')) return
  exporting.value = true
  try {
    const response = await enrolleeAPI.exportExcel(params)
    const blob = new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    const url = URL.createObjectURL(blob)
    const disposition = response.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename="?([^"]+)"?/i)
    const link = document.createElement('a')
    link.href = url
    link.download = match?.[1] || `enrollees_${new Date().toISOString().slice(0, 10)}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error(e.response?.data?.message || 'Could not export enrollees')
  } finally {
    exporting.value = false
  }
}

const openDetails = async (item) => {
  selected.value = item
  detailDrawer.value = true
  try {
    const response = await enrolleeAPI.getById(item.id)
    selected.value = response.data.data?.data || response.data.data
  } catch {
    selected.value = item
  }
}

const openEdit = (item) => {
  selected.value = item
  Object.keys(editForm).forEach((key) => delete editForm[key])
  Object.assign(editForm, {
    first_name: item.first_name, middle_name: item.middle_name, last_name: item.last_name,
    nin: item.nin, sex: Number(item.sex || (item.gender === 'Male' ? 1 : item.gender === 'Female' ? 2 : 3)),
    date_of_birth: item.date_of_birth?.slice?.(0, 10), phone: item.phone, email: item.email,
    occupation: item.occupation, address: item.address, lga_id: item.lga?.id,
    ward_id: item.ward?.id, facility_id: item.facility?.id, funding_type_id: item.funding_type?.id,
    benefactor_id: item.benefactor?.id, enrollment_phase_id: item.enrollment_phase?.id,
  })
  editDialog.value = true
}

const saveEdit = async () => {
  saving.value = true
  try {
    const response = await enrolleeAPI.update(selected.value.id, editForm)
    success('Enrollee updated')
    editDialog.value = false
    selected.value = response.data.data?.data || response.data.data
    await loadEnrollees()
  } catch (e) {
    error(e.response?.data?.message || 'Could not update enrollee')
  } finally {
    saving.value = false
  }
}

const approveEnrollee = async (item) => {
  try {
    await enrolleeAPI.approve(item.id)
    success('Enrollee approved')
    await loadEnrollees()
  } catch (e) {
    error(e.response?.data?.message || 'Could not approve enrollee')
  }
}

const changeStatus = async (item, status) => {
  try {
    await enrolleeAPI.updateStatus(item.id, { status })
    success('Enrollee status updated')
    await loadEnrollees()
  } catch (e) {
    error(e.response?.data?.message || 'Could not update status')
  }
}

const deleteEnrollee = async (item) => {
  if (!window.confirm(`Delete enrollee ${item.enrollee_id}?`)) return
  try {
    await enrolleeAPI.delete(item.id)
    success('Enrollee deleted')
    await loadEnrollees()
  } catch (e) {
    error(e.response?.data?.message || 'Could not delete enrollee')
  }
}

const printIdCard = async (item) => {
  try {
    const response = await enrolleeAPI.idCard(item.id)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = URL.createObjectURL(blob)
    const win = window.open(url, '_blank')
    if (!win) {
      error('Please allow pop-ups to open the ID card PDF')
      URL.revokeObjectURL(url)
      return
    }
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    error(e.response?.data?.message || 'Could not generate ID card')
  }
}

watch([page, perPage], () => { if (hasLoaded.value) loadEnrollees() })
watch(() => filters.lga_id, () => {
  if (filters.ward_id && !filteredWards.value.some((ward) => Number(ward.id) === Number(filters.ward_id))) filters.ward_id = null
  if (filters.facility_id && !filteredFacilities.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) filters.facility_id = null
})
watch(() => filters.ward_id, () => {
  if (filters.facility_id && !filteredFacilities.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) filters.facility_id = null
})
watch(() => filters.facility_id, (facilityId) => {
  const facility = metadata.facilities.find((item) => Number(item.id) === Number(facilityId))
  if (facility) {
    filters.lga_id = facility.lga_id || filters.lga_id
    filters.ward_id = facility.ward_id || filters.ward_id
  }
})
watch(() => filters.funding_type_id, () => {
  if (filters.benefactor_id && !filteredBenefactors.value.some((benefactor) => Number(benefactor.id) === Number(filters.benefactor_id))) filters.benefactor_id = null
})

onMounted(loadMetadata)
</script>

<style scoped>
.enrollees-table-wrap {
  overflow-x: auto;
}

.enrollees-table-wrap :deep(.v-table__wrapper) {
  max-height: 68vh;
}

.enrollees-table-wrap :deep(thead th) {
  position: sticky;
  top: 0;
  z-index: 2;
  background: rgb(248 250 252);
}
</style>
