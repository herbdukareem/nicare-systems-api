<template>
  <AdminLayout>
  <div class="tw-space-y-5">
    <AppPageHeader
      title="Enrollment Configuration"
      subtitle="Publish mobile enrollment form schemas, programme rules, offline capture policy, and NIN requirements."
      icon="mdi-form-select"
    >
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">New schema</v-btn>
    </AppPageHeader>

    <AppCard
      title="Daily Enrollment Window"
      subtitle="Define when officers can capture and queue mobile enrollments. The mobile form locks outside this window and the backend rejects new enrollment sync submissions too."
      icon="mdi-clock-time-eight-outline"
      tone="warning"
    >
      <template #actions>
        <AppBadge :label="enrollmentWindowStatusLabel" :tone="enrollmentWindowStatusTone" size="sm" />
        <v-btn
          color="primary"
          prepend-icon="mdi-content-save-outline"
          :loading="windowSaving"
          @click="saveEnrollmentWindow"
        >
          Save Window
        </v-btn>
      </template>

      <div class="tw-space-y-4">
        <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
          <div class="tw-flex tw-items-start tw-gap-3">
            <v-switch
              v-model="enrollmentWindow.enabled"
              color="primary"
              hide-details
              inset
              label="Enforce daily time restriction"
            />
            <div class="tw-space-y-1">
              <p class="tw-text-sm tw-font-medium tw-text-slate-800">The schedule is evaluated in {{ enrollmentWindow.timezone }}.</p>
              <p class="tw-text-sm tw-text-slate-500">{{ enrollmentWindow.message }}</p>
            </div>
          </div>
          <AppBadge :label="enrollmentWindow.timezone" tone="info" size="sm" />
        </div>

        <v-alert v-if="windowError" type="error" variant="tonal" density="compact">
          {{ windowError }}
        </v-alert>

        <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-slate-50/70 tw-p-3">
          <div class="tw-hidden tw-grid-cols-[minmax(0,1fr),auto,minmax(0,16rem),auto] tw-gap-3 tw-border-b tw-border-slate-200 tw-pb-2 md:tw-grid">
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Day</p>
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Open</p>
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Window</p>
            <p class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Actions</p>
          </div>

          <div class="tw-space-y-3 md:tw-space-y-0">
            <div
              v-for="day in enrollmentWindowDays"
              :key="day.key"
              class="tw-grid tw-gap-3 tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-3 md:tw-grid-cols-[minmax(0,1fr),auto,minmax(0,16rem),auto] md:tw-items-center md:tw-rounded-none md:tw-border-0 md:tw-border-b md:tw-border-slate-200 md:tw-bg-transparent md:tw-px-0"
            >
              <div>
                <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ day.label }}</p>
                <p class="tw-text-xs tw-text-slate-500">{{ dayPreviewLabel(day.key) }}</p>
              </div>

              <v-switch
                v-model="enrollmentWindow.schedule[day.key].enabled"
                color="primary"
                density="compact"
                hide-details
                inset
              />

              <div class="tw-grid tw-grid-cols-2 tw-gap-2">
                <v-text-field
                  v-model="enrollmentWindow.schedule[day.key].start_time"
                  label="Start"
                  type="time"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="!enrollmentWindow.schedule[day.key].enabled"
                />
                <v-text-field
                  v-model="enrollmentWindow.schedule[day.key].end_time"
                  label="End"
                  type="time"
                  variant="outlined"
                  density="compact"
                  hide-details
                  :disabled="!enrollmentWindow.schedule[day.key].enabled"
                />
              </div>

              <div class="tw-flex tw-justify-start md:tw-justify-end">
                <v-btn size="small" variant="text" color="primary" @click="copyWindowToAll(day.key)">
                  Use For All
                </v-btn>
              </div>
            </div>
          </div>
        </div>
      </div>
    </AppCard>

    <AppDataTable
      v-model:page="page"
      v-model:items-per-page="perPage"
      :headers="headers"
      :items="schemas"
      :items-length="total"
      :loading="loading"
      @update:page="loadSchemas"
      @update:items-per-page="loadSchemas"
    >
      <template #item.status="{ item }">
        <AppBadge :label="item.status" :tone="statusTone(item.status)" size="sm" />
      </template>
      <template #item.scope="{ item }">
        <div class="tw-text-sm tw-text-slate-700">
          <div>{{ item.plan?.name || item.programme?.name || 'Default mobile scope' }}</div>
          <div class="tw-text-xs tw-text-slate-400">v{{ item.version }} · {{ item.channel }}</div>
        </div>
      </template>
      <template #item.rules="{ item }">
        <div class="tw-flex tw-flex-wrap tw-gap-1">
          <AppBadge :label="item.requires_nin_verification ? 'Live NIN required' : 'No live NIN'" :tone="item.requires_nin_verification ? 'warning' : 'neutral'" size="sm" />
          <AppBadge :label="item.allow_offline_capture ? 'Offline' : 'Online only'" :tone="item.allow_offline_capture ? 'success' : 'neutral'" size="sm" />
          <AppBadge v-if="item.benefactor_ids?.length" :label="`${item.benefactor_ids.length} benefactors`" tone="info" size="sm" />
          <AppBadge
            :label="item.location_capture_policy?.enabled ? `Location ${String(item.location_capture_policy.mode || 'preferred').replace(/_/g, ' ')}` : 'No location capture'"
            :tone="item.location_capture_policy?.enabled ? 'info' : 'neutral'"
            size="sm"
          />
          <AppBadge
            :label="phasePolicyLabel(item.enrollment_phase_policy)"
            :tone="item.enrollment_phase_policy?.mode === 'select' ? 'warning' : item.enrollment_phase_policy?.mode === 'fixed' ? 'success' : 'neutral'"
            size="sm"
          />
        </div>
      </template>
      <template #item.actions="{ item }">
        <div class="tw-flex tw-gap-1">
          <v-btn icon="mdi-pencil" size="small" variant="text" title="Edit" @click="openEdit(item)" />
          <v-btn icon="mdi-content-copy" size="small" variant="text" title="Create copy" @click="openDuplicate(item)" />
          <v-btn v-if="item.status !== 'published'" icon="mdi-cloud-upload-outline" size="small" variant="text" title="Publish" @click="publishSchema(item)" />
          <v-btn v-if="item.status !== 'revoked'" icon="mdi-cancel" size="small" variant="text" title="Revoke" @click="revokeSchema(item)" />
        </div>
      </template>
    </AppDataTable>

    <v-dialog v-model="dialog" max-width="980" scrollable>
      <div class="schema-dialog-shell tw-bg-white tw-shadow-xl">
        <AppCard :title="dialogTitle" icon="mdi-form-textbox" :padded="true">
          <div class="tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <v-text-field v-model="form.name" label="Name" variant="outlined" density="compact" />
            <v-select v-model="form.status" :items="statusOptions" label="Status" variant="outlined" density="compact" />
            <v-select v-model="form.insurance_programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" variant="outlined" density="compact" clearable />
            <v-select v-model="form.premium_plan_id" :items="filteredPlans" item-title="name" item-value="id" label="Plan" variant="outlined" density="compact" clearable />
            <v-autocomplete
              v-model="form.benefactor_ids"
              :items="metadata.benefactors"
              item-title="name"
              item-value="id"
              label="Available benefactors"
              variant="outlined"
              density="compact"
              multiple
              chips
              closable-chips
              clearable
            />
            <v-text-field v-model.number="form.version" label="Version" type="number" variant="outlined" density="compact" />
            <div class="tw-flex tw-items-center tw-gap-4">
              <v-switch v-model="form.requires_nin_verification" label="Live NIN required" color="primary" hide-details />
              <v-switch v-model="form.allow_offline_capture" label="Offline capture" color="primary" hide-details />
            </div>
          </div>

          <div class="tw-mt-3 tw-rounded-md tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2 tw-text-sm tw-text-slate-600">
            {{ derivedNinPolicyHelp }}
          </div>

          <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <v-select v-model="form.nin_verification_policy.conflict_status" :items="conflictStatusOptions" label="Conflict status" variant="outlined" density="compact" />
            <v-select v-model="form.nin_verification_policy.autofill.overwrite_strategy" :items="overwriteStrategyOptions" label="Autofill overwrite" variant="outlined" density="compact" />
            <v-switch v-model="form.nin_verification_policy.autofill.enabled" label="Autofill from NIN" color="primary" hide-details />
            <v-switch v-model="form.nin_verification_policy.autofill.lock_verified_fields" label="Lock verified fields" color="primary" hide-details />
            <v-combobox
              v-model="form.nin_verification_policy.autofill.editable_fields"
              :items="ninEditableFieldOptions"
              label="Officer editable NIN fields"
              variant="outlined"
              density="compact"
              multiple
              chips
              closable-chips
              clearable
              hint="Leave empty to lock all verified NIN fields. Select fields officers may change after NIN autofill."
              persistent-hint
              class="md:tw-col-span-2"
            />
          </div>

          <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <v-switch v-model="form.location_capture_policy.enabled" label="Capture enrollment location" color="primary" hide-details />
            <v-switch v-model="form.location_capture_policy.allow_submission_without_location" label="Allow submission without location" color="primary" hide-details />
            <v-select
              v-model="form.location_capture_policy.mode"
              :items="locationModeOptions"
              label="Location capture mode"
              variant="outlined"
              density="compact"
            />
            <v-combobox
              v-model="form.location_capture_policy.capture_points"
              :items="locationCapturePointOptions"
              label="Capture points"
              variant="outlined"
              density="compact"
              multiple
              chips
              closable-chips
            />
            <v-text-field
              v-model.number="form.location_capture_policy.minimum_accuracy_meters"
              label="Minimum accuracy (meters)"
              type="number"
              variant="outlined"
              density="compact"
            />
          </div>

          <div class="tw-mt-3 tw-rounded-md tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2 tw-text-sm tw-text-slate-600">
            {{ derivedLocationPolicyHelp }}
          </div>

          <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-3 md:tw-grid-cols-2">
            <v-select
              v-model="form.enrollment_phase_policy.mode"
              :items="phaseModeOptions"
              label="Enrollment phase mode"
              variant="outlined"
              density="compact"
            />
            <v-select
              v-model="form.enrollment_phase_policy.fixed_phase_id"
              :items="filteredEnrollmentPhases"
              item-title="name"
              item-value="id"
              label="Fixed enrollment phase"
              variant="outlined"
              density="compact"
              clearable
              :disabled="form.enrollment_phase_policy.mode !== 'fixed'"
            />
            <v-autocomplete
              v-model="form.enrollment_phase_policy.allowed_phase_ids"
              :items="filteredEnrollmentPhases"
              item-title="name"
              item-value="id"
              label="Officer-selectable phases"
              variant="outlined"
              density="compact"
              multiple
              chips
              closable-chips
              clearable
              class="md:tw-col-span-2"
              :disabled="form.enrollment_phase_policy.mode !== 'select'"
            />
          </div>

          <div class="tw-mt-3 tw-rounded-md tw-border tw-border-slate-200 tw-bg-slate-50 tw-px-3 tw-py-2 tw-text-sm tw-text-slate-600">
            {{ derivedPhasePolicyHelp }}
          </div>

          <div class="tw-mt-4 tw-grid tw-grid-cols-1 tw-gap-3 lg:tw-grid-cols-2">
            <v-textarea v-model="fieldsJson" label="Fields JSON" rows="14" variant="outlined" density="compact" />
            <div class="tw-grid tw-grid-cols-1 tw-gap-3">
              <v-alert type="info" variant="tonal" density="compact">
                NIN autofill map direction is enrollment field to normalized NIN provider_data field. Raw provider response keys are mapped first in NIN Provider Configuration.
              </v-alert>
              <v-textarea
                v-model="ninAutofillFieldsJson"
                label="NIN autofill map JSON: enrollee field -> provider_data field"
                rows="6"
                variant="outlined"
                density="compact"
                hint='Example: "sex": "gender" writes normalized provider_data.gender into the enrollee sex field.'
                persistent-hint
              />
              <v-textarea v-model="uiJson" label="UI / migration hints JSON" rows="6" variant="outlined" density="compact" />
            </div>
          </div>

          <div v-if="formError" class="tw-mt-3 tw-text-sm tw-text-red-600">{{ formError }}</div>

          <div class="schema-dialog-actions tw-mt-4 tw-flex tw-justify-end tw-gap-2">
            <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="saving" @click="saveSchema">Save</v-btn>
          </div>
        </AppCard>
      </div>
    </v-dialog>
  </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { enrollmentSchemaAPI, enrollmentWindowSettingsAPI, premiumAPI } from '../../utils/api'

