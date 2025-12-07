# NiCare Claims Automation - Testing Guide

## Overview
This guide covers testing strategies for the NiCare Claims Automation system, including unit tests, integration tests, and end-to-end tests.

## Test Environment Setup

### Prerequisites
```bash
# Install dependencies
npm install
composer install

# Set up test database
cp .env.example .env.testing
php artisan migrate --env=testing
```

### Running Tests

#### Backend Tests
```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Unit/Services/ClaimProcessingServiceTest.php

# Run with coverage
php artisan test --coverage

# Run specific test method
php artisan test --filter=testCreateClaim
```

#### Frontend Tests
```bash
# Run all tests
npm run test

# Run tests in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage
```

## Test Categories

### 1. Unit Tests

#### Backend Unit Tests
**Location**: `tests/Unit/Services/`

**Test Files**:
- `AdmissionServiceTest.php` - Admission creation and management
- `BundleClassificationServiceTest.php` - Bundle classification logic
- `ClaimProcessingServiceTest.php` - Claim processing workflow
- `PaymentProcessingServiceTest.php` - Payment processing

**Example Test**:
```php
public function testCreateAdmission()
{
    $data = [
        'admission_number' => 'ADM-2025-001',
        'patient_name' => 'John Doe',
        'admission_date' => now(),
        'status' => 'ACTIVE'
    ];
    
    $admission = $this->service->createAdmission($data);
    
    $this->assertNotNull($admission->id);
    $this->assertEquals('ACTIVE', $admission->status);
}
```

#### Frontend Unit Tests
**Location**: `resources/js/components/__tests__/`

**Test Files**:
- `ReferralSubmissionPage.spec.js`
- `ClaimSubmissionPage.spec.js`
- `ClaimsReviewPage.spec.js`
- `AdmissionManagementPage.spec.js`

**Example Test**:
```javascript
describe('ReferralSubmissionPage', () => {
  it('renders form fields', () => {
    const wrapper = mount(ReferralSubmissionPage);
    expect(wrapper.find('input[name="nicare_number"]').exists()).toBe(true);
  });

  it('submits form with valid data', async () => {
    const wrapper = mount(ReferralSubmissionPage);
    await wrapper.vm.submitForm();
    expect(wrapper.vm.submitted).toBe(true);
  });
});
```

### 2. Integration Tests

#### Backend Integration Tests
**Location**: `tests/Feature/`

**Test Scenarios**:

1. **Referral Workflow**
```php
public function testCompleteReferralWorkflow()
{
    // Create referral
    $referral = Referral::factory()->create();
    
    // Approve referral
    $this->patch("/api/referrals/{$referral->id}/approve");
    
    // Validate UTN
    $this->post("/api/referrals/{$referral->id}/validate-utn");
    
    // Assert referral is approved and UTN validated
    $this->assertTrue($referral->fresh()->is_approved);
}
```

2. **Claim Submission Workflow**
```php
public function testClaimSubmissionWorkflow()
{
    $admission = Admission::factory()->create();
    
    // Submit claim
    $response = $this->post('/api/claims', [
        'admission_id' => $admission->id,
        'claim_date' => now(),
        'total_amount_claimed' => 50000,
        'claim_type' => 'BUNDLE'
    ]);
    
    $this->assertDatabaseHas('claims', [
        'status' => 'SUBMITTED'
    ]);
}
```

3. **Claims Approval Workflow**
```php
public function testClaimsApprovalWorkflow()
{
    $claim = Claim::factory()->create(['status' => 'SUBMITTED']);
    
    // Approve claim
    $response = $this->patch("/api/claims/{$claim->id}/approve", [
        'approved_amount' => 45000,
        'comments' => 'Approved'
    ]);
    
    $this->assertEquals('APPROVED', $claim->fresh()->status);
}
```

#### Frontend Integration Tests
**Location**: `resources/js/components/__tests__/integration/`

**Test Scenarios**:

1. **Referral Submission Flow**
```javascript
describe('Referral Submission Flow', () => {
  it('completes full referral submission', async () => {
    const wrapper = mount(ReferralSubmissionPage);
    
    // Fill form
    await wrapper.vm.fillForm({
      nicare_number: 'NC-2025-001',
      severity_level: 'ROUTINE'
    });
    
    // Submit
    await wrapper.vm.submitForm();
    
    // Verify redirect
    expect(router.currentRoute.value.path).toBe('/pas/referrals');
  });
});
```

