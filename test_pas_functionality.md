# PAS Functionality Test Guide

## Issues Fixed

### 1. ✅ **API Endpoint Corrections**
- **Fixed**: ReferralRequestForm now uses `pasAPI.createReferral()` instead of direct axios call
- **Fixed**: Modify referral data structure now uses `new_service_id` instead of `service_id`
- **Fixed**: Proper API imports in ReferralRequestForm component

### 2. ✅ **Data Structure Alignment**
- **Backend expects**: `new_service_id` for modify referral
- **Frontend now sends**: `new_service_id` (fixed in CreateReferralPAPage and RequestReview)
- **PA Code generation**: Data structure matches backend validation requirements

### 3. ✅ **Database Connection**
- **Fixed**: Database connection issues resolved
- **Fixed**: Migrations applied successfully
- **Status**: Database `ngscha_systems` created and configured

## Testing Steps

### Test 1: Referral Request Form
1. **Navigate to**: PAS Management → Create New Referral
2. **Fill out form** with all required fields:
   - Referring Provider Details
   - Contact Person Details
   - Receiving Provider/Facility
   - Patient/Enrollee Details
   - Clinical Justification
   - PA Code Severity Level
   - Referring Personnel Details
   - Upload required documents
3. **Submit form** and verify:
   - ✅ Form validation works
   - ✅ File uploads are processed
   - ✅ Success message appears
   - ✅ Referral is created in database

### Test 2: PA Code Generation
1. **Prerequisites**: Have an approved referral in the system
2. **Navigate to**: PAS Management → Generate PA Code
3. **Select workflow**: PA Code Request
4. **Complete steps**:
   - Step 1: Select Facility
   - Step 2: Select Enrollee
   - Step 3: Select Request Type (PA Code)
   - Step 4: Select Services (optional)
   - Step 5: Select Approved Referral
   - Step 6: Review and Submit
5. **Verify**:
   - ✅ Approved referrals load correctly
   - ✅ Referral selection works
   - ✅ PA Code is generated successfully
   - ✅ Correct API endpoint is called

### Test 3: Modify Referral
1. **Prerequisites**: Have a pending referral in the system
2. **Navigate to**: PAS Management → Modify Referral
3. **Select workflow**: Modify Referral
4. **Complete steps**:
   - Step 1: Select Facility
   - Step 2: Select Pending Referral
   - Step 3: Select New Service
   - Step 4: Provide Modification Reason
   - Step 5: Review and Submit
5. **Verify**:
   - ✅ Pending referrals load correctly
   - ✅ Service selection works
   - ✅ Modification is processed successfully
   - ✅ Referral is updated with new service

## API Endpoints Verified

### Referral APIs
- ✅ `GET /v1/pas/referrals` - List referrals
- ✅ `POST /v1/pas/workflow/referral` - Create referral (workflow)
- ✅ `POST /v1/pas/referrals` - Create referral (direct)
- ✅ `PUT /v1/pas/referrals/{id}/modify` - Modify referral

### PA Code APIs
- ✅ `POST /v1/pas/referrals/{id}/generate-pa-code` - Generate PA code from referral
- ✅ `GET /v1/pas/pa-codes` - List PA codes

## Common Issues Resolved

### 1. **FormData vs Regular Objects**
- **Issue**: `submitData.referral_id` was undefined when using FormData
- **Solution**: Extract referral_id before FormData conversion

### 2. **Validation Errors**
- **Issue**: Backend validation requirements not matching frontend data
- **Solution**: Aligned data structures and field names

### 3. **File Upload Issues**
- **Issue**: File uploads not working with API utility
- **Solution**: Proper Content-Type headers and FormData handling

## Browser Console Debug

When testing, check browser console for:
```javascript
// PA Code Generation
PA Code generation data: { selectedApprovedReferral: {...}, referralId: 5, services: [...] }
Submit request data: { referral_id: 5, services: [...], ... }

// Modify Referral
Modify referral data: { new_service_id: 10, modification_reason: "..." }

// Referral Creation
Referral form data: { referring_facility_name: "...", ... }
```

## Expected Success Messages
- ✅ "Referral request submitted successfully!"
- ✅ "PA code generated successfully!"
- ✅ "Referral service modified successfully!"

## Next Steps
1. Test each workflow end-to-end
2. Verify database records are created correctly
3. Test error scenarios and validation
4. Ensure file uploads work properly
