<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Facilities" icon="mdi-hospital-building">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadFacilities">Refresh</v-btn>
        <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">Add Facility</v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
        <AppStatCard compact label="Total Facilities" :value="facilityStats.total" icon="mdi-hospital-building" color="primary" :loading="loading" />
        <AppStatCard compact label="Active" :value="facilityStats.active" icon="mdi-check-circle-outline" color="success" :loading="loading" />
        <AppStatCard compact label="Inactive / Pending" :value="facilityStats.inactive" icon="mdi-alert-circle-outline" color="warning" :loading="loading" />
        <AppStatCard compact label="LGAs Covered" :value="facilityStats.lgas" icon="mdi-map-marker-multiple-outline" color="info" :loading="loading" />
      </div>

      <AppFilterBar :active-count="activeFilterCount" :cols="5" @clear="resetFilters">
        <v-text-field
          v-model="searchQuery"
          label="Search facilities"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          @keyup.enter="loadFacilities"
        />
        <v-select
          v-model="filters.type"
          :items="facilityTypes"
          label="Facility Type"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
        <v-select
          v-model="filters.lga_id"
          :items="lgas"
          item-title="name"
          item-value="id"
          label="LGA"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
        <v-select
          v-model="filters.ward_id"
          :items="filteredWards"
          item-title="name"
          item-value="id"
          label="Ward"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
        <v-select
          v-model="filters.status"
          :items="statusOptions"
          item-title="title"
          item-value="value"
          label="Status"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />

        <template #tags>
          <AppBadge v-if="filters.type" :label="`Type: ${filters.type}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.lga_id" :label="`LGA: ${selectedLgaName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.ward_id" :label="`Ward: ${selectedWardName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.status !== null" :label="`Status: ${selectedStatusLabel}`" tone="warning" size="sm" />
        </template>
        <template #actions>
          <v-btn size="small" color="primary" prepend-icon="mdi-filter-check-outline" :loading="loading" @click="loadFacilities">Load</v-btn>
        </template>
      </AppFilterBar>

      <AppCard
        title="Facilities"
        icon="mdi-format-list-bulleted"
        tone="primary"
        :padded="false"
      >
        <AppDataTable
          v-model:page="currentPage"
          v-model:items-per-page="itemsPerPage"
          :headers="headers"
          :items="facilities"
          :items-length="totalFacilities"
          :loading="loading"
          item-value="id"
          class="tw-rounded-none tw-border-0"
          @update:sort-by="onUpdateSort"
        >
          <template #item.name="{ item }">
            <div class="tw-space-y-1">
              <div class="tw-font-semibold tw-text-slate-900">{{ item.name }}</div>
              <div class="tw-text-xs tw-text-slate-500">{{ item.hcp_code || 'No HCP code' }}</div>
            </div>
          </template>
          <template #item.type="{ item }">
            <FacilityBadge :status="item.type || item.level_of_care" :label="item.type || item.level_of_care || 'Unknown'" size="sm" />
          </template>
          <template #item.lga="{ item }">{{ item.lga?.name || 'N/A' }}</template>
          <template #item.ward="{ item }">{{ item.ward?.name || 'N/A' }}</template>
          <template #item.capacity="{ item }">
            <div class="tw-space-y-1">
              <div class="tw-font-semibold tw-text-slate-900">{{ item.enrollees_count ?? item.current_enrollees_count ?? 0 }}</div>
              <div class="tw-text-xs tw-text-slate-500">Configured: {{ item.capacity ?? 0 }}</div>
            </div>
          </template>
          <template #item.status="{ item }">
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppStatusBadge :status="item.status === 1 ? 'Active' : 'Inactive'" :label="item.status === 1 ? 'Active' : 'Inactive'" size="sm" />
              <AppBadge :label="item.accreditation_status || 'active'" :tone="item.accreditation_status === 'suspended' ? 'warning' : item.accreditation_status === 'revoked' ? 'danger' : 'success'" size="sm" />
            </div>
          </template>
          <template #item.created_at="{ item }">
            <DateDisplay :value="item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-items-center tw-justify-end tw-gap-1">
              <v-btn icon size="small" variant="text" title="View" @click="viewFacility(item)">
                <v-icon size="18">mdi-eye-outline</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" title="Edit" @click="editFacility(item)">
                <v-icon size="18">mdi-pencil-outline</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" color="error" title="Delete" @click="openDeleteDialog(item)">
                <v-icon size="18">mdi-delete-outline</v-icon>
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              title="No facilities found"
              description="Adjust your search or filters to find matching facilities."
              icon="mdi-hospital-box-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppModal
        v-model="showAddDialog"
        :title="editingFacility ? 'Edit Facility' : 'Add Facility'"
        icon="mdi-hospital-building"
        size="lg"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="saveFacility">
            Save Facility
          </v-btn>
        </template>

        <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
          <v-text-field v-model="facilityForm.hcp_code" label="HCP Code" variant="outlined" />
          <v-text-field v-model="facilityForm.name" label="Facility Name" variant="outlined" />
          <v-select v-model="facilityForm.ownership" :items="ownershipOptions" label="Ownership" variant="outlined" />
          <v-select v-model="facilityForm.type" :items="facilityTypes" label="Facility Type" variant="outlined" />
          <v-select v-model="facilityForm.lga_id" :items="lgas" item-title="name" item-value="id" label="LGA" variant="outlined" />
          <v-select v-model="facilityForm.ward_id" :items="formWards" item-title="name" item-value="id" label="Ward" variant="outlined" />
          <v-text-field v-model="facilityForm.phone" label="Phone" variant="outlined" />
          <v-text-field v-model="facilityForm.email" label="Email" variant="outlined" />
          <v-text-field v-model="facilityForm.capacity" type="number" label="Capacity" variant="outlined" />
          <v-select v-model="facilityForm.status" :items="statusOptions" item-title="title" item-value="value" label="Status" variant="outlined" />
          <v-select
            v-model="facilityForm.accreditation_status"
            :items="accreditationOptions"
            label="Accreditation Status"
            variant="outlined"
          />
          <v-textarea v-model="facilityForm.address" label="Address" variant="outlined" rows="3" class="md:tw-col-span-2" />
        </div>
      </AppModal>

      <AppModal
        v-model="showViewDialog"
        :title="viewingFacility?.name || 'Facility Details'"
        :subtitle="viewingFacility ? `${viewingFacility.type || 'Facility'} • ${viewingFacility.lga?.name || 'No LGA'}` : ''"
        icon="mdi-office-building-outline"
        size="2xl"
      >
        <template #actions>
          <v-btn variant="outlined" @click="showViewDialog = false">Close</v-btn>
          <v-btn color="primary" variant="flat" @click="viewingFacility && editFacility(viewingFacility)">
            Edit Facility
          </v-btn>
        </template>

        <div v-if="viewingFacility" class="tw-space-y-6">
          <div class="tw-flex tw-flex-wrap tw-gap-2">
            <FacilityBadge :status="viewingFacility.type || viewingFacility.level_of_care" :label="viewingFacility.type || viewingFacility.level_of_care || 'Unknown'" />
            <AppStatusBadge :status="viewingFacility.status === 1 ? 'Active' : 'Inactive'" :label="viewingFacility.status === 1 ? 'Active' : 'Inactive'" />
            <AppBadge :label="viewingFacility.accreditation_status || 'active'" :tone="viewingFacility.accreditation_status === 'suspended' ? 'warning' : viewingFacility.accreditation_status === 'revoked' ? 'danger' : 'success'" />
          </div>

          <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
            <AppStatCard compact label="Current Enrollees" :value="facilityEnrolleeMeta.total" icon="mdi-account-group-outline" color="primary" />
            <AppStatCard compact label="Configured Capacity" :value="Number(viewingFacility.capacity || 0)" icon="mdi-hospital-box-outline" color="secondary" />
            <AppStatCard compact label="Status" :value="viewingFacility.status === 1 ? 'Active' : 'Inactive'" icon="mdi-check-circle-outline" color="success" />
            <AppStatCard compact label="Created" :value="viewingFacility.created_at ? new Date(viewingFacility.created_at).toLocaleDateString() : 'N/A'" icon="mdi-calendar-clock-outline" color="info" />
          </div>

          <AppCard title="Facility Information" icon="mdi-information-outline" tone="secondary">
            <div class="tw-grid tw-gap-4 md:tw-grid-cols-2 xl:tw-grid-cols-3">
              <div><p class="tw-text-xs tw-text-slate-500">HCP Code</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.hcp_code || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">Ownership</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.ownership || viewingFacility.category || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">Type</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.type || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">LGA</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.lga?.name || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">Ward</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.ward?.name || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">Phone</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.phone || 'N/A' }}</p></div>
              <div><p class="tw-text-xs tw-text-slate-500">Email</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.email || 'N/A' }}</p></div>
              <div class="md:tw-col-span-2 xl:tw-col-span-3"><p class="tw-text-xs tw-text-slate-500">Address</p><p class="tw-font-semibold tw-text-slate-900">{{ viewingFacility.address || 'N/A' }}</p></div>
            </div>
          </AppCard>

          <AppCard
            title="Facility Enrollees"
            icon="mdi-account-supervisor-outline"
            tone="primary"
            :padded="false"
          >
            <template #actions>
              <v-text-field
                v-model="enrolleeSearchQuery"
                label="Search enrollees"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="compact"
                clearable
                hide-details
                class="tw-min-w-[220px]"
              />
            </template>

            <AppDataTable
              :headers="enrolleeHeaders"
              :items="facilityEnrollees"
              :items-length="facilityEnrolleeMeta.total"
              :loading="loadingEnrollees"
              item-value="id"
              class="tw-rounded-none tw-border-0"
            >
              <template #item.name="{ item }">
                <div class="tw-space-y-1">
                  <div class="tw-font-semibold tw-text-slate-900">{{ item.full_name || item.name || [item.first_name, item.last_name].filter(Boolean).join(' ') || 'N/A' }}</div>
                  <div class="tw-text-xs tw-text-slate-500">{{ item.enrollee_id || 'No enrollee ID' }}</div>
                </div>
              </template>
              <template #item.type="{ item }">
                <AppBadge :label="item.enrollee_type?.name || item.type || 'N/A'" tone="secondary" size="sm" />
              </template>
              <template #item.status="{ item }">
                <AppStatusBadge :status="item.status_label || item.status || 'Unknown'" :label="item.status_label || item.status || 'Unknown'" size="sm" />
              </template>
              <template #item.phone="{ item }">{{ item.phone || 'N/A' }}</template>
              <template #no-data>
                <AppEmptyState
                  title="No enrollees found"
                  description="This facility has no enrollees matching the current search."
                  icon="mdi-account-group-outline"
                />
              </template>
            </AppDataTable>
          </AppCard>
        </div>
      </AppModal>

      <AppConfirmDialog
        v-model="deleteDialog"
        title="Delete facility"
        subtitle="This action will remove the selected facility after backend confirmation."
        :message="deleteDialogMessage"
        warning="Only delete a facility when you are certain it should no longer exist."
        confirm-text="Delete facility"
        icon="mdi-delete-alert-outline"
        tone="danger"
        :loading="deleting"
        @cancel="closeDeleteDialog"
        @confirm="confirmDelete"
        @update:model-value="handleDeleteDialogChange"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import FacilityBadge from '../common/FacilityBadge.vue'
