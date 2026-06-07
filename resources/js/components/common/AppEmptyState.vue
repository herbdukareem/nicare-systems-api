<template>
  <div class="tw-flex tw-flex-col tw-items-center tw-justify-center tw-px-4 tw-py-14 tw-text-center">
    <div class="qds-icon-shell qds-tone-neutral tw-h-14 tw-w-14">
      <v-icon :size="iconSize" :color="iconColor">{{ icon }}</v-icon>
    </div>
    <h3 class="tw-mt-4 tw-text-lg tw-font-semibold tw-text-slate-900">{{ title }}</h3>
    <p class="tw-mt-2 tw-max-w-md tw-text-sm tw-leading-6 tw-text-slate-500">{{ description }}</p>
    <div v-if="$slots.default" class="tw-mt-6">
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
})
</script>
