<template>
  <ol class="tw-space-y-4">
    <li v-for="item in items" :key="item.id || item.title" class="tw-flex tw-gap-3">
      <div class="tw-flex tw-flex-col tw-items-center">
        <span class="qds-icon-shell" :class="item.tone ? `qds-tone-${item.tone}` : 'qds-tone-primary'">
          <v-icon size="16">{{ item.icon || 'mdi-circle-medium' }}</v-icon>
        </span>
        <span class="tw-mt-2 tw-h-full tw-w-px tw-bg-slate-200" />
      </div>
      <div class="tw-flex-1 tw-pb-4">
        <p class="tw-text-sm tw-font-semibold tw-text-slate-900">{{ item.title }}</p>
        <p v-if="item.subtitle" class="tw-text-xs tw-text-slate-500">{{ item.subtitle }}</p>
        <p v-if="item.description" class="tw-mt-2 tw-text-sm tw-text-slate-600">{{ item.description }}</p>
      </div>
    </li>
  </ol>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
})
</script>
