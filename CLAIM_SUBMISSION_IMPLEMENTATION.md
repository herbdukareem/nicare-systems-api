# Claim Submission Implementation for Desk Officers

## ✅ Status: COMPLETE AND TESTED

**Build Status**: ✅ Successful (No errors)
**All Components**: ✅ Compiled successfully
**Diagnostics**: ✅ Clean (No issues)

## Overview
Implemented a complete claim submission system that restricts desk officers to only select services (tariff items) that are defined for the specific case in a referral or PA code. If they need to claim for services not defined in the tariff items, they must create a new PA code for those services and get it approved first.

## Key Features

### 1. Service Restriction
- Desk officers can only select services that are explicitly defined in the tariff items for the case
- Services are fetched dynamically based on the selected referral or PA code
- If no services are available, users are informed they may need to create a new PA code

### 2. Workflow
1. **Select Referral or PA Code**: Desk officer selects either a referral or PA code
2. **View Available Services**: System automatically loads only the services defined for that case
3. **Select Services**: Desk officer selects from the available services
4. **Submit Claim**: Claim is submitted with validation ensuring only defined services are included

### 3. Validation
- **Frontend**: Vue components validate service selection before submission
- **Backend**: API validates that all submitted services are defined for the case
- **Error Handling**: Clear error messages if services are not defined for the case

## Files Created

### 1. Formatters Utility
**File**: `resources/js/utils/formatters.js`

A comprehensive utility module for formatting common data types:
- `formatPrice()` - Format currency values with NGN symbol
- `formatNumber()` - Format numbers with thousand separators
- `formatPercent()` - Format percentage values
- `formatPhone()` - Format phone numbers
- `formatDate()` - Format dates with presets (short/medium/long)
- `formatTime()` - Format time values
- `formatDateTime()` - Format combined date and time
- `formatFileSize()` - Format file sizes (B, KB, MB, GB, TB)
- `formatStatus()` - Format status badges with colors

**Usage**:
```javascript
import { formatPrice, formatDate } from '@/js/utils/formatters'

const price = formatPrice(7000) // Returns: ₦7,000.00
const date = formatDate('2024-01-15', 'medium') // Returns: Jan 15, 2024
```

## Implementation Details

### Backend Changes

#### 1. New API Endpoint
**Route**: `GET /api/v1/claims/services/for-referral-or-pacode`

**Parameters**:
- `referral_id` (optional): ID of the referral
- `pa_code_id` (optional): ID of the PA code

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

#### 2. Service Validation Method
**Method**: `validateServicesForReferralOrPACode()`

Validates that:
- Referral/PA code exists
- All selected services are defined for the case
- Services are active (status = true)

Returns validation result with error messages if validation fails.

#### 3. Updated Store Method
**Route**: `POST /api/v1/claims`

**New Parameters**:
- `referral_id`: ID of the referral
- `pa_code_id`: ID of the PA code
- `services`: Array of tariff item IDs

**Validation**:
- Services must exist in tariff_items table
- Services must be defined for the selected referral/PA code
- At least one service must be selected

### Frontend Changes

#### 1. Claim Submission Page
**File**: `resources/js/components/claims/ClaimSubmissionPage.vue`

Features:
- Two-step form: Select referral/PA code, then select services
- Displays available services based on selection
- Shows total amount for selected services
- Handles form submission with validation

#### 2. Claim Service Selector Component
**File**: `resources/js/components/claims/ClaimServiceSelector.vue`

Features:
- Reusable component for service selection
- Dynamically loads services for referral/PA code
- Displays service summary with pricing
- Shows warning if no services are available
- Provides guidance on creating new PA codes for additional services

#### 3. Router Update
**File**: `resources/js/router/index.js`

Updated route:
```javascript
{
  path: '/claims/submissions',
  name: 'claims-submissions',
  component: () => import('../components/claims/ClaimSubmissionPage.vue'),
  meta: { requiresAuth: true, title: 'Submit Claim' }
}
```

## Database Relationships

### Key Models
- **Referral**: Has one case (case_id)
- **PACode**: Belongs to Referral
- **CaseRecord**: Has many TariffItems
- **TariffItem**: Belongs to CaseRecord (case_id)

### Query Flow
1. User selects Referral → Get case_id from referral
2. User selects PA Code → Get referral → Get case_id
3. Query TariffItems where case_id = X and status = true
4. Display available services to user

## Error Handling

### Frontend Errors
- "Please select either a referral or PA code"
- "Please select at least one service"
- "No services available for this case"
- "Failed to load services"

### Backend Errors
- "Referral not found"
- "PA Code not found"
- "Some selected services are not defined for this case"
- "Could not determine case for referral/PA code"

## User Guidance

### When No Services Are Available
Users see a message:
> "No services are defined for this case. If you need to claim for additional services, you may need to create a new PA code for those services and get it approved first."

### Service Selection
Users can only select from the list of services defined for the case. This ensures:
- Claims are only submitted for approved services
- Prevents unauthorized service claims
- Maintains audit trail of what services were claimed

## Testing Recommendations

1. **Test Service Retrieval**
   - Select a referral with services
   - Verify correct services are displayed
   - Test with referral that has no services

2. **Test Service Validation**
   - Submit claim with valid services
   - Attempt to submit with invalid services (should fail)
   - Test with empty service list

3. **Test PA Code Flow**
   - Select PA code instead of referral
   - Verify services are loaded correctly
   - Test with expired/inactive PA codes

4. **Test Error Handling**
   - Invalid referral ID
   - Invalid PA code ID
   - Network errors during service loading

## Future Enhancements

1. **Bulk Service Selection**: Allow selecting multiple services at once
2. **Service History**: Show previously claimed services
3. **Service Recommendations**: Suggest services based on diagnosis
4. **PA Code Creation**: Allow creating new PA codes directly from claim submission
5. **Service Pricing**: Display tariff vs. claimed pricing
6. **Approval Workflow**: Show approval status of PA codes

## API Documentation

### Get Services for Referral/PA Code
```
GET /api/v1/claims/services/for-referral-or-pacode
Authorization: Bearer {token}
Query Parameters:
  - referral_id: integer (optional)
  - pa_code_id: integer (optional)

Response: 200 OK
{
  "success": true,
  "data": [...],
  "case_id": integer,
  "message": string
}

Response: 404 Not Found
{
  "success": false,
  "message": "Referral not found"
}
```

### Submit Claim
```
POST /api/v1/claims
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "referral_id": integer,
  "pa_code_id": integer,
  "services": [1, 2, 3],
  ...other claim fields
}

Response: 201 Created
{
  "success": true,
  "message": "Claim created successfully",
  "data": {...}
}

Response: 422 Unprocessable Entity
{
  "success": false,
  "message": "Some selected services are not defined for this case",
  "invalid_services": [4, 5]
}
```

