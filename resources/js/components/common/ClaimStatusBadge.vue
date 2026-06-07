<template>
  <AppStatusBadge :status="status" :label="label" :size="size" :show-icon="showIcon" />
</template>

<script setup>
import AppStatusBadge from './AppStatusBadge.vue'

defineProps({
  status: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  size: { type: String, default: 'md' },
  showIcon: { type: Boolean, default: false },
})
</script>
