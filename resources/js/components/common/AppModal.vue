<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidths[size] || maxWidths.md"
    :persistent="persistent || loading"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card :rounded="0" class="tw-overflow-hidden tw-border tw-border-slate-200 tw-shadow-[0_30px_80px_-32px_rgba(15,23,42,0.34)]">
      <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-border-b tw-border-slate-200 tw-bg-slate-50 tw-px-5 tw-py-4">
        <div class="tw-flex tw-min-w-0 tw-items-start tw-gap-3">
          <div v-if="icon" class="qds-icon-shell" :class="headerBg">
            <v-icon size="18">{{ icon }}</v-icon>
          </div>
          <div class="tw-min-w-0">
            <h2 class="tw-text-base tw-font-semibold tw-text-slate-950">{{ title }}</h2>
            <p v-if="subtitle" class="tw-mt-1 tw-text-sm tw-text-slate-500">{{ subtitle }}</p>
          </div>
        </div>
        <button
          v-if="!persistent && !loading"
          type="button"
          class="tw-inline-flex tw-h-8 tw-w-8 tw-items-center tw-justify-center tw-text-slate-400 hover:tw-bg-slate-200/70 hover:tw-text-slate-700"
          @click="$emit('update:modelValue', false)"
        >
          <v-icon size="17">mdi-close</v-icon>
        </button>
      </div>

      <v-card-text class="tw-p-5">
        <slot />
      </v-card-text>

      <div
        v-if="$slots.actions"
        class="tw-flex tw-flex-wrap tw-items-center tw-justify-end tw-gap-2 tw-border-t tw-border-slate-200 tw-bg-slate-50 tw-px-5 tw-py-3"
      >
        <slot name="actions" />
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  size: { type: String, default: 'md' },
  loading: { type: Boolean, default: false },
  persistent: { type: Boolean, default: false },
  color: { type: String, default: 'primary' },
})

defineEmits(['update:modelValue'])

const maxWidths = {
  sm: 460,
  md: 640,
  lg: 900,
  xl: 1100,
  '2xl': 1300,
}

const headerBg = computed(() => {
  const map = {
    primary: 'qds-tone-primary',
    success: 'qds-tone-success',
    error: 'qds-tone-danger',
    warning: 'qds-tone-warning',
    neutral: 'qds-tone-neutral',
    info: 'qds-tone-info',
  }
  return map[props.color] ?? map.primary
})
</script>
