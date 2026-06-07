<template>
  <div class="tw-fixed tw-inset-0 tw-z-[9999] tw-grid tw-place-items-center tw-bg-white/40 tw-backdrop-blur-sm">
    <div class="tw-flex tw-flex-col tw-items-center tw-gap-4">
      <div class="tw-relative tw-grid tw-h-24 tw-w-24 tw-place-items-center">
        <div class="tw-absolute tw-inset-0 tw-rounded-full tw-border-4 tw-border-cyan-100"></div>
        <div class="tw-absolute tw-inset-0 tw-animate-spin tw-rounded-full tw-border-4 tw-border-transparent tw-border-t-cyan-700"></div>
        <Logo size="xl" />
      </div>
      <div class="tw-text-center">
        <p class="tw-text-sm tw-font-semibold tw-text-slate-950">{{ title }}</p>
        <p class="tw-text-xs tw-text-slate-500">{{ subtitle }}</p>
      </div>
    </div>
  </div>  
</template>

<script setup>
import Logo from './Logo.vue';

defineProps({
  title: {
    type: String,
    default: 'Loading NiCare',
  },
  subtitle: {
    type: String,
    default: 'Preparing your workspace',
  },
});
</script>
