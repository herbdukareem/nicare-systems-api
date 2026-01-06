# Quick Start: Permission System

## 🚀 5-Minute Guide to Using Permissions

---

## For Administrators

### Create a Role with Permissions

1. **Navigate:** Settings → Roles & Permissions
2. **Click:** "Create Role" button
3. **Fill in:**
   - Name: `my_role_name`
   - Description: What this role does
   - Modules: Select which modules this role can access
4. **Select Permissions:**
   - Use "Select All" to give all permissions
   - Use "Clear All" to remove all permissions
   - Use "Toggle" on categories to select/deselect groups
   - Or manually check individual permissions
5. **Save:** Click "Save Role"

✅ **Done!** The role now has those permissions.

### Assign Role to User

1. **Navigate:** User Management
2. **Create/Edit** a user
3. **Select Role(s)** from the dropdown
4. **Save** the user

✅ **Done!** User automatically has all permissions from that role.

---

## For Developers

### Backend: Check Permission in Controller

```php
use Illuminate\Http\Request;

public function createReferral(Request $request)
{
    // Check if user has permission
    if (!$request->user()->hasPermission('referrals.create')) {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to create referrals.'
        ], 403);
    }
    
    // User has permission, proceed...
    // Your code here
}
```

### Backend: Check Multiple Permissions

```php
// Check if user has ANY of these permissions
if ($user->hasAnyPermission(['claims.create', 'claims.edit'])) {
    // User can create OR edit claims
}

// Get all user permissions
$permissions = $user->getAllPermissions();
// Returns collection of Permission models
```

### Frontend: Check Permission in Component

```javascript
import { usePermissions } from '@/composables/usePermissions';

export default {
  setup() {
    const { hasPermission, canCreateReferral } = usePermissions();
    
    // Method 1: Direct check
    const canCreate = hasPermission('referrals.create');
    
    // Method 2: Use computed property
    const canCreateRef = canCreateReferral; // Returns ref<boolean>
    
    return { canCreate, canCreateRef };
  }
}
```

### Frontend: Conditional Rendering

```vue
<template>
  <!-- Show button only if user has permission -->
  <v-btn 
    v-if="canCreateReferral"
    @click="createReferral"
  >
    Create Referral
  </v-btn>
  
  <!-- Show different content based on permission -->
  <div v-if="hasPermission('claims.view')">
    <ClaimsList />
  </div>
  <div v-else>
    <p>You don't have permission to view claims.</p>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions';

const { hasPermission, canCreateReferral } = usePermissions();
</script>
```

### Frontend: Route Guard

```javascript
// In router/index.js
{
  path: '/referrals/create',
  component: CreateReferral,
  meta: {
    requiresAuth: true,
    requiredPermission: 'referrals.create',
    requiredModule: 'pas'
  }
}
```

---

## Available Permissions

### Dashboard
- `dashboard.desk_officer.view`
- `dashboard.claims.view`
- `dashboard.management.view`

### Referrals
- `referrals.view`
- `referrals.create`
- `referrals.edit`
- `referrals.delete`
- `referrals.submit`
- `referrals.approve`
- `referrals.print`

### PA Codes
- `pa_codes.view`
- `pa_codes.request`
- `pa_codes.approve`
- `pa_codes.reject`

### Claims
- `claims.view`
- `claims.create`
- `claims.edit`
- `claims.delete`
- `claims.submit`
- `claims.approve`
- `claims.reject`
- `claims.process`

### Admissions
- `admissions.view`
- `admissions.create`
- `admissions.edit`
- `admissions.delete`

### UTN
- `utn.view`
- `utn.validate`
- `utn.manage`

### Documents
- `documents.view`
- `documents.upload`
- `documents.download`
- `documents.delete`

### Facilities
- `facilities.view`
- `facilities.create`
- `facilities.edit`
- `facilities.delete`
- `facilities.assign`

### Users
- `users.view`
- `users.create`
- `users.edit`
- `users.delete`
- `users.manage_roles`

### Roles & Permissions
- `roles.view`
- `roles.create`
- `roles.edit`
- `roles.delete`
- `permissions.view`
- `permissions.create`
- `permissions.edit`
- `permissions.delete`

### Reports
- `reports.view`
- `reports.generate`
- `reports.export`
- `analytics.view`
- `audit.view`

---

## Common Patterns

### Pattern 1: Create with Permission Check
```php
public function store(Request $request)
{
    // 1. Check permission
    if (!$request->user()->hasPermission('claims.create')) {
        return $this->sendError('Unauthorized', 403);
    }
    
    // 2. Validate
    $validated = $request->validate([...]);
    
    // 3. Create
    $claim = Claim::create($validated);
    
    // 4. Return
    return $this->sendResponse($claim, 'Created successfully');
}
```

### Pattern 2: Conditional UI
```vue
<template>
  <div>
    <!-- View for everyone with view permission -->
    <DataTable v-if="canView" :data="items" />
    
    <!-- Actions only for users with edit permission -->
    <v-btn v-if="canEdit" @click="edit">Edit</v-btn>
    <v-btn v-if="canDelete" @click="remove">Delete</v-btn>
  </div>
</template>

<script setup>
const { hasPermission } = usePermissions();
const canView = hasPermission('items.view');
const canEdit = hasPermission('items.edit');
const canDelete = hasPermission('items.delete');
</script>
```

---

## Troubleshooting

### User doesn't have expected permission

**Check:**
1. ✓ Is user assigned a role?
2. ✓ Does the role have the permission?
3. ✓ Is the permission name spelled correctly?
4. ✓ Has user logged out and back in?

### Permission check not working

**Check:**
1. ✓ Permission exists in database
2. ✓ Permission is assigned to role
3. ✓ Role is assigned to user
4. ✓ Using correct permission name (case-sensitive)

---

## Key Takeaways

✅ **Permissions belong to ROLES, not users**  
✅ **Users inherit permissions from their roles**  
✅ **Always check permissions in both frontend and backend**  
✅ **Use descriptive permission names** (e.g., `claims.create`)  
✅ **Test with different roles** to ensure access control works

---

## Need Help?

- 📖 Full Guide: `PERMISSION_INHERITANCE_GUIDE.md`
- 📊 System Status: `PERMISSION_SYSTEM_STATUS.md`
- 🔧 Implementation: `RBAC_IMPLEMENTATION_SUMMARY.md`

