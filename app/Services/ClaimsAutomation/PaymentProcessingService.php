<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Claim;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/**
 * PaymentProcessingService
 * 
 * Handles payment calculation, processing, and tracking
 */
class PaymentProcessingService
{
    /**
     * Calculate facility payment for an approved claim
     * 
     * @param Claim $claim
     * @return array - ['payment_amount' => decimal, 'breakdown' => array]
     * @throws InvalidArgumentException
     */
    public function calculatePayment(Claim $claim): array
    {
        if ($claim->status !== 'APPROVED') {
            throw new InvalidArgumentException("Only APPROVED claims can be paid");
        }

        $bundleTotal = $claim->lineItems()
            ->where('tariff_type', 'BUNDLE')
            ->sum('line_total');

        $ffsTotal = $claim->lineItems()
            ->where('tariff_type', 'FFS')
            ->sum('line_total');

        $totalPayment = $bundleTotal + $ffsTotal;

        return [
            'claim_id' => $claim->id,
            'payment_amount' => $totalPayment,
            'breakdown' => [
                'bundle_amount' => $bundleTotal,
                'ffs_amount' => $ffsTotal,
            ],
            'facility_id' => $claim->facility_id,
            'enrollee_id' => $claim->enrollee_id,
        ];
    }

    /**
     * Process payment for an approved claim
     * 
     * @param Claim $claim
     * @param array $data - payment_method, reference_number, etc.
     * @return array - payment advice data
     */
    public function processPayment(Claim $claim, array $data = []): array
    {
        if ($claim->status !== 'APPROVED') {
            throw new InvalidArgumentException("Only APPROVED claims can be processed for payment");
        }

        $paymentData = $this->calculatePayment($claim);

        // Generate payment advice
        $paymentAdvice = [
            'claim_id' => $claim->id,
            'facility_id' => $claim->facility_id,
            'payment_amount' => $paymentData['payment_amount'],
            'payment_date' => now(),
            'status' => 'PENDING',
            'reference_number' => $this->generatePaymentReference(),
            'payment_method' => $data['payment_method'] ?? 'BANK_TRANSFER',
            'bank_details' => $data['bank_details'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        // Update claim with payment info
        $claim->update([
            'payment_status' => 'PROCESSED',
            'payment_processed_at' => now(),
            'payment_reference' => $paymentAdvice['reference_number'],
        ]);

        return $paymentAdvice;
    }

    /**
     * Generate a unique payment reference number
     * 
     * @return string
     */
    private function generatePaymentReference(): string
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Track payment status
     * 
     * @param Claim $claim
     * @return array - payment status info
     */
    public function trackPaymentStatus(Claim $claim): array
    {
        return [
            'claim_id' => $claim->id,
            'payment_status' => $claim->payment_status ?? 'NOT_PROCESSED',
            'payment_reference' => $claim->payment_reference ?? null,
            'payment_processed_at' => $claim->payment_processed_at ?? null,
            'payment_amount' => $claim->total_amount_claimed ?? 0,
            'facility_id' => $claim->facility_id,
        ];
    }

    /**
     * Get payment summary for a facility
     * 
     * @param int $facilityId
     * @param array $filters - date_from, date_to, status
     * @return array
     */
    public function getFacilityPaymentSummary(int $facilityId, array $filters = []): array
    {
        $query = Claim::where('facility_id', $facilityId)
            ->where('status', 'APPROVED');

        if (isset($filters['date_from'])) {
            $query->where('approved_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('approved_at', '<=', $filters['date_to']);
        }

        if (isset($filters['status'])) {
            $query->where('payment_status', $filters['status']);
        }

        $claims = $query->get();

        $totalAmount = $claims->sum('total_amount_claimed');
        $processedAmount = $claims->where('payment_status', 'PROCESSED')->sum('total_amount_claimed');
        $pendingAmount = $totalAmount - $processedAmount;

        return [
            'facility_id' => $facilityId,
            'total_approved_claims' => $claims->count(),
            'total_amount' => $totalAmount,
            'processed_amount' => $processedAmount,
            'pending_amount' => $pendingAmount,
            'processed_claims' => $claims->where('payment_status', 'PROCESSED')->count(),
            'pending_claims' => $claims->where('payment_status', '!=', 'PROCESSED')->count(),
        ];
    }
}

