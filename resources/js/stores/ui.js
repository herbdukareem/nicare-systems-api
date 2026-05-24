import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    routeLoading: false,
    requestLoading: false,
    requestTitle: 'Loading',
    requestSubtitle: 'Please wait while we contact the server',
    pendingRequests: 0,
    requestLoaderTimer: null,
  }),

  actions: {
    setRouteLoading(value) {
      this.routeLoading = Boolean(value);
    },

    startRequestLoading(options = {}) {
      this.pendingRequests += 1;
      this.requestTitle = options.title || 'Loading';
      this.requestSubtitle = options.subtitle || 'Please wait while we contact the server';

      if (this.requestLoading || this.requestLoaderTimer) return;

      this.requestLoaderTimer = window.setTimeout(() => {
        this.requestLoaderTimer = null;
        if (this.pendingRequests > 0) {
          this.requestLoading = true;
        }
      }, 180);
    },

    finishRequestLoading() {
      this.pendingRequests = Math.max(0, this.pendingRequests - 1);

      if (this.pendingRequests > 0) return;

      if (this.requestLoaderTimer) {
        window.clearTimeout(this.requestLoaderTimer);
        this.requestLoaderTimer = null;
      }

      this.requestLoading = false;
      this.requestTitle = 'Loading';
      this.requestSubtitle = 'Please wait while we contact the server';
    },
  },
});
