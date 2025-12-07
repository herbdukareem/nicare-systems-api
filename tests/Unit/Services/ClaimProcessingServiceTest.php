<?php

namespace Tests\Unit\Services;

use App\Models\Admission;
use App\Models\Claim;
use App\Models\User;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use Tests\TestCase;

class ClaimProcessingServiceTest extends TestCase
{
    private ClaimProcessingService $claimProcessingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->claimProcessingService = app(ClaimProcessingService::class);
    }

    /**
     * Test creating a claim from an active admission
     */
    public function test_create_claim_from_active_admission()
    {
        $admission = Admission::factory()->create(['status' => 'ACTIVE']);

        $claim = $this->claimProcessingService->createClaim($admission->id, [
            'claim_date' => now(),
        ]);

        $this->assertNotNull($claim);
        $this->assertEquals($admission->id, $claim->admission_id);
        $this->assertEquals('DRAFT', $claim->status);
    }

    /**
     * Test cannot create claim from inactive admission
     */
    public function test_cannot_create_claim_from_inactive_admission()
    {
        $admission = Admission::factory()->create(['status' => 'DISCHARGED']);

        $this->expectException(\InvalidArgumentException::class);

        $this->claimProcessingService->createClaim($admission->id, [
            'claim_date' => now(),
        ]);
    }

    /**
     * Test submit claim
     */
    public function test_submit_claim()
    {
        $claim = Claim::factory()->create(['status' => 'DRAFT']);

        $submitted = $this->claimProcessingService->submitClaim($claim);

        $this->assertEquals('SUBMITTED', $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    /**
     * Test cannot submit claim without line items
     */
    public function test_cannot_submit_claim_without_line_items()
    {
        $claim = Claim::factory()->create(['status' => 'DRAFT']);

        $this->expectException(\InvalidArgumentException::class);

        $this->claimProcessingService->submitClaim($claim);
    }

    /**
     * Test approve claim
     */
    public function test_approve_claim()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $claim = Claim::factory()->create(['status' => 'SUBMITTED']);

        $approved = $this->claimProcessingService->approveClaim($claim, [
            'approval_comments' => 'Approved',
        ]);

        $this->assertEquals('APPROVED', $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertEquals($user->id, $approved->approved_by);
    }

    /**
     * Test reject claim
     */
    public function test_reject_claim()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $claim = Claim::factory()->create(['status' => 'SUBMITTED']);

        $rejected = $this->claimProcessingService->rejectClaim($claim, [
            'rejection_reason' => 'Invalid claim',
        ]);

        $this->assertEquals('REJECTED', $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertEquals($user->id, $rejected->rejected_by);
    }

    /**
     * Test move claim to review
     */
    public function test_move_claim_to_review()
    {
        $claim = Claim::factory()->create(['status' => 'SUBMITTED']);

        $reviewing = $this->claimProcessingService->moveToReview($claim);

        $this->assertEquals('REVIEWING', $reviewing->status);
    }

    /**
     * Test get claim summary
     */
    public function test_get_claim_summary()
    {
        $claim = Claim::factory()->create([
            'bundle_amount' => 1000,
            'ffs_amount' => 500,
        ]);

        $summary = $this->claimProcessingService->getClaimSummary($claim);

        $this->assertIsArray($summary);
        $this->assertEquals($claim->id, $summary['claim_id']);
        $this->assertEquals(1000, $summary['bundle_total']);
        $this->assertEquals(500, $summary['ffs_total']);
    }
}

