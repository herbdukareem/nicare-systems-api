<template>
<div class="tw-fixed tw-top-4 tw-right-4 tw-space-y-2" style="z-index: 3000;">
    <transition-group name="toast" tag="div">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="getToastClasses(toast.type)"
        class="tw-flex tw-items-center tw-justify-between tw-p-4 tw-rounded-lg tw-shadow-lg tw-min-w-80 tw-max-w-md"
      >
        <div class="tw-flex tw-items-center tw-space-x-3">
          <div :class="getIconClasses(toast.type)">
            <v-icon :size="20">{{ getIcon(toast.type) }}</v-icon>
          </div>
          <p class="tw-text-sm tw-font-medium">{{ toast.message }}</p>
        </div>
        <button
          @click="removeToast(toast.id)"
          class="tw-ml-4 tw-text-gray-400 hover:tw-text-gray-600 tw-transition-colors"
        >
          <v-icon size="16">mdi-close</v-icon>
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { useToast } from '../../composables/useToast';

const { toasts, removeToast } = useToast();

const getToastClasses = (type) => {
  const baseClasses = 'tw-border-l-4';
  switch (type) {
    case 'success':
      return `${baseClasses} tw-border-green-500 tw-bg-green-50 tw-text-green-800`;
    case 'error':
      return `${baseClasses} tw-border-red-500 tw-bg-red-50 tw-text-red-800`;
    case 'warning':
      return `${baseClasses} tw-border-yellow-500 tw-bg-yellow-50 tw-text-yellow-800`;
    case 'info':
    default:
      return `${baseClasses} tw-border-blue-500 tw-bg-blue-50 tw-text-blue-800`;
  }
};

const getIconClasses = (type) => {
  switch (type) {
    case 'success':
      return 'tw-text-green-500';
    case 'error':
      return 'tw-text-red-500';
    case 'warning':
      return 'tw-text-yellow-500';
    case 'info':
    default:
      return 'tw-text-blue-500';
  }
};

const getIcon = (type) => {
  switch (type) {
    case 'success':
      return 'mdi-check-circle';
    case 'error':
      return 'mdi-alert-circle';
    case 'warning':
      return 'mdi-alert';
    case 'info':
    default:
      return 'mdi-information';
  }
};
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
