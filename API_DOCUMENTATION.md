# NiCare Claims Automation - API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
All endpoints require Bearer token authentication:
```
Authorization: Bearer {token}
```

## Response Format
All responses follow this format:
```json
{
  "success": true,
  "data": {},
  "message": "Success message"
}
```

## Referral Endpoints

### List Referrals
```
GET /referrals
```
**Query Parameters:**
- `status` - Filter by status (PENDING, APPROVED, REJECTED)
- `facility_id` - Filter by facility
- `page` - Pagination page number
- `per_page` - Items per page (default: 15)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "nicare_number": "NC-2025-001",
      "status": "APPROVED",
      "severity_level": "ROUTINE",
      "referring_facility_id": 1,
      "receiving_facility_id": 2,
      "enrollee_id": 1,
      "presenting_complains": "Chest pain",
      "clinical_notes": "Patient presents with acute chest pain",
      "created_at": "2025-12-04T10:00:00Z"
    }
  ],
  "pagination": {
    "total": 100,
    "per_page": 15,
    "current_page": 1
  }
}
```

### Create Referral
```
POST /referrals
```
**Request Body:**
```json
{
  "nicare_number": "NC-2025-001",
  "severity_level": "ROUTINE",
  "referring_facility_id": 1,
  "receiving_facility_id": 2,
  "enrollee_id": 1,
  "presenting_complains": "Chest pain",
  "reasons_for_referral": "Specialist consultation needed",
  "clinical_notes": "Patient presents with acute chest pain"
}
```

### Get Referral Details
```
GET /referrals/{id}
```

### Update Referral
```
PUT /referrals/{id}
```

---

## Claim Endpoints

### List Claims
```
GET /claims
```
**Query Parameters:**
- `status` - Filter by status (DRAFT, SUBMITTED, APPROVED, REJECTED)
- `admission_id` - Filter by admission
- `page` - Pagination page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "claim_number": "CLM-2025-001",
      "admission_id": 1,
      "status": "SUBMITTED",
      "claim_date": "2025-12-04",
      "total_amount_claimed": 50000,
      "total_amount_approved": 45000,
      "claim_type": "BUNDLE",
      "clinical_summary": "Treatment for chest pain",
      "created_at": "2025-12-04T10:00:00Z"
    }
  ]
}
```

### Create Claim
```
POST /claims
```
**Request Body:**
```json
{
  "admission_id": 1,
  "claim_date": "2025-12-04",
  "total_amount_claimed": 50000,
  "claim_type": "BUNDLE",
  "clinical_summary": "Treatment for chest pain",
  "claim_lines": [
    {
      "service_code": "SVC-001",
      "description": "Consultation",
      "quantity": 1,
      "unit_price": 5000,
      "amount": 5000
    }
  ]
}
```

### Get Claim Details
```
GET /claims/{id}
```

### Update Claim
```
PUT /claims/{id}
```

### Process Claim
```
POST /claims/{id}/process
```
**Request Body:**
```json
{
  "bundle_id": 1,
  "bundle_amount": 40000,
  "ffs_items": [
    {
      "service_code": "SVC-002",
      "description": "Additional service",
      "amount": 5000
    }
  ]
}
```

---

## Admission Endpoints

### List Admissions
```
GET /admissions
```
**Query Parameters:**
- `status` - Filter by status (ACTIVE, DISCHARGED, PENDING)
- `facility_id` - Filter by facility
- `page` - Pagination page number

### Create Admission
```
POST /admissions
```
**Request Body:**
```json
{
  "admission_number": "ADM-2025-001",
  "patient_name": "John Doe",
  "admission_date": "2025-12-04",
  "status": "ACTIVE",
  "facility_id": 1,
  "enrollee_id": 1
}
```

### Get Admission Details
```
GET /admissions/{id}
```

### Update Admission
```
PUT /admissions/{id}
```

### Discharge Patient
```
POST /admissions/{id}/discharge
```

---

## Bundle Endpoints

### List Bundles
```
GET /bundles
```
**Query Parameters:**
- `status` - Filter by status (ACTIVE, INACTIVE)
- `page` - Pagination page number

### Create Bundle
```
POST /bundles
```
**Request Body:**
```json
{
  "name": "Chest Pain Bundle",
  "description": "Treatment bundle for chest pain",
  "price": 40000,
  "status": "ACTIVE",
  "icd10_code": "R07.9"
}
```

### Update Bundle
```
PUT /bundles/{id}
```

### Delete Bundle
```
DELETE /bundles/{id}
```

---

## Payment Endpoints

### Get Facility Payment Summary
```
GET /payments/facility-summary
```
**Response:**
```json
{
  "data": [
    {
      "facility_name": "General Hospital",
      "total_claims": 50,
      "total_amount": 500000,
      "paid_amount": 450000,
      "pending_amount": 50000,
      "payment_status": "PENDING"
    }
  ]
}
```

### Process Payment
```
POST /payments/process
```
**Request Body:**
```json
{
  "claim_id": 1,
  "amount": 45000,
  "payment_method": "BANK_TRANSFER"
}
```

---

## Error Responses

### Validation Error (400)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "nicare_number": ["The nicare number field is required"]
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthorized"
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Resource not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "message": "Internal server error"
}
```

---

## Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created |
| 400 | Bad Request - Validation error |
| 401 | Unauthorized - Missing/invalid token |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation failed |
| 500 | Server Error - Internal error |

---

## Rate Limiting
- 100 requests per minute per user
- Rate limit headers included in response

---

## Pagination
All list endpoints support pagination:
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)

---

## Filtering & Sorting
Most endpoints support filtering and sorting:
- `filter[field]` - Filter by field value
- `sort` - Sort field (prefix with `-` for descending)

---

## Examples

### Create Referral with cURL
```bash
curl -X POST http://localhost:8000/api/referrals \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "nicare_number": "NC-2025-001",
    "severity_level": "ROUTINE",
    "referring_facility_id": 1,
    "receiving_facility_id": 2,
    "enrollee_id": 1,
    "presenting_complains": "Chest pain",
    "clinical_notes": "Patient presents with acute chest pain"
  }'
```

### Create Claim with JavaScript
```javascript
const response = await fetch('http://localhost:8000/api/claims', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    admission_id: 1,
    claim_date: '2025-12-04',
    total_amount_claimed: 50000,
    claim_type: 'BUNDLE',
    clinical_summary: 'Treatment for chest pain'
  })
});
```

---

**Last Updated**: 2025-12-04
**API Version**: 1.0

