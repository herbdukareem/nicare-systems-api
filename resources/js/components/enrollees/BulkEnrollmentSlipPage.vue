<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div>
        <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Enrollment Slips</h1>
        <p class="tw-text-sm tw-text-slate-500">Download one enrollee's slip or generate a filtered bulk file.</p>
      </div>

      <v-alert type="info" variant="tonal">
        Enter an Enrollment Number or NIN for a single slip, or select a Benefactor or Provider/Facility for bulk slips. Bulk files are generated in smaller parts and combined into one download in your browser.
      </v-alert>

      <v-alert v-if="exportState.status" :type="exportAlertType" variant="tonal" aria-live="polite">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-2">
          <span>{{ exportState.message }}</span>
          <span v-if="isExportPending" class="tw-text-sm tw-font-semibold">
            {{ exportState.progress }}%
          </span>
        </div>
        <v-progress-linear
          v-if="isExportPending"
          class="tw-mt-3"
          color="primary"
          height="8"
          :indeterminate="exportState.status === 'preparing' || (exportState.status === 'generating' && exportState.processedCount === 0)"
          :model-value="exportState.progress"
          rounded
        />
        <div v-if="exportState.enrolleeCount" class="tw-mt-2 tw-flex tw-flex-wrap tw-justify-between tw-gap-2 tw-text-xs tw-font-medium">
          <span>{{ exportState.processedCount }} / {{ exportState.enrolleeCount }} slips prepared</span>
          <span v-if="exportState.currentPart && exportState.totalParts">
            Part {{ exportState.currentPart }} of {{ exportState.totalParts }}
          </span>
        </div>
      </v-alert>

      <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-5">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
          <v-text-field
            v-model="filters.identifier"
            class="md:tw-col-span-3"
            label="Enrollment Number or NIN"
            placeholder="e.g. NGSCHA074144 or an 11-digit NIN"
            prepend-inner-icon="mdi-account-search-outline"
            density="compact"
            variant="outlined"
            hint="Use this field to download a single enrollee's slip."
            persistent-hint
            clearable
            @keyup.enter="downloadPdf"
          />

          <div class="md:tw-col-span-3 tw-flex tw-items-center tw-gap-3 tw-py-1" aria-hidden="true">
            <span class="tw-h-px tw-flex-1 tw-bg-slate-200"></span>
            <span class="tw-text-xs tw-font-medium tw-uppercase tw-tracking-wide tw-text-slate-400">Or use batch filters</span>
            <span class="tw-h-px tw-flex-1 tw-bg-slate-200"></span>
          </div>

          <v-autocomplete v-model="filters.benefactor_id" :items="metadata.benefactors" :disabled="isSingleLookup" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.facility_id" :items="metadata.facilities" :disabled="isSingleLookup" item-title="name" item-value="id" label="Provider / Facility" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.insurance_programme_id" :items="metadata.insurance_programmes" :disabled="isSingleLookup" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.enrollee_category_id" :items="metadata.enrollee_categories" :disabled="isSingleLookup" item-title="name" item-value="id" label="Category" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.funding_type_id" :items="metadata.funding_types" :disabled="isSingleLookup" item-title="name" item-value="id" label="Funding Type" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" :disabled="isSingleLookup" item-title="name" item-value="id" label="Enrollment Phase" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.approval_status" :items="approvalOptions" :disabled="isSingleLookup" item-title="title" item-value="value" label="Approval Status" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_from" :disabled="isSingleLookup" type="date" label="Date From" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_to" :disabled="isSingleLookup" type="date" label="Date To" density="compact" variant="outlined" />
        </div>
        <div class="tw-mt-4 tw-flex tw-justify-end">
          <v-btn color="primary" prepend-icon="mdi-file-download-outline" :loading="downloading" @click="downloadPdf">
            {{ isSingleLookup ? 'Download Enrollee Slip' : 'Download Slip File' }}
          </v-btn>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import JSZip from 'jszip';
