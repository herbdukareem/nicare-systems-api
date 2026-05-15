<template>
  <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-p-6 tw-border tw-border-gray-100 tw-transition-all tw-duration-200 hover:tw-shadow-md">
    <div class="tw-flex tw-items-center tw-justify-between">
      <div class="tw-flex tw-items-center tw-min-w-0">
        <div :class="`tw-p-3 tw-rounded-full tw-bg-${color}-100 tw-shrink-0`">
          <v-icon :color="color" size="24">{{ icon }}</v-icon>
        </div>
        <div class="tw-ml-4 tw-min-w-0">
          <p class="tw-text-sm tw-font-medium tw-text-gray-500 tw-truncate">{{ label }}</p>
          <template v-if="!loading">
            <p class="tw-text-2xl tw-font-bold tw-text-gray-900 tw-leading-tight">{{ formattedValue }}</p>
            <p v-if="subLabel" class="tw-text-xs tw-text-gray-400 tw-mt-0.5">{{ subLabel }}</p>
          </template>
          <template v-else>
            <div class="tw-h-7 tw-w-20 tw-bg-gray-200 tw-rounded tw-animate-pulse tw-mt-1" />
          </template>
        </div>
      </div>
      <div v-if="change !== null && change !== undefined" class="tw-shrink-0 tw-text-right tw-ml-3">
        <div :class="['tw-flex tw-items-center tw-justify-end tw-text-sm tw-font-semibold', changeClass]">
          <v-icon size="14" class="tw-mr-0.5">{{ change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down' }}</v-icon>
          {{ Math.abs(change) }}%
        </div>
        <p class="tw-text-xs tw-text-gray-400">vs last period</p>
      </div>
    </div>
    <div v-if="$slots.footer" class="tw-mt-4 tw-pt-4 tw-border-t tw-border-gray-100">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: 'mdi-chart-bar' },
  color: { type: String, default: 'blue' },
  loading: { type: Boolean, default: false },
  change: { type: Number, default: null },
  subLabel: { type: String, default: '' },
  currency: { type: Boolean, default: false },
});

const formattedValue = computed(() => {
  if (typeof props.value === 'string') return props.value;
  if (props.currency) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(props.value);
  }
  return props.value?.toLocaleString() ?? '0';
});

const changeClass = computed(() => {
  if (props.change === null || props.change === undefined) return '';
  return props.change >= 0 ? 'tw-text-green-600' : 'tw-text-red-500';
});
</script>
