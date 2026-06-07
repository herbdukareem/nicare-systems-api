<template>
  <v-skeleton-loader :type="type" :loading="loading" class="tw-rounded-2xl">
    <slot />
  </v-skeleton-loader>
</template>

<script setup>
defineProps({
  type: { type: String, default: 'article' },
  loading: { type: Boolean, default: true },
})
</script>
