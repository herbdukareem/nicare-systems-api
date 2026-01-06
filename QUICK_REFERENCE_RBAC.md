# RBAC & Module Access - Quick Reference Guide

## For Frontend Developers

### 1. Checking Permissions in Components

```javascript
import { usePermissions } from '@/composables/usePermissions';

const { 
  hasPermission,
  canCreateReferral,
  canApproveReferral,
  hasModuleAccess,
  canAccessPASModule
} = usePermissions();

// Method 1: Use specific permission
if (hasPermission('referrals.create')) {
  // Show create button
}

// Method 2: Use computed property
if (canCreateReferral.value) {
  // Show create button
}

// Method 3: Check module access
if (hasModuleAccess('pas')) {
  // Show PAS-related content
}

// Method 4: Use module computed property
if (canAccessPASModule.value) {
  // Show PAS menu item
}
```

### 2. Using in Templates

```vue
<template>
  <!-- Show button only if user has permission -->
  <v-btn 
    v-if="canCreateReferral"
    @click="createReferral"
  >
    Create Referral
  </v-btn>

  <!-- Show section only if user has module access -->
  <div v-if="canAccessPASModule">
    <h2>PAS Dashboard</h2>
    <!-- PAS content -->
  </div>

  <!-- Disable button if no permission -->
  <v-btn 
    :disabled="!canApproveReferral"
    @click="approveReferral"
  >
    Approve
  </v-btn>
</template>
```

### 3. Adding Routes with Permissions

```javascript
{
  path: '/my-feature',
  name: 'my-feature',
  component: () => import('../components/MyFeature.vue'),
  meta: {
    requiresAuth: true,
    permissions: ['feature.view', 'feature.create'], // User needs at least one
    title: 'My Feature',
    description: 'Feature description'
  }
}
```

---

## For Backend Developers

### 1. Checking Permissions in Controllers

```php
// Check single permission
if (!$user->hasPermission('referrals.create')) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have permission to create referrals.'
    ], 403);
}

// Check multiple permissions (user needs at least one)
if (!$user->hasAnyPermission(['referrals.approve', 'referrals.reject'])) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have permission to manage referrals.'
    ], 403);
}

// Check all permissions (user needs all)
if (!$user->hasAllPermissions(['referrals.view', 'referrals.approve'])) {
    return response()->json([
        'success' => false,
        'message' => 'Insufficient permissions.'
    ], 403);
}
```

### 2. Using Middleware (Recommended)

```php
// In routes/api.php
Route::middleware(['auth:sanctum', 'permission:referrals.create'])
    ->post('/referrals', [ReferralController::class, 'store']);

// Multiple permissions (user needs at least one)
Route::middleware(['auth:sanctum', 'permission:referrals.approve,referrals.reject'])
    ->put('/referrals/{id}/status', [ReferralController::class, 'updateStatus']);
```

---

## Available Permissions

### Referrals
- `referrals.view` - View referrals
- `referrals.create` - Create referrals
- `referrals.submit` - Submit referrals
- `referrals.approve` - Approve referrals
- `referrals.reject` - Reject referrals
- `referrals.print` - Print referrals

### PA Codes
- `pa_codes.view` - View PA codes
- `pa_codes.request` - Request PA codes
- `pa_codes.approve` - Approve PA code requests
- `pa_codes.reject` - Reject PA code requests

### Claims
- `claims.view` - View claims
- `claims.create` - Create claims
- `claims.submit` - Submit claims
- `claims.approve` - Approve claims
- `claims.reject` - Reject claims

### Admissions
- `admissions.view` - View admissions
- `admissions.create` - Create admissions
- `admissions.update` - Update admissions
- `admissions.discharge` - Discharge patients

### UTN
- `utn.validate` - Validate UTN
- `utn.view` - View UTN

### Documents
- `documents.upload` - Upload documents
- `documents.view` - View documents
- `documents.download` - Download documents
- `documents.manage` - Manage document requirements

### Facilities
- `facilities.view` - View facilities
- `facilities.assign` - Assign facilities to desk officers

### Dashboards
- `dashboard.desk_officer.view` - View DO dashboard
- `dashboard.facility.view` - View facility dashboard

### Reports
- `reports.view` - View reports
- `reports.export` - Export reports

### User Management
- `users.manage` - Manage users
- `roles.manage` - Manage roles
- `permissions.manage` - Manage permissions

---

## Available Modules

- `general` - Core & Admin (accessible to all)
- `pas` - Pre-Authorization System
- `claims` - Claims Management
- `automation` - Claims Automation
- `management` - System Management

---

## Common Patterns

### Pattern 1: Feature with Create, View, Edit, Delete
```javascript
const { 
  hasPermission,
  hasAnyPermission 
} = usePermissions();

const canView = computed(() => hasPermission('feature.view'));
const canCreate = computed(() => hasPermission('feature.create'));
const canEdit = computed(() => hasPermission('feature.edit'));
const canDelete = computed(() => hasPermission('feature.delete'));
const canManage = computed(() => hasAnyPermission(['feature.edit', 'feature.delete']));
```

### Pattern 2: Role-Specific Dashboard Redirect
```javascript
// In router guard or component
const userRole = authStore.userRoles[0]?.name;

if (userRole === 'desk_officer') {
  router.push('/do-dashboard');
} else if (userRole === 'facility_admin' || userRole === 'facility_user') {
  router.push('/facility-dashboard');
} else {
  router.push('/dashboard');
}
```

### Pattern 3: Conditional Menu Items
```javascript
const menuItems = computed(() => {
  const items = [];
  
  if (canAccessPASModule.value) {
    items.push({
      title: 'PAS',
      icon: 'mdi-shield-check',
      path: '/pas'
    });
  }
  
  if (canAccessClaimsModule.value) {
    items.push({
      title: 'Claims',
      icon: 'mdi-file-document',
      path: '/claims'
    });
  }
  
  return items;
});
```

---

## Troubleshooting

### Issue: User can't access a route
1. Check if user has required permission
2. Check if user's role has the module assigned
3. Check if route has correct `meta.permissions` defined
4. Check router guard logs

### Issue: Module switcher doesn't show a module
1. Check if role has module in `modules` array
2. Check if user is using correct role (role switcher)
3. Verify `authStore.availableModules` contains the module

### Issue: Permission check always fails
1. Verify permission exists in database
2. Check if role has permission assigned
3. Ensure user is authenticated
4. Check if using correct permission name (case-sensitive)

---

## Best Practices

1. **Always use permissions, not roles** - Permissions are more granular and flexible
2. **Use computed properties** - Better performance and reactivity
3. **Check permissions in both frontend and backend** - Defense in depth
4. **Use descriptive permission names** - `referrals.create` not `create_ref`
5. **Group related permissions** - Use dot notation for categories
6. **Test with different roles** - Ensure access control works correctly
7. **Document custom permissions** - Add to this guide when creating new ones

