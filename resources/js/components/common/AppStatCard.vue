<template>
  <!-- Compact variant: inline horizontal card, small icon + label above value -->
  <div v-if="compact" class="qds-card tw-px-3.5 tw-py-2.5">
    <div class="tw-flex tw-items-center tw-gap-2">
      <div class="qds-icon-shell-sm tw-shrink-0" :class="toneClass">
        <v-icon size="14">{{ icon }}</v-icon>
      </div>
      <p class="tw-truncate tw-text-xs tw-font-medium tw-text-slate-500">{{ label }}</p>
    </div>
    <template v-if="!loading">
      <p class="tw-mt-1.5 tw-pl-7 tw-text-xl tw-font-bold tw-leading-none tw-text-slate-900">{{ formattedValue }}</p>
      <p v-if="subLabel" class="tw-mt-0.5 tw-pl-7 tw-text-[10px] tw-text-slate-400">{{ subLabel }}</p>
    </template>
    <div v-else class="tw-ml-7 tw-mt-1.5 tw-h-5 tw-w-16 tw-animate-pulse tw-bg-slate-200" />
  </div>

  <!-- Default variant -->
  <AppCard v-else :hover="true" :padded="true" full-height>
    <div class="tw-flex tw-items-start tw-justify-between tw-gap-4">
      <div class="tw-flex tw-min-w-0 tw-gap-4">
        <div class="qds-icon-shell tw-shrink-0" :class="toneClass">
          <v-icon size="22">{{ icon }}</v-icon>
        </div>
        <div class="tw-min-w-0">
          <p class="tw-text-sm tw-font-medium tw-text-slate-500">{{ label }}</p>
          <template v-if="!loading">
            <p class="tw-mt-2 tw-text-3xl tw-font-semibold tw-leading-tight tw-text-slate-950">{{ formattedValue }}</p>
            <p v-if="subLabel" class="tw-mt-1 tw-text-xs tw-text-slate-500">{{ subLabel }}</p>
          </template>
          <div v-else class="tw-mt-3 tw-h-8 tw-w-28 tw-animate-pulse tw-bg-slate-200" />
        </div>
      </div>
      <div v-if="change !== null && change !== undefined" class="tw-text-right">
        <div class="tw-inline-flex tw-items-center tw-gap-1 tw-px-2.5 tw-py-1 tw-text-xs tw-font-semibold" :class="changeClass">
          <v-icon size="14">{{ change >= 0 ? 'mdi-trending-up' : 'mdi-trending-down' }}</v-icon>
          {{ Math.abs(change) }}%
        </div>
        <p class="tw-mt-1 tw-text-[11px] tw-text-slate-400">vs last period</p>
      </div>
    </div>
    <div v-if="$slots.footer" class="tw-mt-4 tw-border-t tw-border-slate-100 tw-pt-4">
      <slot name="footer" />
    </div>
  </AppCard>
</template>

<script setup>
import { computed } from 'vue'
import { toneClasses } from '../../design-system/tokens'
import AppCard from './AppCard.vue'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: 'mdi-chart-bar' },
  color: { type: String, default: 'primary' },
  loading: { type: Boolean, default: false },
  change: { type: Number, default: null },
  subLabel: { type: String, default: '' },
  currency: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const formattedValue = computed(() => {
  if (typeof props.value === 'string') return props.value
  if (props.currency) {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(props.value)
  }
  return props.value?.toLocaleString() ?? '0'
})

const toneClass = computed(() => toneClasses[props.color] || toneClasses.primary)
const changeClass = computed(() => props.change >= 0 ? 'qds-tone-success' : 'qds-tone-danger')
</script>
