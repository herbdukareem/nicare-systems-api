<template>
  <div class="tw-flex tw-flex-col tw-gap-3 sm:tw-flex-row sm:tw-items-start sm:tw-justify-between">
    <div class="tw-min-w-0">
      <h2 class="qds-section-title">{{ title }}</h2>
      <p v-if="subtitle" class="qds-section-subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>
