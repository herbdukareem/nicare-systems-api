<template>
  <AppStatusBadge :status="normalizedStatus" :label="label" :size="size" :show-icon="showIcon" />
</template>

<script setup>
import { computed } from 'vue'
import AppStatusBadge from './AppStatusBadge.vue'

const props = defineProps({
  status: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  size: { type: String, default: 'md' },
  showIcon: { type: Boolean, default: false },
})

const normalizedStatus = computed(() => {
  if (props.label) return props.label
  const map = {
    0: 'pending',
    1: 'approved',
    2: 'rejected',
    3: 'suspended',
    4: 'inactive',
  }
  return map[String(props.status)] || props.status
})
</script>
