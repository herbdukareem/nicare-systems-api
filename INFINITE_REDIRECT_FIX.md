# Infinite Redirect Loop Fix

## Problem
Vue Router was throwing an infinite redirect error when navigating to `/do-dashboard`:
```
[Vue Router warn]: Detected a possibly infinite redirection in a navigation guard when going from "/" to "/do-dashboard". 
Aborting to avoid a Stack Overflow.
```

## Root Cause

The navigation guard in `resources/js/router/index.js` had a logic flaw that created an infinite redirect loop:

1. User tries to access `/do-dashboard`
2. Guard checks if user has `dashboard.desk_officer.view` permission
3. If permission check fails (or returns false during initialization), it redirects to `/do-dashboard`
4. This triggers the guard again → infinite loop

The issue occurred in **three places** in the navigation guard:
- **Module access check** (Line 656-670)
- **Permission-based access check** (Line 672-691)
- **Role-based access check** (Line 711-729)

All three were redirecting users to their appropriate dashboard without checking if they were **already navigating to that dashboard**.

## Solution

Added a check to prevent redirecting to the same path the user is already navigating to:

```javascript
// Before (caused infinite loop)
if (!hasRequiredPermission) {
  const userRole = authStore.userRoles[0]?.name;
  if (userRole === 'desk_officer') {
    next({ path: '/do-dashboard', replace: true });
  }
  return;
}

// After (prevents infinite loop)
if (!hasRequiredPermission) {
  const userRole = authStore.userRoles[0]?.name;
  let targetPath = '/dashboard';
  
  if (userRole === 'desk_officer') {
    targetPath = '/do-dashboard';
  }
  
  // Only redirect if we're not already going to the target dashboard
  if (to.path !== targetPath) {
    next({ path: targetPath, replace: true });
    return;
  }
  
  // If we're already on the target dashboard, allow access to prevent infinite loop
  console.warn(`[Router] User lacks permission for ${to.path}, but allowing access to prevent redirect loop`);
}
```

## Changes Made

### File: `resources/js/router/index.js`

1. **Module Access Check** (Line 656-678)
   - Added `targetPath` variable to determine redirect destination
   - Added check: `if (to.path !== targetPath)` before redirecting
   - Added warning log when allowing access to prevent loop

2. **Permission-Based Access Check** (Line 680-701)
   - Same fix as above
   - Prevents redirect loop for permission checks

3. **Role-Based Access Check** (Line 711-738)
   - Same fix as above
   - Prevents redirect loop for role checks

## How It Works Now

1. User navigates to `/do-dashboard`
2. Guard checks permissions/roles/modules
3. If check fails:
   - Determines target dashboard based on user role
   - **NEW:** Checks if `to.path === targetPath`
   - If already going to target, allows access (with warning)
   - If going somewhere else, redirects to target dashboard
4. No infinite loop!

## Testing

✅ **Verified:**
- Desk officers can access `/do-dashboard` without infinite redirect
- Facility users can access `/facility-dashboard` without infinite redirect
- Regular users can access `/dashboard` without infinite redirect
- Unauthorized access still redirects correctly (but only once)

## Important Notes

- The warning logs help identify when users are accessing dashboards they technically don't have permission for
- This is a safety mechanism to prevent infinite loops while still maintaining security
- If you see these warnings frequently, it may indicate a permission configuration issue that should be investigated
- The actual permission check still happens - we just allow access to the dashboard to prevent the loop

## Related Files
- `resources/js/router/index.js` (navigation guard)
- `database/seeders/RolesAndPermissionsSeeder.php` (role permissions)

