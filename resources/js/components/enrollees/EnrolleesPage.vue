<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <!-- Page header -->
      <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Enrollees</h1>
          <p class="tw-text-sm tw-text-slate-500">Search, review, edit, and print enrollee records.</p>
        </div>
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <v-btn color="primary" variant="outlined" prepend-icon="mdi-account-check-outline" to="/enrollees/approval">Pending Approval</v-btn>
          <v-btn color="primary" prepend-icon="mdi-account-plus-outline" to="/enrollees/demo-enrollment">New Enrollee</v-btn>
        </div>
      </div>

      <!-- Quick stats -->
      <div class="tw-grid tw-gap-3 md:tw-grid-cols-3 xl:tw-grid-cols-6">
        <div v-for="stat in stats" :key="stat.label" class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
          <p class="tw-text-xs tw-font-medium tw-uppercase tw-tracking-wide tw-text-slate-500">{{ stat.label }}</p>
          <p class="tw-mt-2 tw-text-2xl tw-font-bold tw-text-slate-950">{{ stat.value }}</p>
        </div>
      </div>

      <!-- Filters -->
      <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3 xl:tw-grid-cols-6">
          <v-text-field v-model="filters.search" label="Search ID, name, NIN, phone" density="compact" variant="outlined" prepend-inner-icon="mdi-magnify" clearable @keyup.enter="loadEnrollees" />
          <v-select v-model="filters.status" :items="statusOptions" item-title="title" item-value="value" label="Status" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.insurance_programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.enrollee_category_id" :items="metadata.enrollee_categories" item-title="name" item-value="id" label="Category" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.premium_plan_id" :items="metadata.premium_plans" item-title="name" item-value="id" label="Premium Plan" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.lga_id" :items="metadata.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.ward_id" :items="metadata.wards" item-title="name" item-value="id" label="Ward" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Phase" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.coverage_status" :items="coverageOptions" item-title="title" item-value="value" label="Coverage" density="compact" variant="outlined" clearable />
          <v-text-field v-model="filters.date_from" type="date" label="Enrollment from" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_to" type="date" label="Enrollment to" density="compact" variant="outlined" />
          <v-text-field v-model="filters.approval_date_from" type="date" label="Approval from" density="compact" variant="outlined" />
          <v-text-field v-model="filters.approval_date_to" type="date" label="Approval to" density="compact" variant="outlined" />
          <div class="tw-flex tw-gap-2">
            <v-btn color="primary" prepend-icon="mdi-filter" @click="loadEnrollees">Apply</v-btn>
            <v-btn variant="outlined" @click="clearFilters">Clear</v-btn>
          </div>
        </div>
      </div>

      <!-- Enrollees table -->
      <AppDataTable
        :headers="headers"
        :items="enrollees"
        :loading="loading"
        :items-length="meta.total"
        v-model:page="page"
        v-model:items-per-page="perPage"
        item-value="id"
        hover
      >
        <template #item.name="{ item }">
          <div>
            <button class="tw-font-semibold tw-text-cyan-700 hover:tw-underline" @click="openDetails(item)">{{ item.full_name || item.name }}</button>
            <div class="tw-text-xs tw-text-slate-500">{{ item.enrollee_id }}</div>
          </div>
        </template>
        <template #item.programme="{ item }">{{ item.insurance_programme?.name || 'N/A' }}</template>
        <template #item.premium="{ item }">{{ item.premium_plan?.name || 'N/A' }}</template>
        <template #item.facility="{ item }">{{ item.facility?.name || 'N/A' }}</template>
        <template #item.funding="{ item }">{{ item.funding_type?.name || 'N/A' }}</template>
        <template #item.coverage="{ item }">
          <v-chip size="small" :color="coverageColor(item)" variant="flat">{{ item.coverage_label || 'Pending' }}</v-chip>
        </template>
        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(item.status)" variant="flat">{{ item.status_label }}</v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="tw-flex tw-justify-end tw-gap-1">
            <v-btn icon size="small" variant="text" title="View" @click="openDetails(item)"><v-icon size="18">mdi-eye-outline</v-icon></v-btn>
            <v-btn icon size="small" variant="text" :disabled="!canEdit" title="Edit" @click="openEdit(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
            <v-btn icon size="small" variant="text" color="primary" title="Print ID card" @click="printIdCard(item)"><v-icon size="18">mdi-card-account-details-outline</v-icon></v-btn>
          </div>
        </template>
      </AppDataTable>

      <!-- Detail side drawer -->
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
            <v-btn size="small" color="primary" variant="outlined" :disabled="!canEdit" @click="openEdit(selected)">
              <v-icon start size="16">mdi-pencil</v-icon>Edit
            </v-btn>
            <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-card-account-details-outline" @click="printIdCard(selected)">Print ID Card</v-btn>
          </div>

          <section v-for="group in detailGroups" :key="group.title" class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
            <h3 class="tw-mb-3 tw-text-xs tw-font-bold tw-uppercase tw-tracking-wide tw-text-slate-500">{{ group.title }}</h3>
            <div class="tw-grid tw-gap-3 sm:tw-grid-cols-2">
              <div v-for="row in group.rows" :key="row.label">
                <p class="tw-text-xs tw-text-slate-500">{{ row.label }}</p>
                <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ row.value || 'N/A' }}</p>
              </div>
            </div>
          </section>
        </div>
      </v-navigation-drawer>

      <!-- Edit enrollee modal -->
      <AppModal
        v-model="editDialog"
        title="Edit Enrollee"
        :subtitle="selected ? (selected.full_name || selected.name) : ''"
        icon="mdi-account-edit-outline"
        size="lg"
        :loading="saving"
      >
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
const canEdit = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollee.update'))

