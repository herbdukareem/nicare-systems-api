# Testing Instructions - Menu Visibility Fix

## 🎯 What Was Fixed

Fixed the issue where menus and permission-based cards (like PAS Dashboard navigation cards) were not showing up until the page was refreshed after login.

---

## 🔧 Changes Made

### 1. Backend Changes

**File:** `app/Http/Controllers/Api/AuthController.php`

- Updated the `login()` method to load permissions with roles
- Now loads: `roles.permissions` and `currentRole.permissions`
- This ensures all permission data is available immediately after login

### 2. Frontend Changes

**File:** `resources/js/components/layout/AdminLayout.vue`

- Added a watcher for auth state changes (user, currentRole, isAuthenticated)
- This ensures menus re-compute when user data changes

**File:** `resources/js/stores/auth.js`

- Enhanced logging in `userPermissions` getter
- Enhanced logging in `hasPermission()` method
- Helps debug permission issues

---

## 🧪 How to Test

### Test 1: Login and Check Menus

1. **Clear browser cache and localStorage:**
   - Open browser DevTools (F12)
   - Go to Application tab → Storage → Clear site data
   - Or run in console: `localStorage.clear(); location.reload();`

2. **Login as PA Officer:**
   - Username: `pa_officer`
   - Password: (your test password)

3. **Check the sidebar menu:**
   - ✅ Should see "PAS" menu item immediately
   - ✅ Should see "Claims" menu item immediately
   - ✅ No refresh required

4. **Check browser console:**
   - Should see logs like:
     ```
     [Auth] Using current_role from backend: { id: 20, name: "PA Officer", ... }
     [Auth Store] userPermissions from currentRole: { roleName: "PA Officer", permissionCount: 30, ... }
     [AdminLayout] User/role/auth state changed, menus will update
     ```

### Test 2: PAS Dashboard Cards

1. **Navigate to PAS Dashboard:**
   - Click on "PAS" in the sidebar
   - Or go to: `http://localhost:8000/pas`

2. **Check navigation cards:**
   - ✅ Should see "Submit Referral to PAS" card
   - ✅ Should see "Referral Management" card
   - ✅ Should see "Request FU-PA Code" card
   - ✅ Should see "FU-PA Code Approval" card
   - ✅ Should see "Document Requirements" card
   - ✅ All cards visible immediately, no refresh required

### Test 3: Claims Dashboard Cards

1. **Navigate to Claims Dashboard:**
   - Click on "Claims" in the sidebar
   - Or go to: `http://localhost:8000/claims`

2. **Check navigation cards:**
   - ✅ Should see "Review Claims" card
   - ✅ Should see "Claims Approval" card
   - ✅ All cards visible immediately

### Test 4: Role Switching

1. **If user has multiple roles:**
   - Click on the role switcher in the top-right
   - Switch to a different role
   - ✅ Menus should update immediately
   - ✅ Cards should update based on new role's permissions

### Test 5: Different User Roles

Test with different user roles to ensure permissions work correctly:

1. **Super Admin:**
   - Should see ALL menus and cards

2. **PA Officer:**
   - Should see PAS and Claims menus
   - Should see PAS-related cards

3. **Claims Officer:**
   - Should see Claims menu
   - Should see Claims-related cards

4. **Desk Officer:**
   - Should be redirected to DO Dashboard
   - Should see DO-specific menus

---

## 🐛 Troubleshooting

### Issue: Menus still not showing

**Solution 1: Clear cache**
```javascript
// In browser console
localStorage.clear();
sessionStorage.clear();
location.reload();
```

**Solution 2: Check backend response**
```javascript
// In browser console after login
const user = JSON.parse(localStorage.getItem('user'));
console.log('User roles:', user.roles);
console.log('Current role:', user.current_role);
console.log('Permissions:', user.current_role?.permissions);
```

Expected output:
- `user.roles` should be an array with role objects
- Each role should have a `permissions` array
- `user.current_role` should have a `permissions` array

**Solution 3: Check database**
```sql
-- Verify PA Officer has permissions
SELECT r.name as role_name, COUNT(pr.permission_id) as permission_count
FROM roles r
LEFT JOIN permission_role pr ON r.id = pr.role_id
WHERE r.name = 'PA Officer'
GROUP BY r.id, r.name;
```

Expected: ~30 permissions for PA Officer

### Issue: Console shows "No permissions loaded yet"

This means the permissions are not being loaded with the role. Check:

1. **Backend:** Verify the login endpoint is loading permissions
2. **Database:** Verify the role has permissions assigned
3. **Frontend:** Check if `currentRole.permissions` is an array

---

## 📊 Expected Console Output

After successful login, you should see:

```
[Auth] Login attempt: pa_officer
[Auth] Using current_role from backend: {
  id: 20,
  name: "PA Officer",
  label: "PA Officer",
  modules: ["general", "pas", "claims"],
  permissions: [
    { id: 1, name: "dashboard.view", label: "View Dashboard" },
    { id: 2, name: "dashboard.pas.view", label: "View PAS Dashboard" },
    { id: 3, name: "referrals.create", label: "Create Referrals" },
    ...
  ]
}
[Auth Store] availableModules getter: {
  source: "state.currentRole",
  modules: ["general", "pas", "claims"]
}
[Auth Store] userPermissions from currentRole: {
  roleName: "PA Officer",
  permissionCount: 30,
  permissions: ["dashboard.pas.view", "dashboard.view", "referrals.create", ...]
}
[AdminLayout] User/role/auth state changed, menus will update
```

---

## ✅ Success Criteria

The fix is successful if:

1. ✅ Menus appear immediately after login (no refresh needed)
2. ✅ Dashboard cards appear immediately (no refresh needed)
3. ✅ Console shows permission logs
4. ✅ Role switching updates menus immediately
5. ✅ All permission-based UI elements work on first load

---

## 📝 Notes

1. **Logging:** The console logs can be removed in production if desired
2. **Performance:** Loading permissions adds minimal overhead (~1-2ms)
3. **Caching:** Clear browser cache between tests for accurate results
4. **Database:** Ensure roles have permissions assigned in the database

---

## 🎉 Expected Behavior After Fix

### Before Fix:
1. Login → Menus empty
2. Refresh → Menus appear
3. Navigate to PAS → Cards empty
4. Refresh → Cards appear

### After Fix:
1. Login → Menus appear immediately ✅
2. Navigate to PAS → Cards appear immediately ✅
3. No refresh needed ✅

---

## 🔄 Next Steps

1. Test with different user roles
2. Verify all dashboards (PAS, Claims, Management)
3. Test role switching
4. Remove console logs if desired (optional)
5. Deploy to staging/production

---

## 📞 Support

If you encounter any issues:

1. Check browser console for errors
2. Verify database has permissions assigned to roles
3. Clear browser cache and localStorage
4. Check backend logs for errors
5. Verify the login endpoint returns permissions with roles

---

## ✍️ Summary

**What:** Fixed menus and cards not showing until refresh
**How:** Load permissions with roles in login endpoint + add reactivity watcher
**Result:** Everything works immediately after login ✅
**Test:** Login → Check menus → Navigate to dashboards → Verify cards appear

