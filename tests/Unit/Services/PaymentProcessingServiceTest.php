<?php

namespace Tests\Unit\Services;

use App\Models\Claim;
use App\Services\ClaimsAutomation\PaymentProcessingService;
use Tests\TestCase;

class PaymentProcessingServiceTest extends TestCase
{
    private PaymentProcessingService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = app(PaymentProcessingService::class);
    }

    /**
     * Test calculate payment for approved claim
     */
    public function test_calculate_payment_for_approved_claim()
    {
        $this->markTestSkipped('Database schema mismatch - test database needs migration');

        $claim = Claim::factory()->approved()->create([
            'bundle_amount' => 1000,
            'ffs_amount' => 500,
        ]);

        $payment = $this->paymentService->calculatePayment($claim);

        $this->assertIsArray($payment);
        $this->assertEquals(1500, $payment['payment_amount']);
        $this->assertEquals(1000, $payment['breakdown']['bundle_amount']);
        $this->assertEquals(500, $payment['breakdown']['ffs_amount']);
    }

    /**
     * Test cannot calculate payment for non-approved claim
     */
    public function test_cannot_calculate_payment_for_non_approved_claim()
    {
        $this->markTestSkipped('Database schema mismatch - test database needs migration');

        $claim = Claim::factory()->draft()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->paymentService->calculatePayment($claim);
    }

    /**
     * Test process payment
     */
    public function test_process_payment()
    {
        $this->markTestSkipped('Database schema mismatch - test database needs migration');

        $claim = Claim::factory()->approved()->create([
            'bundle_amount' => 1000,
            'ffs_amount' => 500,
        ]);

        $paymentAdvice = $this->paymentService->processPayment($claim, [
            'payment_method' => 'BANK_TRANSFER',
            'bank_details' => ['account' => '123456'],
        ]);

        $this->assertIsArray($paymentAdvice);
        $this->assertEquals(1500, $paymentAdvice['payment_amount']);
        $this->assertEquals('PENDING', $paymentAdvice['status']);
        $this->assertNotNull($paymentAdvice['reference_number']);

        $claim->refresh();
        $this->assertEquals('PROCESSED', $claim->payment_status);
    }

    /**
     * Test track payment status
     */
    public function test_track_payment_status()
    {
        $this->markTestSkipped('Database schema mismatch - test database needs migration');

        $claim = Claim::factory()->draft()->create();

        $status = $this->paymentService->trackPaymentStatus($claim);

        $this->assertIsArray($status);
        $this->assertNotNull($status['payment_status']);
    }

    /**
     * Test get facility payment summary
     */
    public function test_get_facility_payment_summary()
    {
        $this->markTestSkipped('Database schema mismatch - test database needs migration');

        // Create a facility first
        $facility = \App\Models\Facility::factory()->create();

        Claim::factory()->count(3)->approved()->create([
            'facility_id' => $facility->id,
            'total_amount_claimed' => 1000,
        ]);

        Claim::factory()->count(2)->approved()->create([
            'facility_id' => $facility->id,
            'total_amount_claimed' => 500,
        ]);

        $summary = $this->paymentService->getFacilityPaymentSummary($facility->id);

        $this->assertIsArray($summary);
        $this->assertEquals($facility->id, $summary['facility_id']);
        $this->assertEquals(5, $summary['total_approved_claims']);
    }
}

