<template>
  <AppStatusBadge :status="status" :label="label" :size="sizeMap[size] || 'md'" :show-icon="showIcon" />
</template>

<script setup>
import AppStatusBadge from './AppStatusBadge.vue'

defineProps({
  status: { type: String, default: '' },
  size: { type: String, default: 'small' },
  showIcon: { type: Boolean, default: false },
  label: { type: String, default: '' },
})

const sizeMap = {
  xsmall: 'sm',
  small: 'sm',
  default: 'md',
  large: 'lg',
}
</script>
