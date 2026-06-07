<template>
  <AppCard :title="title" :subtitle="subtitle" :icon="icon" :tone="tone" :hover="hover" full-height>
    <div class="tw-flex tw-items-end tw-justify-between tw-gap-4">
      <div class="tw-min-w-0">
        <div class="tw-text-3xl tw-font-semibold tw-leading-tight tw-text-slate-950">
          <slot name="value">
            {{ value }}
          </slot>
        </div>
        <p v-if="helper" class="tw-mt-2 tw-text-sm tw-text-slate-500">{{ helper }}</p>
      </div>
      <slot name="aside" />
    </div>
  </AppCard>
</template>

<script setup>
import AppCard from './AppCard.vue'

defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  helper: { type: String, default: '' },
  value: { type: [String, Number], default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'primary' },
  hover: { type: Boolean, default: false },
})
</script>
