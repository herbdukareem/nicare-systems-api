<template>
  <AppBadge :label="eligible ? 'Eligible' : 'Not Eligible'" :tone="eligible ? 'success' : 'danger'" :icon="eligible ? 'mdi-shield-check-outline' : 'mdi-shield-off-outline'" :size="size" />
</template>

<script setup>
import AppBadge from './AppBadge.vue'

defineProps({
  eligible: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})
</script>
