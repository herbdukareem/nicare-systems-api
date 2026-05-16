<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <!-- Header -->
      <div>
        <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Bulk ID Cards</h1>
        <p class="tw-text-sm tw-text-slate-500">Generate a PDF of NiCare enrolee ID cards (front + back) grouped by facility.</p>
      </div>

      <v-alert type="info" variant="tonal" class="tw-text-sm">
        Select at least a <strong>Benefactor</strong> or <strong>Provider/Facility</strong> before generating. Maximum 200 cards per batch.
      </v-alert>

      <!-- Filter card -->
      <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-5">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
          <v-select
            v-model="filters.benefactor_id"
            :items="metadata.benefactors"
            item-title="name"
            item-value="id"
            label="Benefactor"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.facility_id"
            :items="metadata.facilities"
            item-title="name"
            item-value="id"
            label="Provider / Facility"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.insurance_programme_id"
            :items="metadata.insurance_programmes"
            item-title="name"
            item-value="id"
            label="Programme"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.enrollee_category_id"
            :items="metadata.enrollee_categories"
            item-title="name"
            item-value="id"
            label="Category"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.funding_type_id"
            :items="metadata.funding_types"
            item-title="name"
            item-value="id"
            label="Funding Type"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.enrollment_phase_id"
            :items="metadata.enrollment_phases"
            item-title="name"
            item-value="id"
            label="Enrollment Phase"
            density="compact"
            variant="outlined"
            clearable
          />
          <v-select
            v-model="filters.approval_status"
            :items="approvalOptions"
            item-title="title"
            item-value="value"
            label="Approval Status"
            density="compact"
            variant="outlined"
          />
          <v-text-field v-model="filters.date_from" type="date" label="Enrolled From" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_to"   type="date" label="Enrolled To"   density="compact" variant="outlined" />
        </div>

        <div class="tw-mt-4 tw-flex tw-items-center tw-justify-end tw-gap-3">
          <span v-if="generating" class="tw-text-sm tw-text-slate-500">Generating PDF, please wait…</span>
          <v-btn
            color="primary"
            prepend-icon="mdi-card-account-details-outline"
            :loading="generating"
            @click="openPdf"
          >
            Generate ID Cards PDF
          </v-btn>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import { enrolleeAPI, premiumAPI } from '../../utils/api'
import { useToast } from '../../composables/useToast'

const { error, success } = useToast()
const generating = ref(false)

const metadata = reactive({
  benefactors: [], facilities: [], insurance_programmes: [],
  enrollee_categories: [], funding_types: [], enrollment_phases: [],
})

const filters = reactive({
  benefactor_id: null,
  facility_id: null,
  insurance_programme_id: null,
  enrollee_category_id: null,
  funding_type_id: null,
  enrollment_phase_id: null,
  approval_status: 'approved',
  date_from: '',
  date_to: '',
})

const approvalOptions = [
  { title: 'All',      value: 'all'      },
  { title: 'Pending',  value: 'pending'  },
  { title: 'Approved', value: 'approved' },
]

const openPdf = async () => {
  if (!filters.benefactor_id && !filters.facility_id) {
    error('Please select at least a Benefactor or Provider/Facility before generating.')
    return
  }

  generating.value = true
  try {
    const params = { ...filters }
    Object.keys(params).forEach((k) => (params[k] === '' || params[k] === null) && delete params[k])

    const response = await enrolleeAPI.bulkIdCard(params)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url  = URL.createObjectURL(blob)
    const win  = window.open(url, '_blank')

    if (!win) {
      error('Please allow pop-ups to open the ID cards PDF')
      URL.revokeObjectURL(url)
      return
    }

    success('Bulk ID cards PDF generated successfully')
    setTimeout(() => URL.revokeObjectURL(url), 120000)
  } catch (e) {
    error(e.response?.data?.message || 'Could not generate bulk ID cards')
  } finally {
    generating.value = false
  }
}

onMounted(async () => {
  const response = await premiumAPI.metadata()
  Object.assign(metadata, response.data.data || {})
})
</script>
