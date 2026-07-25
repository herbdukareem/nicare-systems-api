<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Enrollees" icon="mdi-account-group-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :disabled="!hasLoaded" @click="loadEnrollees">
          Refresh
        </v-btn>
        <v-btn size="small" variant="outlined" prepend-icon="mdi-account-check-outline" to="/enrollees/approval">
          Pending
        </v-btn>
        <v-btn size="small" variant="outlined" prepend-icon="mdi-filter-remove-outline" @click="resetFilters">
          Reset
        </v-btn>
        <AppExportButton label="Export" :loading="exporting" :disabled="!canExport" @click="exportExcel" />
        <v-btn color="primary" prepend-icon="mdi-account-plus-outline" to="/enrollees/demo-enrollment">
          New Enrollee
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-5">
        <AppStatCard compact label="Total" icon="mdi-account-group-outline" color="primary" :value="summary.total" :loading="loading" />
        <AppStatCard compact label="Loaded" icon="mdi-table-row" color="info" :value="enrollees.length" :loading="loading" />
        <AppStatCard compact label="Approved" icon="mdi-check-decagram-outline" color="success" :value="summary.approved" :loading="loading" />
        <AppStatCard compact label="Pending" icon="mdi-clock-outline" color="warning" :value="summary.pending" :loading="loading" />
        <AppStatCard compact label="Active Coverage" icon="mdi-shield-check-outline" color="secondary" :value="summary.active_coverage" :loading="loading" />
      </div>

      <AppFilterBar :active-count="activeFilterCount" :cols="6" :advanced-cols="4" @clear="resetFilters">
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
          label="Enrollment No"
          placeholder="NGSCHA052361"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-card-account-details-outline"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-text-field
          v-model="filters.nin"
          label="NIN"
          placeholder="38817514594"
          density="compact"
          variant="outlined"
          prepend-inner-icon="mdi-card-bulleted-outline"
          clearable
          hide-details
          @keyup.enter="applyFilters"
        />
        <v-autocomplete
          v-model="filters.lga_id"
          :items="metadata.lgas"
          item-title="name"
          item-value="id"
          label="LGA"
          density="compact"
          variant="outlined"
          clearable
          hide-details
        />
        <v-autocomplete
          v-model="filters.ward_id"
          :items="filteredWards"
          item-title="name"
          item-value="id"
          label="Ward"
          density="compact"
          variant="outlined"
          clearable
          hide-details
          :disabled="!filters.lga_id && filteredWards.length === 0"
        />
        <v-autocomplete
          v-model="filters.facility_id"
          :items="filteredFacilities"
          item-title="name"
          item-value="id"
          label="Facility"
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
            v-model="filters.enrollment_phase_id"
            :items="metadata.enrollment_phases"
            item-title="name"
            item-value="id"
            label="Phase"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
          <v-select
            v-model="filters.date_field"
            :items="dateFieldOptions"
            item-title="title"
            item-value="value"
            label="Date Type"
            density="compact"
            variant="outlined"
            hide-details
          />
          <v-text-field
            v-model="filters.date_from"
            type="date"
            label="Date From"
            density="compact"
            variant="outlined"
            hide-details
          />
          <v-text-field
            v-model="filters.date_to"
            type="date"
            label="Date To"
            density="compact"
            variant="outlined"
            hide-details
          />
          <v-select
            v-model="filters.coverage_status"
            :items="coverageOptions"
            item-title="title"
            item-value="value"
            label="Coverage"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          />
        </template>

        <template #tags>
          <AppBadge v-if="filters.search" :label="`Search: ${filters.search}`" tone="primary" size="sm" />
          <AppBadge v-if="filters.enrollee_id" :label="`Enrollment No: ${filters.enrollee_id}`" tone="primary" size="sm" />
          <AppBadge v-if="filters.nin" :label="`NIN: ${filters.nin}`" tone="primary" size="sm" />
          <AppBadge v-if="filters.lga_id" :label="`LGA: ${selectedLgaName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.ward_id" :label="`Ward: ${selectedWardName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.facility_id" :label="`Facility: ${selectedFacilityName}`" tone="secondary" size="sm" />
          <AppBadge v-if="filters.funding_type_id" :label="`Funding: ${selectedFundingName}`" tone="info" size="sm" />
          <AppBadge v-if="filters.benefactor_id" :label="`Benefactor: ${selectedBenefactorName}`" tone="info" size="sm" />
          <AppBadge v-if="filters.enrollment_phase_id" :label="`Phase: ${selectedPhaseName}`" tone="warning" size="sm" />
          <AppBadge v-if="filters.status !== null && filters.status !== ''" :label="`Status: ${selectedStatusLabel}`" tone="warning" size="sm" />
          <AppBadge v-if="filters.coverage_status" :label="`Coverage: ${selectedCoverageLabel}`" tone="primary" size="sm" />
        </template>
      </AppFilterBar>

      <AppAlert
        v-if="loadError"
        tone="danger"
        :message="loadError"
      />

      <AppCard
        title="Enrollees"
        icon="mdi-table-account"
        tone="primary"
        :padded="false"
      >
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
          :per-page-options="[25, 50, 100, 250]"
          item-value="id"
          hover
          class="tw-rounded-none tw-border-0"
          @update:sort-by="handleSort"
        >
          <template #item.sn="{ index }">{{ serialNumber(index) }}</template>
          <template #item.enrollee="{ item }">
            <button class="tw-text-left tw-font-semibold tw-text-cyan-700 hover:tw-underline" @click="openDetails(item)">
              {{ item.enrollee_id }}
            </button>
            <div v-if="item.legacy_id" class="tw-text-xs tw-text-slate-500">Legacy: {{ item.legacy_id }}</div>
          </template>
          <template #item.full_name="{ item }">
            <div class="tw-font-medium tw-text-slate-900">{{ item.full_name || item.name || 'N/A' }}</div>
          </template>
          <template #item.lga="{ item }">{{ relationName(item.lga) || item.lga_name || 'N/A' }}</template>
          <template #item.ward="{ item }">{{ relationName(item.ward) || item.ward_name || 'N/A' }}</template>
          <template #item.facility="{ item }">
            <FacilityBadge v-if="relationName(item.facility)" :status="item.facility?.type || item.facility?.level_of_care || 'facility'" :label="relationName(item.facility)" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.funding="{ item }">
            <FundingTypeBadge v-if="relationName(item.funding_type)" :label="relationName(item.funding_type)" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.benefactor="{ item }">
            <BenefactorBadge v-if="relationName(item.benefactor)" :label="relationName(item.benefactor)" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.phase="{ item }">
            <AppBadge v-if="relationName(item.enrollment_phase)" :label="relationName(item.enrollment_phase)" tone="warning" size="sm" />
            <span v-else>N/A</span>
          </template>
          <template #item.status="{ item }">
            <EnrolleeStatusBadge
              :status="item.status_label || statusLabel(item.status)"
              :label="item.status_label || statusLabel(item.status)"
              size="sm"
            />
          </template>
          <template #item.created_at="{ item }">
            <DateDisplay :value="item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <v-menu location="bottom end">
              <template #activator="{ props }">
                <v-btn icon size="small" variant="text" v-bind="props" title="Actions">
                  <v-icon size="18">mdi-dots-vertical</v-icon>
                </v-btn>
              </template>
              <v-list density="compact" min-width="210">
                <v-list-item
                  v-if="canView"
                  prepend-icon="mdi-eye-outline"
                  title="View"
                  @click="openDetails(item)"
                />
              <v-list-item
                  v-if="canEdit"
                  prepend-icon="mdi-pencil-outline"
                  title="Edit"
                  @click="openEdit(item)"
                />
                <v-list-item
                  v-if="canChangeStatus"
                  prepend-icon="mdi-swap-horizontal"
                  title="Change status"
                  @click="openStatusDialog(item)"
                />
                <v-list-item
                  v-if="canResetPassword"
                  prepend-icon="mdi-lock-reset"
                  title="Reset portal password"
                  @click="openPasswordDialog(item)"
                />
                <v-list-item
                  prepend-icon="mdi-card-account-details-outline"
                  title="Print ID card"
                  @click="printIdCard(item)"
                />
                <v-list-item
                  v-if="canDelete"
                  prepend-icon="mdi-delete-outline"
                  title="Delete"
                  class="tw-text-red-600"
                  @click="promptDelete(item)"
                />
                <v-list-item
                  v-if="canExport"
                  prepend-icon="mdi-file-excel-outline"
                  title="Export"
                  @click="exportExcel"
                />
              </v-list>
            </v-menu>
          </template>
          <template #no-data>
            <AppEmptyState
              :title="hasLoaded ? 'No enrollees matched these filters' : 'No enrollees loaded yet'"
              :description="hasLoaded
                ? 'Try adjusting LGA, ward, facility, funding type, benefactor, or enrollment phase filters.'
                : 'Apply your filters and load the latest enrollee records from the server.'"
              icon="mdi-account-search-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <v-navigation-drawer v-model="detailDrawer" location="right" temporary width="560">
        <div v-if="selected" class="tw-space-y-4 tw-p-5">
          <div class="tw-flex tw-items-start tw-justify-between tw-gap-4">
            <div class="tw-flex tw-items-start tw-gap-4">
              <div class="tw-flex tw-h-24 tw-w-24 tw-items-center tw-justify-center tw-overflow-hidden tw-rounded-2xl tw-border tw-border-slate-200 tw-bg-slate-100">
                <img
                  v-if="selected.image_url"
                  :src="selected.image_url"
                  :alt="selected.full_name || selected.name || 'Enrollee photo'"
                  class="tw-h-full tw-w-full tw-object-cover"
                />
                <v-icon v-else size="34" color="grey">mdi-account-box-outline</v-icon>
              </div>

              <div>
                <h2 class="tw-text-xl tw-font-bold tw-text-slate-950">{{ selected.full_name || selected.name }}</h2>
                <p class="tw-text-sm tw-text-slate-500">{{ selected.enrollee_id }}</p>
              </div>
            </div>
            <v-btn icon variant="text" @click="detailDrawer = false">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>

          <div class="tw-flex tw-flex-wrap tw-gap-2">
            <EnrolleeStatusBadge
              :status="selected.status_label || statusLabel(selected.status)"
              :label="selected.status_label || statusLabel(selected.status)"
              show-icon
            />
            <FundingTypeBadge v-if="relationName(selected.funding_type)" :label="relationName(selected.funding_type)" />
            <BenefactorBadge v-if="relationName(selected.benefactor)" :label="relationName(selected.benefactor)" />
          </div>

          <div class="tw-flex tw-gap-2">
            <v-btn size="small" color="primary" variant="outlined" :disabled="!canEdit" @click="openEdit(selected)">
              <v-icon start size="16">mdi-pencil</v-icon>
              Edit
            </v-btn>
            <v-btn v-if="canChangeStatus" size="small" color="warning" variant="outlined" prepend-icon="mdi-swap-horizontal" @click="openStatusDialog(selected)">
              Change Status
            </v-btn>
            <v-btn v-if="canResetPassword" size="small" color="secondary" variant="outlined" prepend-icon="mdi-lock-reset" @click="openPasswordDialog(selected)">
              Reset Password
            </v-btn>
            <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-card-account-details-outline" @click="printIdCard(selected)">
              Print ID Card
            </v-btn>
          </div>

          <AppCard
            v-for="group in detailGroups"
            :key="group.title"
            :title="group.title"
            :icon="group.icon"
            tone="secondary"
          >
            <div class="tw-grid tw-gap-3 sm:tw-grid-cols-2">
              <div v-for="row in group.rows" :key="row.label">
                <p class="tw-text-xs tw-text-slate-500">{{ row.label }}</p>
                <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ row.value || 'N/A' }}</p>
              </div>
            </div>
          </AppCard>
        </div>
      </v-navigation-drawer>

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
          <v-btn color="primary" variant="flat" :loading="saving" prepend-icon="mdi-content-save" @click="saveEdit">
            Save Changes
          </v-btn>
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
          <v-autocomplete
            v-model="editForm.occupation"
            :items="occupationOptions"
            label="Occupation"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select v-model="editForm.lga_id" :items="metadata.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" />
          <v-select v-model="editForm.ward_id" :items="metadata.wards" item-title="name" item-value="id" label="Ward" density="compact" variant="outlined" />
          <v-select v-model="editForm.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" />
          <v-select v-model="editForm.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding type" density="compact" variant="outlined" />
          <v-select v-model="editForm.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="editForm.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Enrollment phase" density="compact" variant="outlined" clearable />
          <v-textarea v-model="editForm.address" label="Address" density="compact" variant="outlined" rows="2" class="md:tw-col-span-3" />
        </div>
      </AppModal>

      <AppModal
        v-model="passwordDialog"
        title="Reset Portal Password"
        :subtitle="passwordTarget ? (passwordTarget.full_name || passwordTarget.name || passwordTarget.enrollee_id) : ''"
        icon="mdi-lock-reset"
        size="md"
        :loading="passwordSaving"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="passwordSaving" @click="closePasswordDialog">Cancel</v-btn>
          <v-btn color="secondary" variant="flat" :loading="passwordSaving" prepend-icon="mdi-content-save" @click="savePasswordReset">
            Reset Password
          </v-btn>
        </template>

        <div class="tw-space-y-4">
          <div v-if="passwordTarget" class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <p class="tw-text-xs tw-uppercase tw-tracking-[0.24em] tw-text-slate-500">Portal account</p>
            <div class="tw-mt-2">
              <p class="tw-font-semibold tw-text-slate-900">{{ passwordTarget.full_name || passwordTarget.name }}</p>
              <p class="tw-text-sm tw-text-slate-500">{{ passwordTarget.enrollee_id }}</p>
            </div>
          </div>

          <v-text-field
            v-model="passwordForm.password"
            label="Temporary password"
            type="password"
            density="compact"
            variant="outlined"
          />
          <v-text-field
            v-model="passwordForm.password_confirmation"
            label="Confirm temporary password"
            type="password"
            density="compact"
            variant="outlined"
          />
          <p class="tw-text-sm tw-text-slate-500">
            The enrollee will need to sign in with this password. Existing enrollee portal sessions will be signed out.
          </p>
        </div>
      </AppModal>

      <AppModal
        v-model="statusDialog"
        title="Change Enrollee Status"
        :subtitle="statusTarget ? (statusTarget.full_name || statusTarget.name || statusTarget.enrollee_id) : ''"
        icon="mdi-swap-horizontal"
        size="md"
        :loading="statusSaving"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="statusSaving" @click="closeStatusDialog">Cancel</v-btn>
          <v-btn color="warning" variant="flat" :loading="statusSaving" prepend-icon="mdi-content-save" @click="saveStatusChange">
            Update Status
          </v-btn>
        </template>

        <div class="tw-space-y-4">
          <div v-if="statusTarget" class="tw-rounded-xl tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-4">
            <p class="tw-text-xs tw-uppercase tw-tracking-[0.24em] tw-text-slate-500">Current status</p>
            <div class="tw-mt-2 tw-flex tw-items-center tw-gap-3">
              <EnrolleeStatusBadge
                :status="statusTarget.status_label || statusLabel(statusTarget.status)"
                :label="statusTarget.status_label || statusLabel(statusTarget.status)"
                show-icon
              />
              <span class="tw-text-sm tw-text-slate-500">{{ statusTarget.enrollee_id }}</span>
            </div>
          </div>

          <v-select
            v-model="statusForm.status"
            :items="manageableStatusOptions"
            item-title="title"
            item-value="value"
            label="New status"
            density="compact"
            variant="outlined"
          />
          <v-textarea
            v-model="statusForm.comment"
            label="Comment"
            placeholder="Why is this status changing?"
            density="compact"
            variant="outlined"
            rows="3"
            counter="500"
          />
        </div>
      </AppModal>

      <AppConfirmDialog
        v-model="deleteDialog"
        title="Delete enrollee"
        subtitle="This action permanently removes the enrollee record."
        message="Delete this enrollee from the system?"
        warning="Only proceed if you have confirmed this record should no longer exist."
        confirm-text="Delete enrollee"
        icon="mdi-delete-alert-outline"
        tone="danger"
        :loading="deleting"
        @cancel="closeDeleteDialog"
        @confirm="deleteEnrollee"
        @update:model-value="handleDeleteDialogChange"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppExportButton from '../common/AppExportButton.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppModal from '../common/AppModal.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import BenefactorBadge from '../common/BenefactorBadge.vue'