import { facilityAPI, lgaAPI, wardAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const loading = ref(false)
const loadingEnrollees = ref(false)
const saving = ref(false)
const deleting = ref(false)
const facilities = ref([])
const lgas = ref([])
const wards = ref([])
const totalFacilities = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(15)
const sortBy = ref([{ key: 'created_at', order: 'desc' }])
const searchQuery = ref('')
const enrolleeSearchQuery = ref('')
const searchDebouncer = ref(null)
const enrolleeSearchDebouncer = ref(null)

const showAddDialog = ref(false)
const showViewDialog = ref(false)
const deleteDialog = ref(false)
const editingFacility = ref(null)
const viewingFacility = ref(null)
const deleteTarget = ref(null)

const facilityEnrollees = ref([])
const facilityEnrolleeMeta = reactive({ total: 0 })
const facilityStats = reactive({ total: 0, active: 0, inactive: 0, lgas: 0 })

const filters = reactive({
  type: null,
  lga_id: null,
  ward_id: null,
  status: null,
})

const facilityForm = reactive({
  hcp_code: '',
  name: '',
  ownership: 'Public',
  type: 'Primary',
  address: '',
  phone: '',
  email: '',
  lga_id: null,
  ward_id: null,
  capacity: '',
  status: 1,
  accreditation_status: 'active',
})

const facilityTypes = ['Primary', 'Secondary', 'Tertiary']
const ownershipOptions = ['Public', 'Private', 'Faith-Based']
const accreditationOptions = ['active', 'suspended', 'revoked']
const statusOptions = [
  { title: 'Active', value: 1 },
  { title: 'Inactive', value: 0 },
]

const headers = [
  { title: 'Facility', key: 'name', sortable: true },
  { title: 'Type', key: 'type', sortable: true },
  { title: 'LGA', key: 'lga', sortable: false },
  { title: 'Ward', key: 'ward', sortable: false },
  { title: 'Capacity', key: 'capacity', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const enrolleeHeaders = [
  { title: 'Enrollee', key: 'name', sortable: false },
  { title: 'Type', key: 'type', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Phone', key: 'phone', sortable: false },
]

const activeFilterCount = computed(() => [searchQuery.value, filters.type, filters.lga_id, filters.ward_id, filters.status].filter((value) => value !== null && value !== '').length)
const filteredWards = computed(() => wards.value.filter((ward) => !filters.lga_id || Number(ward.lga_id) === Number(filters.lga_id)))
const formWards = computed(() => wards.value.filter((ward) => !facilityForm.lga_id || Number(ward.lga_id) === Number(facilityForm.lga_id)))
const selectedLgaName = computed(() => lgas.value.find((item) => Number(item.id) === Number(filters.lga_id))?.name || 'Selected')
const selectedWardName = computed(() => wards.value.find((item) => Number(item.id) === Number(filters.ward_id))?.name || 'Selected')
const selectedStatusLabel = computed(() => statusOptions.find((item) => Number(item.value) === Number(filters.status))?.title || 'Selected')
const deleteDialogMessage = computed(() => deleteTarget.value ? `Delete facility "${deleteTarget.value.name}"?` : 'Delete the selected facility?')

const buildFacilityParams = () => {
  const params = {
    page: currentPage.value,
    per_page: itemsPerPage.value,
    sort_by: sortBy.value[0]?.key || 'created_at',
    sort_direction: sortBy.value[0]?.order || 'desc',
  }

  if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
  if (filters.type) params.type = filters.type
  if (filters.lga_id) params.lga_id = filters.lga_id
  if (filters.ward_id) params.ward_id = filters.ward_id
  if (filters.status !== null) params.status = filters.status

  return params
}

const extractCollection = (response) => {
  const payload = response?.data?.data ?? response?.data ?? []
  if (Array.isArray(payload)) return { items: payload, total: payload.length }
  if (Array.isArray(payload?.data)) return { items: payload.data, total: payload.meta?.total ?? payload.total ?? payload.data.length }
  return { items: [], total: 0 }
}

const loadReferenceData = async () => {
  try {
    const [lgaResponse, wardResponse] = await Promise.all([
      lgaAPI.getAll({ per_page: 500 }),
      wardAPI.getAll({ per_page: 1000 }),
    ])
    lgas.value = extractCollection(lgaResponse).items
    wards.value = extractCollection(wardResponse).items
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load facility reference data')
  }
}

const loadFacilities = async () => {
  loading.value = true
  try {
    const response = await facilityAPI.getAll(buildFacilityParams())
    const { items, total } = extractCollection(response)
    facilities.value = items
    totalFacilities.value = total
    facilityStats.total = total
    facilityStats.active = items.filter((facility) => Number(facility.status) === 1).length
    facilityStats.inactive = items.filter((facility) => Number(facility.status) !== 1).length
    facilityStats.lgas = new Set(items.map((facility) => facility.lga?.id).filter(Boolean)).size
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load facilities')
    facilities.value = []
    totalFacilities.value = 0
  } finally {
    loading.value = false
  }
}

const loadFacilityEnrollees = async () => {
  if (!viewingFacility.value?.id) return
  loadingEnrollees.value = true
  try {
    const response = await facilityAPI.getEnrollees(viewingFacility.value.id, {
      per_page: 50,
      search: enrolleeSearchQuery.value || undefined,
    })
    const { items, total } = extractCollection(response)
    facilityEnrollees.value = items
    facilityEnrolleeMeta.total = total
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load facility enrollees')
    facilityEnrollees.value = []
    facilityEnrolleeMeta.total = 0
  } finally {
    loadingEnrollees.value = false
  }
}

const openCreateDialog = () => {
  editingFacility.value = null
  resetFacilityForm()
  showAddDialog.value = true
}

const editFacility = (facility) => {
  editingFacility.value = facility
  Object.assign(facilityForm, {
    hcp_code: facility.hcp_code || '',
    name: facility.name || '',
    ownership: facility.ownership || facility.category || 'Public',
    type: facility.type || 'Primary',
    address: facility.address || '',
    phone: facility.phone || '',
    email: facility.email || '',
    lga_id: facility.lga?.id || null,
    ward_id: facility.ward?.id || null,
    capacity: facility.capacity ?? '',
    status: Number(facility.status ?? 1),
    accreditation_status: facility.accreditation_status || 'active',
  })
  showViewDialog.value = false
  showAddDialog.value = true
}

const viewFacility = async (facility) => {
  try {
    const response = await facilityAPI.getById(facility.id)
    viewingFacility.value = response.data?.data?.data || response.data?.data || facility
  } catch {
    viewingFacility.value = facility
  }
  showViewDialog.value = true
  enrolleeSearchQuery.value = ''
  await loadFacilityEnrollees()
}

const saveFacility = async () => {
  if (!facilityForm.hcp_code || !facilityForm.name || !facilityForm.lga_id || !facilityForm.ward_id) {
    error('HCP code, facility name, LGA, and ward are required')
    return
  }

  saving.value = true
  try {
    const payload = {
      hcp_code: facilityForm.hcp_code,
      name: facilityForm.name,
      ownership: facilityForm.ownership,
      category: facilityForm.ownership,
      type: facilityForm.type,
      address: facilityForm.address,
      phone: facilityForm.phone,
      email: facilityForm.email,
      lga_id: facilityForm.lga_id,
      ward_id: facilityForm.ward_id,
      capacity: facilityForm.capacity === '' ? null : Number(facilityForm.capacity),
      status: Number(facilityForm.status),
      accreditation_status: facilityForm.accreditation_status,
    }

    if (editingFacility.value) {
      await facilityAPI.update(editingFacility.value.id, payload)
      success('Facility updated successfully')
    } else {
      await facilityAPI.create(payload)
      success('Facility created successfully')
    }

    closeDialog()
    await loadFacilities()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to save facility')
  } finally {
    saving.value = false
  }
}

const closeDialog = () => {
  showAddDialog.value = false
  editingFacility.value = null
  resetFacilityForm()
}

const resetFacilityForm = () => {
  Object.assign(facilityForm, {
    hcp_code: '',
    name: '',
    ownership: 'Public',
    type: 'Primary',
    address: '',
    phone: '',
    email: '',
    lga_id: null,
    ward_id: null,
    capacity: '',
    status: 1,
    accreditation_status: 'active',
  })
}

const openDeleteDialog = (facility) => {
  deleteTarget.value = facility
  deleteDialog.value = true
}

const closeDeleteDialog = () => {
  deleteDialog.value = false
  deleteTarget.value = null
}

const handleDeleteDialogChange = (value) => {
  deleteDialog.value = value
  if (!value) deleteTarget.value = null
}

const confirmDelete = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await facilityAPI.delete(deleteTarget.value.id)
    success('Facility deleted successfully')
    closeDeleteDialog()
    await loadFacilities()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to delete facility')
  } finally {
    deleting.value = false
  }
}

const onUpdateSort = (value) => {
  sortBy.value = value
  loadFacilities()
}

const resetFilters = () => {
  searchQuery.value = ''
  filters.type = null
  filters.lga_id = null
  filters.ward_id = null
  filters.status = null
  currentPage.value = 1
  loadFacilities()
}

watch([currentPage, itemsPerPage], () => {
  loadFacilities()
})

watch(searchQuery, () => {
  clearTimeout(searchDebouncer.value)
  searchDebouncer.value = setTimeout(() => {
    currentPage.value = 1
    loadFacilities()
  }, 350)
})

watch(() => filters.lga_id, () => {
  if (filters.ward_id && !filteredWards.value.some((ward) => Number(ward.id) === Number(filters.ward_id))) {
    filters.ward_id = null
  }
})

watch(() => facilityForm.lga_id, () => {
  if (facilityForm.ward_id && !formWards.value.some((ward) => Number(ward.id) === Number(facilityForm.ward_id))) {
    facilityForm.ward_id = null
  }
})

watch(enrolleeSearchQuery, () => {
  if (!showViewDialog.value) return
  clearTimeout(enrolleeSearchDebouncer.value)
  enrolleeSearchDebouncer.value = setTimeout(() => {
    loadFacilityEnrollees()
  }, 300)
})

onMounted(async () => {
  await loadReferenceData()
  await loadFacilities()
})
</script>
