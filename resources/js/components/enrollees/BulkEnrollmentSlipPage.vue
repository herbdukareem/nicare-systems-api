<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <AppPageHeader title="Enrollment Slips" subtitle="Generate outstanding slips, track printing exercises, and reprint previous slips." />
      <AppAlert tone="info">
        Generated for printing means the download file was prepared; paper printing is not confirmed.
        Slips without a recorded generation, including older enrolments, remain in the outstanding queue.
      </AppAlert>
      <AppAlert v-if="exportState.status" :tone="exportAlertType" aria-live="polite">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
          <span>{{ exportState.message }}</span>
          <AppButton v-if="downloading && exportState.status !== 'finalizing'" variant="outlined" @click="pauseDownload">Pause</AppButton>
        </div>
        <v-progress-linear v-if="downloading" class="tw-mt-3" color="primary" :model-value="exportState.progress" :indeterminate="exportState.status === 'preparing'" height="8" />
      </AppAlert>
      <AppTabs v-model="activeTab" :tabs="tabs">
        <template v-if="activeTab === 'queue'">
          <AppAlert v-if="unfinishedCount" tone="warning">
            You have {{ unfinishedCount }} unfinished printing exercise(s). Resume or release them from My printing exercises.
            <AppButton variant="text" @click="activeTab = 'history'">View exercises</AppButton>
          </AppAlert>
          <AppCard title="Find enrolment slips">
            <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
              <v-text-field v-model="filters.identifier" :disabled="downloading" label="Enrollment Number or NIN" hint="Find one enrollee, including previously generated slips." persistent-hint density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.benefactor_id" :items="metadata.benefactors" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="selectedLgaId" :items="metadata.lgas" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.facility_id" :items="filteredFacilities" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Provider / Facility" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.insurance_programme_id" :items="metadata.insurance_programmes" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.enrollee_category_id" :items="metadata.enrollee_categories" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Category" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.funding_type_id" :items="metadata.funding_types" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Funding Type" density="compact" variant="outlined" clearable />
              <v-autocomplete v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" :disabled="batchFiltersDisabled" item-title="name" item-value="id" label="Enrollment Phase" density="compact" variant="outlined" clearable />
              <v-select v-model="filters.approval_status" :items="approvalOptions" :disabled="batchFiltersDisabled" item-title="title" item-value="value" label="Approval Status" density="compact" variant="outlined" />
              <v-select v-model="filters.printing_status" :items="printingOptions" :disabled="batchFiltersDisabled" item-title="title" item-value="value" label="Printing Status" density="compact" variant="outlined" />
              <v-text-field v-model="filters.date_from" :disabled="batchFiltersDisabled" type="date" label="Enrolled From" density="compact" variant="outlined" />
              <v-text-field v-model="filters.date_to" :disabled="batchFiltersDisabled" type="date" label="Enrolled To" density="compact" variant="outlined" />
            </div>
            <div class="tw-flex tw-flex-wrap tw-justify-end tw-gap-2">
              <AppButton variant="outlined" prepend-icon="mdi-printer-outline" :disabled="downloading || !!printingList || loading" :loading="printingList === 'queue'" @click="printCollectionList()">{{ selectedIds.length ? 'Print selected collection list' : 'Print generated slips list' }}</AppButton>
              <AppButton variant="outlined" :disabled="downloading" :loading="loading" prepend-icon="mdi-refresh" @click="refreshQueue">Refresh slips</AppButton>
              <AppButton :disabled="downloading || loading || !selectedIds.length" @click="downloadPdf(true)">Generate selected ({{ selectedIds.length }})</AppButton>
              <AppButton :disabled="downloading || loading || !!loadError || !total" prepend-icon="mdi-file-download-outline" @click="downloadPdf(false)">Generate all {{ total }} matching slips</AppButton>
            </div>
            <p class="tw-mt-2 tw-text-xs tw-text-slate-500">Collection lists use the current filters and include only slips already generated, grouped by facility, with spaces for collection dates and signatures.</p>
          </AppCard>
          <AppAlert v-if="loadError" tone="danger" :message="loadError" />
          <AppCard title="Matching enrolment slips" :padded="false">
            <AppDataTable v-model="selectedIds" :headers="queueHeaders" :items="rows" :loading="loading" :items-length="total" :page="page" :items-per-page="perPage" :per-page-options="[10, 25, 50, 100]" item-value="id" item-selectable="available" :show-select="!downloading" @update:page="changePage" @update:items-per-page="changePerPage">
              <template #item.printing_status="{ item }">
                <AppBadge :label="item.printing_status === 'generated' ? 'Generated for printing' : 'No recorded generation'" :tone="item.printing_status === 'generated' ? 'success' : 'warning'" size="sm" />
                <div v-if="!item.available" class="tw-text-xs tw-text-slate-500">In a printing exercise</div>
              </template>
              <template #item.last_generated_at="{ item }">
                <span :title="item.first_generated_at ? 'First generated: ' + formatDate(item.first_generated_at) : ''">{{ formatDate(item.last_generated_at) }}</span>
                <div v-if="item.last_batch_id" :title="item.last_batch_id" class="tw-text-xs tw-text-slate-500">Batch {{ item.last_batch_id.slice(0, 8) }}</div>
              </template>
              <template #item.last_generated_by="{ item }">{{ item.last_generated_by || '?' }}</template>
              <template #item.actions="{ item }">
                <AppButton v-if="item.printing_status === 'generated'" variant="text" :disabled="downloading || !item.available" @click="reprint(item)">Reprint</AppButton>
              </template>
              <template #no-data><AppEmptyState title="No matching slips" description="Newly approved enrolments appear here when their slips have no recorded generation. Change Printing Status to find previous slips." compact /></template>
            </AppDataTable>
          </AppCard>
        </template>
        <AppCard v-else title="My printing exercises" subtitle="Resume interrupted exercises within two hours. Saved completed copies are available for seven days; downloading the same exercise does not count as a reprint." :padded="false">
          <template #actions><AppButton variant="outlined" :disabled="downloading" :loading="historyLoading" @click="loadHistory">Refresh</AppButton></template>
          <AppAlert v-if="historyError" tone="danger" :message="historyError" />
          <AppDataTable :headers="historyHeaders" :items="batches" :loading="historyLoading" :items-length="historyTotal" :page="historyPage" :items-per-page="10" :per-page-options="[10]" @update:page="changeHistoryPage">
            <template #item.id="{ item }"><span :title="item.id">{{ item.id.slice(0, 8) }}</span></template>
            <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
            <template #item.status="{ item }"><AppBadge :label="batchStatusLabel(item.status)" :tone="item.status === 'completed' ? 'success' : item.status === 'preparing' ? 'info' : 'neutral'" size="sm" /></template>
            <template #item.generated_parts="{ item }">{{ item.generated_parts }} / {{ item.total_parts }}</template>
            <template #item.actions="{ item }">
              <div class="tw-flex tw-gap-2">
                <AppButton v-if="item.status === 'completed'" variant="outlined" prepend-icon="mdi-printer-outline" :disabled="downloading || !!printingList" :loading="printingList === item.id" @click="printCollectionList(item)">Print collection list</AppButton>
                <AppButton v-if="item.status === 'preparing' || (item.status === 'completed' && item.can_download)" variant="text" :disabled="downloading" @click="downloadPdf(false, item)">{{ item.status === 'completed' ? 'Download saved copy' : 'Resume' }}</AppButton>
                <AppButton v-if="item.status === 'preparing'" variant="outlined" :disabled="downloading" @click="releaseBatch(item)">Release slips</AppButton>
                <span v-if="item.status === 'completed' && !item.can_download" class="tw-text-xs tw-text-slate-500">Saved copy expired; use Reprint</span>
              </div>
            </template>
            <template #no-data><AppEmptyState title="No printing exercises yet" description="Your generation and reprint exercises will appear here." compact /></template>
          </AppDataTable>
        </AppCard>
      </AppTabs>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import JSZip from 'jszip';
