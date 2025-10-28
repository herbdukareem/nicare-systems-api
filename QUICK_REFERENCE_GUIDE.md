# Claim Submission System - Quick Reference Guide

## 🚀 Quick Start

### Access the Claim Submission Page
```
URL: /claims/submissions
Route Name: claims-submissions
Required Auth: Yes
Required Role: desk_officer
```

### API Endpoints

#### Get Services for Referral/PA Code
```bash
GET /api/v1/claims/services/for-referral-or-pacode?referral_id=1
GET /api/v1/claims/services/for-referral-or-pacode?pa_code_id=1
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "case_id": 5,
      "tariff_item": "Consultation - General",
      "price": "7000.00",
      "service_type_id": 1
    }
  ],
  "case_id": 5,
  "message": "Services retrieved successfully"
}
```

#### Submit Claim
```bash
POST /api/v1/claims
Content-Type: application/json
Authorization: Bearer {token}

{
  "referral_id": 1,
  "pa_code_id": null,
  "services": [1, 2, 3]
}
```

---

## 📁 File Structure

```
resources/js/
├── components/
│   └── claims/
│       ├── ClaimSubmissionPage.vue      (Main page)
│       └── ClaimServiceSelector.vue     (Service selector)
├── utils/
│   └── formatters.js                    (Formatting utilities)
└── router/
    └── index.js                         (Routes)

app/Http/Controllers/
└── ClaimsController.php                 (Backend logic)

routes/
└── api.php                              (API routes)
```

---

## 🔧 Key Components

### ClaimSubmissionPage.vue
**Purpose**: Main claim submission form

**Props**: None

**Data**:
- `selectedReferralId` - Selected referral ID
- `selectedPACodeId` - Selected PA code ID
- `selectedServices` - Array of selected service IDs
- `referrals` - List of available referrals
- `paCodes` - List of available PA codes

**Methods**:
- `fetchReferrals()` - Load referrals
- `fetchPACodes()` - Load PA codes
- `submitClaim()` - Submit the claim
- `resetForm()` - Reset form fields

### ClaimServiceSelector.vue
**Purpose**: Service selection component

**Props**:
- `modelValue` - Selected service IDs (Array)
- `referralId` - Referral ID (Number/String)
- `paCodeId` - PA code ID (Number/String)
- `disabled` - Disable component (Boolean)

**Emits**:
- `update:modelValue` - When services change
- `services-loaded` - When services are loaded

**Methods**:
- `fetchServices()` - Load services for referral/PA code
- `getServiceName(id)` - Get service name by ID
- `getServicePrice(id)` - Get service price by ID

---

## 📊 Data Flow

```
User selects Referral
    ↓
ClaimSubmissionPage.onReferralSelected()
    ↓
ClaimServiceSelector watches referralId
    ↓
ClaimServiceSelector.fetchServices()
    ↓
API: GET /api/v1/claims/services/for-referral-or-pacode?referral_id=X
    ↓
Backend: Get case_id from referral
    ↓
Backend: Query TariffItems where case_id = X
    ↓
Return services to frontend
    ↓
Display services in dropdown
    ↓
User selects services
    ↓
ClaimServiceSelector emits update:modelValue
    ↓
ClaimSubmissionPage updates selectedServices
    ↓
User clicks Submit
    ↓
API: POST /api/v1/claims with services
    ↓
Backend: Validate services
    ↓
Backend: Create claim or return error
```

---

## 🎯 Usage Examples

### Import Formatters
```javascript
import { formatPrice, formatDate } from '@/js/utils/formatters'

// Format price
const price = formatPrice(7000) // ₦7,000.00

// Format date
const date = formatDate('2024-01-15', 'medium') // Jan 15, 2024
```

### Use ClaimServiceSelector
```vue
<template>
  <ClaimServiceSelector
    v-model="selectedServices"
    :referral-id="selectedReferralId"
    :pa-code-id="selectedPACodeId"
    :disabled="!selectedReferralId && !selectedPACodeId"
    @services-loaded="onServicesLoaded"
  />
</template>

<script setup>
import ClaimServiceSelector from '@/js/components/claims/ClaimServiceSelector.vue'

const selectedServices = ref([])
const selectedReferralId = ref(null)
const selectedPACodeId = ref(null)

const onServicesLoaded = (services) => {
  console.log('Services loaded:', services)
}
</script>
```

---

## 🔍 Debugging Tips

### Check Services Loading
```javascript
// In browser console
fetch('/api/v1/claims/services/for-referral-or-pacode?referral_id=1', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
  }
}).then(r => r.json()).then(d => console.log(d))
```

### Check Form State
```javascript
// In ClaimSubmissionPage component
console.log('Selected Referral:', selectedReferralId.value)
console.log('Selected PA Code:', selectedPACodeId.value)
console.log('Selected Services:', selectedServices.value)
```

### Check Validation
```javascript
// In ClaimServiceSelector component
console.log('Available Services:', availableServices.value)
console.log('Selected Service IDs:', selectedServiceIds.value)
console.log('Total Amount:', totalAmount.value)
```

---

## ⚠️ Common Issues

### Issue: No services showing
**Solution**: 
- Check if referral/PA code has a case_id
- Verify tariff items exist for that case
- Check API response in browser console

### Issue: Services not loading
**Solution**:
- Check auth token in localStorage
- Verify API endpoint is correct
- Check network tab for errors

### Issue: Claim submission fails
**Solution**:
- Verify all services are defined for the case
- Check backend validation errors
- Review API response message

---

## 📝 Validation Rules

### Frontend
- At least one referral or PA code must be selected
- At least one service must be selected
- Form must be valid before submission

### Backend
- Referral/PA code must exist
- All services must be defined for the case
- Services must have status = true
- Case ID must be retrievable

---

## 🧪 Testing Checklist

- [ ] Load claim submission page
- [ ] Select a referral
- [ ] Verify services load
- [ ] Select multiple services
- [ ] Verify total amount calculates
- [ ] Submit claim
- [ ] Verify success message
- [ ] Test with PA code instead
- [ ] Test error scenarios
- [ ] Test network errors

---

## 📞 Support

For issues or questions:
1. Check the implementation guide: `CLAIM_SUBMISSION_IMPLEMENTATION.md`
2. Review the final summary: `CLAIM_SUBMISSION_FINAL_SUMMARY.md`
3. Check the implementation checklist: `IMPLEMENTATION_CHECKLIST.md`
4. Review component code comments
5. Check browser console for errors

---

## 🎓 Learning Resources

- Vue 3 Composition API: https://vuejs.org/guide/extras/composition-api-faq.html
- Vuetify Components: https://vuetifyjs.com/
- Tailwind CSS: https://tailwindcss.com/
- Laravel API: https://laravel.com/docs/11/eloquent

---

**Last Updated**: 2024
**Version**: 1.0
**Status**: Production Ready ✅

