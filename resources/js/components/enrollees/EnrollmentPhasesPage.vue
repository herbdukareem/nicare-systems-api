<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <AppPageHeader
        title="Enrollment Phases"
        subtitle="Create time-bound enrollment campaigns and define the BHCPF allocation for each LGA."
        kicker="Enrollment Control"
        icon="mdi-calendar-range-outline"
      >
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">New Enrollment Phase</v-btn>
      </AppPageHeader>

      <AppCard title="Enrollment Campaigns" subtitle="Each phase has its own dates, sponsor, and BHCPF target allocation." icon="mdi-calendar-clock" tone="primary">
        <AppDataTable :headers="phaseHeaders" :items="phases" :loading="loading" :items-per-page="25">
          <template #item.name="{ item }">
            <div class="tw-font-semibold tw-text-slate-900">{{ item.name }}</div>
            <div class="tw-text-xs tw-text-slate-500">{{ item.benefactor_name || 'No benefactor' }}</div>
          </template>
          <template #item.period="{ item }">{{ formatDate(item.start_date) }} to {{ formatDate(item.end_date) }}</template>
          <template #item.target="{ item }">{{ formatNumber(item.bhcpf_target_total) }}</template>
          <template #item.coverage="{ item }">{{ item.bhcpf_targets_count }} LGAs</template>
          <template #item.status="{ item }">
            <AppBadge :label="item.is_current ? 'Current' : item.status ? 'Active' : 'Inactive'" :tone="item.is_current ? 'success' : item.status ? 'info' : 'neutral'" size="sm" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-items-center tw-gap-1">
              <v-btn icon="mdi-bullseye-arrow" size="small" variant="text" color="primary" title="Manage BHCPF targets" @click="openTargetsDialog(item)" />
              <v-btn icon="mdi-pencil-outline" size="small" variant="text" title="Edit phase" @click="openEditDialog(item)" />
              <v-btn icon="mdi-delete-outline" size="small" variant="text" color="error" title="Deactivate or delete phase" @click="removePhase(item)" />
            </div>
          </template>
        </AppDataTable>
      </AppCard>

      <AppModal v-model="phaseDialog" :title="editingPhase ? 'Edit Enrollment Phase' : 'New Enrollment Phase'" size="md">
        <div class="tw-grid tw-gap-4 sm:tw-grid-cols-2">
          <v-text-field v-model="phaseForm.name" class="sm:tw-col-span-2" label="Phase name" variant="outlined" density="comfortable" />
          <v-select v-model="phaseForm.benefactor_id" :items="benefactors" item-title="name" item-value="id" label="Benefactor" variant="outlined" density="comfortable" />
          <v-select v-model="phaseForm.status" :items="statusOptions" item-title="title" item-value="value" label="Status" variant="outlined" density="comfortable" />
          <v-text-field v-model="phaseForm.start_date" label="Start date" type="date" variant="outlined" density="comfortable" />
          <v-text-field v-model="phaseForm.end_date" label="End date" type="date" variant="outlined" density="comfortable" />
          <v-switch v-model="phaseForm.is_current" class="sm:tw-col-span-2" color="primary" label="Set as the current enrollment phase" hide-details inset />
        </div>
        <template #actions>
          <v-btn variant="outlined" @click="phaseDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingPhase" @click="savePhase">{{ editingPhase ? 'Save Changes' : 'Create Phase' }}</v-btn>
        </template>
      </AppModal>

      <AppModal v-model="targetsDialog" :title="`${selectedPhase?.name || 'Enrollment Phase'} BHCPF Targets`" size="xl">
        <p class="tw-mb-4 tw-text-sm tw-text-slate-600">Set the proposed enrollment target and optional vulnerable-group allocation for each LGA. Zero values are retained so the phase has a complete statewide allocation sheet.</p>
        <AppDataTable :headers="targetHeaders" :items="targetRows" :loading="loadingTargets" :items-per-page="50">
          <template #item.proposed_enrolments="{ item }"><v-text-field v-model.number="item.proposed_enrolments" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
          <template #item.plwd_target="{ item }"><v-text-field v-model.number="item.plwd_target" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
          <template #item.under_5_target="{ item }"><v-text-field v-model.number="item.under_5_target" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
          <template #item.female_reproductive_target="{ item }"><v-text-field v-model.number="item.female_reproductive_target" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
          <template #item.elderly_target="{ item }"><v-text-field v-model.number="item.elderly_target" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
          <template #item.others_target="{ item }"><v-text-field v-model.number="item.others_target" type="number" min="0" density="compact" variant="outlined" hide-details /></template>
        </AppDataTable>
        <template #actions>
          <v-btn variant="outlined" @click="targetsDialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingTargets" prepend-icon="mdi-content-save-outline" @click="saveTargets">Save Targets</v-btn>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppModal from '../common/AppModal.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import { benefactorAPI, enrollmentPhaseAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { success, error } = useToast()
