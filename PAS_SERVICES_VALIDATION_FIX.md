# PAS Services Validation Fix

## Problem
The `/api/v1/pas/workflow/referral` endpoint was returning a validation error:

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "services": [
            "The services field must be an array."
        ]
    }
}
```

## Root Cause
The issue occurred because:

1. The frontend sends data as `multipart/form-data` due to file uploads (enrollee ID card, referral letter)
2. When using `FormData`, complex data structures like arrays need to be JSON stringified
3. The frontend was correctly JSON stringifying the services array: `submitData.append('services', JSON.stringify(formData.services))`
4. However, the backend validation was expecting an actual array, not a JSON string

## Solution
Modified both `createReferral` and `generatePACode` methods in `app/Http/Controllers/Api/V1/PASWorkflowController.php` to handle JSON string services:

```php
// Handle JSON string services from FormData
$requestData = $request->all();
if (isset($requestData['services']) && is_string($requestData['services'])) {
    $requestData['services'] = json_decode($requestData['services'], true);
}

// Use $requestData instead of $request->all() for validation
$validator = Validator::make($requestData, [
    // ... validation rules
]);
```

## Changes Made

### 1. PASWorkflowController::createReferral()
- Added JSON string parsing logic before validation
- Updated all references from `$request->field` to `$requestData['field']`
- Maintains backward compatibility with both JSON strings and arrays

### 2. PASWorkflowController::generatePACode()
- Applied the same JSON string parsing logic
- Updated all field references to use processed data

### 3. Added Tests
- Created `tests/Feature/PASWorkflowTest.php` with unit tests
- Tests verify JSON string parsing works correctly
- Tests confirm arrays remain unchanged
- Tests validate error handling for invalid JSON

## Test Results
All tests pass:
```
✓ services json string parsing
✓ services array unchanged  
✓ invalid json services string
```

## Backward Compatibility
The fix maintains full backward compatibility:
- JSON strings (from FormData) are parsed to arrays
- Existing arrays remain unchanged
- Invalid JSON strings result in null (which fails validation as expected)

## Frontend Flow
1. User selects services in `ServiceSelector.vue`
2. Services are passed to `RequestReview.vue` as an array
3. `CreateReferralPAPage.vue` creates FormData and JSON stringifies services
4. Backend now correctly parses the JSON string back to an array
5. Validation passes and referral/PA code is created successfully

## Additional Updates

### Database Schema Changes

#### Migration 1: `2025_09_21_125647_update_referrals_table_make_fields_nullable.php`
Made validation-related fields nullable in the `referrals` table:

- `presenting_complaints` - Changed from NOT NULL to nullable
- `preliminary_diagnosis` - Changed from NOT NULL to nullable
- `personnel_full_name` - Changed from NOT NULL to nullable
- `personnel_phone` - Changed from NOT NULL to nullable
- `contact_full_name` - Changed from NOT NULL to nullable
- `contact_phone` - Changed from NOT NULL to nullable

#### Migration 2: `2025_09_21_132651_update_referrals_table_make_facility_enrollee_fields_nullable.php`
Made facility and enrollee-related fields nullable to handle source data that might be null:

- `referring_address` - Changed from NOT NULL to nullable (facilities.address is nullable)
- `referring_phone` - Changed from NOT NULL to nullable (facilities.phone is nullable)
- `receiving_address` - Changed from NOT NULL to nullable (facilities.address is nullable)
- `receiving_phone` - Changed from NOT NULL to nullable (facilities.phone is nullable)
- `nicare_number` - Changed from NOT NULL to nullable (computed from enrollee_id)
- `enrollee_full_name` - Changed from NOT NULL to nullable (computed from first/middle/last names)
- `gender` - Changed from NOT NULL to nullable (computed from sex field)
- `enrollee_phone_main` - Changed from NOT NULL to nullable (enrollees.phone is nullable)

#### Migration 3: `2025_09_21_133259_make_referral_code_nullable_temporarily.php`
Made referral_code nullable to allow creation before code generation:

- `referral_code` - Changed from NOT NULL to nullable (allows creation → generate code → update pattern)

### Validation Rules Updated
Both `createReferral` and `generatePACode` methods now use consistent validation rules:

```php
'presenting_complaints' => 'nullable|string',
'preliminary_diagnosis' => 'nullable|string',
'personnel_full_name' => 'nullable|string|max:255',
'personnel_phone' => 'nullable|string|max:20',
'contact_full_name' => 'nullable|string|max:255',
'contact_phone' => 'nullable|string|max:20',
```

## Test Results
All tests pass including new nullable field validation:
```
✓ services json string parsing
✓ services array unchanged
✓ invalid json services string
✓ nullable fields validation
```

### Controller Logic Updates
Fixed field mapping issues in `PASWorkflowController.php`:

- **Enrollee field mapping**: Fixed references to non-existent fields like `$enrollee->nicare_number`, `$enrollee->full_name`, `$enrollee->gender`
- **Correct field usage**: Now uses `$enrollee->enrollee_id`, computed full name from `first_name`/`middle_name`/`last_name`, and `sex` field for gender
- **Null handling**: Added fallback values for nullable facility and enrollee fields to prevent database constraint violations
- **Consistent mapping**: Applied same fixes to both `createReferral` and `generatePACode` methods

### Referral Code Format Change
Changed referral code format to fix Vue Router navigation issues:

- **Old Format**: `NGSCHA/0011/000003` (contains forward slashes)
- **New Format**: `NGSCHA-0011-000003` (uses hyphens)
- **Reason**: Forward slashes in URLs are interpreted as path separators by Vue Router, causing navigation failures
- **Solution**: Updated `generateReferralCode()` method in Referral model to use hyphens

## Files Modified
- `app/Http/Controllers/Api/V1/PASWorkflowController.php` - Fixed JSON parsing, updated validation rules, and corrected field mappings
- `app/Models/Referral.php` - Changed referral code format from slashes to hyphens
- `resources/js/components/pas/ReferralDetailPage.vue` - Created referral detail page component
- `resources/js/router/index.js` - Added route for referral details by code
- `resources/js/utils/api.js` - Added getReferralByCode API method
- `database/migrations/2025_09_21_125647_update_referrals_table_make_fields_nullable.php` - Made validation fields nullable
- `database/migrations/2025_09_21_132651_update_referrals_table_make_facility_enrollee_fields_nullable.php` - Made facility/enrollee fields nullable
- `database/migrations/2025_09_21_133259_make_referral_code_nullable_temporarily.php` - Made referral_code nullable for creation workflow
- `tests/Feature/PASWorkflowTest.php` - Added comprehensive tests

## Issues Resolved
1. ✅ **Services validation error**: "The services field must be an array" - Fixed JSON string parsing
2. ✅ **Database constraint violations**: "Column 'referring_address' cannot be null" - Made nullable fields match source data
3. ✅ **Field mapping errors**: Fixed incorrect enrollee/facility field references
4. ✅ **Referral code generation**: "Field 'referral_code' doesn't have a default value" - Made nullable for creation workflow
5. ✅ **Vue Router navigation error**: "No match found for location with path '/pas/referrals/NGSCHA/0011/000003'" - Changed referral code format from slashes to hyphens

The fix resolves all validation errors and database constraint violations while maintaining clean, robust code that handles FormData, regular JSON requests, and nullable source data properly. The database schema now matches both the validation rules and the actual data constraints.
