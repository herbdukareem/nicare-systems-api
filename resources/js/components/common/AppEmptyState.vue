<template>
  <div
    class="tw-flex tw-flex-col tw-items-center tw-justify-center tw-px-4 tw-text-center"
    :class="compact ? 'tw-py-8' : 'tw-py-14'"
  >
    <div class="qds-icon-shell qds-tone-neutral" :class="compact ? 'tw-h-12 tw-w-12' : 'tw-h-14 tw-w-14'">
      <v-icon :size="iconSize" :color="iconColor">{{ icon }}</v-icon>
    </div>
    <h3 :class="compact ? 'tw-mt-3 tw-text-base' : 'tw-mt-4 tw-text-lg'" class="tw-font-semibold tw-text-slate-900">{{ title }}</h3>
    <p class="tw-mt-2 tw-max-w-md tw-text-sm tw-leading-6 tw-text-slate-500">{{ description }}</p>
    <div v-if="$slots.default" :class="compact ? 'tw-mt-4' : 'tw-mt-6'">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: 'mdi-inbox-outline' },
  iconColor: { type: String, default: 'grey' },
  iconSize: { type: Number, default: 22 },
  title: { type: String, default: 'No data found' },
  description: { type: String, default: 'There are no records matching your criteria.' },
  compact: { type: Boolean, default: false },
})
</script>