const loading = ref(false)
const loadingTargets = ref(false)
const savingPhase = ref(false)
const savingTargets = ref(false)
const phases = ref([])
const benefactors = ref([])
const targetRows = ref([])
const selectedPhase = ref(null)
const editingPhase = ref(null)
const phaseDialog = ref(false)
const targetsDialog = ref(false)

const statusOptions = [{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }]
const phaseHeaders = [
  { title: 'Phase', key: 'name' },
  { title: 'Period', key: 'period', sortable: false },
  { title: 'BHCPF Target', key: 'target' },
  { title: 'Allocation', key: 'coverage' },
  { title: 'Status', key: 'status', sortable: false },
  { title: '', key: 'actions', sortable: false, width: 140 },
]
const targetHeaders = [
  { title: 'LGA', key: 'lga_name' },
  { title: 'Enrollment Target', key: 'proposed_enrolments', width: 170 },
  { title: 'PLWD', key: 'plwd_target', width: 120 },
  { title: 'Under 5', key: 'under_5_target', width: 120 },
  { title: 'Female 15-45', key: 'female_reproductive_target', width: 145 },
  { title: 'Elderly', key: 'elderly_target', width: 120 },
  { title: 'Others', key: 'others_target', width: 120 },
]
const emptyPhase = () => ({ name: '', benefactor_id: null, start_date: '', end_date: '', status: 1, is_current: false })
const phaseForm = ref(emptyPhase())

const loadPhases = async () => {
  loading.value = true
  try {
    const { data } = await enrollmentPhaseAPI.getAll()
    phases.value = data.data || []
  } catch (err) {
    error(err.response?.data?.message || 'Unable to load enrollment phases.')
  } finally {
    loading.value = false
  }
}

const loadBenefactors = async () => {
  try {
    const { data } = await benefactorAPI.getAll({ per_page: 100 })
    const payload = data.data
    benefactors.value = Array.isArray(payload) ? payload : (payload?.data || [])
  } catch (err) {
    error('Unable to load benefactors for enrollment phases.')
  }
}

const openCreateDialog = () => {
  editingPhase.value = null
  phaseForm.value = emptyPhase()
  phaseDialog.value = true
}

const openEditDialog = (phase) => {
  editingPhase.value = phase
  phaseForm.value = {
    name: phase.name,
    benefactor_id: phase.benefactor_id,
    start_date: phase.start_date,
    end_date: phase.end_date,
    status: Number(phase.status),
    is_current: Boolean(phase.is_current),
  }
  phaseDialog.value = true
}

const savePhase = async () => {
  savingPhase.value = true
  try {
    const payload = { ...phaseForm.value, status: Number(phaseForm.value.status), is_current: Boolean(phaseForm.value.is_current) }
    if (editingPhase.value) await enrollmentPhaseAPI.update(editingPhase.value.id, payload)
    else await enrollmentPhaseAPI.create(payload)
    success(editingPhase.value ? 'Enrollment phase updated.' : 'Enrollment phase created. Set its BHCPF targets next.')
    phaseDialog.value = false
    await loadPhases()
  } catch (err) {
    error(err.response?.data?.message || 'Unable to save enrollment phase.')
  } finally {
    savingPhase.value = false
  }
}

const openTargetsDialog = async (phase) => {
  selectedPhase.value = phase
  targetsDialog.value = true
  loadingTargets.value = true
  try {
    const { data } = await enrollmentPhaseAPI.targets(phase.id)
    targetRows.value = data.data || []
  } catch (err) {
    error(err.response?.data?.message || 'Unable to load BHCPF targets.')
  } finally {
    loadingTargets.value = false
  }
}

const saveTargets = async () => {
  if (!selectedPhase.value) return
  savingTargets.value = true
  try {
    await enrollmentPhaseAPI.updateTargets(selectedPhase.value.id, { targets: targetRows.value })
    success('BHCPF targets saved for this enrollment phase.')
    await loadPhases()
  } catch (err) {
    error(err.response?.data?.message || 'Unable to save BHCPF targets.')
  } finally {
    savingTargets.value = false
  }
}

const removePhase = async (phase) => {
  if (!window.confirm(`Deactivate or delete ${phase.name}? Phases with targets or enrollees are safely deactivated.`)) return
  try {
    await enrollmentPhaseAPI.remove(phase.id)
    success('Enrollment phase updated.')
    await loadPhases()
  } catch (err) {
    error(err.response?.data?.message || 'Unable to remove enrollment phase.')
  }
}

const formatNumber = (value) => Number(value || 0).toLocaleString()
const formatDate = (value) => value ? new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : 'Not set'

onMounted(async () => {
  await Promise.all([loadPhases(), loadBenefactors()])
})
</script>
