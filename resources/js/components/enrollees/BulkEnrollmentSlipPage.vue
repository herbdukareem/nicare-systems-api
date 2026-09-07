<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div>
        <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Enrollment Slips</h1>
        <p class="tw-text-sm tw-text-slate-500">Download one enrollee's slip or generate a filtered bulk file.</p>
      </div>

      <v-alert type="info" variant="tonal">
        Enter an Enrollment Number or NIN for a single slip, or select a Benefactor or Provider/Facility for bulk slips. A single-enrollee lookup takes priority over the batch filters.
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
import { computed, onMounted, reactive, ref } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import { enrolleeAPI, premiumAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { error, success } = useToast();
const downloading = ref(false);
const metadata = reactive({ benefactors: [], facilities: [], insurance_programmes: [], enrollee_categories: [], funding_types: [], enrollment_phases: [] });
const filters = reactive({ identifier: '', benefactor_id: null, facility_id: null, insurance_programme_id: null, enrollee_category_id: null, funding_type_id: null, enrollment_phase_id: null, approval_status: 'all', date_from: '', date_to: '' });
const approvalOptions = [{ title: 'All', value: 'all' }, { title: 'Pending', value: 'pending' }, { title: 'Approved', value: 'approved' }];
const normalizedIdentifier = computed(() => String(filters.identifier || '').trim());
const isSingleLookup = computed(() => Boolean(normalizedIdentifier.value));

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
  const fallback = 'Could not download the enrollment slip';
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
  if (!isSingleLookup.value && !filters.benefactor_id && !filters.facility_id) {
    error('Enter an Enrollment Number or NIN, or select at least a Benefactor or Provider/Facility.');
    return;
  }

  downloading.value = true;
  try {
    const params = isSingleLookup.value
      ? { identifier: normalizedIdentifier.value }
      : { ...filters, identifier: normalizedIdentifier.value };
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key]);
    const response = await enrolleeAPI.bulkEnrollmentSlip(params);
    const contentType = response?.headers?.['content-type'] || '';
    const fallbackName = isSingleLookup.value
      ? `enrollment_slip_${normalizedIdentifier.value.replace(/[^A-Za-z0-9_-]+/g, '_')}.pdf`
      : contentType.includes('zip')
      ? `bulk_enrollment_slips_${new Date().toISOString().slice(0, 10)}.zip`
      : `bulk_enrollment_slip_${new Date().toISOString().slice(0, 10)}.pdf`;
    const filename = extractFilename(response, fallbackName);
    downloadBlob(response.data, filename);
    success(isSingleLookup.value
      ? 'Enrollee slip downloaded'
      : contentType.includes('zip')
        ? 'Bulk enrollment slip package downloaded'
        : 'Bulk enrollment slip downloaded');
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