import AdminLayout from '../layout/AdminLayout.vue';
import AppAlert from '../common/AppAlert.vue';
import AppBadge from '../common/AppBadge.vue';
import AppButton from '../common/AppButton.vue';
import AppCard from '../common/AppCard.vue';
import AppDataTable from '../common/AppDataTable.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppTabs from '../common/AppTabs.vue';
import { enrolleeAPI, premiumAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { error, success } = useToast();
const downloading = ref(false);
const printingList = ref('');
const loading = ref(true);
const loadError = ref('');
const historyLoading = ref(false);
const historyError = ref('');
const rows = ref([]);
const total = ref(0);
const selectedIds = ref([]);
const page = ref(1);
const perPage = ref(25);
const batches = ref([]);
const historyTotal = ref(0);
const historyPage = ref(1);
const activeTab = ref('queue');
const tabs = [{ value: 'queue', label: 'Printing queue', icon: 'mdi-printer-outline' }, { value: 'history', label: 'My printing exercises', icon: 'mdi-history' }];
const metadata = reactive({ benefactors: [], lgas: [], facilities: [], insurance_programmes: [], enrollee_categories: [], funding_types: [], enrollment_phases: [] });
const selectedLgaId = ref(null);
const filters = reactive({ identifier: '', benefactor_id: null, facility_id: null, insurance_programme_id: null, enrollee_category_id: null, funding_type_id: null, enrollment_phase_id: null, approval_status: 'approved', printing_status: 'outstanding', date_from: '', date_to: '' });
const filteredFacilities = computed(() => metadata.facilities.filter((facility) => !selectedLgaId.value || Number(facility.lga_id ?? facility.lga?.id) === Number(selectedLgaId.value)));
const batchFiltersDisabled = computed(() => downloading.value || !!printingList.value || Boolean(String(filters.identifier || '').trim()));
const unfinishedCount = computed(() => batches.value.filter((batch) => batch.status === 'preparing').length);
const exportState = reactive({ status: '', message: '', progress: 0, enrolleeCount: 0, processedCount: 0, currentPart: 0, totalParts: 0 });
const exportAlertType = computed(() => exportState.status === 'failed' ? 'danger' : exportState.status === 'completed' ? 'success' : 'info');
const approvalOptions = [{ title: 'All', value: 'all' }, { title: 'Pending', value: 'pending' }, { title: 'Approved', value: 'approved' }];
const printingOptions = [{ title: 'Not yet generated', value: 'outstanding' }, { title: 'Generated for printing', value: 'generated' }, { title: 'All', value: 'all' }];
const queueHeaders = [
  { title: 'Enrollment Number', key: 'enrollee_id' }, { title: 'Name', key: 'name' }, { title: 'Facility', key: 'facility' },
  { title: 'Printing Status', key: 'printing_status' }, { title: 'Last Generated', key: 'last_generated_at' },
  { title: 'Generated By', key: 'last_generated_by' }, { title: 'Times Generated', key: 'generation_count' }, { title: '', key: 'actions' },
].map((header) => ({ ...header, sortable: false }));
const historyHeaders = [
  { title: 'Batch', key: 'id' }, { title: 'Started', key: 'created_at' }, { title: 'Status', key: 'status' },
  { title: 'Slips', key: 'enrollee_count' }, { title: 'PDF Parts', key: 'generated_parts' }, { title: '', key: 'actions' },
].map((header) => ({ ...header, sortable: false }));
const formatDate = (value) => value ? new Date(value).toLocaleString() : '?';
const batchStatusLabel = (value) => ({ preparing: 'In progress', completed: 'Generated for printing', expired: 'Expired', cancelled: 'Released' }[value] || value);
let activeExportController = null;
let queueController = null;
let historyController = null;
let filterTimer = null;
let disposed = false;

const extractFilename = (response, fallback) => {
  const disposition = response?.headers?.['content-disposition'] || response?.headers?.['Content-Disposition'];
  if (!disposition) return fallback;

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const plainMatch = disposition.match(/filename="?([^"]+)"?/i);
  return plainMatch?.[1] || fallback;
};

