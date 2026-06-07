<template>
  <AppModal
    :model-value="modelValue"
    :title="title"
    :subtitle="subtitle"
    :icon="icon"
    size="sm"
    :color="tone === 'danger' ? 'error' : tone"
    :loading="loading"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="tw-space-y-4">
      <p class="tw-text-sm tw-leading-6 tw-text-slate-600">{{ message }}</p>
      <AppAlert v-if="warning" :message="warning" :tone="tone" />
    </div>

    <template #actions>
      <v-btn variant="outlined" :disabled="loading" @click="$emit('cancel')">{{ cancelText }}</v-btn>
      <v-btn :color="tone === 'danger' ? 'error' : 'primary'" variant="flat" :loading="loading" @click="$emit('confirm')">
        {{ confirmText }}
      </v-btn>
    </template>
  </AppModal>
</template>

<script setup>
import AppAlert from './AppAlert.vue'
import AppModal from './AppModal.vue'

defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Confirm action' },
  subtitle: { type: String, default: '' },
  message: { type: String, required: true },
  warning: { type: String, default: '' },
  confirmText: { type: String, default: 'Confirm' },
  cancelText: { type: String, default: 'Cancel' },
  icon: { type: String, default: 'mdi-alert-outline' },
  tone: { type: String, default: 'danger' },
  loading: { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'confirm', 'cancel'])
</script>
