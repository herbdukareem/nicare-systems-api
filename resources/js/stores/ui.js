import { defineStore } from 'pinia';

const MODULE_KEYS = ['general', 'pas', 'claims', 'automation'];

export const useUiStore = defineStore('ui', {
  state: () => ({
    // Which high-level module sidebar is currently active
    currentModule: localStorage.getItem('currentModule') || 'general',
  }),

  getters: {
    modules: () => MODULE_KEYS,
  },

  actions: {
    setModule(module) {
      const safeModule = MODULE_KEYS.includes(module) ? module : 'general';
      this.currentModule = safeModule;
      localStorage.setItem('currentModule', safeModule);
    },
  },
});