const downloadBlob = (blob, filename) => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 0);
};

const extractErrorMessage = async (err) => {
  const fallback = err?.message || 'Could not download the enrollment slip';
  const payload = err?.response?.data;

  if (!payload) {
    return fallback;
  }

  if (payload instanceof Blob) {
    try {
      const text = await payload.text();
      const parsed = JSON.parse(text);
      return parsed?.message || fallback;
    } catch {
      return fallback;
    }
  }

  return payload?.message || fallback;
};

const resetExportState = () => {
  Object.assign(exportState, {
    status: '',
    message: '',
    progress: 0,
    enrolleeCount: 0,
    processedCount: 0,
    currentPart: 0,
    totalParts: 0,
  });
};

const wait = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));

const downloadExportPart = async (token, part, cursor, totalParts, signal) => {
  let lastError;

  for (let attempt = 1; attempt <= 3; attempt += 1) {
    try {
      return await enrolleeAPI.bulkEnrollmentSlipPart({ token, part, cursor }, { signal });
    } catch (err) {
      if (signal.aborted || err?.code === 'ERR_CANCELED') throw err;
      lastError = err;

      if (attempt < 3) {
        exportState.message = `Retrying part ${part} of ${totalParts} (attempt ${attempt + 1} of 3)...`;
        await wait(attempt * 1000);
      }
    }
  }

  throw lastError;
};


const queueParams = () => {
  const identifier = String(filters.identifier || '').trim();
  const params = identifier ? { identifier } : { ...filters, identifier: '' };
  Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key]);
  return params;
};

