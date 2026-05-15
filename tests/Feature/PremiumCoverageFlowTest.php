<?php

namespace Tests\Feature;

use App\Models\Benefactor;
use App\Models\BenefitPackage;
use App\Models\Capitation;
use App\Models\CapitationDetail;
use App\Models\CoveragePeriod;
use App\Models\Enrollee;
use App\Models\EnrolleeCategory;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use App\Models\InsuranceSubProgramme;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use App\Models\User;
use App\Services\CapitationService;
use App\Services\PremiumCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PremiumCoverageFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Facility $facility;
    private FundingType $fundingType;
    private PremiumPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
        $this->facility = Facility::factory()->create();
        $this->fundingType = FundingType::create(['name' => 'Individual Premium', 'status' => 1]);

        $programme = InsuranceProgramme::create(['name' => 'Informal Sector Programme', 'code' => 'informal_sector']);
        $sub = InsuranceSubProgramme::create(['insurance_programme_id' => $programme->id, 'name' => 'Individual / Family', 'code' => 'individual_family']);
        $category = EnrolleeCategory::create(['insurance_programme_id' => $programme->id, 'insurance_sub_programme_id' => $sub->id, 'name' => 'Premium PIN Users', 'code' => 'premium_pin_users']);
        $benefitPackage = BenefitPackage::first() ?: BenefitPackage::create(['name' => 'Standard Package', 'code' => 'standard', 'status' => 1]);

        $this->plan = PremiumPlan::create([
            'insurance_programme_id' => $programme->id,
            'insurance_sub_programme_id' => $sub->id,
            'enrollee_category_id' => $category->id,
            'benefit_package_id' => $benefitPackage->id,
            'name' => 'Informal Annual',
            'code' => 'informal_annual',
            'amount' => 12000,
            'duration_days' => 365,
            'waiting_period_days' => 30,
            'status' => 'active',
        ]);
    }

    public function test_pin_cannot_be_used_twice(): void
    {
        $service = app(PremiumCoverageService::class);
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id, 'funding_type_id' => $this->fundingType->id]);
        $purchase = $this->confirmedPurchase();
        $pin = PremiumPin::create([
            'premium_plan_id' => $this->plan->id,
            'premium_purchase_id' => $purchase->id,
            'batch_code' => 'B1',
            'pin' => '123456789012',
            'serial_number' => 'SN1',
            'amount' => $this->plan->amount,
            'status' => PremiumPin::STATUS_SOLD,
            'sold_at' => now(),
        ]);

        $service->usePinForCoverage($pin, $enrollee, $this->facility->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BR-13');
        $service->usePinForCoverage($pin->fresh(), $enrollee, $this->facility->id);
    }

    public function test_expired_pin_cannot_be_used(): void
    {
        $pin = PremiumPin::create([
            'premium_plan_id' => $this->plan->id,
            'premium_purchase_id' => $this->confirmedPurchase()->id,
            'batch_code' => 'B2',
            'pin' => '223456789012',
            'serial_number' => 'SN2',
            'amount' => $this->plan->amount,
            'status' => PremiumPin::STATUS_SOLD,
            'expires_at' => now()->subDay(),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BR-14');
        app(PremiumCoverageService::class)->validatePin($pin->pin);
    }

    public function test_informal_sector_coverage_enters_waiting_period(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);

        $coverage = app(PremiumCoverageService::class)->createCoverage($enrollee, $this->plan, [
            'activation_source' => 'payment',
            'facility_id' => $this->facility->id,
        ]);

        $this->assertSame(CoveragePeriod::STATUS_PENDING_WAITING_PERIOD, $coverage->status);
        $this->assertEquals(30, $coverage->waiting_period_days);
    }

    public function test_active_coverage_required_before_referral(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);
        $receiving = Facility::factory()->create();

        $response = $this->postJson('/api/referrals', [
            'enrollee_id' => $enrollee->id,
            'referring_facility_id' => $this->facility->id,
            'receiving_facility_id' => $receiving->id,
            'presenting_complains' => 'Fever',
            'reasons_for_referral' => 'Specialist review',
            'treatments_given' => 'Paracetamol',
            'investigations_done' => 'RDT',
            'examination_findings' => 'Stable',
            'preliminary_diagnosis' => 'Malaria',
            'severity_level' => 'routine',
            'referring_person_name' => 'Dr Test',
            'referring_person_specialisation' => 'GP',
            'referring_person_cadre' => 'Doctor',
        ]);

        $response->assertStatus(422);
    }

    public function test_vulnerable_enrollee_coverage_requires_subsidy_source(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BR-15');
        app(PremiumCoverageService::class)->createCoverage($enrollee, $this->plan, ['activation_source' => 'subsidy']);
    }

    public function test_donor_coverage_requires_benefactor_and_purchase(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('BR-16');
        app(PremiumCoverageService::class)->createCoverage($enrollee, $this->plan, ['activation_source' => 'donor']);
    }

    public function test_capitation_counts_only_active_coverage(): void
    {
        $eligible = Enrollee::factory()->create(['facility_id' => $this->facility->id]);
        $expired = Enrollee::factory()->create(['facility_id' => $this->facility->id]);
        $missingBenefitPackage = Enrollee::factory()->create(['facility_id' => $this->facility->id]);

        app(PremiumCoverageService::class)->createCoverage($eligible, $this->plan, [
            'facility_id' => $this->facility->id,
            'funding_type_id' => $this->fundingType->id,
            'activation_source' => 'admin',
            'status' => CoveragePeriod::STATUS_ACTIVE,
        ]);
        CoveragePeriod::create([
            'enrollee_id' => $expired->id,
            'insurance_programme_id' => $this->plan->insurance_programme_id,
            'premium_plan_id' => $this->plan->id,
            'facility_id' => $this->facility->id,
            'funding_type_id' => $this->fundingType->id,
            'coverage_start_date' => now()->subYear(),
            'coverage_end_date' => now()->subMonth(),
            'status' => CoveragePeriod::STATUS_EXPIRED,
            'activation_source' => 'admin',
        ]);
        CoveragePeriod::create([
            'enrollee_id' => $missingBenefitPackage->id,
            'insurance_programme_id' => $this->plan->insurance_programme_id,
            'premium_plan_id' => $this->plan->id,
            'facility_id' => $this->facility->id,
            'funding_type_id' => $this->fundingType->id,
            'coverage_start_date' => now()->startOfMonth(),
            'coverage_end_date' => now()->endOfMonth(),
            'status' => CoveragePeriod::STATUS_ACTIVE,
            'activation_source' => 'admin',
        ]);

        $capitation = Capitation::create([
            'name' => 'May 2026',
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'capitation_rate' => 500,
            'capitated_month' => now()->month,
            'capitation_month' => now()->month,
            'year' => now()->year,
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        app(CapitationService::class)->computeForPeriod($capitation);

        $this->assertSame(1, CapitationDetail::where('facility_id', $this->facility->id)->value('total_enrollees'));
    }

    public function test_active_coverage_requires_benefit_package_for_care(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);

        CoveragePeriod::create([
            'enrollee_id' => $enrollee->id,
            'insurance_programme_id' => $this->plan->insurance_programme_id,
            'premium_plan_id' => $this->plan->id,
            'facility_id' => $this->facility->id,
            'funding_type_id' => $this->fundingType->id,
            'coverage_start_date' => now()->startOfMonth(),
            'coverage_end_date' => now()->endOfMonth(),
            'status' => CoveragePeriod::STATUS_ACTIVE,
            'activation_source' => 'admin',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('benefit package');

        app(\App\Services\EligibilityService::class)->assertEligibleForCare($enrollee);
    }

    public function test_creator_cannot_approve_own_premium_purchase(): void
    {
        $purchase = $this->pendingPurchase(['sold_by' => $this->user->id]);

        $this->expectException(InvalidArgumentException::class);
        app(PremiumCoverageService::class)->confirmPurchase($purchase, $this->user->id);
    }

    public function test_coverage_renewal_preserves_previous_history(): void
    {
        $enrollee = Enrollee::factory()->create(['facility_id' => $this->facility->id]);
        $coverage = app(PremiumCoverageService::class)->createCoverage($enrollee, $this->plan, [
            'facility_id' => $this->facility->id,
            'activation_source' => 'admin',
            'status' => CoveragePeriod::STATUS_ACTIVE,
            'coverage_start_date' => now()->subYear(),
            'coverage_end_date' => now()->subDay(),
        ]);

        $renewal = app(PremiumCoverageService::class)->renewCoverage($coverage);

        $this->assertDatabaseHas('coverage_periods', ['id' => $coverage->id]);
        $this->assertNotSame($coverage->id, $renewal->id);
        $this->assertSame($coverage->id, $renewal->metadata['renewed_from_coverage_id']);
    }

    private function confirmedPurchase(): PremiumPurchase
    {
        return $this->pendingPurchase(['payment_status' => 'confirmed', 'confirmed_by' => $this->user->id, 'confirmed_at' => now()]);
    }

    private function pendingPurchase(array $overrides = []): PremiumPurchase
    {
        $benefactor = Benefactor::create(['name' => 'Self Payer', 'type' => 'individual', 'status' => 1]);

        return PremiumPurchase::create(array_merge([
            'premium_plan_id' => $this->plan->id,
            'benefactor_id' => $benefactor->id,
            'funding_type_id' => $this->fundingType->id,
            'payer_type' => 'individual',
            'payer_name' => 'Self Payer',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'quantity' => 1,
            'amount' => $this->plan->amount,
            'sold_by' => User::factory()->create()->id,
        ], $overrides));
    }
}
