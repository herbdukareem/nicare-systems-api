<?php

namespace Tests\Feature\ClaimsAutomation;

use App\Models\Bundle;
use App\Models\Claim;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\PACode;
use App\Models\Referral;
use App\Models\User;
use App\Services\ClaimsAutomation\AdmissionService;
use App\Services\ClaimsAutomation\BundleClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Simplified Claims Automation Tests
 *
 * Tests the core business rules:
 * 1. Referral must be approved before UTN is generated
 * 2. UTN must be validated before admission
 * 3. Bundle claims linked to UTN (one UTN = one principal bundle)
 * 4. FFS requires approved PA code
 * 5. Cannot add FFS line items without valid PA
 */
class ClaimsAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Facility $facility;
    protected Enrollee $enrollee;
    protected Referral $referral;
    protected AdmissionService $admissionService;
    protected BundleClassificationService $bundleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->facility = Facility::factory()->create([
            'name' => 'Test Hospital',
        ]);
        $this->enrollee = Enrollee::factory()->create([
            'enrollee_id' => 'NIC001',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Create an approved referral with validated UTN
        $this->referral = Referral::factory()->create([
            'nicare_number' => $this->enrollee->enrollee_id,
            'receiving_facility_id' => $this->facility->id,
            'receiving_facility_name' => $this->facility->name,
            'receiving_nicare_code' => $this->facility->hcp_code,
            'status' => 'approved',
            'utn' => 'UTN-' . now()->format('Ymd') . '-001',
            'utn_validated' => true,
        ]);

        $this->admissionService = app(AdmissionService::class);
        $this->bundleService = app(BundleClassificationService::class);
    }

    /**
     * Test Rule 1: Admission requires approved referral
     */
    public function test_admission_requires_approved_referral(): void
    {
        // Create unapproved referral
        $pendingReferral = Referral::factory()->create([
            'nicare_number' => $this->enrollee->enrollee_id,
            'receiving_facility_id' => $this->facility->id,
            'receiving_facility_name' => $this->facility->name,
            'receiving_nicare_code' => $this->facility->hcp_code,
            'status' => 'pending',
            'utn' => null,
            'utn_validated' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Referral must be approved before admission');

        $this->admissionService->createAdmission([
            'referral_id' => $pendingReferral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);
    }

    /**
     * Test Rule 2: Admission requires validated UTN
     */
    public function test_admission_requires_validated_utn(): void
    {
        // Create approved referral but UTN not validated
        $approvedReferral = Referral::factory()->create([
            'nicare_number' => $this->enrollee->enrollee_id,
            'receiving_facility_id' => $this->facility->id,
            'receiving_facility_name' => $this->facility->name,
            'receiving_nicare_code' => $this->facility->hcp_code,
            'status' => 'approved',
            'utn' => 'UTN-' . now()->format('Ymd') . '-002',
            'utn_validated' => false,  // Not validated yet
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('UTN must be validated by receiving facility before admission');

        $this->admissionService->createAdmission([
            'referral_id' => $approvedReferral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);
    }

    /**
     * Test Rule 3: Successful admission with valid referral and UTN
     */
    public function test_admission_succeeds_with_valid_referral_and_utn(): void
    {
        // Create bundle for auto-matching
        Bundle::factory()->create([
            'bundle_code' => 'MAL001',
            'bundle_name' => 'Severe Malaria Treatment',
            'icd10_code' => 'B50',
            'bundle_tariff' => 50000,
        ]);

        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
            'principal_diagnosis_description' => 'Severe Malaria',
        ]);

        $this->assertNotNull($admission);
        $this->assertEquals($this->referral->id, $admission->referral_id);
        $this->assertEquals('active', $admission->status);
        $this->assertNotNull($admission->bundle_id); // Auto-matched bundle
    }

    /**
     * Test Rule 4: FFS treatment requires approved PA code
     */
    public function test_ffs_treatment_requires_approved_pa_code(): void
    {
        // Create admission
        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);

        // Create claim
        $claim = Claim::factory()->create([
            'admission_id' => $admission->id,
            'facility_id' => $this->facility->id,
            'nicare_number' => $this->enrollee->enrollee_id,
        ]);

        // Create pending PA code (not approved)
        $pendingPA = PACode::factory()->create([
            'referral_id' => $this->referral->id,
            'pa_type' => 'ffs',
            'status' => 'pending',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PA code must be approved');

        $this->bundleService->addFFSTreatment($claim, $pendingPA->id, [
            'service_type' => 'medication',
            'service_description' => 'Additional Medication',
            'unit_price' => 5000,
        ]);
    }

    /**
     * Test Rule 5: FFS treatment succeeds with approved PA code
     */
    public function test_ffs_treatment_succeeds_with_approved_pa_code(): void
    {
        // Create admission
        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);

        // Create claim
        $claim = Claim::factory()->create([
            'admission_id' => $admission->id,
            'facility_id' => $this->facility->id,
            'nicare_number' => $this->enrollee->enrollee_id,
        ]);

        // Create approved PA code
        $approvedPA = PACode::factory()->create([
            'referral_id' => $this->referral->id,
            'pa_type' => 'ffs',
            'status' => 'active',  // Approved
        ]);

        $treatment = $this->bundleService->addFFSTreatment($claim, $approvedPA->id, [
            'service_type' => 'medication',
            'service_description' => 'Additional Medication',
            'unit_price' => 5000,
        ]);

        $this->assertNotNull($treatment);
        $this->assertEquals('ffs', $treatment->item_type);
        $this->assertEquals($approvedPA->id, $treatment->pa_code_id);
    }

    /**
     * Test bundle treatment does not require PA code
     */
    public function test_bundle_treatment_does_not_require_pa_code(): void
    {
        // Create admission
        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);

        // Create claim
        $claim = Claim::factory()->create([
            'admission_id' => $admission->id,
            'facility_id' => $this->facility->id,
            'nicare_number' => $this->enrollee->enrollee_id,
        ]);

        // Add bundle treatment without PA code
        $treatment = $this->bundleService->addBundleTreatment($claim, [
            'service_type' => 'professional_service',
            'service_description' => 'Malaria Bundle Treatment',
            'unit_price' => 50000,
        ]);

        $this->assertNotNull($treatment);
        $this->assertEquals('bundle', $treatment->item_type);
        $this->assertNull($treatment->pa_code_id);  // No PA required for bundle
    }

    /**
     * Test claim totals are updated correctly
     */
    public function test_claim_totals_are_updated_correctly(): void
    {
        // Create admission
        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);

        // Create claim
        $claim = Claim::factory()->create([
            'admission_id' => $admission->id,
            'facility_id' => $this->facility->id,
            'nicare_number' => $this->enrollee->enrollee_id,
        ]);

        // Add bundle treatment
        $this->bundleService->addBundleTreatment($claim, [
            'service_type' => 'professional_service',
            'service_description' => 'Malaria Bundle',
            'unit_price' => 50000,
        ]);

        // Create approved PA and add FFS treatment
        $approvedPA = PACode::factory()->create([
            'referral_id' => $this->referral->id,
            'pa_type' => 'ffs',
            'status' => 'active',
        ]);

        $this->bundleService->addFFSTreatment($claim, $approvedPA->id, [
            'service_type' => 'medication',
            'service_description' => 'Additional Medication',
            'unit_price' => 5000,
        ]);

        $claim->refresh();

        $this->assertEquals(50000, $claim->bundle_amount);
        $this->assertEquals(5000, $claim->ffs_amount);
        $this->assertEquals(55000, $claim->total_amount_claimed);
    }

    /**
     * Test canAdmit returns correct status
     */
    public function test_can_admit_returns_correct_status(): void
    {
        // Test with valid referral
        $result = $this->admissionService->canAdmit($this->referral->id);
        $this->assertTrue($result['can_admit']);

        // Create admission
        $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
        ]);

        // Create new referral for same enrollee
        $newReferral = Referral::factory()->create([
            'nicare_number' => $this->enrollee->enrollee_id,
            'receiving_facility_id' => $this->facility->id,
            'receiving_facility_name' => $this->facility->name,
            'receiving_nicare_code' => $this->facility->hcp_code,
            'status' => 'approved',
            'utn' => 'UTN-' . now()->format('Ymd') . '-003',
            'utn_validated' => true,
        ]);

        // Should not be able to admit - already has active admission
        $result = $this->admissionService->canAdmit($newReferral->id);
        $this->assertFalse($result['can_admit']);
        $this->assertEquals('Patient already has an active admission', $result['reason']);
    }

    /**
     * Test discharge patient
     */
    public function test_discharge_patient(): void
    {
        $admission = $this->admissionService->createAdmission([
            'referral_id' => $this->referral->id,
            'enrollee_id' => $this->enrollee->id,
            'facility_id' => $this->facility->id,
            'principal_diagnosis_icd10' => 'B50.9',
            'admission_date' => now()->subDays(3),
        ]);

        $this->assertEquals('active', $admission->status);

        $discharged = $this->admissionService->dischargePatient($admission, [
            'discharge_summary' => 'Patient recovered',
        ]);

        $this->assertEquals('discharged', $discharged->status);
        $this->assertNotNull($discharged->discharge_date);
        $this->assertEquals('Patient recovered', $discharged->discharge_summary);
    }
}