const metadata = reactive({ insurance_programmes: [], enrollee_categories: [], premium_plans: [], facilities: [], lgas: [], wards: [], funding_types: [], benefactors: [], enrollment_phases: [] })
const filters = reactive({ search: '', status: null, insurance_programme_id: null, enrollee_category_id: null, premium_plan_id: null, facility_id: null, lga_id: null, ward_id: null, funding_type_id: null, benefactor_id: null, enrollment_phase_id: null, coverage_status: null, date_from: '', date_to: '', approval_date_from: '', approval_date_to: '' })
const enrollees = ref([])
const selected = ref(null)
const detailDrawer = ref(false)
const editDialog = ref(false)
const editForm = reactive({})
const loading = ref(false)
const saving = ref(false)
const page = ref(1)
const perPage = ref(25)
const meta = reactive({ total: 0 })

const statusOptions = [
  { title: 'Pending', value: 0 }, { title: 'Active', value: 1 }, { title: 'Rejected', value: 2 },
  { title: 'Suspended', value: 3 }, { title: 'Expired', value: 4 },
]
const coverageOptions = [
  { title: 'Active coverage', value: 'active' }, { title: 'Expired coverage', value: 'expired' },
  { title: 'No expiry', value: 'no_expiry' }, { title: 'Future coverage', value: 'future' },
]
const sexOptions = [{ title: 'Male', value: 1 }, { title: 'Female', value: 2 }, { title: 'Other', value: 3 }]
const headers = [
  { title: 'Enrollee', key: 'name' },
  { title: 'NIN', key: 'nin' },
  { title: 'Phone', key: 'phone' },
  { title: 'Programme', key: 'programme' },
  { title: 'Premium Plan', key: 'premium' },
  { title: 'Facility', key: 'facility' },
  { title: 'Funding', key: 'funding' },
  { title: 'Coverage', key: 'coverage' },
  { title: 'Status', key: 'status' },
  { title: '', key: 'actions', align: 'end', sortable: false },
]

const responseItems = (response) => response?.data?.data?.data || response?.data?.data || []
const responseMeta = (response) => response?.data?.data?.meta || response?.data?.meta || {}
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : null)
const relationName = (object) => object?.name || object?.full_name || null

const stats = computed(() => {
  const rows = enrollees.value
  return [
    { label: 'Loaded', value: rows.length },
    { label: 'Active', value: rows.filter((x) => Number(x.status) === 1).length },
    { label: 'Pending', value: rows.filter((x) => Number(x.status) === 0).length },
    { label: 'Suspended', value: rows.filter((x) => Number(x.status) === 3).length },
    { label: 'Expired', value: rows.filter((x) => Number(x.status) === 4 || x.coverage_label === 'Expired').length },
    { label: 'No Expiry', value: rows.filter((x) => x.is_no_expiry).length },
  ]
})

