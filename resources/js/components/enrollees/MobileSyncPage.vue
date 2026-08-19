<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader
        title="Mobile Sync Monitor"
        subtitle="Track mobile enrollment sync attempts, review failure reasons, and inspect records that moved cleanly into approval."
        kicker="Enrollment"
        icon="mdi-cellphone-sync"
      >
        <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadMonitor()">
          Refresh
        </v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-grid-cols-2 tw-gap-2 md:tw-grid-cols-4">
        <AppStatCard
          compact
          label="Total attempts"
          icon="mdi-database-outline"
          color="primary"
          :value="summary.total"
          :loading="loading"
        />
        <AppStatCard
          compact
          label="Received today"
          icon="mdi-calendar-today-outline"
          color="info"
          :value="summary.today"
          :loading="loading"
        />
        <AppStatCard
          compact
          label="Ready or approved"
          icon="mdi-check-decagram-outline"
          color="success"
          :value="summary.ready"
          :loading="loading"
        />
        <AppStatCard
          compact
          label="Needs attention"
          icon="mdi-alert-decagram-outline"
          color="warning"
          :value="summary.attention"
          :loading="loading"
        />
      </div>

      <AppFilterBar :active-count="activeFiltersCount" :cols="5" :advanced-cols="3" @clear="clearFilters">
        <v-text-field
          v-model="filters.search"
          label="Search"
          placeholder="Enrollee, officer, batch, client ref, device, or reason"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          @input="debouncedSearch"
        />
        <v-select
          v-model="filters.status"
          label="Status"
          :items="statusSelectItems"
          item-title="label"
          item-value="value"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          @update:model-value="loadMonitor({ resetPage: true })"
        />
        <v-select
          v-model="filters.officer_user_id"
          label="Enrollment officer"
          :items="officerSelectItems"
          item-title="label"
          item-value="value"
          variant="outlined"
          density="compact"
          clearable
          hide-details
          @update:model-value="loadMonitor({ resetPage: true })"
        />
        <div class="tw-grid tw-grid-cols-2 tw-gap-2">
          <v-menu
            v-model="pickerMenus.capturedDateFrom"
            :close-on-content-click="false"
            location="bottom"
          >
            <template #activator="{ props }">
              <v-text-field
                :model-value="displayDateFilter(filters.captured_date_from)"
                label="Enroll from"
                placeholder="Select date"
                prepend-inner-icon="mdi-calendar"
                variant="outlined"
                density="compact"
                clearable
                readonly
                hide-details
                v-bind="props"
                @click:clear="clearFilterValue('captured_date_from', 'capturedDateFrom')"
              />
            </template>
            <v-date-picker
              color="primary"
              :model-value="filters.captured_date_from || null"
              @update:model-value="setDateFilter('captured_date_from', $event, 'capturedDateFrom')"
            />
          </v-menu>
          <v-menu
            v-model="pickerMenus.capturedDateTo"
            :close-on-content-click="false"
            location="bottom"
          >
            <template #activator="{ props }">
              <v-text-field
                :model-value="displayDateFilter(filters.captured_date_to)"
                label="Enroll to"
                placeholder="Select date"
                prepend-inner-icon="mdi-calendar"
                variant="outlined"
                density="compact"
                clearable
                readonly
                hide-details
                v-bind="props"
                @click:clear="clearFilterValue('captured_date_to', 'capturedDateTo')"
              />
            </template>
            <v-date-picker
              color="primary"
              :model-value="filters.captured_date_to || null"
              @update:model-value="setDateFilter('captured_date_to', $event, 'capturedDateTo')"
            />
          </v-menu>
        </div>
        <div class="tw-grid tw-grid-cols-2 tw-gap-2">
          <v-menu
            v-model="pickerMenus.capturedTimeFrom"
            :close-on-content-click="false"
            location="bottom"
          >
            <template #activator="{ props }">
              <v-text-field
                :model-value="displayTimeFilter(filters.captured_time_from)"
                label="Time from"
                placeholder="Select time"
                prepend-inner-icon="mdi-clock-outline"
                variant="outlined"
                density="compact"
                clearable
                readonly
                hide-details
                v-bind="props"
                @click:clear="clearFilterValue('captured_time_from', 'capturedTimeFrom')"
              />
            </template>
            <v-time-picker
              color="primary"
              format="24hr"
              :model-value="filters.captured_time_from || null"
              @update:model-value="setTimeFilter('captured_time_from', $event, 'capturedTimeFrom')"
            />
          </v-menu>
          <v-menu
            v-model="pickerMenus.capturedTimeTo"
            :close-on-content-click="false"
            location="bottom"
          >
            <template #activator="{ props }">
              <v-text-field
                :model-value="displayTimeFilter(filters.captured_time_to)"
                label="Time to"
                placeholder="Select time"
                prepend-inner-icon="mdi-clock-outline"
                variant="outlined"
                density="compact"
                clearable
                readonly
                hide-details
                v-bind="props"
                @click:clear="clearFilterValue('captured_time_to', 'capturedTimeTo')"
              />
            </template>
            <v-time-picker
              color="primary"
              format="24hr"
              :model-value="filters.captured_time_to || null"
              @update:model-value="setTimeFilter('captured_time_to', $event, 'capturedTimeTo')"
            />
          </v-menu>
        </div>

        <template #advanced>
          <v-text-field
            v-model="filters.batch_id"
            label="Batch ID"
            variant="outlined"
            density="compact"
            clearable
            hide-details
            @keyup.enter="loadMonitor({ resetPage: true })"
            @click:clear="loadMonitor({ resetPage: true })"
          />
          <v-text-field
            v-model="filters.device_uuid"
            label="Device"
            variant="outlined"
            density="compact"
            clearable
            hide-details
            @keyup.enter="loadMonitor({ resetPage: true })"
            @click:clear="loadMonitor({ resetPage: true })"
          />
          <div class="tw-grid tw-grid-cols-2 tw-gap-2">
            <v-menu
              v-model="pickerMenus.receivedDateFrom"
              :close-on-content-click="false"
              location="bottom"
            >
              <template #activator="{ props }">
                <v-text-field
                  :model-value="displayDateFilter(filters.date_from)"
                  label="Received from"
                  placeholder="Select date"
                  prepend-inner-icon="mdi-calendar"
                  variant="outlined"
                  density="compact"
                  clearable
                  readonly
                  hide-details
                  v-bind="props"
                  @click:clear="clearFilterValue('date_from', 'receivedDateFrom')"
                />
              </template>
              <v-date-picker
                color="primary"
                :model-value="filters.date_from || null"
                @update:model-value="setDateFilter('date_from', $event, 'receivedDateFrom')"
              />
            </v-menu>
            <v-menu
              v-model="pickerMenus.receivedDateTo"
              :close-on-content-click="false"
              location="bottom"
            >
              <template #activator="{ props }">
                <v-text-field
                  :model-value="displayDateFilter(filters.date_to)"
                  label="Received to"
                  placeholder="Select date"
                  prepend-inner-icon="mdi-calendar"
                  variant="outlined"
                  density="compact"
                  clearable
                  readonly
                  hide-details
                  v-bind="props"
                  @click:clear="clearFilterValue('date_to', 'receivedDateTo')"
                />
              </template>
              <v-date-picker
                color="primary"
                :model-value="filters.date_to || null"
                @update:model-value="setDateFilter('date_to', $event, 'receivedDateTo')"
              />
            </v-menu>
          </div>
        </template>

        <template #tags>
          <AppBadge :label="`Batches ${formatCount(summary.batches)}`" tone="info" size="sm" />
          <AppBadge :label="`Officers ${formatCount(summary.officers)}`" tone="primary" size="sm" />
          <AppBadge :label="`Devices ${formatCount(summary.devices)}`" tone="neutral" size="sm" />
          <AppBadge :label="`Failed ${formatCount(statusCount('sync_failed'))}`" tone="danger" size="sm" />
          <AppBadge :label="`Review ${formatCount(statusCount('requires_review'))}`" tone="warning" size="sm" />
          <AppBadge :label="`Approved ${formatCount(statusCount('approved'))}`" tone="success" size="sm" />
        </template>
      </AppFilterBar>

      <AppCard
        title="Synchronization Attempts"
        subtitle="Compact monitor view for quick triage. Open any row to inspect the full backend reasoning, payload, and audit trail."
        icon="mdi-format-list-bulleted"
        tone="primary"
      >
        <AppDataTable
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="headers"
          :items="records"
          :items-length="total"
          :loading="loading"
          class="tw-mt-4"
          @update:page="loadMonitor"
          @update:items-per-page="loadMonitor"
        >
          <template #item.officer="{ item }">
            <div class="tw-min-w-[11rem] tw-text-sm tw-font-medium tw-text-slate-900">
              {{ item.officer?.name || 'Unknown officer' }}
            </div>
          </template>

          <template #item.attempt="{ item }">
            <div class="tw-flex tw-min-w-[12rem] tw-items-center tw-gap-2 tw-text-sm tw-font-medium tw-text-slate-900">
              <v-icon size="16" color="primary">mdi-calendar-clock-outline</v-icon>
              <DateDisplay :value="item.received_at || item.created_at" format="medium" />
            </div>
          </template>

          <template #item.record="{ item }">
            <div class="tw-min-w-[14rem] tw-space-y-1">
              <div class="tw-font-medium tw-text-slate-900">{{ recordTitle(item) }}</div>
              <div class="tw-text-sm tw-text-slate-600">
                {{ recordNinLabel(item) }}
              </div>
              <div class="tw-text-xs tw-font-medium tw-uppercase tw-tracking-[0.12em] tw-text-slate-500">
                {{ enrolleeIdLabel(item) }}
              </div>
            </div>
          </template>

          <template #item.status="{ item }">
            <div class="tw-min-w-[12rem]">
              <AppBadge :label="monitorStatusLabel(item.status)" :tone="monitorStatusTone(item.status)" size="sm" />
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-flex-wrap tw-justify-end tw-gap-2">
              <v-btn size="small" variant="text" color="primary" prepend-icon="mdi-eye-outline" @click="openDetails(item)">
                Details
              </v-btn>
              <v-btn
                v-if="item.enrollee_id && auth.hasPermission('enrollees.view')"
                size="small"
                variant="text"
                color="primary"
                prepend-icon="mdi-account-arrow-right-outline"
                @click="viewEnrollee(item)"
              >
                Enrollee
              </v-btn>
            </div>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-cellphone-off"
              title="No sync attempts found"
              description="No mobile enrollment sync records matched the current filters."
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppModal
        :model-value="detailDialog"
        title="Sync Attempt Details"
        subtitle="Inspect the record outcome, backend reasoning, and audit trail for this mobile enrollment submission."
        icon="mdi-file-search-outline"
        size="2xl"
        color="primary"
        @update:modelValue="handleDetailModal"
      >
        <div v-if="detailLoading" class="tw-py-12 tw-text-center">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <template v-else-if="selectedRecord">
          <div class="tw-space-y-4">
            <AppAlert
              :tone="attentionTone(selectedRecord.status)"
              :title="statusLabel(selectedRecord.status)"
              :message="selectedRecord.status_reason || defaultReason(selectedRecord.status)"
            />

            <div class="tw-grid tw-gap-4 xl:tw-grid-cols-2">
              <AppCard title="Outcome" icon="mdi-checklist" tone="primary">
                <div class="tw-space-y-3">
                  <div class="tw-flex tw-flex-wrap tw-gap-2">
                    <AppStatusBadge :status="selectedRecord.status" :label="statusLabel(selectedRecord.status)" size="sm" />
                    <AppBadge :label="attentionLabel(selectedRecord.status)" :tone="attentionTone(selectedRecord.status)" size="sm" />
                    <AppBadge :label="selectedRecord.attachments_count ? `${selectedRecord.attachments_count} attachments` : 'No attachments'" :tone="selectedRecord.attachments_count ? 'info' : 'neutral'" size="sm" />
                  </div>
                  <div class="tw-grid tw-gap-2 sm:tw-grid-cols-2">
                    <div v-for="field in outcomeFields(selectedRecord)" :key="field.label" class="tw-border tw-border-slate-200 tw-bg-white tw-p-3">
                      <p class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">{{ field.label }}</p>
                      <p class="tw-mt-1 tw-text-sm tw-text-slate-900">{{ field.value || 'N/A' }}</p>
                    </div>
                  </div>
                </div>
              </AppCard>

              <AppCard title="Officer and Device" icon="mdi-account-smartphone-outline" tone="secondary">
                <div class="tw-grid tw-gap-2 sm:tw-grid-cols-2">
                  <div v-for="field in officerDeviceFields(selectedRecord)" :key="field.label" class="tw-border tw-border-slate-200 tw-bg-white tw-p-3">
                    <p class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">{{ field.label }}</p>
                    <p class="tw-mt-1 tw-text-sm tw-text-slate-900">{{ field.value || 'N/A' }}</p>
                  </div>
                </div>
              </AppCard>

              <AppCard title="Enrollment Snapshot" icon="mdi-account-details-outline" tone="info">
                <div class="tw-grid tw-gap-2 sm:tw-grid-cols-2">
                  <div v-for="field in snapshotFields(selectedRecord)" :key="field.label" class="tw-border tw-border-slate-200 tw-bg-white tw-p-3">
                    <p class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">{{ field.label }}</p>
                    <p class="tw-mt-1 tw-text-sm tw-text-slate-900">{{ field.value || 'N/A' }}</p>
                  </div>
                </div>
              </AppCard>

              <AppCard title="Related Records" icon="mdi-link-variant" tone="warning">
                <div class="tw-space-y-3">
                  <div class="tw-grid tw-gap-2 sm:tw-grid-cols-2">
                    <div v-for="field in relatedFields(selectedRecord)" :key="field.label" class="tw-border tw-border-slate-200 tw-bg-white tw-p-3">
                      <p class="tw-text-[11px] tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">{{ field.label }}</p>
                      <p class="tw-mt-1 tw-text-sm tw-text-slate-900">{{ field.value || 'N/A' }}</p>
                    </div>
                  </div>
                  <div class="tw-flex tw-flex-wrap tw-gap-2">
                    <v-btn
                      v-if="selectedRecord.enrollee_id && auth.hasPermission('enrollees.view')"
                      color="primary"
                      variant="tonal"
                      prepend-icon="mdi-account-arrow-right-outline"
                      @click="viewEnrollee(selectedRecord)"
                    >
                      View enrollee
                    </v-btn>
                    <v-btn
                      v-if="canOpenApproval(selectedRecord)"
                      color="primary"
                      variant="text"
                      prepend-icon="mdi-account-check-outline"
                      @click="openApprovalQueue"
                    >
                      Open approval queue
                    </v-btn>
                  </div>
                </div>
              </AppCard>
            </div>

            <div class="tw-grid tw-gap-4 xl:tw-grid-cols-2">
              <AppCard title="NIN Review Metadata" icon="mdi-card-account-details-outline" tone="primary">
                <template v-if="hasNinMetadata(selectedRecord)">
                  <div class="tw-space-y-3">
                    <div v-if="selectedRecord.nin_verified_data">
                      <p class="tw-mb-1 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Verified data</p>
                      <pre class="tw-max-h-56 tw-overflow-auto tw-bg-slate-50 tw-p-3 tw-text-xs tw-text-slate-700">{{ pretty(selectedRecord.nin_verified_data) }}</pre>
                    </div>
                    <div v-if="selectedRecord.nin_conflicts?.length">
                      <p class="tw-mb-1 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Conflicts</p>
                      <pre class="tw-max-h-56 tw-overflow-auto tw-bg-slate-50 tw-p-3 tw-text-xs tw-text-slate-700">{{ pretty(selectedRecord.nin_conflicts) }}</pre>
                    </div>
                    <div v-if="selectedRecord.verified_field_edit_reasons">
                      <p class="tw-mb-1 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Officer reasons</p>
                      <pre class="tw-max-h-56 tw-overflow-auto tw-bg-slate-50 tw-p-3 tw-text-xs tw-text-slate-700">{{ pretty(selectedRecord.verified_field_edit_reasons) }}</pre>
                    </div>
                  </div>
                </template>
                <AppEmptyState
                  v-else
                  icon="mdi-card-account-details-outline"
                  title="No NIN review metadata"
                  description="This record did not store verification metadata or conflict notes."
                />
              </AppCard>

              <AppCard title="Location and Attachments" icon="mdi-map-marker-radius-outline" tone="secondary">
                <div class="tw-space-y-3">
                  <div v-if="selectedRecord.location_payload">
                    <p class="tw-mb-1 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.16em] tw-text-slate-500">Location payload</p>
                    <pre class="tw-max-h-48 tw-overflow-auto tw-bg-slate-50 tw-p-3 tw-text-xs tw-text-slate-700">{{ pretty(selectedRecord.location_payload) }}</pre>
                  </div>

                  <div v-if="selectedRecord.attachments?.length" class="tw-space-y-2">
                    <div
                      v-for="attachment in selectedRecord.attachments"
                      :key="attachment.id"
                      class="tw-flex tw-items-start tw-justify-between tw-gap-3 tw-border tw-border-slate-200 tw-bg-white tw-p-3"
                    >
                      <div>
                        <p class="tw-text-sm tw-font-medium tw-text-slate-900">{{ attachment.original_name || attachment.kind || `Attachment #${attachment.id}` }}</p>
                        <p class="tw-text-xs tw-text-slate-500">{{ attachment.mime_type || 'Unknown type' }}{{ attachment.size ? ` / ${attachment.size} bytes` : '' }}</p>
                        <p v-if="attachment.failure_reason" class="tw-mt-1 tw-text-xs tw-text-rose-700">{{ attachment.failure_reason }}</p>
                      </div>
                      <AppStatusBadge :status="attachment.status || 'uploaded'" :label="statusLabel(attachment.status || 'uploaded')" size="sm" />
                    </div>
                  </div>

                  <AppEmptyState
                    v-else
                    icon="mdi-paperclip"
                    title="No attachment records"
                    description="This sync attempt did not store any backend attachment entries."
                  />
                </div>
              </AppCard>
            </div>

            <AppCard title="Audit Trail" icon="mdi-timeline-outline" tone="neutral">
              <AppTimeline v-if="timelineItems.length" :items="timelineItems" />
              <AppEmptyState
                v-else
                icon="mdi-timeline-alert-outline"
                title="No audit events"
                description="No audit trail entries were recorded for this sync attempt."
              />
            </AppCard>
          </div>
        </template>

        <template #actions>
          <v-btn color="primary" variant="flat" @click="detailDialog = false">Close</v-btn>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { debounce } from 'lodash'