import DateDisplay from '../common/DateDisplay.vue'
import EnrolleeStatusBadge from '../common/EnrolleeStatusBadge.vue'
import FacilityBadge from '../common/FacilityBadge.vue'
import FundingTypeBadge from '../common/FundingTypeBadge.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const { success, error } = useToast()
const auth = useAuthStore()

const canView = computed(() => auth.hasPermission('enrollees.view'))
const canEdit = computed(() => auth.hasPermission('enrollees.update') || auth.hasPermission('enrollee.update'))
const canChangeStatus = computed(() => auth.hasPermission('enrollee.status.change') || auth.hasPermission('enrollees.update') || auth.hasPermission('enrollees.edit') || auth.hasPermission('enrollee.approve'))
const canResetPassword = computed(() => auth.hasPermission('enrollee.password.reset'))
const canDelete = computed(() => auth.hasPermission('enrollees.delete'))
const canExport = computed(() => auth.hasPermission('enrollees.export'))

const metadata = reactive({
  insurance_programmes: [],
  enrollee_categories: [],
  premium_plans: [],
  facilities: [],
  lgas: [],
  wards: [],
  funding_types: [],
  benefactors: [],
  enrollment_phases: [],
})

const filters = reactive({
  search: '',
  enrollee_id: '',
  nin: '',
  lga_id: null,
  ward_id: null,
  facility_id: null,
  funding_type_id: null,
  benefactor_id: null,
  enrollment_phase_id: null,
  status: null,
  coverage_status: null,
  date_field: 'created_at',
  date_from: '',
  date_to: '',
})

