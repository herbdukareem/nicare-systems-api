<template>
  <v-chip
    :color="chipColor"
    :size="size"
    variant="flat"
    class="tw-font-medium tw-capitalize"
    label
  >
    <v-icon v-if="showIcon" start size="14">{{ chipIcon }}</v-icon>
    {{ displayLabel }}
  </v-chip>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, default: '' },
  size: { type: String, default: 'small' },
  showIcon: { type: Boolean, default: false },
  label: { type: String, default: '' },
});

const STATUS_MAP = {
  active:      { color: 'success', icon: 'mdi-check-circle' },
  approved:    { color: 'success', icon: 'mdi-check-circle' },
  completed:   { color: 'success', icon: 'mdi-check-circle' },
  paid:        { color: 'success', icon: 'mdi-check-circle' },
  validated:   { color: 'success', icon: 'mdi-shield-check' },
  verified:    { color: 'success', icon: 'mdi-shield-check' },
  used:        { color: 'success', icon: 'mdi-check' },
  discharged:  { color: 'success', icon: 'mdi-check' },

  pending:     { color: 'warning', icon: 'mdi-clock-outline' },
  processing:  { color: 'warning', icon: 'mdi-cog-outline' },
  in_review:   { color: 'warning', icon: 'mdi-eye-outline' },
  review:      { color: 'warning', icon: 'mdi-eye-outline' },
  submitted:   { color: 'warning', icon: 'mdi-upload' },
  generated:   { color: 'blue', icon: 'mdi-key' },
  admitted:    { color: 'blue', icon: 'mdi-hospital' },
  issued:      { color: 'blue', icon: 'mdi-key-chain' },

  rejected:    { color: 'error', icon: 'mdi-close-circle' },
  failed:      { color: 'error', icon: 'mdi-alert-circle' },
  cancelled:   { color: 'error', icon: 'mdi-cancel' },
  suspended:   { color: 'error', icon: 'mdi-pause-circle' },
  expired:     { color: 'error', icon: 'mdi-clock-alert' },
  denied:      { color: 'error', icon: 'mdi-close-circle' },

  inactive:    { color: 'default', icon: 'mdi-minus-circle' },
  draft:       { color: 'default', icon: 'mdi-file-edit' },
  modified:    { color: 'default', icon: 'mdi-pencil' },
  low:         { color: 'default', icon: 'mdi-arrow-down' },
  medium:      { color: 'warning', icon: 'mdi-minus' },
  high:        { color: 'error', icon: 'mdi-arrow-up' },
  critical:    { color: 'error', icon: 'mdi-alert' },
};

const key = computed(() => (props.status || '').toLowerCase().replace(/\s+/g, '_'));
const chipColor = computed(() => STATUS_MAP[key.value]?.color ?? 'default');
const chipIcon  = computed(() => STATUS_MAP[key.value]?.icon  ?? 'mdi-circle-small');
const displayLabel = computed(() => {
  if (props.label) return props.label;
  return (props.status || '').replace(/_/g, ' ');
});
</script>
