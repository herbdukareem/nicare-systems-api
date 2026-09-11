<template>
  <v-btn v-bind="$attrs" :color="color" :variant="variant" :size="size" :rounded="0">
    <template v-for="(_, name) in $slots" #[name]="slotProps">
      <slot :name="name" v-bind="slotProps || {}" />
    </template>
  </v-btn>
</template>

<script setup>
defineOptions({ inheritAttrs: false })
defineProps({
  color: { type: String, default: 'primary' },
  variant: { type: String, default: 'flat' },
  size: { type: String, default: 'small' },
})
</script>
