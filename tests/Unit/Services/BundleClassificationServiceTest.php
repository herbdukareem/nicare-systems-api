<?php

namespace Tests\Unit\Services;

use App\Models\Claim;
use App\Models\PACode;
use App\Services\ClaimsAutomation\BundleClassificationService;
use Tests\TestCase;

class BundleClassificationServiceTest extends TestCase
{
    private BundleClassificationService $classificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classificationService = app(BundleClassificationService::class);
    }

    /**
     * Test adding a bundle treatment to a claim
     */
    public function test_add_bundle_treatment()
    {
        $claim = Claim::factory()->create();
        $paCode = PACode::factory()->create(['type' => 'BUNDLE']);

        $claimLine = $this->classificationService->addBundleTreatment($claim, $paCode->id, [
            'service_description' => 'Bundle Service',
            'quantity' => 1,
        ]);

        $this->assertNotNull($claimLine);
        $this->assertEquals('BUNDLE', $claimLine->tariff_type);
        $this->assertEquals($paCode->id, $claimLine->pa_code_id);
    }

    /**
     * Test cannot add multiple bundles to same claim
     */
    public function test_cannot_add_multiple_bundles()
    {
        $claim = Claim::factory()->create();
        $paCode1 = PACode::factory()->create(['type' => 'BUNDLE']);
        $paCode2 = PACode::factory()->create(['type' => 'BUNDLE']);

        // Add first bundle
        $this->classificationService->addBundleTreatment($claim, $paCode1->id, [
            'service_description' => 'Bundle Service 1',
            'quantity' => 1,
        ]);

        // Try to add second bundle - should fail
        $this->expectException(\InvalidArgumentException::class);

        $this->classificationService->addBundleTreatment($claim, $paCode2->id, [
            'service_description' => 'Bundle Service 2',
            'quantity' => 1,
        ]);
    }

    /**
     * Test adding FFS treatment to a claim
     */
    public function test_add_ffs_treatment()
    {
        $claim = Claim::factory()->create();
        $paCode = PACode::factory()->create(['type' => 'FFS_TOP_UP']);

        $claimLine = $this->classificationService->addFFSTreatment($claim, $paCode->id, [
            'service_description' => 'FFS Service',
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $this->assertNotNull($claimLine);
        $this->assertEquals('FFS', $claimLine->tariff_type);
        $this->assertEquals(200, $claimLine->line_total);
    }

    /**
     * Test classify treatments
     */
    public function test_classify_treatments()
    {
        $claim = Claim::factory()->create();
        $bundlePA = PACode::factory()->create(['type' => 'BUNDLE']);
        $ffsPA = PACode::factory()->create(['type' => 'FFS_TOP_UP']);

        $this->classificationService->addBundleTreatment($claim, $bundlePA->id, [
            'service_description' => 'Bundle',
            'quantity' => 1,
        ]);

        $this->classificationService->addFFSTreatment($claim, $ffsPA->id, [
            'service_description' => 'FFS',
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $classification = $this->classificationService->classifyTreatments($claim);

        $this->assertIsArray($classification);
        $this->assertEquals(1, $classification['bundle_count']);
        $this->assertEquals(1, $classification['ffs_count']);
    }

    /**
     * Test update claim totals
     */
    public function test_update_claim_totals()
    {
        $claim = Claim::factory()->create([
            'bundle_amount' => 0,
            'ffs_amount' => 0,
        ]);

        $bundlePA = PACode::factory()->create(['type' => 'BUNDLE']);
        $ffsPA = PACode::factory()->create(['type' => 'FFS_TOP_UP']);

        $this->classificationService->addBundleTreatment($claim, $bundlePA->id, [
            'service_description' => 'Bundle',
            'quantity' => 1,
        ]);

        $this->classificationService->addFFSTreatment($claim, $ffsPA->id, [
            'service_description' => 'FFS',
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->classificationService->updateClaimTotals($claim);

        $claim->refresh();

        $this->assertGreater($claim->bundle_amount, 0);
        $this->assertGreater($claim->ffs_amount, 0);
        $this->assertGreater($claim->total_amount_claimed, 0);
    }
}