const detailGroups = computed(() => {
  const e = selected.value || {}
  return [
    { title: 'Identity', rows: [
      { label: 'Legacy ID', value: e.legacy_id }, { label: 'NIN', value: e.nin }, { label: 'Sex', value: e.gender },
      { label: 'DOB', value: formatDate(e.date_of_birth) }, { label: 'Phone', value: e.phone }, { label: 'Email', value: e.email },
      { label: 'Address', value: e.address }, { label: 'Village', value: e.village }, { label: 'Occupation', value: e.occupation },
    ] },
    { title: 'Programme', rows: [
      { label: 'Programme', value: relationName(e.insurance_programme) }, { label: 'Category', value: relationName(e.enrollee_category) },
      { label: 'Premium Plan', value: relationName(e.premium_plan) }, { label: 'Benefit Package', value: relationName(e.benefit_package) },
      { label: 'Coverage Start', value: formatDate(e.coverage_start_date) }, { label: 'Coverage End', value: e.coverage_end_date ? formatDate(e.coverage_end_date) : 'No Expiry' },
    ] },
    { title: 'Funding', rows: [
      { label: 'Funding Type', value: relationName(e.funding_type) }, { label: 'Benefactor', value: relationName(e.benefactor) },
      { label: 'Enrollment Phase', value: relationName(e.enrollment_phase) },
    ] },
    { title: 'Facility & Approval', rows: [
      { label: 'Facility', value: relationName(e.facility) }, { label: 'HCP Code', value: e.facility?.hcp_code },
      { label: 'LGA', value: relationName(e.lga) }, { label: 'Ward', value: relationName(e.ward) },
      { label: 'Status', value: e.status_label }, { label: 'Approval Date', value: formatDate(e.approval_date) },
      { label: 'Created By', value: relationName(e.creator) }, { label: 'Approved By', value: relationName(e.approver) },
    ] },
    { title: 'Family & Review', rows: [
      { label: 'Relationship', value: e.relationship_to_principal }, { label: 'Principal', value: relationName(e.principal) },
      { label: 'Dependants', value: e.dependants_count }, { label: 'Possible Duplicate', value: e.is_possible_duplicate ? 'Yes' : 'No' },
      { label: 'Duplicate Reviewed', value: e.duplicate_reviewed ? 'Yes' : 'No' },
    ] },
  ]
})

const statusColor = (status) => ({ 0: 'warning', 1: 'success', 2: 'error', 3: 'orange', 4: 'grey' }[Number(status)] || 'grey')
const coverageColor = (item) => item.is_no_expiry ? 'success' : item.coverage_label === 'Expired' ? 'error' : Number(item.status) === 0 ? 'warning' : 'primary'

const loadMetadata = async () => {
  const response = await premiumAPI.metadata()
  Object.assign(metadata, response.data.data || {})
}

const loadEnrollees = async () => {
  loading.value = true
  try {
    const params = { ...filters, page: page.value, per_page: perPage.value }
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key])
    const response = await enrolleeAPI.getAll(params)
    enrollees.value = responseItems(response)
    meta.total = responseMeta(response).total || enrollees.value.length
  } catch (e) {
    error(e.response?.data?.message || 'Failed to load enrollees')
  } finally {
    loading.value = false
  }
}

const clearFilters = () => {
  Object.keys(filters).forEach((key) => { filters[key] = typeof filters[key] === 'string' ? '' : null })
  loadEnrollees()
}

const openDetails = async (item) => {
  selected.value = item
  detailDrawer.value = true
  try {
    const response = await enrolleeAPI.getById(item.id)
    selected.value = response.data.data
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
    selected.value = response.data.data
    await loadEnrollees()
  } catch (e) {
    error(e.response?.data?.message || 'Could not update enrollee')
  } finally {
    saving.value = false
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

watch([page, perPage], loadEnrollees)
onMounted(async () => {
  await loadMetadata()
  await loadEnrollees()
})
</script>
