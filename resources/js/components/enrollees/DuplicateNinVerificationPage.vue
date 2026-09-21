<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Duplicate NIN Verification" icon="mdi-card-account-details-star-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadBatches">
          Refresh
        </v-btn>
      </AppPageHeader>

      <AppAlert
        tone="warning"
        title="Controlled cleanup workflow"
        message="This page verifies duplicate NIN groups where NIN verification has not started. A verified/manual decision lets one enrollee keep the NIN and clears it from the other affected records, with history retained."
      />

      <AppAlert v-if="errorMessage" tone="danger" :message="errorMessage" />

      <AppCard title="Prepare Batch" icon="mdi-playlist-plus" tone="primary">
        <div class="tw-grid tw-gap-3 lg:tw-grid-cols-[240px_1fr_auto] lg:tw-items-end">
          <v-text-field
            v-model.number="form.count"
            type="number"
            min="1"
            max="5000"
            label="Unique duplicate NINs"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <p class="tw-text-sm tw-text-slate-500">
            Example: entering 2000 prepares up to 2000 unique NIN groups where the same NIN appears on multiple not-started enrollee records.
          </p>
          <v-btn color="primary" prepend-icon="mdi-magnify-scan" :loading="creating" @click="createBatch">
            Query & Preview
          </v-btn>
        </div>
      </AppCard>

      <div class="tw-grid tw-gap-4 xl:tw-grid-cols-[320px_1fr]">
        <AppCard title="Recent Batches" icon="mdi-history" tone="secondary" :padded="false">
          <div class="tw-divide-y tw-divide-slate-100">
            <button
              v-for="batch in batches"
              :key="batch.id"
              type="button"
              class="tw-block tw-w-full tw-px-4 tw-py-3 tw-text-left hover:tw-bg-slate-50"
              :class="{ 'tw-bg-cyan-50': currentBatch?.id === batch.id }"
              @click="loadBatch(batch.id)"
            >
              <div class="tw-flex tw-items-center tw-justify-between tw-gap-3">
                <span class="tw-font-semibold tw-text-slate-900">{{ batch.reference }}</span>
                <AppBadge :label="statusLabel(batch.status)" :tone="statusTone(batch.status)" size="sm" />
              </div>
              <div class="tw-mt-1 tw-text-xs tw-text-slate-500">
                {{ numberFormat(batch.unique_nin_count) }} NINs · {{ numberFormat(batch.total_candidate_count) }} enrollees
              </div>
            </button>
            <div v-if="!batches.length" class="tw-px-4 tw-py-8">
              <AppEmptyState title="No batches yet" description="Create a batch to preview duplicate NIN groups." icon="mdi-history" />
            </div>
          </div>
        </AppCard>

        <AppCard title="Batch Preview" icon="mdi-table-eye" tone="primary" :padded="false">
          <template #actions>
            <div v-if="currentBatch" class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
              <AppBadge :label="currentBatch.reference" tone="neutral" size="sm" />
              <v-btn size="small" color="primary" prepend-icon="mdi-shield-check-outline" :loading="verifyingBatch" @click="verifyAll">
                Verify All
              </v-btn>
            </div>
          </template>

          <div v-if="currentBatch" class="tw-grid tw-gap-2 tw-border-b tw-border-slate-100 tw-p-4 md:tw-grid-cols-4">
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Unique NINs</p>
              <p class="tw-text-xl tw-font-bold tw-text-slate-900">{{ numberFormat(currentBatch.unique_nin_count) }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Affected Enrollees</p>
              <p class="tw-text-xl tw-font-bold tw-text-slate-900">{{ numberFormat(currentBatch.total_candidate_count) }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Applied</p>
              <p class="tw-text-xl tw-font-bold tw-text-emerald-700">{{ numberFormat(appliedCount) }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Needs Review</p>
              <p class="tw-text-xl tw-font-bold tw-text-amber-700">{{ numberFormat(needsReviewCount) }}</p>
            </div>
          </div>

          <AppDataTable
            v-if="currentBatch"
            v-model:page="page"
            v-model:items-per-page="perPage"
            :headers="headers"
            :items="currentItems"
            :items-length="currentItems.length"
            :loading="loading"
            :per-page-options="[10, 25, 50, 100]"
            item-value="id"
            class="tw-rounded-none tw-border-0"
          >
            <template #item.nin="{ item }">
              <div class="tw-font-mono tw-font-semibold tw-text-slate-900">{{ item.nin }}</div>
            </template>
            <template #item.affected="{ item }">
              <div class="tw-space-y-1">
                <div
                  v-for="candidate in item.candidates.slice(0, 3)"
                  :key="candidate.id"
                  class="tw-text-xs tw-text-slate-700"
                >
                  <span class="tw-font-semibold">{{ candidate.enrollee_code || 'N/A' }}</span>
                  <span class="tw-text-slate-400"> · </span>
                  <span>{{ candidate.full_name || 'Unnamed enrollee' }}</span>
                </div>
                <div v-if="item.candidates.length > 3" class="tw-text-xs tw-text-slate-400">
                  +{{ item.candidates.length - 3 }} more
                </div>
              </div>
            </template>
            <template #item.best_match="{ item }">
              <div v-if="bestCandidate(item)" class="tw-text-sm">
                <div class="tw-font-semibold tw-text-slate-900">{{ bestCandidate(item).full_name }}</div>
                <div class="tw-text-xs tw-text-slate-500">{{ bestCandidate(item).match_score }}% match</div>
              </div>
              <span v-else class="tw-text-sm tw-text-slate-400">Not checked</span>
            </template>
            <template #item.status="{ item }">
              <AppBadge :label="statusLabel(item.status)" :tone="statusTone(item.status)" size="sm" />
            </template>
            <template #item.actions="{ item }">
              <div class="tw-flex tw-flex-wrap tw-gap-2">
                <v-btn size="small" variant="outlined" prepend-icon="mdi-eye-outline" @click="openDetails(item)">
                  Details
                </v-btn>
                <v-btn size="small" color="primary" variant="flat" prepend-icon="mdi-shield-sync-outline" :loading="verifyingItemId === item.id" @click="verifyOne(item)">
                  Verify
                </v-btn>
                <v-btn size="small" variant="outlined" prepend-icon="mdi-account-check-outline" @click="openDecision(item)">
                  Decide
                </v-btn>
              </div>
            </template>
          </AppDataTable>

          <div v-else class="tw-p-8">
            <AppEmptyState title="No batch selected" description="Create a new batch or select a recent batch to preview duplicate NINs." icon="mdi-table-eye" />
          </div>
        </AppCard>
      </div>
    </div>

    <v-dialog v-model="detailsDialog" max-width="860">
      <v-card>
        <v-card-title class="tw-flex tw-items-center tw-justify-between">
          <span>Duplicate NIN Details</span>
          <v-btn icon="mdi-close" variant="text" @click="detailsDialog = false" />
        </v-card-title>
        <v-card-text v-if="selectedItem" class="tw-space-y-4">
          <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">NIN</p>
              <p class="tw-font-mono tw-text-lg tw-font-bold">{{ selectedItem.nin }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Provider</p>
              <p class="tw-font-semibold">{{ selectedItem.provider_name || 'Not verified yet' }}</p>
            </div>
            <div>
              <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">Status</p>
              <AppBadge :label="statusLabel(selectedItem.status)" :tone="statusTone(selectedItem.status)" size="sm" />
            </div>
          </div>

          <div>
            <h3 class="tw-mb-2 tw-text-sm tw-font-bold tw-text-slate-900">Provider Data</h3>
            <div class="tw-grid tw-gap-3 lg:tw-grid-cols-[160px_1fr]">
              <div class="tw-flex tw-h-44 tw-w-40 tw-items-center tw-justify-center tw-border tw-border-slate-200 tw-bg-slate-50">
                <img
                  v-if="providerPhoto(selectedItem)"
                  :src="providerPhoto(selectedItem)"
                  alt="Provider NIN photo"
                  class="tw-h-full tw-w-full tw-object-cover"
                />
                <div v-else class="tw-text-center tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">
                  No Photo
                </div>
              </div>
              <div class="tw-grid tw-gap-2 md:tw-grid-cols-3">
                <div v-for="field in providerFields" :key="field.key" class="tw-border tw-border-slate-200 tw-p-3">
                  <p class="tw-text-xs tw-font-semibold tw-uppercase tw-text-slate-400">{{ field.label }}</p>
                  <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ selectedItem.provider_data?.[field.key] || 'N/A' }}</p>
                </div>
              </div>
            </div>
          </div>

          <v-text-field
            v-model="matchSearch"
            label="Search matches"
            placeholder="Search NiCare ID, name, phone, DOB, gender, or score"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
          />

          <v-table density="compact">
            <thead>
              <tr>
                <th>Photo</th>
                <th>NiCare ID</th>
                <th>Name</th>
                <th>Phone Number</th>
                <th>DOB</th>
                <th>Gender</th>
                <th>Score</th>
                <th>Decision</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="candidate in filteredMatchCandidates" :key="candidate.id">
                <td>
                  <div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-overflow-hidden tw-border tw-border-slate-200 tw-bg-slate-50">
                    <img
                      v-if="photoSrc(candidate.photo_url)"
                      :src="photoSrc(candidate.photo_url)"
                      alt="Enrollee photo"
                      class="tw-h-full tw-w-full tw-object-cover"
                    />
                    <v-icon v-else size="18" color="grey">mdi-account-outline</v-icon>
                  </div>
                </td>
                <td class="tw-font-semibold">{{ candidate.enrollee_code }}</td>
                <td>{{ candidate.full_name }}</td>
                <td>{{ candidate.phone || 'N/A' }}</td>
                <td>{{ candidate.date_of_birth || 'N/A' }}</td>
                <td>{{ candidate.gender || 'N/A' }}</td>
                <td>{{ candidate.match_score }}%</td>
                <td>
                  <AppBadge v-if="candidate.keeps_nin" label="Keeps NIN" tone="success" size="sm" />
                  <AppBadge v-else-if="candidate.nin_cleared" label="NIN cleared" tone="danger" size="sm" />
                  <span v-else class="tw-text-xs tw-text-slate-400">Pending</span>
                </td>
              </tr>
              <tr v-if="filteredMatchCandidates.length === 0">
                <td colspan="8" class="tw-py-6 tw-text-center tw-text-sm tw-text-slate-400">
                  No matching enrollees found.
                </td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-dialog v-model="decisionDialog" max-width="620">
      <v-card>
        <v-card-title>Manual Decision</v-card-title>
        <v-card-text v-if="selectedItem" class="tw-space-y-4">
          <AppAlert tone="info" message="Choose the enrollee that should keep this NIN. Other affected enrollees in this group will have their NIN cleared." />
          <v-radio-group v-model="decisionForm.selected_enrollee_id">
            <v-radio
              v-for="candidate in selectedItem.candidates"
              :key="candidate.id"
              :value="candidate.enrollee_id"
            >
              <template #label>
                <div class="tw-flex tw-items-center tw-gap-3">
                  <div class="tw-flex tw-h-10 tw-w-10 tw-shrink-0 tw-items-center tw-justify-center tw-overflow-hidden tw-border tw-border-slate-200 tw-bg-slate-50">
                    <img
                      v-if="photoSrc(candidate.photo_url)"
                      :src="photoSrc(candidate.photo_url)"
                      alt="Enrollee photo"
                      class="tw-h-full tw-w-full tw-object-cover"
                    />
                    <v-icon v-else size="18" color="grey">mdi-account-outline</v-icon>
                  </div>
                  <div>
                  <div class="tw-font-semibold tw-text-slate-900">{{ candidate.enrollee_code }} · {{ candidate.full_name }}</div>
                  <div class="tw-text-xs tw-text-slate-500">
                    DOB {{ candidate.date_of_birth || 'N/A' }} · Score {{ candidate.match_score }}%
                  </div>
                  </div>
                </div>
              </template>
            </v-radio>
          </v-radio-group>
          <v-textarea v-model="decisionForm.note" label="Decision note" variant="outlined" rows="3" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="decisionDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="deciding" @click="applyDecision">Apply Decision</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { duplicateNinVerificationAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()

const loading = ref(false)
const creating = ref(false)
const verifyingBatch = ref(false)
const deciding = ref(false)
const verifyingItemId = ref(null)
const errorMessage = ref('')
const batches = ref([])
const currentBatch = ref(null)
const selectedItem = ref(null)
const matchSearch = ref('')
const detailsDialog = ref(false)
const decisionDialog = ref(false)
const page = ref(1)
const perPage = ref(25)

const form = reactive({ count: 2000 })
const decisionForm = reactive({
  selected_enrollee_id: null,
  note: '',
})

const headers = [
  { title: 'NIN', key: 'nin', width: 150 },
  { title: 'Affected Enrollees', key: 'affected', sortable: false },
  { title: 'Best Match', key: 'best_match', width: 220 },
  { title: 'Status', key: 'status', width: 140 },
  { title: 'Actions', key: 'actions', sortable: false, width: 300 },
]

const providerFields = [
  { key: 'first_name', label: 'First Name' },
  { key: 'middle_name', label: 'Middle Name' },
  { key: 'last_name', label: 'Last Name' },
  { key: 'date_of_birth', label: 'Date of Birth' },
  { key: 'gender', label: 'Gender' },
  { key: 'phone', label: 'Phone' },
]

const currentItems = computed(() => currentBatch.value?.items || [])
const appliedCount = computed(() => currentItems.value.filter((item) => item.status === 'applied').length)
const needsReviewCount = computed(() => currentItems.value.filter((item) => item.status === 'needs_review').length)
const filteredMatchCandidates = computed(() => {
  const candidates = selectedItem.value?.candidates || []
  const term = String(matchSearch.value || '').trim().toLowerCase()

  if (!term) return candidates

  return candidates.filter((candidate) => [
    candidate.enrollee_code,
    candidate.full_name,
    candidate.first_name,
    candidate.middle_name,
    candidate.last_name,
    candidate.phone,
    candidate.date_of_birth,
    candidate.gender,
    `${candidate.match_score}%`,
  ].some((value) => String(value || '').toLowerCase().includes(term)))
})

const numberFormat = (value) => Number(value || 0).toLocaleString()
const statusLabel = (status) => String(status || 'pending').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
const statusTone = (status) => ({
  applied: 'success',
  completed: 'success',
  verified: 'info',
  needs_review: 'warning',
  failed: 'danger',
  partial: 'warning',
  processing: 'info',
  draft: 'neutral',
}[status] || 'neutral')

const bestCandidate = (item) => {
  const candidates = [...(item.candidates || [])]
  return candidates.sort((a, b) => Number(b.match_score || 0) - Number(a.match_score || 0))[0] || null
}

const photoSrc = (value) => {
  const raw = String(value || '').trim()
  if (!raw) return ''
  if (raw.startsWith('data:image/')) return raw
  if (/^https?:\/\//i.test(raw)) return raw
  if (raw.startsWith('/')) return raw
  if (raw.startsWith('storage/') || raw.startsWith('uploads/')) return `/${raw}`
  if (/^[A-Za-z0-9+/=\r\n]+$/.test(raw) && raw.replace(/\s+/g, '').length >= 32) {
    return `data:image/jpeg;base64,${raw.replace(/\s+/g, '')}`
  }
  return raw
}

const providerPhoto = (item) => {
  const data = item?.provider_data || {}
  return photoSrc(data.photo || data.image || data.passport || data.photo_url || data.image_url)
}

const setError = (message) => {
  errorMessage.value = message || ''
  if (message) error(message)
}

const loadBatches = async () => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.batches()
    batches.value = response.data?.data || []
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to load duplicate NIN batches.')
  } finally {
    loading.value = false
  }
}

const loadBatch = async (id) => {
  loading.value = true
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.getBatch(id)
    currentBatch.value = response.data?.data || null
    selectedItem.value = null
    page.value = 1
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to load this batch.')
  } finally {
    loading.value = false
  }
}

