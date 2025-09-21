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
    _initializing: false, // Flag to prevent multiple initializations
  }),

  getters: {
    isLoggedIn: (state) => !!state.token,
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
      if (!this.token) return false;

      try {
        const response = await authAPI.getUser();
        console.log(response)
        // Expecting { success: true, data: { ...user } }
        if (response?.data?.success) {
          
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
            } catch {
              this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
            }
          } else {
            this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
          }

          return true; // ✅ success
        }

        // Backend responded but not success
        this.isAuthenticated = false;
        return false;
      } catch (error) {
        // ❌ Do NOT logout here — just mark unauthenticated and let the caller decide
        console.error('Failed to fetch user:', error);
        this.isAuthenticated = false;
        return false;
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
  if (this._initializing) return;

  this._initializing = true;
  try {
    const token = localStorage.getItem('token');
    if (token) {
      this.token = token;

      const ok = await this.fetchUser();
      if (!ok) {
  
        // Token invalid or /user failed — clear quietly, no redirect here
        this.user = null;
        this.isAuthenticated = false;
        this.currentRole = null;
        this.availableRoles = [];

        this.token = null;
        localStorage.removeItem('token');
        localStorage.removeItem('currentRole');
      }
    }
  } finally {
    this._initializing = false;
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