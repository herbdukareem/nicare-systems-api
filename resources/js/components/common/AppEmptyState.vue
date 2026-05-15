<template>
  <div class="tw-flex tw-flex-col tw-items-center tw-justify-center tw-py-16 tw-text-center">
    <v-icon :size="iconSize" :color="iconColor" class="tw-mb-4 tw-opacity-40">{{ icon }}</v-icon>
    <h3 class="tw-text-lg tw-font-semibold tw-text-gray-700 tw-mb-1">{{ title }}</h3>
    <p class="tw-text-sm tw-text-gray-400 tw-max-w-sm tw-mb-6">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup>
defineProps({
  icon:        { type: String, default: 'mdi-inbox-outline' },
  iconColor:   { type: String, default: 'grey' },
  iconSize:    { type: Number, default: 64 },
  title:       { type: String, default: 'No data found' },
  description: { type: String, default: 'There are no records matching your criteria.' },
});
</script>