const enrollmentWindowDays = [
  { key: 'monday', label: 'Monday' },
  { key: 'tuesday', label: 'Tuesday' },
  { key: 'wednesday', label: 'Wednesday' },
  { key: 'thursday', label: 'Thursday' },
  { key: 'friday', label: 'Friday' },
  { key: 'saturday', label: 'Saturday' },
  { key: 'sunday', label: 'Sunday' },
]

const buildDefaultEnrollmentWindowSchedule = () => enrollmentWindowDays.reduce((schedule, day) => {
  schedule[day.key] = {
    enabled: true,
    start_time: '08:00',
    end_time: '17:00',
  }
  return schedule
}, {})

const headers = [
  { title: 'Name', key: 'name' },
  { title: 'Scope', key: 'scope', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Rules', key: 'rules', sortable: false },
  { title: 'Updated', key: 'updated_at' },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

const statusOptions = ['draft', 'published', 'archived', 'revoked']
const conflictStatusOptions = ['requires_review', 'nin_failed']
const overwriteStrategyOptions = ['empty_only', 'always', 'never']
const locationModeOptions = ['disabled', 'preferred', 'required', 'required_on_submit']
const locationCapturePointOptions = ['start', 'submit']
const phaseModeOptions = ['hidden', 'fixed', 'select']
const schemas = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const editingId = ref(null)
const duplicateSourceId = ref(null)
const formError = ref('')
const windowSaving = ref(false)
const windowError = ref('')
const fieldsJson = ref('[]')
const uiJson = ref('{}')
const ninAutofillFieldsJson = ref('{}')
const defaultNinPolicy = ref(null)
const defaultLocationPolicy = ref(null)
const defaultEnrollmentPhasePolicy = ref(null)
const enrollmentWindow = reactive({
  enabled: false,
  timezone: 'Africa/Lagos',
  schedule: buildDefaultEnrollmentWindowSchedule(),
  is_open: true,
  status: 'not_enforced',
  today: null,
  message: 'Daily enrollment time restriction is currently disabled.',
})

const metadata = reactive({
  insurance_programmes: [],
  premium_plans: [],
  benefactors: [],
  enrollment_phases: [],
})

const form = reactive({
  name: '',
  status: 'draft',
  insurance_programme_id: null,
  premium_plan_id: null,
  benefactor_ids: [],
  version: 1,
  requires_nin_verification: false,
  allow_offline_capture: true,
  nin_verification_policy: {
    mode: 'none',
    offline_behavior: 'allow_capture',
    conflict_status: 'requires_review',
    autofill: {
      enabled: false,
      overwrite_strategy: 'empty_only',
      lock_verified_fields: true,
      editable_fields: [],
      fields: {
        first_name: 'first_name',
        middle_name: 'middle_name',
        last_name: 'last_name',
        date_of_birth: 'date_of_birth',
        sex: 'gender',
        phone: 'phone',
        photo: 'photo',
        address: 'address',
      },
    },
  },
  location_capture_policy: {
    enabled: false,
    mode: 'disabled',
    capture_points: ['start', 'submit'],
    minimum_accuracy_meters: 100,
    allow_submission_without_location: true,
  },
  enrollment_phase_policy: {
    mode: 'hidden',
    fixed_phase_id: null,
    allowed_phase_ids: [],
  },
})

const filteredPlans = computed(() => metadata.premium_plans.filter((plan) => !form.insurance_programme_id || Number(plan.insurance_programme_id) === Number(form.insurance_programme_id)))
const filteredEnrollmentPhases = computed(() => metadata.enrollment_phases.filter((phase) => !form.benefactor_ids.length || !phase.benefactor_id || form.benefactor_ids.map(Number).includes(Number(phase.benefactor_id))))
const dialogTitle = computed(() => {
  if (editingId.value) return 'Edit schema'
  if (duplicateSourceId.value) return 'Copy schema'
  return 'New schema'
})
const enrollmentWindowStatusTone = computed(() => {
  if (!enrollmentWindow.enabled) return 'neutral'
  return enrollmentWindow.is_open ? 'success' : 'warning'
})
const enrollmentWindowStatusLabel = computed(() => {
  if (!enrollmentWindow.enabled) return 'Restriction disabled'
  if (enrollmentWindow.is_open) {
    return enrollmentWindow.today?.end_time
      ? `Open now • closes ${enrollmentWindow.today.end_time}`
      : 'Open now'
  }
  return 'Closed now'
})
const ninEditableFieldOptions = computed(() => {
  try {
    const parsed = JSON.parse(ninAutofillFieldsJson.value || '{}')
    return Object.keys(parsed)
  } catch {
    return Object.keys(form.nin_verification_policy.autofill?.fields || {})
  }
})

const statusTone = (status) => ({
  published: 'success',
  draft: 'neutral',
  archived: 'warning',
  revoked: 'danger',
}[status] || 'neutral')

const clonePolicy = (policy = null) => JSON.parse(JSON.stringify(policy || defaultNinPolicy.value || form.nin_verification_policy))

const deriveNinPolicy = (policy = null) => {
  const next = clonePolicy(policy)
  next.autofill = next.autofill || {}

  if (!form.requires_nin_verification) {
    next.mode = 'none'
    next.offline_behavior = form.allow_offline_capture ? 'allow_capture' : 'block_capture'
    return next
  }

  if (form.allow_offline_capture) {
    next.mode = 'live_required'
    next.offline_behavior = 'allow_capture'
    next.autofill.enabled = next.autofill.enabled ?? true
    return next
  }

  next.mode = 'online_only'
  next.offline_behavior = 'block_capture'
  next.autofill.enabled = next.autofill.enabled ?? true
  return next
}

const derivedNinPolicyHelp = computed(() => {
  if (!form.requires_nin_verification) {
    return 'NIN live verification is not required for this enrollment form.'
  }

  if (form.allow_offline_capture) {
    return 'Live NIN is required when online. If the officer is offline, capture is allowed and NIN verification will continue during sync.'
  }

  return 'Live NIN is required before the officer can continue. Offline capture is blocked for this enrollment form.'
})

const derivedLocationPolicyHelp = computed(() => {
  if (!form.location_capture_policy.enabled || form.location_capture_policy.mode === 'disabled') {
    return 'Device GPS capture is disabled for this enrollment form.'
  }

  if (form.location_capture_policy.mode === 'required_on_submit') {
    return 'Location is captured on device and must be available when the officer queues or submits the enrollment.'
  }

  if (form.location_capture_policy.mode === 'required') {
    return 'Location is expected during capture and submit. If GPS is unavailable and submission without location is disabled, queueing will be blocked.'
  }

  return 'Location will be captured on the device when available and stored for approval-time audit review.'
})

const derivedPhasePolicyHelp = computed(() => {
  if (form.enrollment_phase_policy.mode === 'fixed') {
    return 'The selected enrollment phase is applied automatically on mobile and hidden from the officer.'
  }

  if (form.enrollment_phase_policy.mode === 'select') {
    return 'The officer must choose one of the allowed enrollment phases before queueing the record.'
  }

  return 'Enrollment phase is hidden on mobile for this schema.'
})

const phasePolicyLabel = (policy = {}) => {
  if (policy?.mode === 'fixed') return 'Fixed phase'
  if (policy?.mode === 'select') return 'Officer selects phase'
  return 'Phase hidden'
}

const hydrateEnrollmentWindow = (payload = {}) => {
  enrollmentWindow.enabled = Boolean(payload.enabled)
  enrollmentWindow.timezone = payload.timezone || 'Africa/Lagos'
  enrollmentWindowDays.forEach((day) => {
    const dayPayload = payload.schedule?.[day.key] || {}
    enrollmentWindow.schedule[day.key] = {
      enabled: dayPayload.enabled !== false,
      start_time: dayPayload.start_time || '08:00',
      end_time: dayPayload.end_time || '17:00',
    }
  })
  enrollmentWindow.is_open = payload.is_open !== false
  enrollmentWindow.status = payload.status || (enrollmentWindow.enabled ? 'open' : 'not_enforced')
  enrollmentWindow.today = payload.today || null
  enrollmentWindow.message = payload.message || 'Daily enrollment time restriction is currently disabled.'
}

const loadEnrollmentWindow = async () => {
  windowError.value = ''
  try {
    const response = await enrollmentWindowSettingsAPI.getConfig()
    hydrateEnrollmentWindow(response.data?.data || {})
  } catch (error) {
    windowError.value = error.response?.data?.message || error.message || 'Unable to load enrollment window settings.'
  }
}

const saveEnrollmentWindow = async () => {
  windowSaving.value = true
  windowError.value = ''
  try {
    const payload = {
      enabled: enrollmentWindow.enabled,
      timezone: enrollmentWindow.timezone,
      schedule: enrollmentWindowDays.reduce((schedule, day) => {
        schedule[day.key] = {
          enabled: Boolean(enrollmentWindow.schedule[day.key]?.enabled),
          start_time: enrollmentWindow.schedule[day.key]?.start_time || '',
          end_time: enrollmentWindow.schedule[day.key]?.end_time || '',
        }
        return schedule
      }, {}),
    }

    const response = await enrollmentWindowSettingsAPI.updateConfig(payload)
    hydrateEnrollmentWindow(response.data?.data || payload)
  } catch (error) {
    windowError.value = error.response?.data?.message || error.message || 'Unable to save enrollment window settings.'
  } finally {
    windowSaving.value = false
  }
}

const copyWindowToAll = (sourceDayKey) => {
  const source = enrollmentWindow.schedule[sourceDayKey]
  if (!source) return

  enrollmentWindowDays.forEach((day) => {
    enrollmentWindow.schedule[day.key] = {
      enabled: Boolean(source.enabled),
      start_time: source.start_time,
      end_time: source.end_time,
    }
  })
}

const dayPreviewLabel = (dayKey) => {
  const dayWindow = enrollmentWindow.schedule[dayKey]
  if (!dayWindow?.enabled) return 'Closed'
  return `${dayWindow.start_time} - ${dayWindow.end_time}`
}

const setPolicy = (policy = null, requiresNin = false) => {
  const next = clonePolicy(policy)
  const defaultFields = defaultNinPolicy.value?.autofill?.fields || form.nin_verification_policy.autofill?.fields || {}
  const defaultEditableFields = defaultNinPolicy.value?.autofill?.editable_fields || []
  next.autofill = next.autofill || {}
  next.autofill.fields = { ...defaultFields, ...(next.autofill.fields || {}) }
  next.autofill.editable_fields = Array.isArray(next.autofill.editable_fields) ? next.autofill.editable_fields : defaultEditableFields
  if (!policy && requiresNin) {
    next.mode = 'live_required'
    next.offline_behavior = 'defer_until_sync'
    next.autofill.enabled = true
  }
  form.nin_verification_policy = next
  ninAutofillFieldsJson.value = JSON.stringify(next.autofill?.fields || {}, null, 2)
}

const setLocationPolicy = (policy = null) => {
  const next = JSON.parse(JSON.stringify(policy || defaultLocationPolicy.value || form.location_capture_policy))
  next.capture_points = Array.isArray(next.capture_points) && next.capture_points.length ? next.capture_points : ['start', 'submit']
  next.minimum_accuracy_meters = Number.isFinite(Number(next.minimum_accuracy_meters)) ? Number(next.minimum_accuracy_meters) : 100
  next.allow_submission_without_location = next.allow_submission_without_location !== false
  form.location_capture_policy = next
}

const setEnrollmentPhasePolicy = (policy = null) => {
  const next = JSON.parse(JSON.stringify(policy || defaultEnrollmentPhasePolicy.value || form.enrollment_phase_policy))
  next.mode = ['hidden', 'fixed', 'select'].includes(next.mode) ? next.mode : 'hidden'
  next.fixed_phase_id = Number.isFinite(Number(next.fixed_phase_id)) ? Number(next.fixed_phase_id) : null
  next.allowed_phase_ids = Array.isArray(next.allowed_phase_ids) ? next.allowed_phase_ids.map(Number).filter((id) => id > 0) : []
  form.enrollment_phase_policy = next
}

const loadSchemas = async () => {
  loading.value = true
  try {
    const response = await enrollmentSchemaAPI.list({ page: page.value, per_page: perPage.value })
    const payload = response.data?.data?.schemas || {}
    schemas.value = payload.data || []
    total.value = payload.total || schemas.value.length
    if (!fieldsJson.value || fieldsJson.value === '[]') {
      fieldsJson.value = JSON.stringify(response.data?.data?.default_fields || [], null, 2)
    }
    defaultNinPolicy.value = response.data?.data?.default_nin_verification_policy || defaultNinPolicy.value
    defaultLocationPolicy.value = response.data?.data?.default_location_capture_policy || defaultLocationPolicy.value
    defaultEnrollmentPhasePolicy.value = response.data?.data?.default_enrollment_phase_policy || defaultEnrollmentPhasePolicy.value
  } finally {
    loading.value = false
  }
}

const loadMetadata = async () => {
  const response = await premiumAPI.metadata()
  const payload = response.data?.data || {}
  metadata.insurance_programmes = payload.insurance_programmes || payload.programmes || []
  metadata.premium_plans = payload.premium_plans || []
  metadata.benefactors = payload.benefactors || []
  metadata.enrollment_phases = payload.enrollment_phases || []
}

const openCreate = () => {
  editingId.value = null
  duplicateSourceId.value = null
  form.name = 'Mobile enrollment form'
  form.status = 'draft'
  form.insurance_programme_id = null
  form.premium_plan_id = null
  form.benefactor_ids = []
  form.version = 1
  form.requires_nin_verification = false
  form.allow_offline_capture = true
  setPolicy(null, false)
  setLocationPolicy(null)
  setEnrollmentPhasePolicy(null)
  fieldsJson.value = fieldsJson.value || '[]'
  uiJson.value = '{}'
  formError.value = ''
  dialog.value = true
}

const openEdit = (item) => {
  editingId.value = item.id
  duplicateSourceId.value = null
  form.name = item.name
  form.status = item.status
  form.insurance_programme_id = item.insurance_programme_id
  form.premium_plan_id = item.premium_plan_id
  form.benefactor_ids = item.benefactor_ids || []
  form.version = item.version
  form.requires_nin_verification = Boolean(item.requires_nin_verification)
  form.allow_offline_capture = Boolean(item.allow_offline_capture)
  setPolicy(item.nin_verification_policy, Boolean(item.requires_nin_verification))
  setLocationPolicy(item.location_capture_policy)
  setEnrollmentPhasePolicy(item.enrollment_phase_policy)
  fieldsJson.value = JSON.stringify(item.fields || [], null, 2)
  uiJson.value = JSON.stringify({ ui_schema: item.ui_schema || {}, migration_hints: item.migration_hints || null }, null, 2)
  formError.value = ''
  dialog.value = true
}

const openDuplicate = (item) => {
  editingId.value = null
  duplicateSourceId.value = item.id
  form.name = `${item.name} Copy`
  form.status = 'draft'
  form.insurance_programme_id = item.insurance_programme_id
  form.premium_plan_id = item.premium_plan_id
  form.benefactor_ids = item.benefactor_ids || []
  form.version = Number(item.version || 1) + 1
  form.requires_nin_verification = Boolean(item.requires_nin_verification)
  form.allow_offline_capture = Boolean(item.allow_offline_capture)
  setPolicy(item.nin_verification_policy, Boolean(item.requires_nin_verification))
  setLocationPolicy(item.location_capture_policy)
  setEnrollmentPhasePolicy(item.enrollment_phase_policy)
  fieldsJson.value = JSON.stringify(item.fields || [], null, 2)
  uiJson.value = JSON.stringify({ ui_schema: item.ui_schema || {}, migration_hints: item.migration_hints || null }, null, 2)
  formError.value = ''
  dialog.value = true
}

const payload = () => {
  const ui = JSON.parse(uiJson.value || '{}')
  const policy = deriveNinPolicy(form.nin_verification_policy)
  policy.autofill = policy.autofill || {}
  policy.autofill.fields = JSON.parse(ninAutofillFieldsJson.value || '{}')
  policy.autofill.editable_fields = Array.isArray(policy.autofill.editable_fields) ? policy.autofill.editable_fields : []
  const phasePolicy = JSON.parse(JSON.stringify(form.enrollment_phase_policy || {}))
  if (phasePolicy.mode !== 'fixed') {
    phasePolicy.fixed_phase_id = null
  }
  if (phasePolicy.mode !== 'select') {
    phasePolicy.allowed_phase_ids = []
  }

  return {
    ...form,
    requires_nin_verification: form.requires_nin_verification,
    nin_verification_policy: policy,
    location_capture_policy: form.location_capture_policy,
    enrollment_phase_policy: phasePolicy,
    fields: JSON.parse(fieldsJson.value || '[]'),
    ui_schema: ui.ui_schema || ui,
    migration_hints: ui.migration_hints || null,
  }
}

const saveSchema = async () => {
  saving.value = true
  formError.value = ''
  try {
    if (editingId.value) {
      await enrollmentSchemaAPI.update(editingId.value, payload())
    } else {
      await enrollmentSchemaAPI.create(payload())
    }
    dialog.value = false
    await loadSchemas()
  } catch (error) {
    formError.value = error.response?.data?.message || error.message || 'Unable to save schema.'
  } finally {
    saving.value = false
  }
}

const publishSchema = async (item) => {
  await enrollmentSchemaAPI.publish(item.id)
  await loadSchemas()
}

const revokeSchema = async (item) => {
  await enrollmentSchemaAPI.revoke(item.id)
  await loadSchemas()
}

onMounted(async () => {
  await Promise.all([loadMetadata(), loadSchemas(), loadEnrollmentWindow()])
})
</script>

<style scoped>
.schema-dialog-shell {
  max-height: calc(100vh - 64px);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.schema-dialog-actions {
  position: sticky;
  bottom: 0;
  z-index: 2;
  padding-top: 12px;
  padding-bottom: 4px;
  background: #fff;
  border-top: 1px solid #e2e8f0;
}
</style>
