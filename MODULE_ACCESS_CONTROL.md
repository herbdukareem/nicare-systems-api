# Module Access Control Implementation

## Overview
Implemented comprehensive module-based access control to ensure users can only access modules assigned to their current role.

---

## System Architecture

### 1. **Module Definition**
The system has 5 main modules:
- **General** - Core & Admin functionality (accessible to all users)
- **PAS** - Pre-Authorization System
- **Claims** - Claims management
- **Automation** - Claims automation
- **Management** - System management

### 2. **Module Assignment**
Modules are assigned to roles in the `roles` table via the `modules` JSON column:
```json
{
  "modules": ["general", "pas", "claims"]
}
```

---

## Implementation Details

### 1. **Frontend - Module Switcher (AdminLayout.vue)**

#### Module Options Filtering
```javascript
const moduleOptions = computed(() => {
  const availableModules = authStore.availableModules || [];

  // Super Admin can access all modules
  if (authStore.hasRole('Super Admin')) {
    return allModuleOptions;
  }

  // If no modules specified, default to general only
  if (availableModules.length === 0) {
    return [{ value: 'general', label: 'Core & Admin' }];
  }

  // Filter to only show modules user has access to
  return allModuleOptions.filter(option => availableModules.includes(option.value));
});
```

#### Module Switch Validation
```javascript
const selectedModule = computed({
  get: () => uiStore.currentModule,
  set: (value) => {
    // Validate that user has access to this module
    const availableModuleValues = moduleOptions.value.map(m => m.value);
    
    if (!availableModuleValues.includes(value)) {
      success('You do not have access to this module');
      return;
    }
    
    uiStore.setModule(value);
    // ... navigate to module
  },
});
```

#### Auto-Redirect on Module Change
When a user's available modules change (e.g., after role switch):
```javascript
watch(
  () => authStore.availableModules,
  (newModules) => {
    const currentModule = uiStore.currentModule;
    const availableModuleValues = moduleOptions.value.map(m => m.value);

    // If current module is not available, switch to first available module
    if (!availableModuleValues.includes(currentModule) && availableModuleValues.length > 0) {
      uiStore.setModule(availableModuleValues[0]);
      router.push(availableModuleValues[0] === 'general' ? '/dashboard' : `/${availableModuleValues[0]}`);
    }
  },
  { immediate: true }
);
```

---

### 2. **Router Guard (router/index.js)**

#### Module-to-Route Mapping
```javascript
const getModuleForRoute = (path) => {
  if (path.startsWith('/claims/automation')) return 'automation';
  if (path.startsWith('/claims')) return 'claims';
  if (path.startsWith('/management')) return 'management';
  if (
    path.startsWith('/pas') ||
    path.startsWith('/tariff-items') ||
    path.startsWith('/case-categories') ||
    // ... other PAS routes
  ) {
    return 'pas';
  }
  return 'general';
};
```

#### Module Access Validation
```javascript
router.beforeEach(async (to, _from, next) => {
  // ... authentication check
  
  // Check module access first
  const requiredModule = getModuleForRoute(to.path);
  const availableModules = authStore.availableModules || [];
  
  // Super Admin can access all modules
  const isSuperAdmin = authStore.hasRole('Super Admin');
  
  // Check if user has access to the required module
  if (!isSuperAdmin && requiredModule !== 'general' && availableModules.length > 0) {
    if (!availableModules.includes(requiredModule)) {
      // Redirect to appropriate dashboard
      next({ path: '/do-dashboard', replace: true });
      return;
    }
  }
  
  // ... continue with permission checks
});
```

---

### 3. **usePermissions Composable**

Added module access helper functions:

```javascript
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
```

---

## Usage Examples

### In Vue Components
```javascript
import { usePermissions } from '@/composables/usePermissions';

const { 
  hasModuleAccess, 
  canAccessPASModule,
  canAccessClaimsModule 
} = usePermissions();

// Check specific module
if (hasModuleAccess('pas')) {
  // Show PAS-related content
}

// Use computed property
if (canAccessPASModule.value) {
  // Show PAS menu item
}
```

### In Templates
```vue
<v-btn 
  v-if="canAccessPASModule"
  @click="navigateToPAS"
>
  Go to PAS
</v-btn>
```

---

## Security Layers

The system implements **3 layers of security**:

1. **UI Layer** - Module switcher only shows accessible modules
2. **Router Layer** - Navigation guard blocks unauthorized module access
3. **Permission Layer** - Individual routes check specific permissions

This defense-in-depth approach ensures users cannot bypass restrictions.

---

## Testing Checklist

- [ ] User with only 'general' module cannot access PAS routes
- [ ] User with only 'pas' module cannot access Claims routes
- [ ] Module switcher only shows assigned modules
- [ ] Attempting to manually navigate to unauthorized module redirects to dashboard
- [ ] Super Admin can access all modules
- [ ] Role switching updates available modules correctly
- [ ] Auto-redirect works when current module becomes unavailable
- [ ] Menu items filter based on module access

---

## Database Setup

### Assigning Modules to Roles

```sql
-- Example: Assign PAS and Claims modules to a role
UPDATE roles 
SET modules = '["general", "pas", "claims"]'
WHERE name = 'claims_officer';

-- Example: Facility users only get PAS module
UPDATE roles 
SET modules = '["general", "pas"]'
WHERE name IN ('facility_admin', 'facility_user', 'desk_officer');
```

---

## Next Steps

1. ✅ Implement module filtering in navigation
2. ✅ Add router guard for module validation
3. ✅ Create module access helpers in usePermissions
4. ⏳ Add module access UI in role management
5. ⏳ Create admin interface to assign modules to roles
6. ⏳ Add audit logging for module access attempts

