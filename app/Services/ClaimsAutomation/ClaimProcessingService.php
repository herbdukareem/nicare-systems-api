<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\Claim;
use App\Services\ClaimValidationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        $claim = new Claim([
            'admission_id' => $admissionId,
            'enrollee_id' => $admission->enrollee_id,
            'facility_id' => $admission->facility_id,
            'status' => 'DRAFT',
            'claim_date' => $data['claim_date'] ?? now(),
            'submitted_by' => auth()->id(),
        ]);

        $claim->save();

        return $claim;
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

