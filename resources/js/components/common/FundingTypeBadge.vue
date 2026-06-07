<template>
  <AppBadge :label="label" tone="info" icon="mdi-cash-multiple" :size="size" :outline="outline" />
</template>

<script setup>
import AppBadge from './AppBadge.vue'

defineProps({
  label: { type: String, default: 'Funding Type' },
  size: { type: String, default: 'md' },
  outline: { type: Boolean, default: true },
})
</script>
