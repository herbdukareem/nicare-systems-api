import { defineStore } from 'pinia';
import { authAPI } from '../utils/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
    loading: false,
    currentRole: null, // Currently active role
    availableRoles: [], // All roles user can switch to
  }),

  getters: {
    isLoggedIn: (state) => !!state.token && !!state.user,
    userName: (state) => state.user?.name || '',
    userRoles: (state) => state.user?.roles || [],
    getUserAvailableRoles: (state) => state.availableRoles || [],
    getUserCurrentRole: (state) => state.currentRole,
    currentRolePermissions: (state) => {
      if (!state.currentRole) return [];
      return state.currentRole.permissions || [];
    },
    userPermissions: (state) => {
      // If a specific role is active, return only that role's permissions
      if (state.currentRole) {
        return state.currentRole.permissions || [];
      }
      // Otherwise, return all permissions from all roles
      const directPermissions = state.user?.permissions || [];
      const rolePermissions = state.user?.roles?.flatMap(role => role.permissions || []) || [];
      return [...directPermissions, ...rolePermissions];
    },
    canSwitchRoles: (state) => state.availableRoles.length > 1,
  },

  actions: {
    async login(credentials) {
      this.loading = true;
      try {
        const response = await authAPI.login(credentials);

        if (response.data.success) {
          const { user, token } = response.data.data;

          this.user = user;
          this.token = token;
          this.isAuthenticated = true;

          // Initialize role switching
          this.availableRoles = user.roles || [];
          this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;

          // Store token and current role in localStorage
          localStorage.setItem('token', token);
          if (this.currentRole) {
            localStorage.setItem('currentRole', JSON.stringify(this.currentRole));
          }

          return response.data;
        } else {
          throw new Error(response.data.message || 'Login failed');
        }
      } catch (error) {
        // Clear any existing auth state on login failure
        this.user = null;
        this.token = null;
        this.isAuthenticated = false;
        localStorage.removeItem('token');
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      try {
        await authAPI.logout();
      } catch (error) {
        console.error('Logout error:', error);
      } finally {
        // Clear local state regardless of API response
        this.user = null;
        this.token = null;
        this.isAuthenticated = false;
        this.currentRole = null;
        this.availableRoles = [];
        localStorage.removeItem('token');
        localStorage.removeItem('currentRole');
      }
    },

    async fetchUser() {
      if (!this.token) return;

      try {
        const response = await authAPI.getUser();
        if (response.data.success) {
          this.user = response.data.data;
          this.isAuthenticated = true;

          // Initialize role switching
          this.availableRoles = this.user.roles || [];

          // Restore current role from localStorage or set default
          const savedRole = localStorage.getItem('currentRole');
          if (savedRole) {
            try {
              const parsedRole = JSON.parse(savedRole);
              // Verify the saved role is still available
              const roleExists = this.availableRoles.find(role => role.id === parsedRole.id);
              this.currentRole = roleExists || (this.availableRoles.length > 0 ? this.availableRoles[0] : null);
            } catch (e) {
              this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
            }
          } else {
            this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
          }
        } else {
          throw new Error(response.data.message || 'Failed to fetch user');
        }
      } catch (error) {
        // Token might be invalid
        console.error('Failed to fetch user:', error);
        this.logout();
        throw error;
      }
    },

    async forgotPassword(email) {
      this.loading = true;
      try {
        const response = await authAPI.forgotPassword(email);
        return response.data;
      } catch (error) {
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async resetPassword(data) {
      this.loading = true;
      try {
        const response = await authAPI.resetPassword(data);
        return response.data;
      } catch (error) {
        throw error;
      } finally {
        this.loading = false;
      }
    },

    // Initialize auth state from localStorage
    async initializeAuth() {
      const token = localStorage.getItem('token');
      if (token) {
        this.token = token;
        try {
          await this.fetchUser();

          // Restore current role from localStorage if available
          const storedRole = localStorage.getItem('currentRole');
          if (storedRole) {
            try {
              const role = JSON.parse(storedRole);
              // Verify the role is still available for this user
              if (this.availableRoles.find(r => r.id === role.id)) {
                this.currentRole = role;
              } else {
                // Role no longer available, clear it
                localStorage.removeItem('currentRole');
              }
            } catch (e) {
              // Invalid JSON, clear it
              localStorage.removeItem('currentRole');
            }
          }
        } catch (error) {
          // If token is invalid, clear it silently
          console.warn('Failed to initialize auth with stored token:', error.message);
          this.logout();
        }
      }
    },

    // Role switching methods
    switchRole(role) {
      if (!this.availableRoles.find(r => r.id === role.id)) {
        throw new Error('Role not available for this user');
      }

      this.currentRole = role;
      localStorage.setItem('currentRole', JSON.stringify(role));
    },

    resetToAllRoles() {
      this.currentRole = null;
      localStorage.removeItem('currentRole');
    },

    hasPermission(permission) {
      const permissions = this.userPermissions;
      return permissions.some(p => p.name === permission || p === permission);
    },

    hasRole(roleName) {
      if (this.currentRole) {
        return this.currentRole.name === roleName;
      }
      return this.userRoles.some(role => role.name === roleName);
    },
  },
});