import AdminLayout from '../layout/AdminLayout.vue';
import { enrolleeAPI, premiumAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { error, success } = useToast();
const downloading = ref(false);
const metadata = reactive({ benefactors: [], facilities: [], insurance_programmes: [], enrollee_categories: [], funding_types: [], enrollment_phases: [] });
const filters = reactive({ identifier: '', benefactor_id: null, facility_id: null, insurance_programme_id: null, enrollee_category_id: null, funding_type_id: null, enrollment_phase_id: null, approval_status: 'all', date_from: '', date_to: '' });
const exportState = reactive({ status: '', message: '', progress: 0, enrolleeCount: 0, processedCount: 0, currentPart: 0, totalParts: 0 });
const approvalOptions = [{ title: 'All', value: 'all' }, { title: 'Pending', value: 'pending' }, { title: 'Approved', value: 'approved' }];
const normalizedIdentifier = computed(() => String(filters.identifier || '').trim());
const isSingleLookup = computed(() => Boolean(normalizedIdentifier.value));
const isExportPending = computed(() => ['preparing', 'generating', 'packaging'].includes(exportState.status));
const exportAlertType = computed(() => exportState.status === 'failed' ? 'error' : exportState.status === 'completed' ? 'success' : 'info');
let activeExportController = null;

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

const downloadSingleSlip = async (params) => {
  const response = await enrolleeAPI.bulkEnrollmentSlip(params);
  const fallbackName = `enrollment_slip_${normalizedIdentifier.value.replace(/[^A-Za-z0-9_-]+/g, '_')}.pdf`;
  downloadBlob(response.data, extractFilename(response, fallbackName));
  success('Enrollee slip downloaded');
};

const downloadPdf = async () => {
  if (!isSingleLookup.value && !filters.benefactor_id && !filters.facility_id) {
    error('Enter an Enrollment Number or NIN, or select at least a Benefactor or Provider/Facility.');
    return;
  }

  activeExportController?.abort();
  const controller = new AbortController();
  activeExportController = controller;
  downloading.value = true;
  resetExportState();

  try {
    const params = isSingleLookup.value
      ? { identifier: normalizedIdentifier.value }
      : { ...filters, identifier: normalizedIdentifier.value };
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key]);

    if (isSingleLookup.value) {
      await downloadSingleSlip(params);
      return;
    }

    exportState.status = 'preparing';
    exportState.message = 'Finding matching enrollees...';

    const manifestResponse = await enrolleeAPI.prepareBulkEnrollmentSlip(params);
    const manifest = manifestResponse?.data?.data || {};
    const totalParts = Number(manifest.total_parts || 0);
    const enrolleeCount = Number(manifest.enrollee_count || 0);
    const chunkSize = Number(manifest.chunk_size || 0);

    if (!manifest.token || totalParts < 1 || enrolleeCount < 1 || chunkSize < 1) {
      throw new Error('The server returned an invalid enrollment slip export manifest.');
    }

    Object.assign(exportState, {
      status: 'generating',
      message: `Generating part 1 of ${totalParts}...`,
      progress: 0,
      enrolleeCount,
      processedCount: 0,
      currentPart: 1,
      totalParts,
    });

    const zip = totalParts > 1 ? new JSZip() : null;
    let singlePart = null;
    let cursor = 0;

    for (let part = 1; part <= totalParts; part += 1) {
      if (controller.signal.aborted) return;

      exportState.currentPart = part;
      exportState.message = `Generating part ${part} of ${totalParts}...`;

      const response = await downloadExportPart(manifest.token, part, cursor, totalParts, controller.signal);
      const fallbackName = `bulk_enrollment_slip_part_${part}_of_${totalParts}.pdf`;
      const filename = extractFilename(response, fallbackName);
      const nextCursor = Number(response?.headers?.['x-enrollment-slip-next-cursor'] || 0);

      if (nextCursor <= cursor && part < totalParts) {
        throw new Error('The server returned an invalid enrollment slip cursor. Please restart the export.');
      }
      cursor = nextCursor;

      if (zip) {
        zip.file(filename, response.data);
      } else {
        singlePart = { blob: response.data, filename };
      }

      exportState.processedCount = Math.min(enrolleeCount, part * chunkSize);
      exportState.progress = totalParts === 1
        ? 100
        : Math.min(90, Math.round((exportState.processedCount / enrolleeCount) * 90));
    }

    if (singlePart) {
      downloadBlob(singlePart.blob, singlePart.filename);
    } else {
      exportState.status = 'packaging';
      exportState.message = 'Combining the generated PDF parts into one ZIP file...';
      exportState.currentPart = totalParts;

      const zipBlob = await zip.generateAsync(
        { type: 'blob', compression: 'STORE' },
        ({ percent }) => {
          exportState.progress = Math.min(99, 90 + Math.round(percent / 10));
        }
      );

      if (controller.signal.aborted) return;
      downloadBlob(zipBlob, manifest.file_name || `bulk_enrollment_slips_${new Date().toISOString().slice(0, 10)}.zip`);
    }

    exportState.status = 'completed';
    exportState.progress = 100;
    exportState.message = `${enrolleeCount} enrollment slip${enrolleeCount === 1 ? '' : 's'} downloaded successfully.`;
    success(exportState.message);
  } catch (e) {
    if (!controller.signal.aborted && e?.code !== 'ERR_CANCELED') {
      const message = await extractErrorMessage(e);
      exportState.status = 'failed';
      exportState.message = message;
      error(message);
    }
  } finally {
    if (activeExportController === controller) {
      activeExportController = null;
    }
    downloading.value = false;
  }
};

onMounted(async () => {
  const response = await premiumAPI.metadata();
  Object.assign(metadata, response.data.data || {});
});

onBeforeUnmount(() => {
  activeExportController?.abort();
});
</script>