const enrollees = ref([])
const selected = ref(null)
const deleteTarget = ref(null)
const statusTarget = ref(null)
const passwordTarget = ref(null)
const detailDrawer = ref(false)
const editDialog = ref(false)
const statusDialog = ref(false)
const passwordDialog = ref(false)
const deleteDialog = ref(false)
const editForm = reactive({})
const statusForm = reactive({
  status: null,
  comment: '',
})
const passwordForm = reactive({
  password: '',
  password_confirmation: '',
})
const loading = ref(false)
const exporting = ref(false)
const saving = ref(false)
const statusSaving = ref(false)
const passwordSaving = ref(false)
const deleting = ref(false)
const hasLoaded = ref(false)
const loadError = ref('')
const page = ref(1)
const perPage = ref(50)
const sortBy = ref('created_at')
const sortDirection = ref('desc')

const meta = reactive({ total: 0, from: null, to: null })
const summary = reactive({ total: 0, approved: 0, pending: 0, active_coverage: 0 })
const summaryLoading = ref(false)

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

const manageableStatusOptions = [
  { title: 'Pending Approval', value: 0 },
  { title: 'Approved', value: 1 },
  { title: 'Rejected', value: 2 },
  { title: 'Suspended', value: 3 },
  { title: 'Inactive', value: 4 },
]

