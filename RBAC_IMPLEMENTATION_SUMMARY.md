# Role-Based Access Control (RBAC) Implementation Summary

## Overview
Replaced hard-coded role checks with permission-based access control throughout the application to provide more flexible and maintainable security.

---

## Changes Made

### 1. Created `usePermissions` Composable
**File:** `resources/js/composables/usePermissions.js`

A centralized composable for permission-based access control that provides:

#### Permission Check Functions:
- `hasPermission(permission)` - Check single permission
- `hasAnyPermission(permissions)` - Check if user has any of the permissions
- `hasAllPermissions(permissions)` - Check if user has all permissions
- `hasRole(role)` - Check single role (for backward compatibility)
- `hasAnyRole(roles)` - Check if user has any of the roles

#### Computed Properties for Common Permissions:
**Referrals:**
- `canCreateReferral`
- `canViewReferrals`
- `canSubmitReferral`
- `canApproveReferral`
- `canRejectReferral`
- `canPrintReferral`

**PA Codes:**
- `canRequestPACode`
- `canViewPACodes`
- `canApprovePACode`
- `canRejectPACode`

**Claims:**
- `canCreateClaim`
- `canViewClaims`
- `canSubmitClaim`
- `canApproveClaim`
- `canRejectClaim`

**Admissions:**
- `canCreateAdmission`
- `canViewAdmissions`
- `canUpdateAdmission`
- `canDischargePatient`

**UTN:**
- `canValidateUTN`
- `canViewUTN`

**Documents:**
- `canUploadDocuments`
- `canViewDocuments`
- `canDownloadDocuments`

**Reports:**
- `canViewReports`
- `canExportReports`

**User Management:**
- `canManageUsers`
- `canManageRoles`
- `canManagePermissions`

**Role-Based Checks (Backward Compatibility):**
- `isFacilityRole`
- `isAdmin`
- `isDeskOfficer`
- `isFacilityAdmin`
- `isFacilityUser`
- `isClaimsOfficer`

---

### 2. Updated Frontend Components

#### ReferralSubmissionPage.vue
**Before:**
```javascript
const isFacilityRole = computed(() => {
  const user_roles = user.value?.roles || []
  const user_role_names = user_roles.map(role => role.name);
  return user_role_names.some(role => ['facility_admin', 'facility_user', 'desk_officer'].includes(role));
});
```

**After:**
```javascript
import { usePermissions } from '../../composables/usePermissions';
const { isFacilityRole, canCreateReferral } = usePermissions();
```

---

### 3. Updated Router Configuration

Changed from hard-coded roles to permission-based access:

#### Referral Routes:
**Before:**
```javascript
meta: {
  requiresAuth: true,
  roles: ['admin', 'Super Admin', 'claims_officer'],
  ...
}
```

**After:**
```javascript
meta: {
  requiresAuth: true,
  permissions: ['referrals.create', 'referrals.submit'],
  ...
}
```

#### Updated Routes:
1. `/do-dashboard` → `permissions: ['dashboard.desk_officer.view']`
2. `/facility-dashboard` → `permissions: ['dashboard.facility.view']`
3. `/do/assigned-referrals` → `permissions: ['referrals.view']`
4. `/document-requirements` → `permissions: ['documents.manage']`
5. `/do-facilities` → `permissions: ['facilities.assign']`
6. `/pas/validate-utn` → `permissions: ['utn.validate']`
7. `/pas/fu-pa-request` → `permissions: ['pa_codes.request']`
8. `/pas/fu-pa-approval` → `permissions: ['pa_codes.approve', 'pa_codes.reject']`
9. `/pas/facility-pa-codes` → `permissions: ['pa_codes.view', 'pa_codes.request']`
10. `/pas/referral-management` → `permissions: ['referrals.view']`
11. `/pas/referrals` → `permissions: ['referrals.create', 'referrals.submit']`
12. `/claims/referral-request` → `permissions: ['referrals.create', 'referrals.submit']`
13. `/claims/approval` → `permissions: ['claims.approve']`

---

### 4. Updated Backend Controllers

#### DODashboardController.php
**Before:**
```php
if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
    return response()->json([
        'success' => false,
        'message' => 'Access denied. Facility role required.'
    ], 403);
}
```

**After:**
```php
if (!$user->hasPermission('utn.validate')) {
    return response()->json([
        'success' => false,
        'message' => 'Access denied. You do not have permission to validate UTN.'
    ], 403);
}
```

#### DOFacilityController.php (store & update methods)
**Before:**
```php
if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
    return response()->json([
        'success' => false,
        'message' => 'User must have facility role to be assigned to facilities',
        ...
    ], 422);
}
```

**After:**
```php
if (!$user->hasPermission('facilities.view')) {
    return response()->json([
        'success' => false,
        'message' => 'User must have facility permissions to be assigned to facilities',
        ...
    ], 422);
}
```

---

## Benefits

1. **Flexibility:** Permissions can be assigned/revoked without code changes
2. **Maintainability:** Centralized permission logic in one composable
3. **Scalability:** Easy to add new permissions without modifying multiple files
4. **Security:** Fine-grained access control based on actual permissions
5. **Backward Compatibility:** Role-based checks still available for legacy code

---

## Migration Guide for Developers

### For Vue Components:
```javascript
// Old way (DON'T USE)
const isAdmin = computed(() => {
  return user.value?.roles?.some(role => role.name === 'admin');
});

// New way (USE THIS)
import { usePermissions } from '@/composables/usePermissions';
const { hasPermission, canCreateReferral } = usePermissions();

// Check specific permission
if (hasPermission('referrals.create')) {
  // Allow action
}

// Or use computed property
if (canCreateReferral.value) {
  // Allow action
}
```

### For Backend Controllers:
```php
// Old way (DON'T USE)
if (!$user->hasRole('admin')) {
    return response()->json(['message' => 'Forbidden'], 403);
}

// New way (USE THIS)
if (!$user->hasPermission('users.manage')) {
    return response()->json(['message' => 'Forbidden'], 403);
}
```

---

## Module Access Control

In addition to permission-based access, the system now enforces **module-based access control**:

### Module Assignment
Roles can be assigned specific modules via the `modules` JSON column:
```json
{
  "modules": ["general", "pas", "claims"]
}
```

### Available Modules
- `general` - Core & Admin (accessible to all)
- `pas` - Pre-Authorization System
- `claims` - Claims Management
- `automation` - Claims Automation
- `management` - System Management

### Implementation
1. **Module Switcher** - Only shows modules assigned to current role
2. **Router Guard** - Validates module access before navigation
3. **usePermissions** - Provides module access helper functions

See `MODULE_ACCESS_CONTROL.md` for detailed documentation.

---

## Next Steps

1. ✅ Update remaining components with hard-coded role checks
2. ✅ Update backend API routes to use permission middleware
3. ✅ Implement module-based access control
4. ⏳ Add permission checks to all sensitive operations
5. ⏳ Update documentation for permission requirements
6. ⏳ Create permission management UI for admins
7. ⏳ Add module assignment UI in role management

---

## Testing Checklist

- [ ] Test referral submission with different user permissions
- [ ] Test PA code request/approval with different permissions
- [ ] Test claim submission/approval with different permissions
- [ ] Test UTN validation with different permissions
- [ ] Test facility assignment with different permissions
- [ ] Test dashboard access with different permissions
- [ ] Verify users without permissions are properly blocked
- [ ] Verify backward compatibility with existing role checks

