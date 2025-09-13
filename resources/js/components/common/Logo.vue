<template>
  <div :class="containerClass">
    <img
      :src="logoUrl"
      alt="NGSCHA Logo"
      :class="imageClass"
      @error="showFallback = true"
      v-if="!showFallback"
    />
    <v-icon 
      v-else 
      :color="iconColor" 
      :size="iconSize"
      :class="iconClass"
    >
      mdi-shield-account
    </v-icon>
    <span v-if="showText" :class="textClass">{{ text }}</span>
  </div>
</template>
<script setup>
import { ref, computed } from 'vue';
import logoImage from '../../../images/logo.png';

const props = defineProps({
  size: {
    type: String,
    default: 'md', // xs, sm, md, lg, xl, 2xl
    validator: (value) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'].includes(value)
  },
  variant: {
    type: String,
    default: 'default', // default, circle, square
    validator: (value) => ['default', 'circle', 'square'].includes(value)
  },
  showText: {
    type: Boolean,
    default: false
  },
  text: {
    type: String,
    default: 'NGSCHA'
  },
  iconColor: {
    type: String,
    default: 'primary'
  }
});

const showFallback = ref(false);

// Logo URL - use imported image or fallback to public path
const logoUrl = computed(() => logoImage || '/logo.png');

// Size mappings
const sizeClasses = {
  xs: { container: 'tw-w-4 tw-h-4',  image: 'tw-w-4 tw-h-4',  icon: '16', text: 'tw-text-xs' },
  sm: { container: 'tw-w-6 tw-h-6',  image: 'tw-w-6 tw-h-6',  icon: '20', text: 'tw-text-sm' },
  md: { container: 'tw-w-8 tw-h-8',  image: 'tw-w-8 tw-h-8',  icon: '24', text: 'tw-text-base' },
  lg: { container: 'tw-w-12 tw-h-12', image: 'tw-w-12 tw-h-12', icon: '32', text: 'tw-text-lg' },
  xl: { container: 'tw-w-16 tw-h-16', image: 'tw-w-16 tw-h-16', icon: '40', text: 'tw-text-xl' },
  // NEW: support 2xl
  '2xl': { container: 'tw-w-20 tw-h-20', image: 'tw-w-20 tw-h-20', icon: '48', text: 'tw-text-2xl' },
};

// Always return a valid size record (fallback to md)
const currentSize = computed(() => sizeClasses[props.size] ?? sizeClasses.md);

// Computed classes
const containerClass = computed(() => {
  const base = 'tw-flex tw-items-center tw-justify-center tw-overflow-hidden';
  const sizeCls = currentSize.value.container;

  let variantClasses = '';
  if (props.variant === 'circle') {
    variantClasses = 'tw-rounded-full tw-bg-blue-600 tw-bg-opacity-20';
  } else if (props.variant === 'square') {
    variantClasses = 'tw-rounded-lg tw-bg-blue-600 tw-bg-opacity-20';
  }

  // keep size even when showText=true so the icon/image has a consistent frame
  const withSize = `${base} ${sizeCls} ${variantClasses}`;
  return props.showText ? `${withSize} tw-space-x-2` : withSize;
});

const imageClass = computed(() => `tw-object-contain ${currentSize.value.image}`);

const iconClass = computed(() => (props.variant === 'circle' || props.variant === 'square') ? 'tw-text-white' : '');

const iconSize = computed(() => currentSize.value.icon);

const textClass = computed(() => `tw-font-bold tw-text-gray-900 ${currentSize.value.text}`);
</script>
