<template>
  <section
    class="qds-card"
    :class="[
      padded ? 'qds-card-padding' : '',
      hover ? 'qds-hover-lift' : '',
      muted ? 'qds-card-muted' : '',
      fullHeight ? 'tw-h-full' : '',
    ]"
  >
    <header v-if="title || subtitle || $slots.header || $slots.actions" class="tw-flex tw-flex-col tw-gap-2 tw-px-4 tw-py-2.5 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
      <div class="tw-min-w-0">
        <slot name="header">
          <div class="tw-flex tw-items-start tw-gap-3">
            <div v-if="icon" class="qds-icon-shell" :class="toneClass">
              <v-icon size="18">{{ icon }}</v-icon>
            </div>
            <div class="tw-min-w-0">
              <h2 v-if="title" class="qds-section-title">{{ title }}</h2>
              <p v-if="subtitle" class="qds-section-subtitle">{{ subtitle }}</p>
            </div>
          </div>
        </slot>
      </div>
      <div v-if="$slots.actions" class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
        <slot name="actions" />
      </div>
    </header>

    <div :class="padded ? 'tw-px-4 tw-py-3' : ''">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { toneClasses } from '../../design-system/tokens'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  icon: { type: String, default: '' },
  tone: { type: String, default: 'primary' },
  padded: { type: Boolean, default: true },
  hover: { type: Boolean, default: false },
  muted: { type: Boolean, default: false },
  fullHeight: { type: Boolean, default: false },
})

const toneClass = computed(() => toneClasses[props.tone] || toneClasses.primary)
</script>
