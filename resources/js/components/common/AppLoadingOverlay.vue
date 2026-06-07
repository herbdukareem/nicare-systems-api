<template>
  <transition name="fade">
    <div v-if="modelValue" class="qds-loading-overlay">
      <div class="qds-loading-panel">
        <v-progress-circular indeterminate size="36" width="3" color="primary" />
        <div class="tw-text-center">
          <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ title }}</p>
          <p v-if="subtitle" class="tw-text-xs tw-text-slate-500">{{ subtitle }}</p>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Loading' },
  subtitle: { type: String, default: '' },
})
</script>
