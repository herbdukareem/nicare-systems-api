# Permission Inheritance Guide

## Overview
The NiCare system uses a **Role-Based Access Control (RBAC)** model where permissions are assigned to roles, and users automatically inherit permissions from their assigned roles.

---

## How It Works

### 1. **Permission Assignment Flow**

```
Permissions → Assigned to Roles → Roles Assigned to Users → Users Inherit Permissions
```

### 2. **Automatic Inheritance**

When you:
- ✅ Create a role and select permissions → Those permissions are saved to the role
- ✅ Assign a role to a user → User automatically gets all permissions from that role
- ✅ Update role permissions → All users with that role automatically get updated permissions
- ✅ Remove a permission from a role → All users with that role lose that permission

**No manual permission assignment to individual users is needed!**

---

## Creating a Role with Permissions

### Step 1: Navigate to Roles & Permissions
Go to **Settings → Roles & Permissions**

### Step 2: Create New Role
1. Click **"Create Role"** button
2. Fill in role details:
   - **Name**: Internal role name (e.g., `claims_officer`)
   - **Description**: What this role does
   - **Status**: Active or Inactive

### Step 3: Assign Modules
Select which modules this role can access:
- ☑️ General - Core & Admin
- ☑️ PAS - Pre-Authorization System
- ☑️ Claims - Claims Management
- ☑️ Automation - Claims Automation
- ☑️ Management - System Management

### Step 4: Select Permissions
Choose permissions for this role:

**Quick Actions:**
- **Select All** - Give role all permissions
- **Clear All** - Remove all permissions
- **Toggle Category** - Select/deselect all permissions in a category

**Permission Categories:**
- Dashboard Permissions
- Referral Permissions
- PA Code Permissions
- Claim Permissions
- Admission Permissions
- UTN Permissions
- Document Permissions
- Facility Permissions
- Report Permissions
- User Management Permissions

### Step 5: Save Role
Click **"Save Role"** - Permissions are now assigned to the role!

---

## Assigning Roles to Users

### Method 1: During User Creation
When creating a new user, select the role(s) to assign. The user will automatically inherit all permissions from those roles.

### Method 2: Edit Existing User
1. Go to user management
2. Edit the user
3. Assign/remove roles
4. Save - User permissions update automatically

---

## Database Structure

### Tables Involved

1. **`permissions`** - Stores all available permissions
   ```sql
   id | name | label | description
   ```

2. **`roles`** - Stores all roles
   ```sql
   id | name | label | description | modules (JSON)
   ```

3. **`permission_role`** - Links permissions to roles (pivot table)
   ```sql
   id | permission_id | role_id
   ```

4. **`role_user`** - Links roles to users (pivot table)
   ```sql
   id | role_id | user_id
   ```

### Relationships

```
User ←→ role_user ←→ Role ←→ permission_role ←→ Permission
```

When checking if a user has a permission:
1. Get user's roles from `role_user`
2. Get permissions for those roles from `permission_role`
3. Check if requested permission is in the list

---

## Code Examples

### Backend (PHP)

#### Check if User Has Permission
```php
// In a controller
if (!$user->hasPermission('referrals.create')) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have permission to create referrals.'
    ], 403);
}
```

#### Get All User Permissions
```php
$permissions = $user->getAllPermissions();
// Returns collection of all permissions from all user's roles
```

#### Assign Permissions to Role
```php
// When creating/updating a role
$role->permissions()->sync($permissionIds);
// This automatically updates permissions for all users with this role
```

### Frontend (JavaScript)

#### Check Permission in Component
```javascript
import { usePermissions } from '@/composables/usePermissions';

const { hasPermission, canCreateReferral } = usePermissions();

// Method 1: Direct check
if (hasPermission('referrals.create')) {
  // Show create button
}

// Method 2: Use computed property
if (canCreateReferral.value) {
  // Show create button
}
```

#### Check Permission in Template
```vue
<v-btn 
  v-if="canCreateReferral"
  @click="createReferral"
>
  Create Referral
</v-btn>
```

---

## Important Notes

### ✅ Best Practices

1. **Assign permissions to roles, not users** - This makes management easier
2. **Use descriptive role names** - e.g., `claims_officer` not `role1`
3. **Group related permissions** - Use categories to organize
4. **Test with different roles** - Ensure access control works correctly
5. **Document custom roles** - Keep track of what each role can do

### ⚠️ Common Mistakes

1. ❌ **Don't assign permissions directly to users** - Use roles instead
2. ❌ **Don't create too many roles** - Keep it simple and manageable
3. ❌ **Don't forget to assign modules** - Users need module access to see routes
4. ❌ **Don't give everyone all permissions** - Follow principle of least privilege

### 🔒 Security Considerations

1. **Super Admin** - Has all permissions and module access
2. **Regular Roles** - Only have permissions you explicitly assign
3. **Permission Changes** - Take effect immediately for all users with that role
4. **Module Access** - Checked before permission checks (users need both)

---

## Troubleshooting

### User Can't Access a Feature

**Check:**
1. ✓ Does user have a role assigned?
2. ✓ Does the role have the required permission?
3. ✓ Does the role have the required module assigned?
4. ✓ Is the role status "active"?
5. ✓ Has the user refreshed their session?

### Permission Not Working

**Check:**
1. ✓ Is permission name spelled correctly? (case-sensitive)
2. ✓ Does permission exist in database?
3. ✓ Is permission assigned to the role?
4. ✓ Is role assigned to the user?
5. ✓ Check browser console for errors

---

## Summary

✅ **Permissions are assigned to roles**  
✅ **Roles are assigned to users**  
✅ **Users automatically inherit permissions from their roles**  
✅ **No manual permission assignment to users needed**  
✅ **Changes to role permissions affect all users with that role immediately**

This design makes permission management simple, scalable, and maintainable! 🎉

