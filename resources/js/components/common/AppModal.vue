<template>
  <v-dialog
    :model-value="modelValue"
    :max-width="maxWidths[size] || maxWidths.md"
    :persistent="persistent || loading"
    scrollable
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl" class="tw-overflow-hidden">
      <!-- Header -->
      <div
        class="tw-flex tw-items-center tw-justify-between tw-px-5 tw-py-4"
        :class="headerBg"
      >
        <div class="tw-flex tw-min-w-0 tw-items-center tw-gap-3">
          <div
            v-if="icon"
            class="tw-flex tw-h-9 tw-w-9 tw-flex-shrink-0 tw-items-center tw-justify-center tw-rounded-lg tw-bg-white/20"
          >
            <v-icon color="white" size="20">{{ icon }}</v-icon>
          </div>
          <div class="tw-min-w-0">
            <h2 class="tw-truncate tw-text-sm tw-font-semibold tw-leading-tight tw-text-white">{{ title }}</h2>
            <p v-if="subtitle" class="tw-mt-0.5 tw-truncate tw-text-xs tw-text-white/70">{{ subtitle }}</p>
          </div>
        </div>
        <button
          v-if="!persistent && !loading"
          type="button"
          class="tw-ml-4 tw-flex tw-h-7 tw-w-7 tw-flex-shrink-0 tw-items-center tw-justify-center tw-rounded-md tw-text-white/80 tw-transition-colors hover:tw-bg-white/20 hover:tw-text-white focus:tw-outline-none"
          @click="$emit('update:modelValue', false)"
        >
          <v-icon size="17">mdi-close</v-icon>
        </button>
      </div>

      <!-- Body -->
      <v-card-text class="tw-p-5">
        <slot />
      </v-card-text>

      <!-- Actions footer -->
      <div
        v-if="$slots.actions"
        class="tw-flex tw-items-center tw-justify-end tw-gap-2 tw-border-t tw-border-slate-100 tw-bg-slate-50 tw-px-5 tw-py-3"
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
    primary: 'tw-bg-gradient-to-br tw-from-cyan-800 tw-to-cyan-600',
    success: 'tw-bg-gradient-to-br tw-from-emerald-700 tw-to-emerald-500',
    error: 'tw-bg-gradient-to-br tw-from-red-700 tw-to-red-500',
    warning: 'tw-bg-gradient-to-br tw-from-amber-600 tw-to-amber-500',
    neutral: 'tw-bg-gradient-to-br tw-from-slate-700 tw-to-slate-600',
    info: 'tw-bg-gradient-to-br tw-from-blue-700 tw-to-blue-500',
  }
  return map[props.color] ?? map.primary
})
</script>
