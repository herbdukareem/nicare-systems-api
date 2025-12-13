<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Services\ClaimValidationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ClaimProcessingService
 * 
 * Handles complete claim workflow: creation, validation, approval, rejection
 */
class ClaimProcessingService
{
    private ClaimValidationService $validationService;

    public function __construct(ClaimValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Create a new claim from an admission
     * 
     * @param int $admissionId
     * @param array $data - claim_date, submitted_by, etc.
     * @return Claim
     * @throws ModelNotFoundException
     */
    public function createClaim(int $admissionId, array $data): Claim
    {
        $admission = Admission::find($admissionId);
        if (!$admission) {
            throw new ModelNotFoundException("Admission not found");
        }

        if ($admission->status !== 'active') {
            throw new InvalidArgumentException("Admission must be active to create a claim");
        }

        // Check no claim exists for this admission
        if ($admission->claim) {
            throw new InvalidArgumentException("Claim already exists for this admission");
        }

        // Get UTN from admission's referral
        $utn = $admission->referral?->utn;

        $claim = new Claim([
            'admission_id' => $admissionId,
            'enrollee_id' => $admission->enrollee_id,
            'facility_id' => $admission->facility_id,
            'utn' => $utn, // Denormalized for faster lookups
            'status' => 'DRAFT',
            'claim_date' => $data['claim_date'] ?? now(),
            'submitted_by' => auth()->id(),
        ]);

        $claim->save();

        return $claim;
    }

    /**
     * Create a new claim from an admission with line items
     *
     * @param int $admissionId
     * @param string $claimDate
     * @param array $lineItems
     * @return Claim
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function createClaimWithLineItems(int $admissionId, string $claimDate, array $lineItems): Claim
    {
        $admission = Admission::find($admissionId);
        if (!$admission) {
            throw new ModelNotFoundException("Admission not found");
        }

        if ($admission->status !== 'discharged') {
            throw new InvalidArgumentException("Admission must be discharged to create a claim");
        }

        // Check no claim exists for this admission
        if ($admission->claim) {
            throw new InvalidArgumentException("Claim already exists for this admission");
        }

        // Get UTN from admission's referral
        $utn = $admission->referral?->utn;

        if (!$utn) {
            throw new InvalidArgumentException("Admission must have a valid UTN");
        }

        // Validate UTN is validated
        if (!$admission->referral->utn_validated) {
            throw new InvalidArgumentException("UTN must be validated before creating a claim");
        }

        // Use transaction to ensure atomicity
        return DB::transaction(function () use ($admission, $utn, $claimDate, $lineItems) {
            // Create the claim
            $claim = new Claim([
                'admission_id' => $admission->id,
                'enrollee_id' => $admission->enrollee_id,
                'facility_id' => $admission->facility_id,
                'utn' => $utn,
                'status' => 'DRAFT',
                'claim_date' => $claimDate,
                'claim_number' => 'CLM-' . strtoupper(uniqid()),
                'submitted_by' => auth()->id(),
            ]);

            $claim->save();

            // Create line items
            $bundleAmount = 0;
            $ffsAmount = 0;

            foreach ($lineItems as $lineItem) {
                $lineTotal = (float) $lineItem['line_total'];

                ClaimLine::create([
                    'claim_id' => $claim->id,
                    'pa_code_id' => $lineItem['pa_code_id'],
                    'tariff_type' => $lineItem['tariff_type'],
                    'service_type' => $lineItem['service_type'],
                    'service_description' => $lineItem['service_description'],
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'line_total' => $lineTotal,
                    'reporting_type' => $lineItem['reporting_type'],
                    'reported_diagnosis_code' => $lineItem['reported_diagnosis_code'] ?? null,
                ]);

                // Accumulate amounts
                if ($lineItem['tariff_type'] === 'BUNDLE') {
                    $bundleAmount += $lineTotal;
                } else {
                    $ffsAmount += $lineTotal;
                }
            }

            // Update claim totals
            $claim->update([
                'bundle_amount' => $bundleAmount,
                'ffs_amount' => $ffsAmount,
                'total_amount_claimed' => $bundleAmount + $ffsAmount,
            ]);

            return $claim->fresh(['lineItems', 'admission', 'enrollee', 'facility']);
        });
    }

    /**
     * Submit a claim for review
     *
     * @param Claim $claim
     * @return Claim
     * @throws InvalidArgumentException
     */
    public function submitClaim(Claim $claim): Claim
    {
        if ($claim->status !== 'DRAFT') {
            throw new InvalidArgumentException("Only DRAFT claims can be submitted");
        }

        if ($claim->lineItems()->count() === 0) {
            throw new InvalidArgumentException("Claim must have at least one line item");
        }

        $claim->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        return $claim;
    }

    /**
     * Validate a claim and return alerts
     * 
     * @param Claim $claim
     * @return array - validation alerts
     */
    public function validateClaim(Claim $claim): array
    {
        return $this->validationService->runChecks($claim);
    }

    /**
     * Approve a claim
     * 
     * @param Claim $claim
     * @param array $data - approval_comments, etc.
     * @return Claim
     * @throws InvalidArgumentException
     */
    public function approveClaim(Claim $claim, array $data = []): Claim
    {
        if ($claim->status !== 'SUBMITTED' && $claim->status !== 'REVIEWING') {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be approved. Current status: {$claim->status}"
            );
        }

        $claim->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'approval_comments' => $data['approval_comments'] ?? null,
        ]);

        return $claim;
    }

    /**
     * Reject a claim
     * 
     * @param Claim $claim
     * @param array $data - rejection_reason, etc.
     * @return Claim
     * @throws InvalidArgumentException
     */
    public function rejectClaim(Claim $claim, array $data = []): Claim
    {
        if ($claim->status !== 'SUBMITTED' && $claim->status !== 'REVIEWING') {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be rejected. Current status: {$claim->status}"
            );
        }

        $claim->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return $claim;
    }

    /**
     * Move claim to review status
     * 
     * @param Claim $claim
     * @return Claim
     */
    public function moveToReview(Claim $claim): Claim
    {
        if ($claim->status !== 'SUBMITTED') {
            throw new InvalidArgumentException("Only SUBMITTED claims can move to REVIEWING");
        }

        $claim->update(['status' => 'REVIEWING']);

        return $claim;
    }

    /**
     * Get claim summary with totals
     * 
     * @param Claim $claim
     * @return array
     */
    public function getClaimSummary(Claim $claim): array
    {
        $bundleTotal = $claim->lineItems()
            ->where('tariff_type', 'BUNDLE')
            ->sum('line_total');

        $ffsTotal = $claim->lineItems()
            ->where('tariff_type', 'FFS')
            ->sum('line_total');

        return [
            'claim_id' => $claim->id,
            'status' => $claim->status,
            'bundle_total' => $bundleTotal,
            'ffs_total' => $ffsTotal,
            'total_claimed' => $bundleTotal + $ffsTotal,
            'line_items_count' => $claim->lineItems()->count(),
            'bundle_items_count' => $claim->lineItems()->where('tariff_type', 'BUNDLE')->count(),
            'ffs_items_count' => $claim->lineItems()->where('tariff_type', 'FFS')->count(),
        ];
    }
}

