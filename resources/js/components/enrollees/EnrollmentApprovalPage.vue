<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Pending Approval</h1>
          <p class="tw-text-sm tw-text-slate-500">Approve pending enrollees from a tracked random batch.</p>
        </div>
        <div class="tw-flex tw-gap-2">
          <v-select v-model="limit" :items="[50, 75, 100]" label="Batch size" density="compact" variant="outlined" hide-details class="tw-w-36" />
          <v-btn color="primary" prepend-icon="mdi-refresh" :loading="loading" @click="loadBatch">Load New Batch</v-btn>
        </div>
      </div>

      <div class="tw-grid tw-gap-3 md:tw-grid-cols-4">
        <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4"><p class="tw-text-xs tw-text-slate-500">Loaded</p><p class="tw-text-2xl tw-font-bold">{{ rows.length }}</p></div>
        <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4"><p class="tw-text-xs tw-text-slate-500">Approved</p><p class="tw-text-2xl tw-font-bold tw-text-green-700">{{ approvedCount }}</p></div>
        <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4"><p class="tw-text-xs tw-text-slate-500">Remaining</p><p class="tw-text-2xl tw-font-bold tw-text-amber-700">{{ remainingCount }}</p></div>
        <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4"><p class="tw-text-xs tw-text-slate-500">Failed</p><p class="tw-text-2xl tw-font-bold tw-text-red-700">{{ failedCount }}</p></div>
      </div>

      <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
        <v-progress-linear :model-value="progress" color="success" height="10" rounded />
        <div class="tw-mt-4 tw-grid tw-gap-3 md:tw-grid-cols-5">
          <v-select v-model="filters.programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Facility" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Phase" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding" density="compact" variant="outlined" clearable />
        </div>
      </div>

      <div class="tw-space-y-3">
        <div
          v-for="row in rows"
          :key="row.id"
          class="tw-rounded-lg tw-border tw-p-4 tw-transition"
          :class="row.local_status === 'approved' ? 'tw-border-green-200 tw-bg-green-50' : row.local_status === 'failed' ? 'tw-border-red-200 tw-bg-red-50' : 'tw-border-slate-200 tw-bg-white'"
        >
          <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
            <div class="tw-grid tw-flex-1 tw-gap-3 md:tw-grid-cols-3 xl:tw-grid-cols-6">
              <Info label="Enrollee" :value="`${row.full_name || row.name} (${row.enrollee_id})`" />
              <Info label="Phone / NIN" :value="`${row.phone || 'N/A'} / ${row.nin || 'N/A'}`" />
              <Info label="Programme" :value="row.insurance_programme?.name || 'N/A'" />
              <Info label="Premium" :value="row.premium_plan?.name || 'N/A'" />
              <Info label="Facility" :value="row.facility?.name || 'N/A'" />
              <Info label="Funding" :value="row.funding_type?.name || 'N/A'" />
              <Info label="Benefactor" :value="row.benefactor?.name || 'N/A'" />
              <Info label="Enrollment Date" :value="formatDate(row.enrollment_date || row.created_at)" />
              <Info label="Duplicate" :value="row.is_possible_duplicate ? 'Review needed' : 'Clear'" />
              <Info label="Payment" :value="row.premium_plan?.payment_required ? 'Required' : 'Not required'" />
            </div>
            <div class="tw-flex tw-min-w-40 tw-flex-col tw-gap-2">
              <v-chip v-if="row.local_status === 'approved'" color="success" variant="flat">Approved</v-chip>
              <v-chip v-else-if="row.local_status === 'failed'" color="error" variant="flat">Failed</v-chip>
              <v-btn v-else color="primary" :loading="approvingId === row.id" @click="approve(row)">Approve</v-btn>
              <p v-if="row.error" class="tw-text-xs tw-text-red-700">{{ row.error }}</p>
            </div>
          </div>
        </div>
      </div>

      <v-alert v-if="!loading && rows.length === 0" type="info" variant="tonal">No pending enrollees found for the selected filters.</v-alert>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, reactive, ref } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { enrolleeAPI, premiumAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const Info = defineComponent({
  props: { label: String, value: [String, Number] },
  setup: (props) => () => h('div', [h('p', { class: 'tw-text-xs tw-text-slate-500' }, props.label), h('p', { class: 'tw-text-sm tw-font-semibold tw-text-slate-900' }, props.value || 'N/A')]),
});

const { success, error } = useToast();
const rows = ref([]);
const loading = ref(false);
const approvingId = ref(null);
const limit = ref(50);
const metadata = reactive({ insurance_programmes: [], facilities: [], benefactors: [], enrollment_phases: [], funding_types: [] });
const filters = reactive({ programme_id: null, facility_id: null, benefactor_id: null, enrollment_phase_id: null, funding_type_id: null });

const approvedCount = computed(() => rows.value.filter((row) => row.local_status === 'approved').length);
const failedCount = computed(() => rows.value.filter((row) => row.local_status === 'failed').length);
const remainingCount = computed(() => rows.value.filter((row) => !row.local_status || row.local_status === 'pending').length);
const progress = computed(() => rows.value.length ? Math.round((approvedCount.value / rows.value.length) * 100) : 0);
const apiItems = (response) => response?.data?.data?.data || response?.data?.data || [];
const formatDate = (value) => value ? new Date(value).toLocaleDateString() : 'N/A';

const loadMetadata = async () => {
  const response = await premiumAPI.metadata();
  Object.assign(metadata, response.data.data || {});
};

const loadBatch = async () => {
  loading.value = true;
  try {
    const params = { ...filters, limit: limit.value, random: true };
    Object.keys(params).forEach((key) => (params[key] === null || params[key] === '') && delete params[key]);
    const response = await enrolleeAPI.pendingApproval(params);
    rows.value = apiItems(response).map((row) => ({ ...row, local_status: 'pending', error: '' }));
  } catch (e) {
    error(e.response?.data?.message || 'Could not load pending approval batch');
  } finally {
    loading.value = false;
  }
};

const approve = async (row) => {
  approvingId.value = row.id;
  row.error = '';
  try {
    const response = await enrolleeAPI.approve(row.id);
    Object.assign(row, response.data.data, { local_status: 'approved' });
    success(`${row.full_name || row.name} approved`);
  } catch (e) {
    row.local_status = 'failed';
    row.error = e.response?.data?.message || 'Approval failed';
  } finally {
    approvingId.value = null;
  }
};

onMounted(async () => {
  await loadMetadata();
  await loadBatch();
});
</script>
