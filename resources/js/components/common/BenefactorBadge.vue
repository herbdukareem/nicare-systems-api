<template>
  <AppBadge :label="label" tone="secondary" icon="mdi-account-heart-outline" :size="size" :outline="outline" />
</template>

<script setup>
import AppBadge from './AppBadge.vue'

defineProps({
  label: { type: String, default: 'Benefactor' },
  size: { type: String, default: 'md' },
  outline: { type: Boolean, default: true },
})
</script>
