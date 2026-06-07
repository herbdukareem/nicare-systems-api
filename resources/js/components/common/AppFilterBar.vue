<template>
  <div class="qds-card tw-overflow-hidden">
    <div class="tw-flex tw-items-center tw-justify-between tw-border-b tw-border-slate-200 tw-bg-slate-50/80 tw-px-3 tw-py-2">
      <div class="tw-flex tw-items-center tw-gap-2">
        <v-icon size="16" color="primary">mdi-filter-variant</v-icon>
        <span class="tw-text-xs tw-font-semibold tw-uppercase tw-tracking-[0.18em] tw-text-slate-600">Filters</span>
        <span v-if="activeCount > 0" class="qds-badge qds-badge-sm qds-tone-primary qds-badge-solid">{{ activeCount }}</span>
      </div>
      <div class="tw-flex tw-items-center tw-gap-2">
        <slot name="actions" />
        <button
          v-if="$slots.advanced"
          class="tw-flex tw-items-center tw-gap-1 tw-text-xs tw-font-medium tw-text-slate-500 hover:tw-text-slate-700"
          @click="expanded = !expanded"
        >
          <v-icon size="13">{{ expanded ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
          {{ expanded ? 'Fewer' : 'More' }}
        </button>
        <v-btn
          v-if="activeCount > 0"
          variant="text"
          size="x-small"
          color="grey"
          @click="$emit('clear')"
        >
          <v-icon size="14">mdi-close</v-icon>
          Clear
        </v-btn>
      </div>
    </div>
    <div class="tw-p-3">
      <div :class="gridClass">
        <slot />
      </div>
      <div v-if="$slots.advanced && expanded" class="tw-mt-2">
        <div :class="advancedGridClass">
          <slot name="advanced" />
        </div>
      </div>
      <div v-if="$slots.tags" class="tw-mt-2 tw-flex tw-flex-wrap tw-gap-1.5">
        <slot name="tags" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  activeCount: { type: Number, default: 0 },
  cols: { type: Number, default: 4 },
  advancedCols: { type: Number, default: 4 },
})

defineEmits(['clear'])

const expanded = ref(false)

const gridClass = computed(() => {
  const map = {
    1: 'tw-grid tw-grid-cols-1 tw-gap-2',
    2: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-2',
    3: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-2',
    4: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-2',
    5: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-5 tw-gap-2',
  }
  return map[props.cols] ?? map[4]
})

const advancedGridClass = computed(() => {
  const map = {
    3: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-2',
    4: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-4 tw-gap-2',
    5: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-5 tw-gap-2',
    6: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-3 lg:tw-grid-cols-6 tw-gap-2',
    7: 'tw-grid tw-grid-cols-2 md:tw-grid-cols-4 lg:tw-grid-cols-7 tw-gap-2',
  }
  return map[props.advancedCols] ?? map[4]
})
</script>
