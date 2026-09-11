<template>
  <AppModal
    :model-value="modelValue"
    title="Bulk NIN Update"
    subtitle="Update enrollee NINs using a CSV or Excel file."
    icon="mdi-card-account-details-outline"
    size="lg"
    :loading="uploading"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="tw-space-y-4">
      <AppAlert tone="warning" message="A blank NIN cell clears the enrollee's current NIN. Verified NINs are protected and will be skipped." />
      <p class="tw-text-sm tw-text-slate-600">
        Use NICARE ID and NIN columns, with one enrollee per row. NINs must contain exactly 11 digits.
        Format NIN cells as Text in Excel to preserve leading zeros. Maximum: 5,000 rows and 5 MB.
        Uploads apply to the IDs in your file, regardless of the page filters or selection.
      </p>
      <AppButton variant="outlined" prepend-icon="mdi-download" :disabled="uploading" @click="downloadTemplate">Download template</AppButton>
      <v-file-input
        v-model="file"
        label="Enrollee NIN file"
        accept=".csv,.xlsx,.xls"
        variant="outlined"
        density="compact"
        show-size
        :disabled="uploading"
        hide-details
        @update:model-value="resetResult"
      />
      <AppAlert v-if="uploadError" tone="danger" :message="uploadError" />
      <AppCard v-if="result" title="Upload results" icon="mdi-clipboard-check-outline" :padded="false">
        <div class="tw-flex tw-flex-wrap tw-gap-2 tw-p-4">
          <AppBadge :label="`${result.updated} updated`" tone="success" />
          <AppBadge :label="`${result.cleared} cleared`" tone="warning" />
          <AppBadge :label="`${result.unchanged} unchanged`" tone="secondary" />
          <AppBadge :label="`${result.skipped} skipped`" tone="danger" />
        </div>
        <AppDataTable :headers="headers" :items="result.rows" item-value="row" :items-per-page="10">
          <template #item.status="{ item }">
            <AppBadge :label="item.status" :tone="statusTone(item.status)" size="sm" />
          </template>
        </AppDataTable>
      </AppCard>
    </div>
    <template #actions>
      <AppButton variant="outlined" :disabled="uploading" @click="$emit('update:modelValue', false)">Close</AppButton>
      <AppButton :loading="uploading" :disabled="!selectedFile || Boolean(result)" prepend-icon="mdi-upload" @click="upload">Apply NIN updates</AppButton>
    </template>
  </AppModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppButton from '../common/AppButton.vue'
import AppCard from '../common/AppCard.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppModal from '../common/AppModal.vue'
import { enrolleeAPI } from '../../utils/api'

defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'completed'])
const file = ref(null)
const uploading = ref(false)
const uploadError = ref('')
const result = ref(null)
const selectedFile = computed(() => Array.isArray(file.value) ? file.value[0] : file.value)
const headers = [
  { title: 'Row', key: 'row' },
  { title: 'NICARE ID', key: 'enrollee_id' },
  { title: 'Result', key: 'status' },
  { title: 'Details', key: 'message', sortable: false },
]
const statusTone = (status) => ({ updated: 'success', cleared: 'warning', unchanged: 'secondary', skipped: 'danger' }[status])
const resetResult = () => { result.value = null; uploadError.value = '' }

const downloadTemplate = () => {
  const url = URL.createObjectURL(new Blob(['\uFEFFNICARE ID,NIN\r\n'], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'bulk_nin_update_template.csv'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

const upload = async () => {
  if (!selectedFile.value || uploading.value) return
  uploadError.value = ''
  if (selectedFile.value.size > 5 * 1024 * 1024) {
    uploadError.value = 'Upload a file no larger than 5 MB.'
    return
  }
  uploading.value = true
  try {
    const payload = new FormData()
    payload.append('file', selectedFile.value)
    const response = await enrolleeAPI.bulkUpdateNin(payload)
    result.value = response.data.data
    emit('completed')
  } catch (err) {
    uploadError.value = err.response?.data?.errors?.file?.[0] || err.response?.data?.message || 'Could not complete the upload. Refresh the records before retrying.'
  } finally {
    uploading.value = false
  }
}
</script>