const createBatch = async () => {
  creating.value = true
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.createBatch({ count: form.count })
    currentBatch.value = response.data?.data || null
    success('Duplicate NIN batch prepared.')
    await loadBatches()
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to prepare duplicate NIN batch.')
  } finally {
    creating.value = false
  }
}

const replaceItem = (nextItem) => {
  if (!currentBatch.value || !nextItem) return
  const index = currentBatch.value.items.findIndex((item) => item.id === nextItem.id)
  if (index >= 0) currentBatch.value.items.splice(index, 1, nextItem)
  selectedItem.value = nextItem
}

const verifyOne = async (item) => {
  verifyingItemId.value = item.id
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.verifyItem(item.id)
    replaceItem(response.data?.data)
    success('NIN verification completed.')
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to verify this NIN.')
  } finally {
    verifyingItemId.value = null
  }
}

const verifyAll = async () => {
  if (!currentBatch.value) return
  verifyingBatch.value = true
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.verifyBatch(currentBatch.value.id)
    currentBatch.value = response.data?.data || currentBatch.value
    success('Batch verification completed.')
    await loadBatches()
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to verify this batch.')
  } finally {
    verifyingBatch.value = false
  }
}

const openDetails = (item) => {
  selectedItem.value = item
  matchSearch.value = ''
  detailsDialog.value = true
}

const openDecision = (item) => {
  selectedItem.value = item
  decisionForm.selected_enrollee_id = item.matched_enrollee_id || bestCandidate(item)?.enrollee_id || null
  decisionForm.note = ''
  decisionDialog.value = true
}

const applyDecision = async () => {
  if (!selectedItem.value || !decisionForm.selected_enrollee_id) {
    setError('Select an enrollee to keep this NIN.')
    return
  }

  deciding.value = true
  errorMessage.value = ''
  try {
    const response = await duplicateNinVerificationAPI.decide(selectedItem.value.id, {
      selected_enrollee_id: decisionForm.selected_enrollee_id,
      note: decisionForm.note,
    })
    replaceItem(response.data?.data)
    decisionDialog.value = false
    success('Manual decision applied.')
  } catch (err) {
    setError(err.response?.data?.message || 'Unable to apply this decision.')
  } finally {
    deciding.value = false
  }
}

onMounted(loadBatches)
</script>
