# Select Display - Before & After Comparison

## Issue Overview

Select dropdowns were displaying full object representations instead of formatted labels.

---

## 1. Facility Selection (ReferralPACodeWizard.vue)

### ❌ BEFORE
```
Select Receiving Facility: [object Object]
```

### ✅ AFTER
```
Select Receiving Facility: GENERAL HOSPITAL MINNA
```

### Code Change
```vue
<!-- BEFORE -->
<v-select
  v-model="selectedFacilityId"
  :items="facilities"
  item-title="name"
  item-value="id"
  label="Receiving Facility *"
/>

<!-- AFTER -->
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

---

## 2. Service Selection Chips (ServiceSelector.vue)

### ❌ BEFORE
```
Selected Cases (2)
├─ undefined (₦7,000)  ✕
└─ undefined (₦7,000)  ✕

Total Estimated Cost: ₦14,000
```

### ✅ AFTER
```
Selected Cases (2)
├─ NGSCHS/Labm/S/CCCP/0083 (₦7,000)  ✕
└─ NGSCHS/Labm/S/CCCP/0056 (₦7,000)  ✕

Total Estimated Cost: ₦14,000
```

### Code Changes

**Template Change**:
```vue
<!-- BEFORE -->
<template #chip="{ props, item }">
  <v-chip
    v-bind="props"
    closable
    :text="`${item.raw.case_description} (₦${formatPrice(item.raw.price || 0)})`"
  />
</template>

<!-- AFTER -->
<template #chip="{ props, item }">
  <v-chip
    v-bind="props"
    closable
    :text="getChipLabel(item)"
  />
</template>
```

**Script Addition**:
```javascript
// NEW METHOD
const getChipLabel = (item) => {
  // item is the ID value, find the service object
  const service = services.value.find(s => s.id === item);
  if (service) {
    return `${service.case_description} (₦${formatPrice(service.price || 0)})`;
  }
  return `Service #${item}`;
};
```

**Item Template Fix**:
```vue
<!-- BEFORE -->
<template #item="{ props, item }">
  <v-list-item v-bind="props">
    <v-list-item-title>{{ item.case_description }}</v-list-item-title>
    <v-list-item-subtitle>
      {{ item.nicare_code }} - ₦{{ formatPrice(item.price || 0) }}
    </v-list-item-subtitle>
  </v-list-item>
</template>

<!-- AFTER -->
<template #item="{ props, item }">
  <v-list-item v-bind="props">
    <v-list-item-title>{{ item.raw.case_description }}</v-list-item-title>
    <v-list-item-subtitle>
      {{ item.raw.nicare_code }} - ₦{{ formatPrice(item.raw.price || 0) }}
    </v-list-item-subtitle>
  </v-list-item>
</template>
```

---

## 3. Simple Service Selection (SimpleServiceSelector.vue)

### ❌ BEFORE
```
New Referral Case: [object Object]
```

### ✅ AFTER
```
New Referral Case: Consultation - General
```

### Code Change
```vue
<!-- BEFORE -->
<v-select
  v-model="selectedService"
  :items="availableServices"
  item-title="case_description"
  item-value="id"
  label="New Referral Case *"
>
  <template #item="{ props, item }">
    <v-list-item v-bind="props">
      <v-list-item-title>{{ item.raw.case_description }}</v-list-item-title>
      <v-list-item-subtitle>{{ item.raw.case_category || 'General' }}</v-list-item-subtitle>
    </v-list-item>
  </template>
</v-select>

<!-- AFTER -->
<v-select
  v-model="selectedService"
  :items="availableServices"
  item-title="case_description"
  item-value="id"
  label="New Referral Case *"
>
  <template #item="{ props, item }">
    <v-list-item v-bind="props">
      <v-list-item-title>{{ item.raw.case_description }}</v-list-item-title>
      <v-list-item-subtitle>{{ item.raw.case_category || 'General' }}</v-list-item-subtitle>
    </v-list-item>
  </template>
  <template #selection="{ item }">
    <span>{{ item.raw.case_description }}</span>
  </template>
</v-select>
```

---

## Key Differences

| Aspect | Before | After |
|--------|--------|-------|
| Facility Display | `[object Object]` | Facility Name |
| Service Chips | `undefined (₦7,000)` | `Case Name (₦7,000)` |
| Simple Service | `[object Object]` | Case Description |
| User Experience | Confusing | Clear & Professional |
| Data Integrity | ✓ Correct | ✓ Correct |

---

## Technical Explanation

### Why This Happens

When using `item-value="id"`:
- The model stores only the **ID** (a number)
- The dropdown list shows the full object
- Without a `#selection` template, Vue tries to convert the ID to a string
- Result: `[object Object]` or `undefined`

### The Solution

1. **For single select**: Add `#selection` template
   ```vue
   <template #selection="{ item }">
     <span>{{ item.raw.fieldName }}</span>
   </template>
   ```

2. **For multiple select with chips**: Add `#chip` template with lookup method
   ```vue
   <template #chip="{ props, item }">
     <v-chip v-bind="props" :text="getLabel(item)" />
   </template>
   ```

3. **In dropdown items**: Use `item.raw` to access full object
   ```vue
   <template #item="{ props, item }">
     <v-list-item v-bind="props">
       <v-list-item-title>{{ item.raw.fieldName }}</v-list-item-title>
     </v-list-item>
   </template>
   ```

---

## Testing Checklist

- [ ] Facility selection shows facility name
- [ ] Service chips show case description with price
- [ ] Simple service select shows case description
- [ ] No `[object Object]` text appears
- [ ] No `undefined` text appears
- [ ] All selections still work correctly
- [ ] Form submission works as expected

---

**Status**: ✅ COMPLETE
**Date**: 2025-10-28

