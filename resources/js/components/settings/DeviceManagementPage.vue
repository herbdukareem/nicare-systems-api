<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="EES Officer Management"
        subtitle="Assign enrollment officers to LGAs and enrollment configurations, monitor devices, and revoke lost phones."
        icon="mdi-tablet-dashboard"
      >
        <v-btn color="primary" prepend-icon="mdi-account-plus" @click="openCreateAssignmentForm">Assign officer</v-btn>
      </AppPageHeader>

      <AppTabs v-model="activeTab" :tabs="tabs">
        <template v-if="activeTab === 'devices'">
          <AppDataTable
            v-model:page="devicePage"
            v-model:items-per-page="devicePerPage"
            v-model:search="deviceSearch"
            :headers="deviceHeaders"
            :items="devices"
            :items-length="deviceTotal"
            :loading="loadingDevices"
            searchable
            search-placeholder="Search officers, usernames, emails, or devices"
            @update:page="loadDevices"
            @update:items-per-page="loadDevices"
            @search="handleDeviceSearch"
          >
            <template #item.officer="{ item }">
              <div class="tw-text-sm">
                <div class="tw-font-medium tw-text-slate-800">{{ officerLabel(item.user) }}</div>
                <div class="tw-text-xs tw-text-slate-400">{{ item.user?.email || 'No email' }}</div>
              </div>
            </template>
            <template #item.device="{ item }">
              <div class="tw-text-sm tw-text-slate-700">
                <div>{{ item.device_name || item.device_uuid }}</div>
                <div class="tw-text-xs tw-text-slate-400">{{ item.platform || 'Unknown platform' }} · {{ item.app_version || 'No version' }}</div>
              </div>
            </template>
            <template #item.status="{ item }">
              <AppBadge :label="item.revoked_at ? 'Revoked' : 'Active'" :tone="item.revoked_at ? 'danger' : 'success'" size="sm" />
            </template>
            <template #item.actions="{ item }">
              <v-btn
                :disabled="Boolean(item.revoked_at)"
                color="error"
                size="small"
                variant="text"
                prepend-icon="mdi-cellphone-remove"
                @click="revoke(item)"
              >
                Revoke
              </v-btn>
            </template>
          </AppDataTable>
        </template>

        <template v-else>
          <div ref="assignmentFormSection">
            <AppCard
              :title="isEditingAssignment ? 'Edit Officer Assignment' : 'Officer Assignment'"
              :subtitle="isEditingAssignment
                ? 'Update the officer, assigned local government, and enrollment configuration for this single assignment record.'
                : 'Choose the officer, allowed local governments, and the enrollment form schemas they can use in EES.'"
              icon="mdi-account-check-outline"
            >
            <div class="tw-grid tw-grid-cols-1 tw-gap-3 lg:tw-grid-cols-4">
              <v-autocomplete
                ref="assignmentOfficerField"
                v-model="assignmentForm.user_id"
                :items="officers"
                :item-title="officerLabel"
                item-value="id"
                label="Enrollment officer"
                variant="outlined"
                density="compact"
                clearable
              />
              <v-autocomplete
                v-model="assignmentLgaSelection"
                :items="lgas"
                item-title="name"
                item-value="id"
                :label="isEditingAssignment ? 'Assigned LGA' : 'Assigned LGAs'"
                variant="outlined"
                density="compact"
                :multiple="!isEditingAssignment"
                :chips="!isEditingAssignment"
                :closable-chips="!isEditingAssignment"
                clearable
              />
              <v-autocomplete
                v-model="assignmentSchemaSelection"
                :items="publishedSchemas"
                :item-title="schemaLabel"
                item-value="id"
                :label="isEditingAssignment ? 'Enrollment configuration' : 'Enrollment configurations'"
                variant="outlined"
                density="compact"
                :multiple="!isEditingAssignment"
                :chips="!isEditingAssignment"
                :closable-chips="!isEditingAssignment"
                clearable
              />
              <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-3">
                <v-switch v-model="assignmentForm.enabled" label="Assignment active" color="primary" hide-details />
                <v-btn color="primary" :loading="savingAssignment" prepend-icon="mdi-content-save" @click="saveAssignment">
                  {{ isEditingAssignment ? 'Update assignment' : 'Save' }}
                </v-btn>
                <v-btn
                  v-if="isEditingAssignment"
                  variant="text"
                  color="default"
                  prepend-icon="mdi-close-circle-outline"
                  @click="resetAssignmentForm"
                >
                  Cancel edit
                </v-btn>
              </div>
            </div>

            <div class="tw-mt-3 tw-flex tw-flex-wrap tw-items-center tw-gap-2">
              <AppBadge v-if="isEditingAssignment" label="Editing single assignment record" tone="warning" size="sm" />
              <AppBadge label="Empty LGA = all LGAs" tone="info" size="sm" />
              <AppBadge label="Empty configuration = all published mobile schemas" tone="info" size="sm" />
              <AppBadge v-if="selectedOfficer" :label="selectedOfficer.mobile_enrollment_enabled ? 'Officer enabled' : 'Officer disabled'" :tone="selectedOfficer.mobile_enrollment_enabled ? 'success' : 'danger'" size="sm" />
              <v-btn
                v-if="selectedOfficer"
                size="small"
                variant="tonal"
                :color="selectedOfficer.mobile_enrollment_enabled ? 'error' : 'primary'"
                :prepend-icon="selectedOfficer.mobile_enrollment_enabled ? 'mdi-account-cancel' : 'mdi-account-check'"
                :loading="savingOfficerStatus"
                @click="toggleSelectedOfficerStatus"
              >
                {{ selectedOfficer.mobile_enrollment_enabled ? 'Disable officer' : 'Enable officer' }}
              </v-btn>
            </div>
            </AppCard>
          </div>

          <AppDataTable
            v-model:page="assignmentPage"
            v-model:items-per-page="assignmentPerPage"
            v-model:search="assignmentSearch"
            :headers="assignmentHeaders"
            :items="assignments"
            :items-length="assignmentTotal"
            :loading="loadingAssignments"
            searchable
            search-placeholder="Search officers, usernames, emails, LGAs, or configurations"
            @update:page="loadAssignments"
            @update:items-per-page="loadAssignments"
            @search="handleAssignmentSearch"
          >
            <template #item.officer="{ item }">
              <div class="tw-text-sm">
                <div class="tw-font-medium tw-text-slate-800">{{ officerLabel(item.officer) }}</div>
                <div class="tw-flex tw-flex-wrap tw-gap-1 tw-pt-1">
                  <AppBadge
                    :label="item.officer?.mobile_enrollment_disabled_at ? 'Officer disabled' : 'Officer enabled'"
                    :tone="item.officer?.mobile_enrollment_disabled_at ? 'danger' : 'success'"
                    size="sm"
                  />
                  <AppBadge :label="item.officer?.status === 1 ? 'User active' : 'User inactive'" :tone="item.officer?.status === 1 ? 'success' : 'warning'" size="sm" />
                </div>
              </div>
            </template>
            <template #item.scope="{ item }">
              <div class="tw-text-sm tw-text-slate-700">
                <div>{{ item.lga?.name || 'All LGAs' }}</div>
                <div class="tw-text-xs tw-text-slate-400">{{ schemaLabel(item.schema) }}</div>
              </div>
            </template>
            <template #item.status="{ item }">
              <AppBadge :label="item.enabled ? 'Active assignment' : 'Paused assignment'" :tone="item.enabled ? 'success' : 'warning'" size="sm" />
            </template>
            <template #item.actions="{ item }">
              <div class="tw-flex tw-justify-end tw-gap-1">
                <v-btn
                  size="small"
                  variant="text"
                  color="primary"
                  prepend-icon="mdi-pencil-outline"
                  @click="editAssignment(item)"
                >
                  Edit
                </v-btn>
                <v-btn
                  size="small"
                  variant="text"
                  :color="item.enabled ? 'warning' : 'primary'"
                  :prepend-icon="item.enabled ? 'mdi-pause-circle-outline' : 'mdi-play-circle-outline'"
                  @click="setAssignmentEnabled(item, !item.enabled)"
                >
                  {{ item.enabled ? 'Pause' : 'Activate' }}
                </v-btn>
                <v-btn color="error" size="small" variant="text" prepend-icon="mdi-delete-outline" @click="removeAssignment(item)">Remove</v-btn>
              </div>
            </template>
            <template #no-data>
              <AppEmptyState
                icon="mdi-account-switch-outline"
                title="No officer assignments yet"
                description="Assign an enrollment officer to at least one LGA and enrollment configuration before they can sync mobile enrollment records."
              />
            </template>
          </AppDataTable>
        </template>
      </AppTabs>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useToast } from '../../composables/useToast'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppTabs from '../common/AppTabs.vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { enrollmentSchemaAPI, lgaAPI, officerDeviceAPI, userAPI } from '../../utils/api'

