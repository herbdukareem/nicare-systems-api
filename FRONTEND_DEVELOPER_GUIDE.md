# Frontend Developer Guide - NiCare Claims Automation

## Quick Start

### Using the Claims Store
```javascript
import { useClaimsStore } from '@/stores/claimsStore';

const claimsStore = useClaimsStore();

// Access state
console.log(claimsStore.claims);
console.log(claimsStore.approvedClaims);

// Update state
claimsStore.addClaim(claimData);
claimsStore.updateClaim(id, updatedData);
claimsStore.setFilters({ status: 'APPROVED' });
```

### Using the Claims API Composable
```javascript
import { useClaimsAPI } from '@/composables/useClaimsAPI';

const { 
  fetchClaims, 
  createClaim, 
  getClaim, 
  updateClaim,
  loading, 
  error 
} = useClaimsAPI();

// Fetch claims
await fetchClaims({ status: 'SUBMITTED' });

// Create claim
await createClaim(claimData);

// Update claim
await updateClaim(claimId, updatedData);
```

### Using Toast Notifications
```javascript
import { useToast } from '@/composables/useToast';

const { success, error, warning, info } = useToast();

success('Claim submitted successfully');
error('Failed to submit claim');
warning('Please review the validation alerts');
info('Processing claim...');
```

## Component Structure

### Page Components
- Located in `resources/js/components/claims/`
- Handle routing and page-level logic
- Use composables for API calls
- Use store for state management

### Automation Components
- Located in `resources/js/components/claims/automation/`
- Specialized components for bundle/FFS processing
- Multi-step workflows with validation

## Common Patterns

### Form Validation
```vue
<v-form ref="form" @submit.prevent="submit">
  <v-text-field
    v-model="formData.field"
    label="Field Label"
    outlined
    required
    :rules="[v => !!v || 'Field is required']"
  />
  <v-btn @click="submit" :loading="loading">Submit</v-btn>
</v-form>
```

### Data Table with Actions
```vue
<v-data-table
  :headers="headers"
  :items="items"
  :loading="loading"
>
  <template v-slot:item.actions="{ item }">
    <v-btn icon small @click="edit(item)">
      <v-icon>mdi-pencil</v-icon>
    </v-btn>
  </template>
</v-data-table>
```

### Dialog Forms
```vue
<v-dialog v-model="showDialog" max-width="600px">
  <v-card>
    <v-card-title>Dialog Title</v-card-title>
    <v-card-text>
      <!-- Form content -->
    </v-card-text>
    <v-card-actions>
      <v-spacer></v-spacer>
      <v-btn @click="showDialog = false">Cancel</v-btn>
      <v-btn @click="save">Save</v-btn>
    </v-card-actions>
  </v-card>
</v-dialog>
```

## API Endpoints Reference

### Referrals
- `GET /api/referrals` - List referrals
- `POST /api/referrals` - Create referral
- `GET /api/referrals/:id` - Get referral details

### Claims
- `GET /api/claims` - List claims
- `POST /api/claims` - Create claim
- `GET /api/claims/:id` - Get claim details
- `PUT /api/claims/:id` - Update claim
- `POST /api/claims/:id/process` - Process claim

### Admissions
- `GET /api/admissions` - List admissions
- `POST /api/admissions` - Create admission
- `GET /api/admissions/:id` - Get admission details
- `PUT /api/admissions/:id` - Update admission

### Bundles
- `GET /api/bundles` - List bundles
- `POST /api/bundles` - Create bundle
- `PUT /api/bundles/:id` - Update bundle
- `DELETE /api/bundles/:id` - Delete bundle

### Payments
- `GET /api/payments/facility-summary` - Facility payment summary
- `POST /api/payments/process` - Process payment

## Styling Guidelines

### Color Scheme
- Primary: Blue (#1976D2)
- Success: Green (#4CAF50)
- Warning: Orange (#FF9800)
- Error: Red (#F44336)
- Info: Light Blue (#2196F3)

### Status Colors
- DRAFT: Blue
- SUBMITTED: Orange
- APPROVED: Green
- REJECTED: Red
- ACTIVE: Green
- DISCHARGED: Gray
- PENDING: Orange

## Testing Components

### Unit Test Example
```javascript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ClaimSubmissionPage from '@/components/claims/ClaimSubmissionPage.vue';

describe('ClaimSubmissionPage', () => {
  it('renders form fields', () => {
    const wrapper = mount(ClaimSubmissionPage);
    expect(wrapper.find('input[type="date"]').exists()).toBe(true);
  });
});
```

## Debugging Tips

1. **Check Store State**: Use Vue DevTools to inspect claimsStore
2. **API Calls**: Check Network tab in browser DevTools
3. **Component Props**: Use Vue DevTools to inspect component props
4. **Console Logs**: Use `console.log()` in composables and components
5. **Error Handling**: Check error messages in toast notifications

## Performance Optimization

1. Use `computed` for derived state
2. Lazy load components with `() => import()`
3. Use `v-show` for frequently toggled elements
4. Implement pagination for large data tables
5. Cache API responses when appropriate

## Common Issues & Solutions

### Issue: Import paths not working
**Solution**: Ensure paths use `@/` alias or relative paths from component location

### Issue: Store not updating
**Solution**: Use store actions, not direct mutations. Ensure you're using the store instance correctly.

### Issue: API calls failing
**Solution**: Check API endpoint URLs, authentication headers, and CORS configuration

### Issue: Components not rendering
**Solution**: Check console for errors, verify component registration, check route configuration

---
**Last Updated**: 2025-12-04

