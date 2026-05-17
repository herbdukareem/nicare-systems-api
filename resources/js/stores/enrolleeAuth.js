import { defineStore } from 'pinia';
import { enrolleePortalAPI } from '../utils/enrolleeApi';

export const useEnrolleeAuthStore = defineStore('enrolleeAuth', {
  state: () => ({
    enrollee: null,
    token: null,
    isAuthenticated: false,
    hasCustomPassword: false,
    _initializing: false,
  }),

  getters: {
    isLoggedIn: (state) => !!state.token && state.isAuthenticated,
    fullName: (state) => state.enrollee?.full_name || '',
    enrolleeId: (state) => state.enrollee?.enrollee_id || '',
    currentPlan: (state) => state.enrollee?.premium_plan || state.enrollee?.benefit_package || null,
  },

  actions: {
    async login(credentials) {
      const response = await enrolleePortalAPI.login(credentials);
      if (response.data.success) {
        const { enrollee, token, has_custom_password } = response.data.data;
        this.enrollee = enrollee;
        this.token = token;
        this.isAuthenticated = true;
        this.hasCustomPassword = has_custom_password;
        localStorage.setItem('enrollee_token', token);
        localStorage.setItem('enrollee', JSON.stringify(enrollee));
        return response.data;
      }
      throw new Error(response.data.message || 'Login failed');
    },

    async logout() {
      try {
        await enrolleePortalAPI.logout();
      } catch {
        // ignore
      } finally {
        this._clearSession();
      }
    },

    async fetchMe() {
      if (!this.token) return false;
      try {
        const response = await enrolleePortalAPI.me();
        if (response.data.success) {
          this.enrollee = response.data.data;
          this.isAuthenticated = true;
          localStorage.setItem('enrollee', JSON.stringify(this.enrollee));
          return true;
        }
        this.isAuthenticated = false;
        return false;
      } catch {
        this.isAuthenticated = false;
        return false;
      }
    },

    async initializeAuth() {
      if (this._initializing) return;
      this._initializing = true;
      try {
        const token = localStorage.getItem('enrollee_token');
        const saved = localStorage.getItem('enrollee');
        if (token) {
          this.token = token;
          if (saved) {
            try { this.enrollee = JSON.parse(saved); this.isAuthenticated = true; } catch {}
          }
          const ok = await this.fetchMe();
          if (!ok) this._clearSession();
        }
      } finally {
        this._initializing = false;
      }
    },

    _clearSession() {
      this.enrollee = null;
      this.token = null;
      this.isAuthenticated = false;
      this.hasCustomPassword = false;
      localStorage.removeItem('enrollee_token');
      localStorage.removeItem('enrollee');
    },
  },
});
