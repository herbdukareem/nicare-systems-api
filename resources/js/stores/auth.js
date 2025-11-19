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

          // Store token, user data, and current role in localStorage
          localStorage.setItem('token', token);
          localStorage.setItem('user', JSON.stringify(user));
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
        localStorage.removeItem('user');
        localStorage.removeItem('currentRole');
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
        localStorage.removeItem('user');
        localStorage.removeItem('currentRole');
      }
    },

   async fetchUser() {
      if (!this.token) {
        console.warn('[Auth] fetchUser called without token');
        return false;
      }

      try {
        console.log('[Auth] Fetching user data...');
        const response = await authAPI.getUser();
        console.log('[Auth] User fetch response:', response?.data);

        // Expecting { success: true, data: { ...user } }
        if (response?.data?.success) {
          this.user = response.data.data;
          this.isAuthenticated = true;

          // Store user data in localStorage
          localStorage.setItem('user', JSON.stringify(this.user));

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

          // Update currentRole in localStorage if it changed
          if (this.currentRole) {
            localStorage.setItem('currentRole', JSON.stringify(this.currentRole));
          }

          console.log('[Auth] User authenticated successfully:', this.user.username);
          return true; // ✅ success
        }

        // Backend responded but not success
        console.warn('[Auth] User fetch failed - backend returned success=false');
        this.isAuthenticated = false;
        return false;
      } catch (error) {
        // ❌ Do NOT logout here — just mark unauthenticated and let the caller decide
        console.error('[Auth] Failed to fetch user:', error.message || error);
        if (error.response?.status === 401) {
          console.warn('[Auth] Token appears to be invalid (401)');
        }
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
  if (this._initializing) {
    console.log('[Auth] Already initializing, skipping...');
    return;
  }

  this._initializing = true;
  console.log('[Auth] Initializing authentication...');

  try {
    const token = localStorage.getItem('token');
    const savedUser = localStorage.getItem('user');

    if (token) {
      console.log('[Auth] Token found in localStorage');
      this.token = token;

      // First, try to restore user from localStorage to avoid flicker
      if (savedUser) {
        try {
          const user = JSON.parse(savedUser);
          this.user = user;
          this.isAuthenticated = true;
          this.availableRoles = user.roles || [];

          // Restore current role from localStorage
          const savedRole = localStorage.getItem('currentRole');
          if (savedRole) {
            try {
              const parsedRole = JSON.parse(savedRole);
              const roleExists = this.availableRoles.find(role => role.id === parsedRole.id);
              this.currentRole = roleExists || (this.availableRoles.length > 0 ? this.availableRoles[0] : null);
            } catch {
              this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
            }
          } else {
            this.currentRole = this.availableRoles.length > 0 ? this.availableRoles[0] : null;
          }
          console.log('[Auth] Restored user from localStorage:', user.username);
        } catch (error) {
          console.error('[Auth] Failed to parse saved user:', error);
        }
      }

      // Then verify token is still valid by fetching fresh user data
      console.log('[Auth] Verifying token validity...');
      const ok = await this.fetchUser();
      if (!ok) {
        console.warn('[Auth] Token validation failed - clearing auth state');

        // Token invalid or /user failed — clear quietly, no redirect here
        this.user = null;
        this.isAuthenticated = false;
        this.currentRole = null;
        this.availableRoles = [];

        this.token = null;
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        localStorage.removeItem('currentRole');
      } else {
        console.log('[Auth] Token validated successfully');
      }
    } else {
      console.log('[Auth] No token found in localStorage');
    }
  } catch (error) {
    console.error('[Auth] Error during initialization:', error);
  } finally {
    this._initializing = false;
    console.log('[Auth] Initialization complete');
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