const { success, error } = useToast()

const activeTab = ref('devices')

const deviceHeaders = [
  { title: 'Officer', key: 'officer', sortable: false },
  { title: 'Device', key: 'device', sortable: false },
  { title: 'Last seen', key: 'last_seen_at' },
  { title: 'Status', key: 'status', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

const assignmentHeaders = [
  { title: 'Officer', key: 'officer', sortable: false },
  { title: 'Scope', key: 'scope', sortable: false },
  { title: 'Assigned', key: 'assigned_at' },
  { title: 'Status', key: 'status', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

const devices = ref([])
const deviceTotal = ref(0)
const devicePage = ref(1)
const devicePerPage = ref(20)
const deviceSearch = ref('')
const loadingDevices = ref(false)

const assignments = ref([])
const assignmentTotal = ref(0)
const assignmentPage = ref(1)
const assignmentPerPage = ref(20)
const assignmentSearch = ref('')
const loadingAssignments = ref(false)
const savingAssignment = ref(false)
const savingOfficerStatus = ref(false)
const editingAssignmentId = ref(null)

const officers = ref([])
const lgas = ref([])
const schemas = ref([])
const assignmentFormSection = ref(null)
const assignmentOfficerField = ref(null)

const assignmentForm = reactive({
  user_id: null,
  lga_ids: [],
  enrollment_form_schema_ids: [],
  enabled: true,
})

const tabs = computed(() => [
  { value: 'devices', label: 'Devices', icon: 'mdi-tablet-dashboard', badge: deviceTotal.value || '' },
  { value: 'assignments', label: 'Officer Assignments', icon: 'mdi-account-switch-outline', badge: assignmentTotal.value || '' },
])

const selectedOfficer = computed(() => officers.value.find((officer) => Number(officer.id) === Number(assignmentForm.user_id)) || null)
const publishedSchemas = computed(() => schemas.value.filter((schema) => schema.status === 'published'))
const assignableOfficerRoles = new Set(['enrollment-officer', 'mobile-enrollment-officer'])
const isEditingAssignment = computed(() => editingAssignmentId.value !== null)

const normalizeIdArray = (value) => {
  if (Array.isArray(value)) {
    return value
      .map((item) => Number(item))
      .filter((item) => Number.isFinite(item) && item > 0)
  }

  const normalized = Number(value)
  return Number.isFinite(normalized) && normalized > 0 ? [normalized] : []
}

const assignmentLgaSelection = computed({
  get: () => (isEditingAssignment.value ? assignmentForm.lga_ids[0] ?? null : assignmentForm.lga_ids),
  set: (value) => {
    assignmentForm.lga_ids = isEditingAssignment.value
      ? normalizeIdArray(value).slice(0, 1)
      : normalizeIdArray(value)
  },
})

const assignmentSchemaSelection = computed({
  get: () => (isEditingAssignment.value ? assignmentForm.enrollment_form_schema_ids[0] ?? null : assignmentForm.enrollment_form_schema_ids),
  set: (value) => {
    assignmentForm.enrollment_form_schema_ids = isEditingAssignment.value
      ? normalizeIdArray(value).slice(0, 1)
      : normalizeIdArray(value)
  },
})

const pageItems = (response, nestedKey = null) => {
  const payload = response.data?.data
  const page = nestedKey ? payload?.[nestedKey] : payload
  return {
    items: page?.data || [],
    total: page?.total || page?.data?.length || 0,
  }
}

const collectionItems = (response) => {
  const payload = response.data?.data
  return payload?.data || payload || []
}

const normalizeRoleToken = (value) => String(value || '')
  .trim()
  .toLowerCase()
  .replace(/\s+/g, '-')

const isAssignableOfficer = (user) => {
  const roles = Array.isArray(user?.roles) ? user.roles : []
  return roles.some((role) => assignableOfficerRoles.has(normalizeRoleToken(role?.name) || normalizeRoleToken(role?.label)))
}

const mergeUsersById = (...collections) => {
  const merged = new Map()

  collections.flat().forEach((user) => {
    if (!user?.id) return
    merged.set(Number(user.id), user)
  })

  return Array.from(merged.values()).sort((left, right) => officerLabel(left).localeCompare(officerLabel(right)))
}

const officerLabel = (officer) => {
  if (!officer) return 'Unknown officer'
  return officer.name || officer.username || officer.email || `Officer #${officer.id}`
}

const schemaLabel = (schema) => {
  if (!schema) return 'All published mobile schemas'
  const scope = schema.plan?.name || schema.programme?.name || 'Mobile enrollment'
  return `${schema.name || scope} · v${schema.version || 1}`
}

const extractErrorMessage = (err, fallback) => {
  const validationErrors = err?.response?.data?.errors
  if (validationErrors && typeof validationErrors === 'object') {
    const firstValidationError = Object.values(validationErrors).flat().find(Boolean)
    if (firstValidationError) {
      return String(firstValidationError)
    }
  }

  return err?.response?.data?.message || fallback
}

const focusAssignmentOfficerField = () => {
  assignmentOfficerField.value?.focus?.()
  assignmentOfficerField.value?.$el?.querySelector?.('input')?.focus?.()
}

const resetAssignmentForm = () => {
  editingAssignmentId.value = null
  assignmentForm.user_id = null
  assignmentForm.lga_ids = []
  assignmentForm.enrollment_form_schema_ids = []
  assignmentForm.enabled = true
}

const openAssignmentForm = async () => {
  activeTab.value = 'assignments'
  await nextTick()
  assignmentFormSection.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
  focusAssignmentOfficerField()
}

const openCreateAssignmentForm = async () => {
  resetAssignmentForm()
  await openAssignmentForm()
}

const editAssignment = async (item) => {
  editingAssignmentId.value = Number(item.id)
  assignmentForm.user_id = Number(item.user_id || item.officer?.id || 0) || null
  assignmentForm.lga_ids = item.lga_id ? [Number(item.lga_id)] : []
  assignmentForm.enrollment_form_schema_ids = item.enrollment_form_schema_id ? [Number(item.enrollment_form_schema_id)] : []
  assignmentForm.enabled = Boolean(item.enabled)
  await openAssignmentForm()
}

const loadDevices = async () => {
  loadingDevices.value = true
  try {
    const response = await officerDeviceAPI.list({
      page: devicePage.value,
      per_page: devicePerPage.value,
      search: deviceSearch.value || undefined,
    })
    const payload = pageItems(response)
    devices.value = payload.items
    deviceTotal.value = payload.total
  } finally {
    loadingDevices.value = false
  }
}

const loadAssignments = async () => {
  loadingAssignments.value = true
  try {
    const response = await officerDeviceAPI.assignments({
      page: assignmentPage.value,
      per_page: assignmentPerPage.value,
      search: assignmentSearch.value || undefined,
    })
    const payload = pageItems(response)
    assignments.value = payload.items
    assignmentTotal.value = payload.total
  } finally {
    loadingAssignments.value = false
  }
}

const handleDeviceSearch = async () => {
  devicePage.value = 1
  await loadDevices()
}

const handleAssignmentSearch = async () => {
  assignmentPage.value = 1
  await loadAssignments()
}

const loadMetadata = async () => {
  const [mobileOfficerResponse, enrollmentOfficerResponse, allUsersResponse, lgaResponse, schemaResponse] = await Promise.all([
    userAPI.getWithRoles({ role: 'mobile-enrollment-officer', per_page: 500 }),
    userAPI.getWithRoles({ role: 'enrollment-officer', per_page: 500 }),
    userAPI.getWithRoles({ per_page: 500 }),
    lgaAPI.getAll({ per_page: 500 }),
    enrollmentSchemaAPI.list({ channel: 'mobile', per_page: 500 }),
  ])

  const mobileOfficerItems = collectionItems(mobileOfficerResponse)
  const enrollmentOfficerItems = collectionItems(enrollmentOfficerResponse)
  const fallbackOfficerItems = collectionItems(allUsersResponse).filter(isAssignableOfficer)

  officers.value = mergeUsersById(mobileOfficerItems, enrollmentOfficerItems, fallbackOfficerItems)
  lgas.value = collectionItems(lgaResponse)
  schemas.value = pageItems(schemaResponse, 'schemas').items
}

const revoke = async (item) => {
  try {
    await officerDeviceAPI.revoke(item.id)
    success('Device revoked')
    await loadDevices()
  } catch (err) {
    error(extractErrorMessage(err, 'Failed to revoke device'))
  }
}

const saveAssignment = async () => {
  if (!assignmentForm.user_id) {
    error('Select an enrollment officer before saving.')
    return
  }
  savingAssignment.value = true
  try {
    if (isEditingAssignment.value) {
      await officerDeviceAPI.updateAssignment(editingAssignmentId.value, {
        user_id: assignmentForm.user_id,
        lga_id: assignmentForm.lga_ids[0] ?? null,
        enrollment_form_schema_id: assignmentForm.enrollment_form_schema_ids[0] ?? null,
        enabled: assignmentForm.enabled,
      })
      success('Officer assignment updated')
      resetAssignmentForm()
    } else {
      await officerDeviceAPI.assignEnrollment({
        user_id: assignmentForm.user_id,
        lga_ids: assignmentForm.lga_ids,
        enrollment_form_schema_ids: assignmentForm.enrollment_form_schema_ids,
        enabled: assignmentForm.enabled,
      })
      success('Officer assignment saved')
      assignmentForm.lga_ids = []
      assignmentForm.enrollment_form_schema_ids = []
      assignmentForm.enabled = true
    }

    await loadAssignments()
  } catch (err) {
    error(extractErrorMessage(err, isEditingAssignment.value ? 'Failed to update officer assignment' : 'Failed to save officer assignment'))
  } finally {
    savingAssignment.value = false
  }
}

const setAssignmentEnabled = async (item, enabled) => {
  try {
    await officerDeviceAPI.updateAssignment(item.id, {
      user_id: item.user_id || item.officer?.id,
      lga_id: item.lga_id ?? null,
      enrollment_form_schema_id: item.enrollment_form_schema_id ?? null,
      enabled,
    })
    if (Number(editingAssignmentId.value) === Number(item.id)) {
      assignmentForm.enabled = Boolean(enabled)
    }
    success(enabled ? 'Assignment activated' : 'Assignment paused')
    await loadAssignments()
  } catch (err) {
    error(extractErrorMessage(err, 'Failed to update assignment status'))
  }
}

const removeAssignment = async (item) => {
  try {
    await officerDeviceAPI.removeAssignment(item.id)
    if (Number(editingAssignmentId.value) === Number(item.id)) {
      resetAssignmentForm()
    }
    success('Officer assignment removed')
    await loadAssignments()
  } catch (err) {
    error(extractErrorMessage(err, 'Failed to remove officer assignment'))
  }
}

const toggleSelectedOfficerStatus = async () => {
  if (!selectedOfficer.value) return
  savingOfficerStatus.value = true
  try {
    await officerDeviceAPI.setEnrollmentStatus(selectedOfficer.value.id, !selectedOfficer.value.mobile_enrollment_enabled)
    success(selectedOfficer.value.mobile_enrollment_enabled ? 'Officer disabled' : 'Officer enabled')
    await Promise.all([loadMetadata(), loadAssignments(), loadDevices()])
  } catch (err) {
    error(extractErrorMessage(err, 'Failed to update officer enrollment status'))
  } finally {
    savingOfficerStatus.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadDevices(), loadAssignments(), loadMetadata()])
})
</script>
