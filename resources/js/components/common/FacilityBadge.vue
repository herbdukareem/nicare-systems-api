<template>
  <AppBadge :label="meta.label" :tone="meta.tone" :icon="meta.icon" :size="size" :outline="outline" />
</template>

<script setup>
import { computed } from 'vue'
import { getFacilityTypeMeta } from '../../design-system/status'
import AppBadge from './AppBadge.vue'

const props = defineProps({
  type: { type: String, default: '' },
  label: { type: String, default: '' },
  size: { type: String, default: 'md' },
  outline: { type: Boolean, default: true },
})

const meta = computed(() => getFacilityTypeMeta(props.label || props.type))
</script>
