<template>
  <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-border tw-border-gray-100">
    <div class="tw-px-4 tw-py-3 tw-flex tw-items-center tw-justify-between tw-border-b tw-border-gray-100">
      <div class="tw-flex tw-items-center tw-gap-2">
        <v-icon size="18" color="primary">mdi-filter-variant</v-icon>
        <span class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-uppercase tw-tracking-wide">Filters</span>
        <v-chip v-if="activeCount > 0" size="x-small" color="primary" variant="flat">{{ activeCount }}</v-chip>
      </div>
      <v-btn
        v-if="activeCount > 0"
        variant="text"
        size="small"
        color="grey"
        prepend-icon="mdi-close"
        @click="$emit('clear')"
      >Clear all</v-btn>
    </div>
    <div class="tw-p-4">
      <div :class="gridClass">
        <slot />
      </div>
      <div v-if="$slots.tags" class="tw-mt-3 tw-flex tw-flex-wrap tw-gap-2">
        <slot name="tags" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  activeCount: { type: Number, default: 0 },
  cols: { type: Number, default: 4 },
});

defineEmits(['clear']);

const gridClass = computed(() => {
  const map = { 1: 'tw-grid tw-grid-cols-1 tw-gap-3', 2: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-2 tw-gap-3', 3: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-3 tw-gap-3', 4: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3', 5: 'tw-grid tw-grid-cols-1 md:tw-grid-cols-3 lg:tw-grid-cols-5 tw-gap-3' };
  return map[props.cols] ?? map[4];
});
</script>
