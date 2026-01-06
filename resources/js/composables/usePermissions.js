import { computed } from 'vue';
import { useAuthStore } from '../stores/auth';

/**
 * Composable for permission-based access control
 * Use this instead of hard-coded role checks
 */
export function usePermissions() {
  const authStore = useAuthStore();

  /**
   * Check if user has a specific permission
   * @param {string} permission - Permission name (e.g., 'referrals.create')
   * @returns {boolean}
   */
  const hasPermission = (permission) => {
    return authStore.hasPermission(permission);
  };

  /**
   * Check if user has any of the specified permissions
   * @param {string[]} permissions - Array of permission names
   * @returns {boolean}
   */
  const hasAnyPermission = (permissions) => {
    return permissions.some(permission => authStore.hasPermission(permission));
  };

  /**
   * Check if user has all of the specified permissions
   * @param {string[]} permissions - Array of permission names
   * @returns {boolean}
   */
  const hasAllPermissions = (permissions) => {
    return permissions.every(permission => authStore.hasPermission(permission));
  };

  /**
   * Check if user has a specific role
   * @param {string} role - Role name (e.g., 'desk_officer')
   * @returns {boolean}
   */
  const hasRole = (role) => {
    return authStore.hasRole(role);
  };

  /**
   * Check if user has any of the specified roles
   * @param {string[]} roles - Array of role names
   * @returns {boolean}
   */
  const hasAnyRole = (roles) => {
    return roles.some(role => authStore.hasRole(role));
  };

  // Computed properties for common permission checks
  const canCreateReferral = computed(() => hasPermission('referrals.create'));
  const canViewReferrals = computed(() => hasPermission('referrals.view'));
  const canSubmitReferral = computed(() => hasPermission('referrals.submit'));
  const canApproveReferral = computed(() => hasPermission('referrals.approve'));
  const canRejectReferral = computed(() => hasPermission('referrals.reject'));
  const canPrintReferral = computed(() => hasPermission('referrals.print'));

  const canRequestPACode = computed(() => hasPermission('pa_codes.request'));
  const canViewPACodes = computed(() => hasPermission('pa_codes.view'));
  const canApprovePACode = computed(() => hasPermission('pa_codes.approve'));
  const canRejectPACode = computed(() => hasPermission('pa_codes.reject'));

  const canCreateClaim = computed(() => hasPermission('claims.create'));
  const canViewClaims = computed(() => hasPermission('claims.view'));
  const canSubmitClaim = computed(() => hasPermission('claims.submit'));
  const canApproveClaim = computed(() => hasPermission('claims.approve'));
  const canRejectClaim = computed(() => hasPermission('claims.reject'));

  const canCreateAdmission = computed(() => hasPermission('admissions.create'));
  const canViewAdmissions = computed(() => hasPermission('admissions.view'));
  const canUpdateAdmission = computed(() => hasPermission('admissions.update'));
  const canDischargePatient = computed(() => hasPermission('admissions.discharge'));

  const canValidateUTN = computed(() => hasPermission('utn.validate'));
  const canViewUTN = computed(() => hasPermission('utn.view'));

  const canUploadDocuments = computed(() => hasPermission('documents.upload'));
  const canViewDocuments = computed(() => hasPermission('documents.view'));
  const canDownloadDocuments = computed(() => hasPermission('documents.download'));

  const canViewReports = computed(() => hasPermission('reports.view'));
  const canExportReports = computed(() => hasPermission('reports.export'));

  const canManageUsers = computed(() => hasPermission('users.manage'));
  const canManageRoles = computed(() => hasPermission('roles.manage'));
  const canManagePermissions = computed(() => hasPermission('permissions.manage'));

  // Role-based computed properties (for backward compatibility)
  const isFacilityRole = computed(() => {
    return hasAnyRole(['facility_admin', 'facility_user', 'desk_officer']);
  });

  const isAdmin = computed(() => {
    return hasAnyRole(['admin', 'Super Admin']);
  });

  const isDeskOfficer = computed(() => {
    return hasRole('desk_officer');
  });

  const isFacilityAdmin = computed(() => {
    return hasRole('facility_admin');
  });

  const isFacilityUser = computed(() => {
    return hasRole('facility_user');
  });

  const isClaimsOfficer = computed(() => {
    return hasRole('claims_officer');
  });

  // Module access checks
  const hasModuleAccess = (moduleName) => {
    const availableModules = authStore.availableModules || [];

    // Super Admin has access to all modules
    if (hasRole('Super Admin')) {
      return true;
    }

    // General module is accessible to everyone
    if (moduleName === 'general') {
      return true;
    }

    // Check if module is in user's available modules
    return availableModules.includes(moduleName);
  };

  const canAccessPASModule = computed(() => hasModuleAccess('pas'));
  const canAccessClaimsModule = computed(() => hasModuleAccess('claims'));
  const canAccessAutomationModule = computed(() => hasModuleAccess('automation'));
  const canAccessManagementModule = computed(() => hasModuleAccess('management'));

  return {
    // Permission check functions
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasRole,
    hasAnyRole,

    // Module access
    hasModuleAccess,
    canAccessPASModule,
    canAccessClaimsModule,
    canAccessAutomationModule,
    canAccessManagementModule,

    // Referral permissions
    canCreateReferral,
    canViewReferrals,
    canSubmitReferral,
    canApproveReferral,
    canRejectReferral,
    canPrintReferral,

    // PA Code permissions
    canRequestPACode,
    canViewPACodes,
    canApprovePACode,
    canRejectPACode,

    // Claim permissions
    canCreateClaim,
    canViewClaims,
    canSubmitClaim,
    canApproveClaim,
    canRejectClaim,

    // Admission permissions
    canCreateAdmission,
    canViewAdmissions,
    canUpdateAdmission,
    canDischargePatient,

    // UTN permissions
    canValidateUTN,
    canViewUTN,

    // Document permissions
    canUploadDocuments,
    canViewDocuments,
    canDownloadDocuments,

    // Report permissions
    canViewReports,
    canExportReports,

    // User management permissions
    canManageUsers,
    canManageRoles,
    canManagePermissions,

    // Role-based checks (for backward compatibility)
    isFacilityRole,
    isAdmin,
    isDeskOfficer,
    isFacilityAdmin,
    isFacilityUser,
    isClaimsOfficer,
  };
}

