<template>
  <div
    v-if="count > 0"
    class="qds-card tw-border tw-border-cyan-200 tw-bg-cyan-50/70 tw-px-4 tw-py-3"
  >
    <div class="tw-flex tw-flex-col tw-gap-3 lg:tw-flex-row lg:tw-items-center lg:tw-justify-between">
      <div class="tw-flex tw-items-center tw-gap-3">
        <div class="qds-icon-shell qds-tone-info">
          <v-icon size="18">mdi-checkbox-multiple-marked-outline</v-icon>
        </div>
        <div>
          <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ title }}</p>
          <p class="tw-text-xs tw-text-slate-600">{{ subtitleText }}</p>
        </div>
      </div>
      <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  count: { type: Number, default: 0 },
  title: { type: String, default: 'Bulk actions ready' },
  subtitle: { type: String, default: '' },
})

const subtitleText = computed(() => props.subtitle || `${props.count.toLocaleString()} record(s) selected`)
</script>