import { useRouter } from 'vue-router'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'
import AppTimeline from '../common/AppTimeline.vue'
import DateDisplay from '../common/DateDisplay.vue'
import { mobileEnrollmentMonitorAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const { error } = useToast()

const loading = ref(false)
const detailLoading = ref(false)
const records = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(20)
const detailDialog = ref(false)
const selectedRecord = ref(null)
const selectedAuditTrail = ref([])
const statusOptions = ref([])
const officerOptions = ref([])
const pickerMenus = reactive({
  capturedDateFrom: false,
  capturedDateTo: false,
  capturedTimeFrom: false,
  capturedTimeTo: false,
  receivedDateFrom: false,
  receivedDateTo: false,
})

const summary = ref({
  total: 0,
  today: 0,
  ready: 0,
  attention: 0,
  batches: 0,
  officers: 0,
  devices: 0,
  status_counts: {},
})

const filters = reactive({
  search: '',
  status: null,
  officer_user_id: null,
  batch_id: '',
  device_uuid: '',
  date_from: '',
  date_to: '',
  captured_date_from: '',
  captured_date_to: '',
  captured_time_from: '',
  captured_time_to: '',
})

const headers = [
  { title: 'Officer', key: 'officer', sortable: false, minWidth: 180 },
  { title: 'Attempt', key: 'attempt', sortable: false, minWidth: 200 },
  { title: 'Enrollee', key: 'record', sortable: false, minWidth: 220 },
  { title: 'Status', key: 'status', sortable: false, minWidth: 160 },
  { title: '', key: 'actions', sortable: false, align: 'end', minWidth: 140 },
]

const activeFiltersCount = computed(() => Object.values(filters).filter((value) => value !== null && value !== '').length)

const statusSelectItems = computed(() => statusOptions.value.map((status) => ({
  label: statusLabel(status),
  value: status,
})))

const officerSelectItems = computed(() => officerOptions.value.map((officer) => ({
  label: officer.subtitle ? `${officer.name} (${officer.subtitle})` : officer.name,
  value: officer.id,
})))

const timelineItems = computed(() => selectedAuditTrail.value.map((entry) => ({
  id: entry.id,
  title: formatAuditTitle(entry.action),
  subtitle: `${entry.user?.name || 'System'} - ${dateValue(entry.created_at)}`,
  description: entry.description || 'No description recorded.',
  tone: auditTone(entry.action),
  icon: auditIcon(entry.action),
})))

const loadMonitor = async (options = {}) => {
  const resetPage = typeof options === 'object' && options !== null && options.resetPage === true
  if (resetPage) {
    page.value = 1
  }

  loading.value = true
  try {
    const response = await mobileEnrollmentMonitorAPI.list({
      page: page.value,
      per_page: perPage.value,
      search: filters.search || undefined,
      status: filters.status || undefined,
      officer_user_id: filters.officer_user_id || undefined,
      batch_id: filters.batch_id || undefined,
      device_uuid: filters.device_uuid || undefined,
      date_from: filters.date_from || undefined,
      date_to: filters.date_to || undefined,
      captured_date_from: filters.captured_date_from || undefined,
      captured_date_to: filters.captured_date_to || undefined,
      captured_time_from: filters.captured_time_from || undefined,
      captured_time_to: filters.captured_time_to || undefined,
    })

    const payload = response.data?.data || {}
    const recordsPage = payload.records || {}

    records.value = recordsPage.data || []
    total.value = recordsPage.total || 0
    page.value = recordsPage.current_page || page.value
    perPage.value = recordsPage.per_page || perPage.value
    summary.value = {
      total: payload.summary?.total || 0,
      today: payload.summary?.today || 0,
      ready: payload.summary?.ready || 0,
      attention: payload.summary?.attention || 0,
      batches: payload.summary?.batches || 0,
      officers: payload.summary?.officers || 0,
      devices: payload.summary?.devices || 0,
      status_counts: payload.summary?.status_counts || {},
    }
    statusOptions.value = payload.status_options || []
    officerOptions.value = payload.officer_options || []
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to load mobile sync monitor records.')
  } finally {
    loading.value = false
  }
}

const debouncedSearch = debounce(() => {
  loadMonitor({ resetPage: true })
}, 400)

const clearFilters = async () => {
  filters.search = ''
  filters.status = null
  filters.officer_user_id = null
  filters.batch_id = ''
  filters.device_uuid = ''
  filters.date_from = ''
  filters.date_to = ''
  filters.captured_date_from = ''
  filters.captured_date_to = ''
  filters.captured_time_from = ''
  filters.captured_time_to = ''
  await loadMonitor({ resetPage: true })
}

const normalizePickerValue = (value) => Array.isArray(value) ? value[0] : value

const formatPickerDateValue = (value) => {
  const normalized = normalizePickerValue(value)
  if (!normalized) return ''
  if (normalized instanceof Date) {
    const year = normalized.getFullYear()
    const month = String(normalized.getMonth() + 1).padStart(2, '0')
    const day = String(normalized.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
  }
  return String(normalized).slice(0, 10)
}

const displayDateFilter = (value) => {
  if (!value) return ''
  const [year, month, day] = String(value).split('-').map(Number)
  if (!year || !month || !day) return String(value)
  return new Intl.DateTimeFormat('en-NG', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(new Date(year, month - 1, day))
}

const displayTimeFilter = (value) => {
  if (!value) return ''
  return String(value).slice(0, 5)
}

const clearFilterValue = async (key, menuKey) => {
  filters[key] = ''
  if (menuKey && menuKey in pickerMenus) {
    pickerMenus[menuKey] = false
  }
  await loadMonitor({ resetPage: true })
}

const setDateFilter = async (key, value, menuKey) => {
  filters[key] = formatPickerDateValue(value)
  if (menuKey && menuKey in pickerMenus) {
    pickerMenus[menuKey] = false
  }
  await loadMonitor({ resetPage: true })
}

const setTimeFilter = async (key, value, menuKey) => {
  const normalized = normalizePickerValue(value)
  filters[key] = normalized ? String(normalized).slice(0, 5) : ''
  if (menuKey && menuKey in pickerMenus) {
    pickerMenus[menuKey] = false
  }
  await loadMonitor({ resetPage: true })
}

const openDetails = async (item) => {
  detailDialog.value = true
  detailLoading.value = true
  selectedRecord.value = null
  selectedAuditTrail.value = []

  try {
    const response = await mobileEnrollmentMonitorAPI.get(item.id)
    const payload = response.data?.data || {}
    selectedRecord.value = payload.record || null
    selectedAuditTrail.value = payload.audit_trail || []
  } catch (err) {
    error(err?.response?.data?.message || 'Failed to load sync attempt details.')
    detailDialog.value = false
  } finally {
    detailLoading.value = false
  }
}

const handleDetailModal = (value) => {
  detailDialog.value = value
  if (!value) {
    selectedRecord.value = null
    selectedAuditTrail.value = []
  }
}

const viewEnrollee = (item) => {
  const enrolleeId = item?.enrollee_id || item?.enrollee?.id
  if (!enrolleeId) return
  router.push(`/enrollees/${enrolleeId}`)
}

const openApprovalQueue = () => {
  detailDialog.value = false
  router.push('/enrollees/approval')
}

const recordTitle = (record) => {
  const firstName = record?.enrollee?.first_name || record?.core_data?.first_name || record?.payload?.data?.first_name || record?.payload?.first_name
  const lastName = record?.enrollee?.last_name || record?.core_data?.last_name || record?.payload?.data?.last_name || record?.payload?.last_name
  const fullName = [firstName, lastName].filter(Boolean).join(' ').trim()
  return fullName || `Mobile record #${record.id}`
}

const recordSubtitle = (record) => {
  const nin = record?.enrollee?.nin || record?.core_data?.nin || record?.payload?.data?.nin || record?.payload?.nin
  return nin ? `NIN ${nin}` : 'No NIN submitted'
}

const recordHasNin = (record) => Boolean(
  record?.enrollee?.nin || record?.core_data?.nin || record?.payload?.data?.nin || record?.payload?.nin
)

const recordNinLabel = (record) => recordSubtitle(record)

const enrolleeIdLabel = (record) => {
  const enrolleeId = record?.enrollee?.enrollee_id
  return enrolleeId ? `Enrollee ID ${enrolleeId}` : 'Enrollee ID pending'
}

const shortId = (value, start = 6, end = 4) => {
  const stringValue = String(value || '').trim()
  if (!stringValue) return 'N/A'
  if (stringValue.length <= start + end + 1) return stringValue
  return `${stringValue.slice(0, start)}...${stringValue.slice(-end)}`
}

const timeValue = (value) => {
  if (!value) return 'N/A'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'N/A'
  return new Intl.DateTimeFormat('en-NG', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const reasonSnippet = (record) => record?.status_reason || defaultReason(record?.status)

const schemaChip = (record) => record?.schema?.name
  ? `${record.schema.name} v${record.schema_version || record.schema.version || 1}`
  : `Schema v${record?.schema_version || 1}`

const deviceLabel = (record) => record?.device?.device_name
  || shortId(record?.device?.device_uuid, 6, 4)
  || 'Unknown device'

const platformLabel = (record) => {
  const platform = record?.device?.platform || 'Unknown platform'
  const version = record?.device?.app_version
  return version ? `${platform} / ${version}` : platform
}

const schemaLabel = (record) => {
  if (!record?.schema) {
    return `Version ${record?.schema_version || 1}`
  }

  return `${record.schema.name} v${record.schema_version || record.schema.version || 1}`
}

const outcomeFields = (record) => [
  { label: 'Batch ID', value: record?.sync_batch_id },
  { label: 'Client Record', value: record?.client_record_id },
  { label: 'Received', value: dateValue(record?.received_at || record?.created_at) },
  { label: 'Captured', value: dateValue(record?.captured_at) },
  { label: 'Synced', value: dateValue(record?.synced_at) },
  { label: 'Schema', value: schemaLabel(record) },
]

const officerDeviceFields = (record) => [
  { label: 'Officer', value: record?.officer?.name || 'Unknown officer' },
  { label: 'Email', value: record?.officer?.email || record?.officer?.username || 'N/A' },
  { label: 'Device', value: record?.device?.device_name || record?.device?.device_uuid || 'Unknown device' },
  { label: 'Platform', value: record?.device?.platform || 'Unknown platform' },
  { label: 'App Version', value: record?.device?.app_version || 'N/A' },
  { label: 'Last Seen', value: dateValue(record?.device?.last_seen_at) },
]

const snapshotFields = (record) => {
  const core = record?.core_data || {}
  return [
    { label: 'Name', value: recordTitle(record) },
    { label: 'Phone', value: core.phone || record?.payload?.data?.phone || 'N/A' },
    { label: 'NIN', value: core.nin || record?.payload?.data?.nin || 'N/A' },
    { label: 'Facility ID', value: core.facility_id || record?.payload?.data?.facility_id || 'N/A' },
    { label: 'LGA ID', value: core.lga_id || record?.payload?.data?.lga_id || 'N/A' },
    { label: 'Ward ID', value: core.ward_id || record?.payload?.data?.ward_id || 'N/A' },
    { label: 'Programme ID', value: core.insurance_programme_id || record?.payload?.data?.insurance_programme_id || 'N/A' },
    { label: 'Premium Plan ID', value: core.premium_plan_id || record?.payload?.data?.premium_plan_id || 'N/A' },
  ]
}

const relatedFields = (record) => [
  { label: 'Enrollee', value: enrolleeLabel(record?.enrollee) },
  { label: 'Duplicate Match', value: enrolleeLabel(record?.duplicate_of) },
]

const enrolleeLabel = (enrollee) => {
  if (!enrollee) return 'None'
  const name = [enrollee.first_name, enrollee.last_name].filter(Boolean).join(' ').trim()
  return `${name || 'Unnamed enrollee'}${enrollee.enrollee_id ? ` (${enrollee.enrollee_id})` : ''}`
}

const canOpenApproval = (record) => ['pending_approval', 'pending_nin', 'nin_failed', 'duplicate_suspected', 'requires_review'].includes(record?.status)

const hasNinMetadata = (record) => Boolean(
  record?.nin_verified_data
  || (Array.isArray(record?.nin_conflicts) && record.nin_conflicts.length)
  || record?.verified_field_edit_reasons
)

const pretty = (value) => JSON.stringify(value, null, 2)

const dateValue = (value) => {
  if (!value) return 'N/A'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'N/A'
  return new Intl.DateTimeFormat('en-NG', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const formatCount = (value) => new Intl.NumberFormat('en-NG').format(Number(value || 0))

const statusCount = (status) => Number(summary.value.status_counts?.[status] || 0)

const statusLabel = (status) => ({
  received: 'Received',
  pending_nin: 'Pending NIN',
  nin_failed: 'NIN Failed',
  duplicate_suspected: 'Duplicate Suspected',
  pending_approval: 'Pending Approval',
  requires_review: 'Requires Review',
  approved: 'Approved',
  rejected: 'Rejected',
  sync_failed: 'Sync Failed',
  uploaded: 'Uploaded',
  not_uploaded: 'Not Uploaded',
}[status] || (status ? String(status).replace(/_/g, ' ') : 'Unknown'))

const defaultReason = (status) => ({
  received: 'The server accepted this record and stored it for further processing.',
  pending_nin: 'The enrollment is waiting for NIN verification or retry.',
  nin_failed: 'NIN verification did not pass the configured validation rules.',
  duplicate_suspected: 'The backend flagged a possible duplicate enrollee match.',
  pending_approval: 'The sync passed and the record is ready for approval review.',
  requires_review: 'The sync passed but an officer still needs to review conflicts or exceptions.',
  approved: 'The enrollee was approved on the web and coverage is active.',
  rejected: 'The record was rejected during downstream review.',
  sync_failed: 'The backend could not process this record successfully.',
}[status] || 'No status reason recorded.')

const attentionLabel = (status) => (['pending_approval', 'approved', 'received'].includes(status) ? 'Operationally healthy' : 'Needs follow-up')

const attentionTone = (status) => {
  if (['pending_approval', 'approved', 'received'].includes(status)) return 'success'
  if (['pending_nin', 'requires_review', 'duplicate_suspected'].includes(status)) return 'warning'
  return 'danger'
}

const monitorStatusLabel = (status) => (
  ['pending_approval', 'approved', 'received'].includes(status)
    ? 'Operationally healthy'
    : statusLabel(status)
)

const monitorStatusTone = (status) => attentionTone(status)

const formatAuditTitle = (action) => {
  if (!action) return 'System event'
  return String(action).replace(/_/g, ' ')
}

const auditTone = (action) => {
  if (String(action).includes('failed')) return 'danger'
  if (String(action).includes('duplicate') || String(action).includes('review')) return 'warning'
  if (String(action).includes('approved') || String(action).includes('verified')) return 'success'
  return 'info'
}

const auditIcon = (action) => {
  if (String(action).includes('failed')) return 'mdi-alert-circle-outline'
  if (String(action).includes('duplicate')) return 'mdi-content-duplicate'
  if (String(action).includes('verified')) return 'mdi-check-decagram-outline'
  if (String(action).includes('approved')) return 'mdi-badge-account-horizontal-outline'
  return 'mdi-timeline-outline'
}

onMounted(() => {
  loadMonitor()
})
</script>
