<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\AuditTrail;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Services\ClaimValidationService;
use App\Services\EligibilityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
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

        $serviceDate = $data['service_date'] ?? $data['claim_date'] ?? now();
        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $admission->enrollee_id,
            (int) $admission->facility_id,
            $serviceDate
        );

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
            'service_date' => $serviceDate,
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

        // BR-04: referral UTN must not be expired at claim creation
        if ($admission->referral->isExpired()) {
            throw new InvalidArgumentException(
                "Cannot create claim: The referral UTN has expired (valid until: {$admission->referral->valid_until}). A new referral is required."
            );
        }

        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $admission->enrollee_id,
            (int) $admission->facility_id,
            $claimDate
        );

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
                'service_date' => $claimDate,
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

            return $claim->fresh(['lineItems', 'admission', 'coveragePeriod', 'enrollee', 'facility']);
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

        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $claim->enrollee_id,
            (int) $claim->facility_id,
            $claim->service_date ?? $claim->claim_date ?? now()
        );

        $claim->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        // Mark referral as claim submitted
        if ($claim->referral) {
            $claim->referral->markClaimSubmitted();
        }

        // BR-09: write audit trail entry
        AuditTrail::create([
            'auditable_type' => Claim::class,
            'auditable_id'   => $claim->id,
            'action'         => 'claim_submitted',
            'description'    => "Claim {$claim->claim_number} submitted for review",
            'user_id'        => auth()->id(),
            'old_values'     => ['status' => 'DRAFT'],
            'new_values'     => ['status' => 'SUBMITTED'],
            'ip_address'     => Request::ip(),
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
     * Approve a claim.
     *
     * BR-06: The user who submitted the claim cannot be the one who approves it.
     * BR-09: Every state change is written to the audit trail.
     */
    public function approveClaim(Claim $claim, array $data = []): Claim
    {
        if ($claim->status !== 'SUBMITTED' && $claim->status !== 'REVIEWING') {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be approved. Current status: {$claim->status}"
            );
        }

        // BR-06: four-eyes principle — submitter cannot approve their own claim
        if ($claim->submitted_by && $claim->submitted_by === auth()->id()) {
            throw new InvalidArgumentException(
                "BR-06 violation: The user who submitted this claim cannot approve it. A different officer must approve."
            );
        }

        $oldStatus = $claim->status;

        $claim->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'approval_comments' => $data['approval_comments'] ?? null,
        ]);

        // BR-09: write audit trail entry
        AuditTrail::create([
            'auditable_type' => Claim::class,
            'auditable_id'   => $claim->id,
            'action'         => 'claim_approved',
            'description'    => "Claim {$claim->claim_number} approved. Previous status: {$oldStatus}",
            'user_id'        => auth()->id(),
            'old_values'     => ['status' => $oldStatus],
            'new_values'     => ['status' => 'APPROVED', 'approval_comments' => $data['approval_comments'] ?? null],
            'ip_address'     => Request::ip(),
        ]);

        return $claim;
    }

    /**
     * Reject a claim.
     *
     * BR-06: The user who submitted the claim cannot be the one who rejects it.
     * BR-09: Every state change is written to the audit trail.
     */
    public function rejectClaim(Claim $claim, array $data = []): Claim
    {
        if ($claim->status !== 'SUBMITTED' && $claim->status !== 'REVIEWING') {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be rejected. Current status: {$claim->status}"
            );
        }

        // BR-06: four-eyes principle — submitter cannot reject their own claim
        if ($claim->submitted_by && $claim->submitted_by === auth()->id()) {
            throw new InvalidArgumentException(
                "BR-06 violation: The user who submitted this claim cannot reject it. A different officer must adjudicate."
            );
        }

        if (empty($data['rejection_reason'])) {
            throw new InvalidArgumentException("A rejection reason is mandatory when rejecting a claim.");
        }

        $oldStatus = $claim->status;

        $claim->update([
            'status' => 'REJECTED',
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        // BR-09: write audit trail entry
        AuditTrail::create([
            'auditable_type' => Claim::class,
            'auditable_id'   => $claim->id,
            'action'         => 'claim_rejected',
            'description'    => "Claim {$claim->claim_number} rejected. Reason: {$data['rejection_reason']}",
            'user_id'        => auth()->id(),
            'old_values'     => ['status' => $oldStatus],
            'new_values'     => ['status' => 'REJECTED', 'rejection_reason' => $data['rejection_reason']],
            'ip_address'     => Request::ip(),
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
