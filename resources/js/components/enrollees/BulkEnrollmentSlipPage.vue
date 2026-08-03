<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div>
        <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Bulk Enrollment Slip</h1>
        <p class="tw-text-sm tw-text-slate-500">Generate one PDF grouped by benefactor and provider/facility.</p>
      </div>

      <v-alert type="info" variant="tonal">
        Select at least a Benefactor or Provider/Facility before generating the slip file. Large batches may download as a ZIP package of multiple PDFs.
      </v-alert>

      <div class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-5">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-3">
          <v-autocomplete v-model="filters.benefactor_id" :items="metadata.benefactors" item-title="name" item-value="id" label="Benefactor" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.facility_id" :items="metadata.facilities" item-title="name" item-value="id" label="Provider / Facility" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.insurance_programme_id" :items="metadata.insurance_programmes" item-title="name" item-value="id" label="Programme" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.enrollee_category_id" :items="metadata.enrollee_categories" item-title="name" item-value="id" label="Category" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.funding_type_id" :items="metadata.funding_types" item-title="name" item-value="id" label="Funding Type" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.enrollment_phase_id" :items="metadata.enrollment_phases" item-title="name" item-value="id" label="Enrollment Phase" density="compact" variant="outlined" clearable />
          <v-autocomplete v-model="filters.approval_status" :items="approvalOptions" item-title="title" item-value="value" label="Approval Status" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_from" type="date" label="Date From" density="compact" variant="outlined" />
          <v-text-field v-model="filters.date_to" type="date" label="Date To" density="compact" variant="outlined" />
        </div>
        <div class="tw-mt-4 tw-flex tw-justify-end">
          <v-btn color="primary" prepend-icon="mdi-file-download-outline" :loading="downloading" @click="downloadPdf">Download Slip File</v-btn>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { enrolleeAPI, premiumAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { error, success } = useToast();
const downloading = ref(false);
const metadata = reactive({ benefactors: [], facilities: [], insurance_programmes: [], enrollee_categories: [], funding_types: [], enrollment_phases: [] });
const filters = reactive({ benefactor_id: null, facility_id: null, insurance_programme_id: null, enrollee_category_id: null, funding_type_id: null, enrollment_phase_id: null, approval_status: 'all', date_from: '', date_to: '' });
const approvalOptions = [{ title: 'All', value: 'all' }, { title: 'Pending', value: 'pending' }, { title: 'Approved', value: 'approved' }];

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
  link.click();
  URL.revokeObjectURL(url);
};

const extractErrorMessage = async (err) => {
  const fallback = 'Could not download bulk enrollment slip';
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

const downloadPdf = async () => {
  if (!filters.benefactor_id && !filters.facility_id) {
    error('Please select at least a Benefactor or Provider/Facility before generating bulk slips.');
    return;
  }

  downloading.value = true;
  try {
    const params = { ...filters };
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key]);
    const response = await enrolleeAPI.bulkEnrollmentSlip(params);
    const contentType = response?.headers?.['content-type'] || '';
    const fallbackName = contentType.includes('zip')
      ? `bulk_enrollment_slips_${new Date().toISOString().slice(0, 10)}.zip`
      : `bulk_enrollment_slip_${new Date().toISOString().slice(0, 10)}.pdf`;
    const filename = extractFilename(response, fallbackName);
    downloadBlob(response.data, filename);
    success(contentType.includes('zip') ? 'Bulk enrollment slip package downloaded' : 'Bulk enrollment slip downloaded');
  } catch (e) {
    error(await extractErrorMessage(e));
  } finally {
    downloading.value = false;
  }
};

onMounted(async () => {
  const response = await premiumAPI.metadata();
  Object.assign(metadata, response.data.data || {});
});
</script>
