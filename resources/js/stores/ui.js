import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    routeLoading: false,
  }),

  actions: {
    setRouteLoading(value) {
      this.routeLoading = Boolean(value);
    },
  },
});
