# Frontend Quick Start Guide

## 🚀 Getting Started in 5 Minutes

### 1. Install Dependencies
```bash
npm install
```

### 2. Start Development Server
```bash
npm run dev
```

### 3. Access the Application
```
http://localhost:5173
```

## 📁 Project Structure

```
resources/js/
├── components/
│   └── claims/
│       ├── ReferralSubmissionPage.vue
│       ├── ClaimSubmissionPage.vue
│       ├── ClaimsReviewPage.vue
│       ├── ClaimsSidebar.vue
│       ├── PaymentTrackingDashboard.vue
│       └── automation/
│           ├── AdmissionManagementPage.vue
│           ├── AdmissionDetailPage.vue
│           ├── ClaimsProcessingPage.vue
│           └── BundleManagementPage.vue
├── stores/
│   └── claimsStore.js
├── composables/
│   └── useClaimsAPI.js
└── router/
    └── index.js
```

## 🔑 Key Files to Know

### State Management
**File**: `resources/js/stores/claimsStore.js`
- Manages all claims, referrals, admissions state
- Provides computed properties for filtering
- Actions for CRUD operations

### API Integration
**File**: `resources/js/composables/useClaimsAPI.js`
- Handles all API calls
- Manages loading and error states
- Integrates with claimsStore

### Main Routes
**File**: `resources/js/router/index.js`
- `/claims/referrals` - Submit referral
- `/claims/submissions` - Submit claim
- `/claims/review` - Review claims
- `/claims/automation/admissions` - Manage admissions
- `/claims/automation/process` - Process claims
- `/claims/automation/bundles` - Manage bundles

## 💡 Common Tasks

### Add a New Page Component
```vue
<template>
  <div class="my-page">
    <!-- Your content -->
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useClaimsAPI } from '@/composables/useClaimsAPI';
import { useClaimsStore } from '@/stores/claimsStore';

const claimsStore = useClaimsStore();
const { fetchClaims, loading } = useClaimsAPI();

onMounted(async () => {
  await fetchClaims();
});
</script>
```

### Use the Claims Store
```javascript
import { useClaimsStore } from '@/stores/claimsStore';

const store = useClaimsStore();

// Access state
console.log(store.claims);
console.log(store.approvedClaims);

// Update state
store.addClaim(claimData);
store.setFilters({ status: 'APPROVED' });
```

### Make API Calls
```javascript
import { useClaimsAPI } from '@/composables/useClaimsAPI';

const { fetchClaims, createClaim, loading, error } = useClaimsAPI();

// Fetch claims
await fetchClaims({ status: 'SUBMITTED' });

// Create claim
await createClaim(claimData);
```

### Show Toast Notifications
```javascript
import { useToast } from '@/composables/useToast';

const { success, error, warning, info } = useToast();

success('Operation successful');
error('Something went wrong');
warning('Please review');
info('Processing...');
```

## 🧪 Testing

### Run Tests
```bash
npm run test
```

### Run Tests in Watch Mode
```bash
npm run test:watch
```

### Generate Coverage Report
```bash
npm run test:coverage
```

## 🏗️ Build for Production

### Build
```bash
npm run build
```

### Preview Build
```bash
npm run preview
```

## 🐛 Debugging

### Vue DevTools
1. Install Vue DevTools browser extension
2. Open DevTools (F12)
3. Go to Vue tab
4. Inspect components and store

### Network Debugging
1. Open DevTools (F12)
2. Go to Network tab
3. Check API requests and responses

### Console Logging
```javascript
console.log('Debug:', variable);
console.error('Error:', error);
console.warn('Warning:', warning);
```

## 📚 Documentation

- **API Endpoints**: See `API_DOCUMENTATION.md`
- **Developer Guide**: See `FRONTEND_DEVELOPER_GUIDE.md`
- **Testing Guide**: See `TESTING_GUIDE.md`
- **Component Details**: See `PHASE_2_FRONTEND_IMPLEMENTATION.md`

## 🚨 Common Issues

### Issue: Components not rendering
**Solution**: Check console for errors, verify component registration in router

### Issue: API calls failing
**Solution**: Check API endpoint URLs, verify authentication token, check CORS

### Issue: Store not updating
**Solution**: Use store actions, not direct mutations

### Issue: Import paths not working
**Solution**: Use `@/` alias or relative paths from component location

## 🔗 Useful Links

- [Vue 3 Documentation](https://vuejs.org/)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Vuetify Documentation](https://vuetifyjs.com/)
- [Vue Router Documentation](https://router.vuejs.org/)

## 📞 Getting Help

1. Check the documentation files
2. Review similar components
3. Check browser console for errors
4. Review API responses in Network tab
5. Check Vue DevTools for state issues

## ✅ Pre-Deployment Checklist

- [ ] All components render correctly
- [ ] API calls work
- [ ] State management works
- [ ] Navigation works
- [ ] Forms validate correctly
- [ ] Error handling works
- [ ] Loading states display
- [ ] Responsive design works
- [ ] No console errors
- [ ] Build completes successfully

---

**Last Updated**: 2025-12-04
**Version**: 1.0