const dateFieldOptions = [
  { title: 'Created Date', value: 'created_at' },
  { title: 'Enrollment Date', value: 'enrollment_date' },
]

const sexOptions = [
  { title: 'Male', value: 1 },
  { title: 'Female', value: 2 },
  { title: 'Other', value: 3 },
]

const occupationOptions = [
  'Student',
  'Farmer',
  'Trader/Business Owner',
  'Civil Servant',
  'Private Sector Employee',
  'Teacher/Lecturer',
  'Health Worker',
  'Artisan',
  'Driver/Transport Worker',
  'Security Personnel',
  'Religious Leader',
  'Homemaker',
  'Retired',
  'Unemployed',
  'Self-Employed',
  'Other',
  'Not Stated',
]

const occupationAliasMap = {
  'petty trader': 'Trader/Business Owner',
  trader: 'Trader/Business Owner',
  'business owner': 'Trader/Business Owner',
  businessman: 'Trader/Business Owner',
  businesswoman: 'Trader/Business Owner',
  'private employee': 'Private Sector Employee',
  'private sector worker': 'Private Sector Employee',
  teacher: 'Teacher/Lecturer',
  lecturer: 'Teacher/Lecturer',
  'healthcare worker': 'Health Worker',
  driver: 'Driver/Transport Worker',
  'transport worker': 'Driver/Transport Worker',
  'security officer': 'Security Personnel',
  'house wife': 'Homemaker',
  housewife: 'Homemaker',
  'self employed': 'Self-Employed',
  none: 'Not Stated',
  'n a': 'Not Stated',
  na: 'Not Stated',
}