### 3. End-to-End Tests

#### Using Cypress
**Location**: `cypress/e2e/`

**Test Files**:
- `referral-workflow.cy.js`
- `claim-submission.cy.js`
- `claims-approval.cy.js`

**Example Test**:
```javascript
describe('Complete Referral Workflow', () => {
  it('submits referral and validates UTN', () => {
    cy.visit('/claims/referrals');
    
    // Fill form
    cy.get('input[name="nicare_number"]').type('NC-2025-001');
    cy.get('select[name="severity_level"]').select('ROUTINE');
    
    // Submit
    cy.get('button[type="submit"]').click();
    
    // Verify success
    cy.contains('Referral submitted successfully').should('be.visible');
    cy.url().should('include', '/pas/referrals');
  });
});
```

## Test Data Management

### Factories
**Location**: `database/factories/`

**Available Factories**:
- `ReferralFactory` - Create test referrals
- `ClaimFactory` - Create test claims
- `AdmissionFactory` - Create test admissions
- `BundleFactory` - Create test bundles

**Usage**:
```php
// Create single record
$referral = Referral::factory()->create();

// Create multiple records
$claims = Claim::factory()->count(10)->create();

// Create with specific attributes
$admission = Admission::factory()->create([
    'status' => 'ACTIVE',
    'patient_name' => 'John Doe'
]);
```

### Seeders
**Location**: `database/seeders/`

**Available Seeders**:
- `ReferralSeeder` - Seed test referrals
- `ClaimSeeder` - Seed test claims
- `BundleSeeder` - Seed test bundles

**Usage**:
```bash
# Run specific seeder
php artisan db:seed --class=ReferralSeeder

# Run all seeders
php artisan db:seed
```

## Test Coverage

### Backend Coverage Goals
- Services: 90%+
- Controllers: 80%+
- Models: 85%+
- Overall: 85%+

### Frontend Coverage Goals
- Components: 80%+
- Composables: 90%+
- Stores: 85%+
- Overall: 80%+

### Generate Coverage Report
```bash
# Backend
php artisan test --coverage

# Frontend
npm run test:coverage
```

## Continuous Integration

### GitHub Actions
**File**: `.github/workflows/tests.yml`

**Runs**:
- PHP tests on push
- Frontend tests on push
- Code coverage reports
- Linting checks

## Manual Testing Checklist

### Referral Submission
- [ ] Form validates required fields
- [ ] Facility selection works
- [ ] Severity level selection works
- [ ] Submit button works
- [ ] Success message displays
- [ ] Redirect to referrals list

### Claim Submission
- [ ] Multi-step form works
- [ ] Claim lines can be added/removed
- [ ] Amount calculations are correct
- [ ] Review step displays correct data
- [ ] Submit button works
- [ ] Success message displays

### Claims Review
- [ ] Claims list displays
- [ ] Search/filter works
- [ ] Review dialog opens
- [ ] Approval form works
- [ ] Rejection form works
- [ ] Status updates correctly

### Admission Management
- [ ] Admissions list displays
- [ ] Create dialog works
- [ ] Edit dialog works
- [ ] Delete confirmation works
- [ ] Status updates correctly

## Performance Testing

### Load Testing
```bash
# Using Apache Bench
ab -n 1000 -c 10 http://localhost:8000/api/claims

# Using wrk
wrk -t4 -c100 -d30s http://localhost:8000/api/claims
```

### Frontend Performance
```bash
# Lighthouse audit
npm run lighthouse

# Bundle analysis
npm run build:analyze
```

## Debugging Tests

### Backend Debugging
```php
// Print debug info
dd($variable);

// Log to file
Log::info('Debug message', ['data' => $variable]);

// Use debugbar
\Debugbar::info('Message');
```

### Frontend Debugging
```javascript
// Console logging
console.log('Debug:', variable);

// Vue DevTools
// Use Vue DevTools browser extension

// Network debugging
// Use browser DevTools Network tab
```

## Test Reporting

### Generate Reports
```bash
# Backend coverage report
php artisan test --coverage --coverage-html=coverage

# Frontend coverage report
npm run test:coverage

# Open reports
open coverage/index.html
```

---

**Last Updated**: 2025-12-04
**Test Framework**: PHPUnit, Vitest, Cypress

