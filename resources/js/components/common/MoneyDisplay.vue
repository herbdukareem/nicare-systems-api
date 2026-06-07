<template>
  <span>{{ formatted }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: [String, Number], default: 0 },
  currency: { type: String, default: 'NGN' },
  locale: { type: String, default: 'en-NG' },
  minimumFractionDigits: { type: Number, default: 0 },
  maximumFractionDigits: { type: Number, default: 0 },
})

const formatted = computed(() => {
  const amount = Number(props.value || 0)
  return new Intl.NumberFormat(props.locale, {
    style: 'currency',
    currency: props.currency,
    minimumFractionDigits: props.minimumFractionDigits,
    maximumFractionDigits: props.maximumFractionDigits,
  }).format(amount)
})
</script>