const loadQueue = async () => {
  queueController?.abort();
  const controller = new AbortController();
  queueController = controller;
  loading.value = true;
  loadError.value = '';
  try {
    const response = await enrolleeAPI.enrollmentSlipQueue({ ...queueParams(), page: page.value, per_page: perPage.value }, { signal: controller.signal });
    if (controller.signal.aborted || disposed) return;
    const data = response.data.data;
    rows.value = data.data;
    total.value = data.total;
  } catch (err) {
    if (!controller.signal.aborted && !disposed) {
      rows.value = [];
      total.value = 0;
      loadError.value = await extractErrorMessage(err);
    }
  } finally {
    if (queueController === controller) loading.value = false;
  }
};
const refreshQueue = () => { clearTimeout(filterTimer); page.value = 1; selectedIds.value = []; return loadQueue(); };
const changePage = (value) => { page.value = value; loadQueue(); };
const changePerPage = (value) => { perPage.value = value; page.value = 1; selectedIds.value = []; loadQueue(); };
const loadHistory = async () => {
  historyController?.abort();
  const controller = new AbortController();
  historyController = controller;
  historyLoading.value = true;
  historyError.value = '';
  try {
    const response = await enrolleeAPI.enrollmentSlipBatches({ page: historyPage.value }, { signal: controller.signal });
    if (disposed || controller.signal.aborted) return;
    batches.value = response.data.data.data;
    historyTotal.value = response.data.data.total;
  } catch (err) {
    if (!disposed && !controller.signal.aborted) historyError.value = await extractErrorMessage(err);
  } finally {
    if (historyController === controller) historyLoading.value = false;
  }
};
const changeHistoryPage = (value) => { historyPage.value = value; loadHistory(); };
const pauseDownload = () => activeExportController?.abort();
const releaseBatch = async (batch) => {
  if (downloading.value) return;
  downloading.value = true;
  try {
    await enrolleeAPI.cancelEnrollmentSlipBatch(batch.id);
    success('Outstanding slips released for another exercise.');
    await Promise.all([refreshQueue(), loadHistory()]);
  } catch (err) {
    error(await extractErrorMessage(err));
  } finally {
    downloading.value = false;
  }
};
const requestUuid = () => {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
  return [hex.slice(0, 8), hex.slice(8, 12), hex.slice(12, 16), hex.slice(16, 20), hex.slice(20)].join('-');
};
const reprint = (row) => downloadPdf(false, null, row);

const printCollectionList = async (batch = null) => {
  if (downloading.value || printingList.value) return;
  printingList.value = batch?.id || 'queue';
  // Open during the click so browsers can present the PDF in a printable tab.
  const preview = window.open('', '_blank');
  if (preview) {
    preview.opener = null;
    preview.document.title = 'Enrolment slip collection list - Form C';
    preview.document.body.textContent = 'Preparing collection list...';
  }
  try {
    const params = batch ? { batch_id: batch.id } : {
      ...queueParams(),
      ...(!String(filters.identifier || '').trim() && selectedLgaId.value ? { facility_lga_id: selectedLgaId.value } : {}),
      ...(selectedIds.value.length ? { enrollee_ids: [...selectedIds.value] } : {}),
    };
    const response = await enrolleeAPI.enrollmentSlipCollectionList(params);
    if (disposed) { preview?.close(); return; }
    if (preview && !preview.closed) {
      const url = URL.createObjectURL(response.data);
      preview.location.replace(url);
      window.setTimeout(() => URL.revokeObjectURL(url), 60000);
    } else {
      downloadBlob(response.data, extractFilename(response, 'enrollment_slip_collection_form_c.pdf'));
      success('Collection list downloaded. Open the PDF to print.');
    }
  } catch (err) {
    preview?.close();
    error(await extractErrorMessage(err));
  } finally {
    printingList.value = '';
  }
};

