# Test Referral Creation

## Issues Fixed

### 1. Services Validation Error
**Problem**: `"The services field must be an array"`
**Solution**: Added JSON string parsing for FormData compatibility

### 2. Database Constraint Violations
**Problem**: `SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'referring_address' cannot be null`
**Solution**:
- Made nullable fields in referrals table match source data constraints
- Fixed field mapping from enrollees/facilities tables
- Added fallback values for null data

### 3. Referral Code Generation Error
**Problem**: `SQLSTATE[HY000]: General error: 1364 Field 'referral_code' doesn't have a default value`
**Solution**:
- Made referral_code nullable to allow creation before code generation
- Maintained existing workflow: create → generate code → update

## Field Mappings Fixed

### Enrollee Fields
| Referrals Table Field | Source Field | Fix Applied |
|----------------------|--------------|-------------|
| `nicare_number` | `enrollees.enrollee_id` | Use correct field name |
| `enrollee_full_name` | Computed from `first_name`, `middle_name`, `last_name` | Build full name properly |
| `gender` | `enrollees.sex` (1=Male, 2=Female) | Convert numeric to text |
| `enrollee_phone_main` | `enrollees.phone` (nullable) | Handle null values |

### Facility Fields
| Referrals Table Field | Source Field | Fix Applied |
|----------------------|--------------|-------------|
| `referring_address` | `facilities.address` (nullable) | Made nullable in referrals |
| `referring_phone` | `facilities.phone` (nullable) | Made nullable in referrals |
| `receiving_address` | `facilities.address` (nullable) | Made nullable in referrals |
| `receiving_phone` | `facilities.phone` (nullable) | Made nullable in referrals |

## Validation Rules Updated

### Required Fields
- `facility_id`
- `enrollee_id` 
- `request_type`
- `services` (array)
- `receiving_facility_id`
- `severity_level`
- `reasons_for_referral`

### Nullable Fields
- `presenting_complaints`
- `preliminary_diagnosis`
- `personnel_full_name`
- `personnel_phone`
- `contact_full_name`
- `contact_phone`
- `contact_email`

## Expected Behavior

The `/api/v1/pas/workflow/referral` endpoint should now:

1. ✅ Accept FormData with JSON-stringified services array
2. ✅ Handle nullable facility address/phone fields
3. ✅ Handle nullable enrollee phone field
4. ✅ Properly map enrollee fields (enrollee_id → nicare_number, etc.)
5. ✅ Create referral records without constraint violations
6. ✅ Generate referral codes after creation (nullable workflow)
7. ✅ Support both required and optional form fields

## Test Status
- [x] Unit tests passing (4 tests, 10 assertions)
- [x] Route registration working
- [x] Database migrations applied
- [x] Field mappings corrected
- [x] Null value handling implemented

The PAS workflow should now work correctly with real facility and enrollee data from the database.
