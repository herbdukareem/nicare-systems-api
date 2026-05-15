<template>
  <header class="tw-flex tw-items-start md:tw-items-center tw-justify-between tw-gap-4 tw-flex-wrap">
    <div>
      <div class="tw-flex tw-items-center tw-gap-2">
        <v-icon v-if="icon" :color="iconColor" size="28">{{ icon }}</v-icon>
        <h1 class="tw-text-2xl md:tw-text-3xl tw-font-bold tw-text-gray-900">{{ title }}</h1>
      </div>
      <p v-if="subtitle" class="tw-text-gray-500 tw-mt-1 tw-text-sm">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.default" class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
      <slot />
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  iconColor: { type: String, default: 'primary' },
});
</script>