const downloadPdf = async (selectedOnly = false, resume = null, singleRow = null) => {
  if (downloading.value || printingList.value) return;
  const controller = new AbortController();
  activeExportController = controller;
  downloading.value = true;
  resetExportState();
  try {
    exportState.status = 'preparing';
    exportState.message = resume ? 'Opening saved printing exercise...' : 'Reserving matching enrolment slips...';
    let manifest;
    if (resume) {
      manifest = (await enrolleeAPI.enrollmentSlipBatch(resume.id)).data.data;
    } else {
      const params = singleRow
        ? { identifier: singleRow.enrollee_id }
        : { ...queueParams(), ...(selectedOnly ? { enrollee_ids: [...selectedIds.value] } : {}) };
      manifest = (await enrolleeAPI.prepareBulkEnrollmentSlip({ ...params, tracked: true, request_id: requestUuid() })).data.data;
    }
    if (controller.signal.aborted) throw new DOMException('Paused', 'AbortError');
    if (!['preparing', 'completed'].includes(manifest.status)) throw new Error('This exercise has expired or was released. Start a new exercise.');
    const totalParts = Number(manifest.total_parts);
    const enrolleeCount = Number(manifest.enrollee_count);
    const chunkSize = Number(manifest.chunk_size);
    if (!manifest.token || totalParts < 1 || enrolleeCount < 1 || chunkSize < 1) throw new Error('Invalid printing exercise.');
    Object.assign(exportState, { status: 'generating', enrolleeCount, totalParts });
    const zip = totalParts > 1 ? new JSZip() : null;
    let singlePart;
    let cursor = 0;
    for (let part = 1; part <= totalParts; part += 1) {
      if (controller.signal.aborted) throw new DOMException('Paused', 'AbortError');
      exportState.currentPart = part;
      exportState.message = 'Preparing PDF part ' + part + ' of ' + totalParts + '...';
      const response = await downloadExportPart(manifest.token, part, cursor, totalParts, controller.signal);
      const filename = extractFilename(response, 'enrollment_slips_part_' + part + '.pdf');
      cursor = Number(response.headers?.['x-enrollment-slip-next-cursor'] || 0);
      if (zip) zip.file(filename, response.data);
      else singlePart = { blob: response.data, filename };
      exportState.processedCount = Math.min(enrolleeCount, part * chunkSize);
      exportState.progress = Math.round((part / totalParts) * 90);
    }
    let file = singlePart;
    if (zip) {
      exportState.status = 'packaging';
      exportState.message = 'Combining PDF parts into one ZIP file...';
      const blob = await zip.generateAsync({ type: 'blob', compression: 'STORE' }, ({ percent }) => {
        exportState.progress = Math.min(98, 90 + Math.round(percent * 0.08));
      });
      file = { blob, filename: manifest.file_name };
    }
    if (controller.signal.aborted) throw new DOMException('Paused', 'AbortError');
    exportState.status = 'finalizing';
    exportState.message = 'Recording slips as generated for printing...';
    // Completion is idempotent, including when the server commits but the response is lost.
    for (let attempt = 1; ; attempt++) {
      try {
        await enrolleeAPI.completeEnrollmentSlipBatch(manifest.id);
        break;
      } catch (err) {
        if (attempt === 3 || (err.response && err.response.status < 500)) throw err;
        await wait(attempt * 1000);
      }
    }
    if (!disposed) downloadBlob(file.blob, file.filename);
    exportState.status = 'completed';
    exportState.progress = 100;
    exportState.message = enrolleeCount + ' slip(s) ' + (manifest.status === 'completed' ? 'downloaded from the saved exercise.' : 'generated for printing.');
    success(exportState.message);
  } catch (err) {
    if (controller.signal.aborted || err.name === 'AbortError' || err.code === 'ERR_CANCELED') {
      exportState.status = 'paused';
      exportState.message = 'Download paused. Resume the exercise from My printing exercises, or release its slips.';
    } else {
      exportState.status = 'failed';
      exportState.message = await extractErrorMessage(err);
      error(exportState.message);
    }
  } finally {
    activeExportController = null;
    downloading.value = false;
    historyPage.value = 1;
    if (!disposed) await Promise.all([refreshQueue(), loadHistory()]);
  }
};

watch(selectedLgaId, () => {
  if (filters.facility_id && !filteredFacilities.value.some((facility) => Number(facility.id) === Number(filters.facility_id))) filters.facility_id = null;
});
watch(filters, () => {
  clearTimeout(filterTimer);
  queueController?.abort();
  page.value = 1;
  selectedIds.value = [];
  rows.value = [];
  total.value = 0;
  loading.value = true;
  filterTimer = setTimeout(loadQueue, 300);
}, { deep: true });
watch(activeTab, (value) => { if (value === 'history') loadHistory(); });
onMounted(async () => {
  try {
    const response = await premiumAPI.metadata();
    if (!disposed) Object.assign(metadata, response.data.data || {});
  } catch (err) {
    error(await extractErrorMessage(err));
  }
  if (!disposed) await Promise.all([loadQueue(), loadHistory()]);
});
onBeforeUnmount(() => {
  disposed = true;
  clearTimeout(filterTimer);
  activeExportController?.abort();
  queueController?.abort();
  historyController?.abort();
});
</script>
