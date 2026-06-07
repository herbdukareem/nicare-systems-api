<template>
  <div class="qds-page-header">
    <div class="tw-flex tw-items-start tw-gap-4">
      <div v-if="icon" class="qds-icon-shell" :class="toneClass">
        <v-icon :icon="icon" size="22" />
      </div>
      <div class="tw-min-w-0">
        <p v-if="kicker" class="qds-page-kicker">{{ kicker }}</p>
        <h1 class="qds-page-title">{{ title }}</h1>
        <p v-if="subtitle" class="qds-page-subtitle">{{ subtitle }}</p>
        <div v-if="$slots.meta" class="tw-mt-3 tw-flex tw-flex-wrap tw-items-center tw-gap-2">
          <slot name="meta" />
        </div>
      </div>
    </div>
    <div v-if="$slots.default" class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { toneClasses } from '../../design-system/tokens'

const props = defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  kicker: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconColor: { type: String, default: 'primary' },
})

const toneClass = computed(() => toneClasses[props.iconColor] || toneClasses.primary)
</script>
