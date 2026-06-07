<template>
  <v-btn
    color="primary"
    variant="outlined"
    prepend-icon="mdi-download"
    :loading="loading"
    :disabled="disabled"
    @click="$emit('click')"
  >
    {{ label }}
  </v-btn>
</template>

<script setup>
defineProps({
  label: { type: String, default: 'Export' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
})

defineEmits(['click'])
</script>
