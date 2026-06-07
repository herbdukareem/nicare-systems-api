<template>
  <span class="qds-badge" :class="[sizeClass, toneClass, outline ? 'qds-badge-outline' : 'qds-badge-solid']">
    <v-icon v-if="icon" :size="iconSize" class="tw-shrink-0">{{ icon }}</v-icon>
    <span class="tw-truncate">{{ label }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { toneClasses } from '../../design-system/tokens'

const props = defineProps({
  label: { type: String, default: '' },
  tone: { type: String, default: 'neutral' },
  icon: { type: String, default: '' },
  size: { type: String, default: 'md' },
  outline: { type: Boolean, default: false },
})

const toneClass = computed(() => toneClasses[props.tone] || toneClasses.neutral)
const sizeClass = computed(() => ({
  sm: 'qds-badge-sm',
  md: 'qds-badge-md',
  lg: 'qds-badge-lg',
}[props.size] || 'qds-badge-md'))
const iconSize = computed(() => (props.size === 'lg' ? 16 : 14))
</script>
