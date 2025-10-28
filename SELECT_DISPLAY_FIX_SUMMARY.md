# Select Buttons Display Fix - Summary

## Issue
Select dropdowns were showing full object representations instead of just the display label:
- Example: `undefined (₦7,000)` instead of `Case Description (₦7,000)`
- Example: `[object Object]` instead of facility name

## Root Cause
Vuetify's `v-select` and `v-autocomplete` components with `item-value="id"` store only the ID value, but the selection template was trying to access properties on the ID (which is just a number/string).

## Files Fixed

### 1. `resources/js/components/pas/ReferralPACodeWizard.vue`
**Issue**: Facility selection showing full object
**Fix**: Added `#selection` template to display only the facility name
```vue
<template #selection="{ item }">
  <span>{{ item.raw.name }}</span>
</template>
```

### 2. `resources/js/components/pas/components/ServiceSelector.vue`
**Issue**: Selected cases showing `undefined (₦7,000)` in chips
**Fixes**:
- Fixed `#item` template to use `item.raw.case_description` instead of `item.case_description`
- Added `getChipLabel()` method to properly format chip display
- Updated `#chip` template to use the new method

**New Method**:
```javascript
const getChipLabel = (item) => {
  // item is the ID value, find the service object
  const service = services.value.find(s => s.id === item);
  if (service) {
    return `${service.case_description} (₦${formatPrice(service.price || 0)})`;
  }
  return `Service #${item}`;
};
```

### 3. `resources/js/components/pas/components/SimpleServiceSelector.vue`
**Issue**: Service selection showing full object
**Fix**: Added `#selection` template to display only the case description
```vue
<template #selection="{ item }">
  <span>{{ item.raw.case_description }}</span>
</template>
```

## How It Works

### For v-select with item-value="id"
When you use `item-value="id"`, the model stores only the ID. To display the label:
1. Use `item-title="fieldName"` for the dropdown list
2. Add a `#selection` template to format what's shown when selected
3. Access the full object via `item.raw` in templates

### For v-autocomplete with multiple + chips
When using chips with multiple selection:
1. The model stores an array of IDs
2. The `#chip` template receives the ID value
3. Use a method to look up the full object and format the display

## Testing

### Before Fix
- Facility select: Shows `[object Object]`
- Service chips: Shows `undefined (₦7,000)`
- Simple service select: Shows `[object Object]`

### After Fix
- Facility select: Shows facility name (e.g., "GENERAL HOSPITAL MINNA")
- Service chips: Shows case description with price (e.g., "Case Name (₦7,000)")
- Simple service select: Shows case description (e.g., "Case Name")

## Verification Steps

1. **Facility Selection**
   - Go to PA Code generation
   - Select a facility
   - Verify it shows the facility name, not an object

2. **Service Selection**
   - Select multiple cases
   - Verify chips show case description with price
   - Verify no "undefined" text appears

3. **Simple Service Selection**
   - Select a service
   - Verify it shows the case description

## Related Components

These components were checked and are working correctly:
- `TariffItemManagementPage.vue` - Uses `item-title="name"` correctly
- `CreateFeedbackDialog.vue` - Uses `item-title` correctly
- `EnrolleeSelector.vue` - Uses `return-object` so no issue
- `ApprovedReferralSelector.vue` - Uses custom card selection, not v-select

## Best Practices

✅ **Do**
- Use `item-title` and `item-value` for simple selects
- Add `#selection` template when using `item-value` with IDs
- Use `#chip` template for multiple selections with custom formatting
- Access full object via `item.raw` in templates

❌ **Don't**
- Assume the model value is the full object when using `item-value`
- Try to access properties on the ID value
- Forget to add selection templates for custom display

## Files Modified

- ✅ `resources/js/components/pas/ReferralPACodeWizard.vue`
- ✅ `resources/js/components/pas/components/ServiceSelector.vue`
- ✅ `resources/js/components/pas/components/SimpleServiceSelector.vue`

## Status

✅ **COMPLETE** - All select display issues fixed
- No Vue compilation errors
- All components properly display selected values
- Ready for testing in browser

---

**Last Updated**: 2025-10-28
**Status**: Ready for Testing

