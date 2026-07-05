<template>
  <div class="tw-space-y-4">
    <div class="qds-card tw-flex tw-flex-wrap tw-gap-1 tw-p-2">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        type="button"
        class="tw-inline-flex tw-items-center tw-gap-2 tw-px-4 tw-py-2 tw-text-sm tw-font-semibold tw-transition"
        :class="modelValue === tab.value
          ? 'tw-bg-white tw-text-cyan-700 tw-shadow-sm'
          : 'tw-text-slate-500 hover:tw-bg-white/70 hover:tw-text-slate-800'"
        @click="$emit('update:modelValue', tab.value)"
      >
        <v-icon v-if="tab.icon" size="18">{{ tab.icon }}</v-icon>
        <span>{{ tab.label }}</span>
        <AppBadge v-if="tab.badge" :label="String(tab.badge)" tone="info" size="sm" />
      </button>
    </div>
    <slot />
  </div>
</template>

<script setup>
import AppBadge from './AppBadge.vue'

defineProps({
  modelValue: { type: String, required: true },
  tabs: { type: Array, default: () => [] },
})

defineEmits(['update:modelValue'])
</script>
