<?php

namespace Tests\Unit\Services;

use App\Models\Admission;
use App\Models\Bundle;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Referral;
use App\Services\ClaimsAutomation\AdmissionService;
use Tests\TestCase;

class AdmissionServiceTest extends TestCase
{
    private AdmissionService $admissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admissionService = app(AdmissionService::class);
    }

    /**
     * Test creating an admission from an approved referral
     */
    public function test_create_admission_from_approved_referral()
    {
        // Create test data
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::factory()->create();
        $bundle = Bundle::factory()->create(['icd10_code' => 'A00']);

        $referral = Referral::factory()->create([
            'enrollee_id' => $enrollee->id,
            'receiving_facility_id' => $facility->id,
            'status' => 'APPROVED',
            'utn' => 'UTN123456',
            'utn_validated' => true,
        ]);

        // Create admission
        $admission = $this->admissionService->createAdmission($referral->id, [
            'admission_date' => now(),
            'ward_type' => 'GENERAL',
            'principal_diagnosis_icd10' => 'A00',
        ]);

        // Assertions
        $this->assertNotNull($admission);
        $this->assertEquals($referral->id, $admission->referral_id);
        $this->assertEquals($enrollee->id, $admission->enrollee_id);
        $this->assertEquals('ACTIVE', $admission->status);
    }

    /**
     * Test cannot create admission from unapproved referral
     */
    public function test_cannot_create_admission_from_unapproved_referral()
    {
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::factory()->create();

        $referral = Referral::factory()->create([
            'enrollee_id' => $enrollee->id,
            'receiving_facility_id' => $facility->id,
            'status' => 'PENDING',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->admissionService->createAdmission($referral->id, [
            'admission_date' => now(),
            'ward_type' => 'GENERAL',
            'principal_diagnosis_icd10' => 'A00',
        ]);
    }

    /**
     * Test cannot create admission without validated UTN
     */
    public function test_cannot_create_admission_without_validated_utn()
    {
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::factory()->create();

        $referral = Referral::factory()->create([
            'enrollee_id' => $enrollee->id,
            'receiving_facility_id' => $facility->id,
            'status' => 'APPROVED',
            'utn_validated' => false,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->admissionService->createAdmission($referral->id, [
            'admission_date' => now(),
            'ward_type' => 'GENERAL',
            'principal_diagnosis_icd10' => 'A00',
        ]);
    }

    /**
     * Test discharge patient
     */
    public function test_discharge_patient()
    {
        $admission = Admission::factory()->create(['status' => 'ACTIVE']);

        $discharged = $this->admissionService->dischargePatient($admission, [
            'discharge_date' => now(),
            'discharge_summary' => 'Patient recovered',
            'ward_days' => 5,
        ]);

        $this->assertEquals('DISCHARGED', $discharged->status);
        $this->assertNotNull($discharged->discharge_date);
    }

    /**
     * Test get active admission for enrollee
     */
    public function test_get_active_admission_for_enrollee()
    {
        $enrollee = Enrollee::factory()->create();
        $admission = Admission::factory()->create([
            'enrollee_id' => $enrollee->id,
            'status' => 'ACTIVE',
        ]);

        $activeAdmission = $this->admissionService->getActiveAdmission($enrollee->id);

        $this->assertNotNull($activeAdmission);
        $this->assertEquals($admission->id, $activeAdmission->id);
    }

    /**
     * Test check admission eligibility
     */
    public function test_check_admission_eligibility()
    {
        $enrollee = Enrollee::factory()->create();
        $facility = Facility::factory()->create();

        $referral = Referral::factory()->create([
            'enrollee_id' => $enrollee->id,
            'receiving_facility_id' => $facility->id,
            'status' => 'APPROVED',
            'utn' => 'UTN123456',
            'utn_validated' => true,
        ]);

        $canAdmit = $this->admissionService->canAdmit($referral->id);

        $this->assertTrue($canAdmit);
    }
}

