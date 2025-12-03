# Quick Start Guide - NiCare Frontend Workflows

## 🚀 Getting Started

All 5 workflows are now implemented and ready to use. Here's how to access them:

## 📋 Workflow URLs

### 1. Create Referral Request
**URL**: `http://localhost:3000/pas/create-referral`
**User**: Admin or Desk Officer
**Purpose**: Create new referral requests for patient transfers

**Steps**:
1. Select referring facility (auto-filled for desk officers)
2. Select receiving facility
3. Select enrollee
4. Enter clinical details (complaints, reasons, diagnosis)
5. Select severity level (Emergency/Urgent/Routine)
6. Enter personnel and contact information
7. Review and submit

---

### 2. Validate UTN
**URL**: `http://localhost:3000/pas/validate-utn`
**User**: Receiving Facility Staff
**Purpose**: Validate Unique Transaction Numbers for received referrals

**Steps**:
1. Search for referrals by code, patient name, or NiCare number
2. View list of approved referrals awaiting validation
3. Click "Validate" button on any referral
4. Add optional validation notes
5. Confirm validation

---

### 3. Request PA Code
**URL**: `http://localhost:3000/pas/request-pa-code`
**User**: Receiving Facility Staff
**Purpose**: Request Pre-Authorization codes for services outside bundle

**Steps**:
1. Select an approved referral
2. Select services requiring PA code
3. Set validity period (days) and maximum usage
4. Add optional comments
5. Review and generate PA code

---

### 4. Submit Claim
**URL**: `http://localhost:3000/claims/submissions`
**User**: Receiving Facility Staff
**Purpose**: Submit claims for services rendered

**Steps**:
1. Select referral or PA code
2. Select services rendered
3. Review amounts (bundle vs FFS)
4. Submit claim

---

### 5. Review Claims
**URL**: `http://localhost:3000/claims/review`
**User**: Admin
**Purpose**: Review and approve/reject submitted claims

**Steps**:
1. Filter claims by status, facility, or code
2. Click "Review" on any claim
3. View claim details and services
4. Add approval/rejection comments
5. Click Approve or Reject

---

## 🔧 Backend Requirements

Ensure these endpoints are implemented:

```
POST   /v1/pas/workflow/referral
POST   /v1/pas/referrals/{id}/validate-utn
POST   /v1/pas/workflow/pa-code
GET    /v1/pas/claims
POST   /v1/pas/claims/{id}/approve
POST   /v1/pas/claims/{id}/reject
```

---

## 🧪 Testing

### Frontend Build
```bash
npm run build
```

### Development Server
```bash
npm run dev
```

### Run Tests
```bash
npm run test
```

---

## 📝 Notes

- All forms include validation and error handling
- API responses are handled with toast notifications
- Forms auto-redirect on successful submission
- All pages use consistent styling with Vuetify + Tailwind CSS
- Components are reusable and follow Vue 3 Composition API patterns

---

## 🐛 Troubleshooting

**Issue**: "Component not found" error
- **Solution**: Ensure all referenced components exist in `resources/js/components/`

**Issue**: API 404 errors
- **Solution**: Verify backend endpoints are implemented and accessible

**Issue**: Form validation not working
- **Solution**: Check that validation rules are properly defined in the component

---

## 📚 Related Files

- Components: `resources/js/components/pas/` and `resources/js/components/claims/`
- Router: `resources/js/router/index.js`
- API: `resources/js/utils/api.js`
- Composables: `resources/js/composables/useToast.js`

---

## ✅ Checklist

- [x] Referral Creation Wizard implemented
- [x] UTN Validation Page implemented
- [x] PA Code Request Page implemented
- [x] Claims Submission Page (already existed)
- [x] Claims Review & Approval Page implemented
- [x] Routes added to router
- [x] API methods added
- [x] No syntax errors
- [ ] Backend endpoints implemented
- [ ] End-to-end testing completed

