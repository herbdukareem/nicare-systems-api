<template>
  <div class="qds-alert" :class="toneClass">
    <div class="tw-flex tw-items-start tw-gap-3">
      <v-icon :size="18">{{ iconToUse }}</v-icon>
      <div class="tw-min-w-0">
        <p v-if="title" class="tw-text-sm tw-font-semibold">{{ title }}</p>
        <p class="tw-text-sm">
          <slot>{{ message }}</slot>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, default: '' },
  message: { type: String, default: '' },
  tone: { type: String, default: 'info' },
  icon: { type: String, default: '' },
})

const toneClass = computed(() => `qds-alert-${props.tone}`)
const iconToUse = computed(() => props.icon || ({
  success: 'mdi-check-circle-outline',
  warning: 'mdi-alert-outline',
  danger: 'mdi-alert-circle-outline',
  error: 'mdi-alert-circle-outline',
  info: 'mdi-information-outline',
}[props.tone] || 'mdi-information-outline'))
</script>
