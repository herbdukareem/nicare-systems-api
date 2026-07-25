import { defineStore } from 'pinia';
import { authAPI, userAPI } from '../utils/api';

const permissionAliases = {
  'enrollee.view': ['enrollees.view'],
  'enrollee.create': ['enrollees.create'],
  'enrollee.update': ['enrollees.update', 'enrollees.edit'],
  'enrollee.delete': ['enrollees.delete'],
  'enrollees.view': ['enrollee.view'],
  'enrollees.create': ['enrollee.create'],
  'enrollees.update': ['enrollee.update', 'enrollees.edit'],
  'enrollees.edit': ['enrollee.update', 'enrollees.update'],
  'enrollees.delete': ['enrollee.delete'],
  'facilities.view': ['setup.facility.view'],
  'setup.facility.view': ['facilities.view'],
  'benefactor.view': ['setup.benefactor.view', 'benefactors.view'],
  'setup.benefactor.view': ['benefactor.view', 'benefactors.view'],
  'benefactors.view': ['benefactor.view', 'setup.benefactor.view'],
};

const normalizePermissionName = (permission) => {
  if (typeof permission === 'string') {
    return permission;
  }

  return permission?.name || null;
};

const dedupePermissions = (permissions = []) => {
  const seen = new Set();

  return permissions.filter((permission) => {
    const name = normalizePermissionName(permission);
    if (!name || seen.has(name)) {
      return false;
    }

    seen.add(name);
    return true;
  });
};

const collectPermissions = (state) => {
  const scopedRolePermissions = state.currentRole
    ? (state.currentRole.permissions || [])
    : (state.user?.roles?.flatMap((role) => role.permissions || []) || []);

  return dedupePermissions([
    ...(state.user?.permissions || []),
    ...(state.user?.direct_permissions || []),
    ...scopedRolePermissions,
  ]);
};

const matchesPermission = (loadedPermissions, requestedPermission) => {
  const loadedNames = new Set(
    loadedPermissions
      .map(normalizePermissionName)
      .filter(Boolean)
  );

  if (loadedNames.has(requestedPermission)) {
    return true;
  }

  return (permissionAliases[requestedPermission] || []).some((alias) => loadedNames.has(alias));
};

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

    availableModules: (state) => {
      const permissions = collectPermissions(state);

      return [...new Set(
        permissions
          .map((permission) => permission.category)
          .filter(Boolean)
      )];
    },

    currentRolePermissions: (state) => {
      if (!state.currentRole) return [];
      return dedupePermissions(state.currentRole.permissions || []);
    },
    userPermissions: (state) => {
      return collectPermissions(state);
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

          // Use current_role from user if available, otherwise use first available role
          this.currentRole = user.current_role || (this.availableRoles.length > 0 ? this.availableRoles[0] : null);

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

    clearSession() {
      this.user = null;
      this.token = null;
      this.isAuthenticated = false;
      this.currentRole = null;
      this.availableRoles = [];
      localStorage.removeItem('token');
      localStorage.removeItem('user');
      localStorage.removeItem('currentRole');
    },

   async fetchUser() {
      if (!this.token) {
        console.warn('[Auth] fetchUser called without token');
        return false;
      }

      try {
        const response = await authAPI.getUser();

        // Expecting { success: true, data: { ...user } }
        if (response?.data?.success) {
          this.user = response.data.data;
          this.isAuthenticated = true;

          // Store user data in localStorage
          localStorage.setItem('user', JSON.stringify(this.user));

          // Initialize role switching
          this.availableRoles = this.user.roles || [];

          // Priority: 1) user.current_role from backend, 2) saved role from localStorage, 3) first available role
          if (this.user.current_role) {
            // Use current_role from backend (most authoritative)
            this.currentRole = this.user.current_role;
          } else {
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
          }

          // Update currentRole in localStorage if it changed
          if (this.currentRole) {
            localStorage.setItem('currentRole', JSON.stringify(this.currentRole));
          }

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
    return;
  }

  this._initializing = true;

  try {
    const token = localStorage.getItem('token');
    const savedUser = localStorage.getItem('user');

    if (token) {
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
        } catch (error) {
          console.error('[Auth] Failed to parse saved user:', error);
        }
      }

      // Then verify token is still valid by fetching fresh user data
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
      }
    } else {
    }
  } catch (error) {
    console.error('[Auth] Error during initialization:', error);
  } finally {
    this._initializing = false;
  }
},

    // Role switching methods
    async switchRole(roleId) {
      try {
        // Find the role in available roles
        const role = this.availableRoles.find(r => r.id === roleId);
        if (!role) {
          throw new Error('Role not available for this user');
        }

        // Call backend API to update current_role_id
        if (this.user?.id) {
          await userAPI.switchRole(this.user.id, roleId);
        }

        // Update local state
        this.currentRole = role;
        localStorage.setItem('currentRole', JSON.stringify(role));

        // Update user object if it has current_role_id
        if (this.user) {
          this.user.current_role_id = roleId;
          this.user.current_role = role;
          localStorage.setItem('user', JSON.stringify(this.user));
        }

        return true;
      } catch (error) {
        console.error('[Auth] Failed to switch role:', error);
        throw error;
      }
    },

    resetToAllRoles() {
      this.currentRole = null;
      localStorage.removeItem('currentRole');
    },

    isSuperAdmin() {
      const superAdminNames = ['Super Admin', 'super-admin', 'admin'];
      if (this.currentRole) {
        return superAdminNames.includes(this.currentRole.name);
      }
      return this.userRoles.some(role => superAdminNames.includes(role.name));
    },

    hasPermission(permission) {
      // Super-admin / admin bypass: they have all permissions
      if (this.isSuperAdmin()) return true;

      const permissions = this.userPermissions;
      const hasIt = matchesPermission(permissions, permission);

      if (!hasIt && permissions.length === 0) {
        console.warn(`[Auth] No permissions loaded yet. Checking for: ${permission}`);
      }

      return hasIt;
    },

    hasRole(roleName) {
      if (this.currentRole) {
        return this.currentRole.name === roleName;
      }
      return this.userRoles.some(role => role.name === roleName);
    },
  },
});
