import { ref } from 'vue';

// Global toast state
const toasts = ref([]);

export function useToast() {
  const addToast = (message, type = 'info', duration = 5000) => {
    const id = Date.now() + Math.random();
    const toast = {
      id,
      message,
      type, // 'success', 'error', 'warning', 'info'
      duration,
      show: true,
    };

    toasts.value.push(toast);

    // Auto remove after duration
    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }

    return id;
  };

  const removeToast = (id) => {
    const index = toasts.value.findIndex(toast => toast.id === id);
    if (index > -1) {
      toasts.value.splice(index, 1);
    }
  };

  const success = (message, duration = 5000) => addToast(message, 'success', duration);
  const error = (message, duration = 7000) => addToast(message, 'error', duration);
  const warning = (message, duration = 6000) => addToast(message, 'warning', duration);
  const info = (message, duration = 5000) => addToast(message, 'info', duration);

  // Legacy aliases used across components
  const showSuccess = success;
  const showError = error;
  const showWarning = warning;
  const showInfo = info;
  const warn = warning;

  return {
    toasts,
    addToast,
    removeToast,
    success,
    error,
    warning,
    info,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    warn,
  };
}
