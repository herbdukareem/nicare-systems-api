<template>
  <span>{{ formatted }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: [String, Number, Date], default: '' },
  format: { type: String, default: 'medium' },
  locale: { type: String, default: 'en-NG' },
})

const optionsMap = {
  short: { day: '2-digit', month: 'short', year: 'numeric' },
  medium: { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' },
  date: { day: '2-digit', month: 'long', year: 'numeric' },
}

const formatted = computed(() => {
  if (!props.value) return 'N/A'
  const date = new Date(props.value)
  if (Number.isNaN(date.getTime())) return 'N/A'
  return new Intl.DateTimeFormat(props.locale, optionsMap[props.format] || optionsMap.medium).format(date)
})
</script>
