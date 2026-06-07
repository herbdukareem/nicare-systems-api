<template>
  <AppCard tone="danger" :padded="true">
    <div class="tw-flex tw-flex-col tw-items-center tw-gap-3 tw-py-8 tw-text-center">
      <div class="qds-icon-shell qds-tone-danger">
        <v-icon size="22">mdi-alert-circle-outline</v-icon>
      </div>
      <div>
        <h3 class="tw-text-base tw-font-semibold tw-text-slate-900">{{ title }}</h3>
        <p class="tw-mt-1 tw-text-sm tw-text-slate-500">{{ message }}</p>
      </div>
      <slot />
    </div>
  </AppCard>
</template>

<script setup>
import AppCard from './AppCard.vue'

defineProps({
  title: { type: String, default: 'Something went wrong' },
  message: { type: String, default: 'Please retry or adjust your request.' },
})
</script>