const headers = [
  { title: 'S/N', key: 'sn', sortable: false, width: 72 },
  { title: 'Enrollee ID', key: 'enrollee', sortable: true },
  { title: 'Full Name', key: 'full_name', sortable: true },
  { title: 'NIN', key: 'nin', sortable: false },
  { title: 'Phone', key: 'phone', sortable: false },
  { title: 'Gender', key: 'gender', sortable: false },
  { title: 'LGA', key: 'lga', sortable: true },
  { title: 'Ward', key: 'ward', sortable: false },
  { title: 'Facility', key: 'facility', sortable: false },
  { title: 'Funding Type', key: 'funding', sortable: false },
  { title: 'Benefactor', key: 'benefactor', sortable: false },
  { title: 'Enrollment Phase', key: 'phase', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const responseNodes = (response) => {
  const root = response?.data || {}
  return [root.data?.data?.data, root.data?.data, root.data, root].filter(Boolean)
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
const relationName = (object) => object?.name || object?.full_name || null
const statusLabel = (status) => ({
  0: 'Pending Approval',
  1: 'Approved',
  2: 'Rejected',
  3: 'Suspended',
  4: 'Inactive',
}[Number(status)] || 'Unknown')

const normalizeOccupationValue = (value) => {
  if (!value) return null
  const trimmed = String(value).trim()
  if (!trimmed) return null

  const exact = occupationOptions.find((option) => option.toLowerCase() === trimmed.toLowerCase())
  if (exact) return exact

  const normalized = trimmed.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim()
  return occupationAliasMap[normalized] || trimmed
}

const serialNumber = (index) => ((page.value - 1) * perPage.value) + index + 1

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

const activeFilterCount = computed(() => Object.entries(activeFilterParams()).length)
const showingText = computed(() => meta.total ? `Showing ${formatNumber(meta.from)}-${formatNumber(meta.to)} of ${formatNumber(meta.total)}` : 'No matching records yet')
const matchingRecordsLabel = computed(() => `${formatNumber(meta.total || summary.total)} matching record(s)`)

const findOptionTitle = (items, id) => items.find((item) => Number(item.id) === Number(id))?.name || 'Selected'
const selectedLgaName = computed(() => findOptionTitle(metadata.lgas, filters.lga_id))
const selectedWardName = computed(() => findOptionTitle(metadata.wards, filters.ward_id))
const selectedFacilityName = computed(() => findOptionTitle(metadata.facilities, filters.facility_id))
const selectedFundingName = computed(() => findOptionTitle(metadata.funding_types, filters.funding_type_id))
const selectedBenefactorName = computed(() => findOptionTitle(metadata.benefactors, filters.benefactor_id))
const selectedPhaseName = computed(() => findOptionTitle(metadata.enrollment_phases, filters.enrollment_phase_id))
const selectedStatusLabel = computed(() => statusOptions.find((item) => item.value === filters.status)?.title || 'Selected')
const selectedCoverageLabel = computed(() => coverageOptions.find((item) => item.value === filters.coverage_status)?.title || 'Selected')

const detailGroups = computed(() => {
  const enrollee = selected.value || {}
  return [
    {
      title: 'Identity',
      icon: 'mdi-card-account-details-outline',
      rows: [
        { label: 'Legacy ID', value: enrollee.legacy_id },
        { label: 'NIN', value: enrollee.nin },
        { label: 'Gender', value: enrollee.gender },
        { label: 'DOB', value: enrollee.date_of_birth ? new Date(enrollee.date_of_birth).toLocaleDateString() : null },
        { label: 'Phone', value: enrollee.phone },
        { label: 'Email', value: enrollee.email },
        { label: 'Address', value: enrollee.address },
        { label: 'Village', value: enrollee.village },
        { label: 'Occupation', value: enrollee.occupation },
      ],
    },
    {
      title: 'Funding & Enrollment',
      icon: 'mdi-shield-account-outline',
      rows: [
        { label: 'Funding Type', value: relationName(enrollee.funding_type) },
        { label: 'Benefactor', value: relationName(enrollee.benefactor) },
        { label: 'Enrollment Phase', value: relationName(enrollee.enrollment_phase) },
        { label: 'Status', value: enrollee.status_label || statusLabel(enrollee.status) },
        { label: 'Coverage', value: enrollee.coverage_label },
        { label: 'Created', value: enrollee.created_at ? new Date(enrollee.created_at).toLocaleString() : null },
      ],
    },
    {
      title: 'Facility',
      icon: 'mdi-hospital-box-outline',
      rows: [
        { label: 'Facility', value: relationName(enrollee.facility) },
        { label: 'HCP Code', value: enrollee.facility?.hcp_code },
        { label: 'LGA', value: relationName(enrollee.lga) },
        { label: 'Ward', value: relationName(enrollee.ward) },
      ],
    },
  ]
})

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
  Object.keys(params).forEach((key) => {
    if (params[key] === '' || params[key] === null || params[key] === undefined) {
      delete params[key]
    }
  })
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

const summaryParams = () => ({
  ...activeFilterParams(),
  page: 1,
  per_page: 1,
  include_summary: true,
})

const loadMetadata = async () => {
  try {
    const response = await premiumAPI.metadata()
    normalizeMetadata(response.data.data || {})
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load enrollee filters')
  }
}

const applyFilters = () => {
  page.value = 1
  loadPage()
}

const loadEnrollees = async () => {
  loading.value = true
  loadError.value = ''
  try {
    const response = await enrolleeAPI.getAll(tableParams(), {
      timeout: 60000,
    })
    enrollees.value = responseItems(response)
    Object.assign(meta, { total: 0, from: null, to: null }, responseMeta(response))
    summary.total = meta.total || 0
    hasLoaded.value = true
  } catch (err) {
    loadError.value = err.response?.data?.message || 'Failed to load enrollees'
    error(loadError.value)
  } finally {
    loading.value = false
  }
}

const loadSummary = async () => {
  summaryLoading.value = true
  try {
    const response = await enrolleeAPI.getAll(summaryParams(), {
      timeout: 60000,
      showGlobalLoader: false,
    })
    Object.assign(summary, { total: meta.total || 0, approved: 0, pending: 0, active_coverage: 0 }, responseSummary(response))
  } catch {
    summary.total = meta.total || summary.total || 0
  } finally {
    summaryLoading.value = false
  }
}

const loadPage = async () => {
  await loadEnrollees()
  if (hasLoaded.value) {
    void loadSummary()
  }
}

const resetFilters = () => {
  Object.assign(filters, {
    search: '',
    enrollee_id: '',
    nin: '',
    lga_id: null,
    ward_id: null,
    facility_id: null,
    funding_type_id: null,
    benefactor_id: null,
    enrollment_phase_id: null,
    status: null,
    coverage_status: null,
    date_field: 'created_at',
    date_from: '',
    date_to: '',
  })
  enrollees.value = []
  Object.assign(meta, { total: 0, from: null, to: null })
  Object.assign(summary, { total: 0, approved: 0, pending: 0, active_coverage: 0 })
  hasLoaded.value = false
  loadError.value = ''
}

const handleSort = (items) => {
  const sort = Array.isArray(items) ? items[0] : null
  sortBy.value = sort?.key || 'created_at'
  sortDirection.value = sort?.order || 'desc'
  if (hasLoaded.value) loadPage()
}

const exportExcel = async () => {
  exporting.value = true
  try {
    const response = await enrolleeAPI.exportExcel(activeFilterParams())
    const blob = new Blob([response.data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const disposition = response.headers?.['content-disposition'] || ''
    const match = disposition.match(/filename=\"?([^\"]+)\"?/i)
    const link = document.createElement('a')
    link.href = url
    link.download = match?.[1] || `enrollees_${new Date().toISOString().slice(0, 10)}.xlsx`
    link.click()
    URL.revokeObjectURL(url)
  } catch (err) {
    error(err.response?.data?.message || 'Could not export enrollees')
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
    first_name: item.first_name,
    middle_name: item.middle_name,
    last_name: item.last_name,
    nin: item.nin,
    sex: Number(item.sex || (item.gender === 'Male' ? 1 : item.gender === 'Female' ? 2 : 3)),
    date_of_birth: item.date_of_birth?.slice?.(0, 10),
    phone: item.phone,
    email: item.email,
    occupation: normalizeOccupationValue(item.occupation),
    address: item.address,
    lga_id: item.lga?.id,
    ward_id: item.ward?.id,
    facility_id: item.facility?.id,
    funding_type_id: item.funding_type?.id,
    benefactor_id: item.benefactor?.id,
    enrollment_phase_id: item.enrollment_phase?.id,
  })
  editDialog.value = true
}

const openStatusDialog = (item) => {
  if (!canChangeStatus.value) {
    error('You do not have permission to change enrollee statuses.')
    return
  }

  statusTarget.value = item
  selected.value = item
  statusForm.status = Number(item.status)
  statusForm.comment = ''
  statusDialog.value = true
}

const openPasswordDialog = (item) => {
  if (!canResetPassword.value) {
    error('You do not have permission to reset enrollee portal passwords.')
    return
  }

  passwordTarget.value = item
  selected.value = item
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
  passwordDialog.value = true
}

const closeStatusDialog = () => {
  statusDialog.value = false
  statusTarget.value = null
  statusForm.status = null
  statusForm.comment = ''
}

const closePasswordDialog = () => {
  passwordDialog.value = false
  passwordTarget.value = null
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
}

const saveEdit = async () => {
  saving.value = true
  try {
    const response = await enrolleeAPI.update(selected.value.id, editForm)
    success('Enrollee updated')
    editDialog.value = false
    selected.value = response.data.data?.data || response.data.data
    await loadEnrollees()
  } catch (err) {
    error(err.response?.data?.message || 'Could not update enrollee')
  } finally {
    saving.value = false
  }
}

const saveStatusChange = async () => {
  if (!statusTarget.value) return
  if (statusForm.status === null || statusForm.status === undefined || statusForm.status === '') {
    error('Select a new status before saving.')
    return
  }

  statusSaving.value = true
  try {
    const response = await enrolleeAPI.updateStatus(statusTarget.value.id, {
      status: Number(statusForm.status),
      comment: statusForm.comment || null,
    })

    const updated = response.data.data?.data || response.data.data
    if (updated) {
      statusTarget.value = updated
      if (selected.value && Number(selected.value.id) === Number(updated.id)) {
        selected.value = updated
      }
    }

    success('Enrollee status updated')
    closeStatusDialog()
    await loadPage()
  } catch (err) {
    error(err.response?.data?.message || 'Could not update enrollee status')
  } finally {
    statusSaving.value = false
  }
}

const savePasswordReset = async () => {
  if (!passwordTarget.value) return
  if (!passwordForm.password) {
    error('Enter a temporary password before saving.')
    return
  }
  if (passwordForm.password.length < 8) {
    error('Password must be at least 8 characters.')
    return
  }
  if (passwordForm.password !== passwordForm.password_confirmation) {
    error('Password confirmation does not match.')
    return
  }

  passwordSaving.value = true
  try {
    await enrolleeAPI.resetPassword(passwordTarget.value.id, {
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    success('Enrollee portal password reset successfully.')
    closePasswordDialog()
  } catch (err) {
    error(err.response?.data?.message || 'Could not reset enrollee password')
  } finally {
    passwordSaving.value = false
  }
}

const promptDelete = (item) => {
  deleteTarget.value = item
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

const deleteEnrollee = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await enrolleeAPI.delete(deleteTarget.value.id)
    success('Enrollee deleted')
    closeDeleteDialog()
    await loadEnrollees()
  } catch (err) {
    error(err.response?.data?.message || 'Could not delete enrollee')
  } finally {
    deleting.value = false
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
  } catch (err) {
    error(err.response?.data?.message || 'Could not generate ID card')
  }
}

watch([page, perPage], () => {
  if (hasLoaded.value) loadPage()
})

watch(() => filters.lga_id, () => {
  if (filters.ward_id && !filteredWards.value.some((ward) => Number(ward.id) === Number(filters.ward_id))) {
    filters.ward_id = null
  }
  if (filters.facility_id && !filteredFacilities.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) {
    filters.facility_id = null
  }
})

watch(() => filters.ward_id, () => {
  if (filters.facility_id && !filteredFacilities.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) {
    filters.facility_id = null
  }
})

watch(() => filters.facility_id, (facilityId) => {
  const facility = metadata.facilities.find((item) => Number(item.id) === Number(facilityId))
  if (facility) {
    filters.lga_id = facility.lga_id || filters.lga_id
    filters.ward_id = facility.ward_id || filters.ward_id
  }
})

watch(() => filters.funding_type_id, () => {
  if (filters.benefactor_id && !filteredBenefactors.value.some((benefactor) => Number(benefactor.id) === Number(filters.benefactor_id))) {
    filters.benefactor_id = null
  }
})

onMounted(async () => {
  await loadMetadata()
  await loadPage()
})
</script>

<style scoped>
:deep(.v-navigation-drawer__content) {
  background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(255, 255, 255, 1));
}
</style>
