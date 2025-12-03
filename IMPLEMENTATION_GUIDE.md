# Implementation Guide - Step by Step

## Phase 1: Core Backend Services (Week 1-2)

### Step 1: Create AdmissionService
**File**: `app/Services/ClaimsAutomation/AdmissionService.php`

Key Methods:
- `createAdmission(array $data)` - Create admission with bundle auto-matching
- `dischargePatient(Admission $admission, array $data)` - Discharge workflow
- `canAdmit(int $referralId)` - Check admission eligibility
- `getActiveAdmission(int $enrolleeId)` - Get current admission

Validations:
- Referral must be approved
- UTN must be validated
- Only one active admission per enrollee
- Auto-match bundle from ICD-10 code

### Step 2: Create BundleClassificationService
**File**: `app/Services/ClaimsAutomation/BundleClassificationService.php`

Key Methods:
- `addBundleTreatment(Claim $claim, array $data)` - Add bundle line
- `addFFSTreatment(Claim $claim, int $paCodeId, array $data)` - Add FFS line
- `classifyTreatments(Claim $claim)` - Auto-classify lines
- `validateBundlePolicy(Claim $claim)` - Enforce one-bundle rule

Validations:
- FFS requires approved PA code
- Bundle doesn't require PA code
- Only one bundle per claim
- FFS_TOP_UP PA required for complications

### Step 3: Create Database Migrations
**Files**: `database/migrations/`

Create tables:
- claim_alerts
- claim_treatments
- payment_advices
- audit_logs
- claim_status_history

Add columns to existing tables:
- claims: admission_id, bundle_amount, ffs_amount
- referrals: utn_validated, utn_validated_at, utn_validated_by
- pa_codes: approval_date, approved_by, rejection_reason

### Step 4: Enhance Controllers
**File**: `app/Http/Controllers/Api/V1/ClaimsAutomationController.php`

Implement:
- createAdmission() - Use AdmissionService
- dischargePatient() - Use AdmissionService
- getAdmissionHistory() - Query with filters
- processClaim() - Use BundleClassificationService
- validateClaim() - Use ClaimValidationService

### Step 5: Create ClaimReviewController
**File**: `app/Http/Controllers/Api/V1/ClaimReviewController.php`

Implement:
- approveClaim() - Update status, create payment advice
- rejectClaim() - Update status, notify facility
- requestMoreInfo() - Create info request
- getClaimAlerts() - Retrieve validation alerts

## Phase 2: Frontend Implementation (Week 3-4)

### Step 1: Create Admission Pages
- AdmissionCreationPage.vue - Form to create admission
- AdmissionListPage.vue - List all admissions
- DischargePatientPage.vue - Discharge workflow

### Step 2: Create Claim Pages
- ClaimDetailPage.vue - Full claim view
- ClaimApprovalPage.vue - Approve/reject interface
- PaymentTrackingPage.vue - Payment status

### Step 3: Create Reusable Components
- BundleSelector.vue - Select bundle
- PACodeValidator.vue - Validate PA codes
- ClaimLineEditor.vue - Edit line items
- AlertViewer.vue - Display alerts

### Step 4: Update Router
Add routes for new pages in `resources/js/router/index.js`

### Step 5: Update API Integration
Add methods in `resources/js/utils/api.js` for new endpoints

## Phase 3: Testing (Week 5)

### Unit Tests
- Test each service method
- Test validation logic
- Test calculations

### Feature Tests
- Test complete workflows
- Test API endpoints
- Test database transactions

### Integration Tests
- Test end-to-end flows
- Test error handling
- Test edge cases

## Phase 4: Deployment (Week 6)

### Pre-deployment
- [ ] Run all tests
- [ ] Code review
- [ ] Security audit
- [ ] Performance testing

### Deployment Steps
1. Backup production database
2. Run migrations
3. Deploy backend code
4. Deploy frontend code
5. Run smoke tests
6. Monitor logs

### Post-deployment
- [ ] Verify all endpoints
- [ ] Check database integrity
- [ ] Monitor performance
- [ ] Gather user feedback

## Key Implementation Notes

1. **Error Handling**: Use try-catch with proper logging
2. **Transactions**: Use DB::transaction() for multi-step operations
3. **Validation**: Validate at both backend and frontend
4. **Audit Trail**: Log all important actions
5. **Performance**: Use eager loading, pagination, caching
6. **Security**: Validate user permissions, sanitize inputs
7. **Documentation**: Add PHPDoc comments to all methods

