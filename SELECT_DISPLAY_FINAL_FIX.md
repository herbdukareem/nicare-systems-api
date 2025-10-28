# Select Display Issues - FINAL FIX

## Problem Summary

Select dropdowns were displaying JSON objects or undefined values instead of proper labels:
- ❌ Facility select: `[object Object]`
- ❌ Service chips: `undefined (₦7,000)`
- ❌ Service select: `[object Object]`

## Root Causes

1. **Missing `requestType` prop**: ServiceSelector requires this prop but wasn't receiving it
2. **Incorrect `item-title` function**: Using a function that tried to access properties on raw objects
3. **Missing `#selection` templates**: Single selects needed custom display templates
4. **Missing `no-filter` attribute**: Autocomplete was filtering incorrectly

## All Fixes Applied

### 1. ServiceSelector.vue - CRITICAL FIX
**Problem**: `item-title` was a function trying to format items, causing JSON display

**Before**:
```vue
<v-autocomplete
  :item-title="item => `${item.service_description || item.case_description} – ₦${formatPrice(item.price || 0)}`"
/>
```

**After**:
```vue
<v-autocomplete
  item-title="case_description"
  no-filter
>
  <template #item="{ props, item }">
    <v-list-item v-bind="props">
      <v-list-item-title>{{ item.raw.case_description }}</v-list-item-title>
      <v-list-item-subtitle>
        {{ item.raw.nicare_code }} - ₦{{ formatPrice(item.raw.price || 0) }}
      </v-list-item-subtitle>
    </v-list-item>
  </template>

  <template #chip="{ props, item }">
    <v-chip v-bind="props" closable :text="getChipLabel(item)" />
  </template>

  <template #no-data>
    <v-list-item>
      <v-list-item-title class="tw-text-gray-500">
        No cases available for this facility
      </v-list-item-title>
    </v-list-item>
  </template>
</v-autocomplete>
```

**Additional Changes**:
- Added `getChipLabel()` method to format chip display
- Added `no-filter` attribute
- Added `#no-data` template
- Removed unused `drugAPI` import

### 2. PACodeGenerationWizard.vue
**Problem**: Missing `requestType` prop

**Fix**:
```vue
<ServiceSelector
  v-model="selectedServices"
  :facility="selectedFacility"
  request-type="pa_code"
  :optional="true"
/>
```

### 3. ReferralCreationWizard.vue
**Problem**: Missing `requestType` prop

**Fix**:
```vue
<ServiceSelector 
  v-model="selectedServices"
  :facility="selectedFacility"
  request-type="referral"
  :enrollee="selectedEnrollee"
/>
```

### 4. ReferralPACodeWizard.vue
**Problem**: Facility select showing `[object Object]`

**Fix**: Added `#selection` template
```vue
<v-select
  v-model="selectedFacilityId"
  :items="facilities"
  item-title="name"
  item-value="id"
  label="Receiving Facility *"
>
  <template #selection="{ item }">
    <span>{{ item.raw.name }}</span>
  </template>
</v-select>
```

### 5. SimpleServiceSelector.vue
**Problem**: Service select showing `[object Object]`

**Fix**: Added `#selection` template
```vue
<v-select
  v-model="selectedService"
  :items="availableServices"
  item-title="case_description"
  item-value="id"
  label="New Referral Case *"
>
  <template #selection="{ item }">
    <span>{{ item.raw.case_description }}</span>
  </template>
</v-select>
```

## Expected Results

✅ **Facility Select**: Shows facility name (e.g., "GENERAL HOSPITAL MINNA")
✅ **Service Chips**: Shows case description with price (e.g., "Case Name (₦7,000)")
✅ **Service Select**: Shows case description (e.g., "Case Name")
✅ **No JSON objects** displayed anywhere
✅ **No undefined values** in chips

## Files Modified

1. ✅ `resources/js/components/pas/components/ServiceSelector.vue`
2. ✅ `resources/js/components/pas/PACodeGenerationWizard.vue`
3. ✅ `resources/js/components/pas/ReferralCreationWizard.vue`
4. ✅ `resources/js/components/pas/ReferralPACodeWizard.vue`
5. ✅ `resources/js/components/pas/components/SimpleServiceSelector.vue`

## Testing Checklist

- [ ] Facility selection shows facility name
- [ ] Service chips show case description with price
- [ ] Simple service select shows case description
- [ ] No `[object Object]` text appears
- [ ] No `undefined` text appears
- [ ] Form submission works correctly
- [ ] PA Code generation completes successfully

## Status

✅ **COMPLETE** - All issues fixed and ready for testing

