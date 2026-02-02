# Quick Actions Permission Control

## Overview
Added permission-based filtering for Quick Action cards on the Desk Officer Dashboard to ensure users only see actions they have permission to access.

## Problem
Previously, all Quick Action cards were displayed to all users on the DO Dashboard, regardless of their permissions. This could lead to:
- Users clicking on actions they don't have access to
- Confusion when redirected or shown access denied messages
- Poor user experience

## Solution
Implemented permission-based filtering that:
1. Defines required permissions for each Quick Action
2. Filters actions based on user's actual permissions
3. Only displays actions the user can access
4. Shows a helpful message when no actions are available

## Changes Made

### File: `resources/js/components/do/DODashboard.vue`

#### 1. Added Auth Store Import (Line 171)
```javascript
import { useAuthStore } from '../../stores/auth';
const authStore = useAuthStore();
```

#### 2. Updated Quick Actions Definition (Line 206-261)
**Before:**
```javascript
const quickActions = [
  {
    title: 'Validate UTN',
    icon: 'mdi-shield-check',
    route: '/pas/validate-utn',
  },
  // ... more actions
];
```

**After:**
```javascript
const allQuickActions = [
  {
    title: 'Validate UTN',
    icon: 'mdi-shield-check',
    route: '/pas/validate-utn',
    permissions: ['utn.validate'], // ✅ Added
  },
  // ... more actions with permissions
];

// ✅ Filter based on user permissions
const quickActions = computed(() => {
  return allQuickActions.filter(action => {
    if (!action.permissions || action.permissions.length === 0) {
      return true;
    }
    return action.permissions.some(permission => authStore.hasPermission(permission));
  });
});
```

#### 3. Updated Template (Line 132-168)
- Changed `v-if` to check `quickActions.length > 0`
- Added empty state message when no actions are available

## Quick Actions & Required Permissions

| Action | Route | Required Permissions |
|--------|-------|---------------------|
| **Validate UTN** | `/pas/validate-utn` | `utn.validate` |
| **View Referrals** | `/do/assigned-referrals` | `referrals.view` |
| **FU-PA Code Management** | `/pas/facility-pa-codes` | `pa_codes.view` OR `pa_codes.request` |
| **Admit Patient** | `/facility/admissions` | `admissions.view` OR `admissions.create` |
| **Submit Claim** | `/facility/claims/submit` | `claims.create` OR `claims.submit` |

## Permission Logic

The filtering uses **OR logic** for permissions:
- If an action has multiple permissions: `['pa_codes.view', 'pa_codes.request']`
- User needs **at least ONE** of these permissions to see the action
- This is implemented using `Array.some()` method

```javascript
action.permissions.some(permission => authStore.hasPermission(permission))
```

## User Experience

### For Users With Permissions
- See all Quick Action cards they have access to
- Can click and navigate to the respective pages
- Smooth, intuitive experience

### For Users Without Permissions
- Only see actions they can access
- If no permissions match any action:
  - See a friendly empty state message
  - Icon: Lightning bolt outline
  - Message: "No Quick Actions Available"
  - Subtext: "You don't have permissions to access any quick actions"

## Role-Based Examples

### Desk Officer (typical permissions)
**Sees:**
- ✅ Validate UTN (`utn.validate`)
- ✅ View Referrals (`referrals.view`)
- ✅ FU-PA Code Management (`pa_codes.view`)
- ✅ Admit Patient (`admissions.view`)
- ✅ Submit Claim (`claims.submit`)

### Facility Admin (typical permissions)
**Sees:**
- ✅ Validate UTN (`utn.validate`)
- ✅ FU-PA Code Management (`pa_codes.request`)
- ✅ Admit Patient (`admissions.create`)
- ✅ Submit Claim (`claims.create`)

### Limited User (minimal permissions)
**Sees:**
- ❌ Empty state message (if no matching permissions)

## Benefits

1. **Security**: Users can't accidentally access features they shouldn't
2. **Clarity**: Dashboard shows only relevant actions
3. **User Experience**: No confusion or access denied errors
4. **Maintainability**: Easy to add/remove actions or change permissions
5. **Scalability**: Works with any number of actions and permissions

## Testing

To test the permission control:

1. **Login as different user roles:**
   - Desk Officer
   - Facility Admin
   - Facility User
   - Limited role

2. **Verify Quick Actions:**
   - Check which actions are visible
   - Confirm they match the user's permissions
   - Try clicking each action to ensure navigation works

3. **Check Empty State:**
   - Create a test user with no relevant permissions
   - Verify the empty state message displays correctly

## Future Enhancements

Potential improvements:
- Add tooltips explaining why certain actions are unavailable
- Show disabled actions with visual indicators instead of hiding them
- Add permission request workflow for users who need access
- Track which actions are most used for analytics

## Related Files
- `resources/js/components/do/DODashboard.vue` (main component)
- `resources/js/stores/auth.js` (auth store with `hasPermission` method)
- `resources/js/router/index.js` (route definitions with permissions)
- `database/seeders/RolesAndPermissionsSeeder.php` (permission definitions)

