<template>
  <AppBadge
    :label="meta.label"
    :tone="meta.tone"
    :icon="showIcon ? meta.icon : ''"
    :size="size"
    :outline="outline"
  />
</template>

<script setup>
import { computed } from 'vue'
import { getStatusMeta } from '../../design-system/status'
import AppBadge from './AppBadge.vue'

const props = defineProps({
  status: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  size: { type: String, default: 'md' },
  outline: { type: Boolean, default: false },
  showIcon: { type: Boolean, default: false },
  tone: { type: String, default: '' },
})

const meta = computed(() => getStatusMeta(props.label || props.status, props.tone ? { tone: props.tone } : {}))
</script>
