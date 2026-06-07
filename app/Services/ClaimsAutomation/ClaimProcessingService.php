<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\AuditTrail;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Models\Referral;
use App\Services\ClaimValidationService;
use App\Services\EligibilityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use InvalidArgumentException;

class ClaimProcessingService
{
    public function __construct(private ClaimValidationService $validationService)
    {
    }

    public function createClaim(int $admissionId, array $data): Claim
    {
        $admission = Admission::find($admissionId);
        if (!$admission) {
            throw new ModelNotFoundException('Admission not found');
        }

        if (!$this->admissionStatusIs($admission, 'active')) {
            throw new InvalidArgumentException('Admission must be active to create a claim');
        }

        $serviceDate = $data['service_date'] ?? $data['claim_date'] ?? now();
        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $admission->enrollee_id,
            (int) $admission->facility_id,
            $serviceDate
        );

        if ($admission->claim) {
            throw new InvalidArgumentException('Claim already exists for this admission');
        }

        $claim = new Claim([
            'admission_id' => $admissionId,
            'referral_id' => $admission->referral_id,
            'enrollee_id' => $admission->enrollee_id,
            'facility_id' => $admission->facility_id,
            'utn' => $admission->referral?->utn,
            'claim_number' => Claim::generateClaimNumber(),
            'status' => Claim::STATUS_DRAFT,
            'claim_date' => $data['claim_date'] ?? now(),
            'service_date' => $serviceDate,
            'submitted_by' => auth()->id(),
        ]);

        $claim->save();

        return $claim;
    }

    public function createClaimWithLineItems(int $admissionId, string $claimDate, array $lineItems): Claim
    {
        $admission = Admission::find($admissionId);
        if (!$admission) {
            throw new ModelNotFoundException('Admission not found');
        }

        if (!$this->admissionStatusIs($admission, 'discharged')) {
            throw new InvalidArgumentException('Admission must be discharged to create a claim');
        }

        if ($admission->claim) {
            throw new InvalidArgumentException('Claim already exists for this admission');
        }

        $referral = $admission->referral;
        $utn = $referral?->utn;

        if (!$utn) {
            throw new InvalidArgumentException('Admission must have a valid UTN');
        }

        if (!$referral->utn_validated) {
            throw new InvalidArgumentException('UTN must be validated before creating a claim');
        }

        if ($referral->isExpired()) {
            throw new InvalidArgumentException(
                "Cannot create claim: The referral UTN has expired (valid until: {$referral->valid_until}). A new referral is required."
            );
        }

        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $admission->enrollee_id,
            (int) $admission->facility_id,
            $claimDate
        );

        return DB::transaction(function () use ($admission, $referral, $claimDate, $lineItems): Claim {
            $claim = Claim::create([
                'admission_id' => $admission->id,
                'referral_id' => $admission->referral_id,
                'enrollee_id' => $admission->enrollee_id,
                'facility_id' => $admission->facility_id,
                'utn' => $referral?->utn,
                'status' => Claim::STATUS_DRAFT,
                'claim_date' => $claimDate,
                'service_date' => $claimDate,
                'claim_number' => Claim::generateClaimNumber(),
                'submitted_by' => auth()->id(),
            ]);

            [$bundleAmount, $ffsAmount] = $this->createClaimLineItems($claim, $lineItems, $referral, $admission, 0, null, []);

            $claim->update([
                'bundle_amount' => $bundleAmount,
                'ffs_amount' => $ffsAmount,
                'total_amount' => $bundleAmount + $ffsAmount,
                'total_amount_claimed' => $bundleAmount + $ffsAmount,
            ]);

            return $claim->fresh(['lineItems', 'admission', 'referral', 'enrollee', 'facility']);
        });
    }

    public function createDraftClaimFromReferral(Referral $referral, array $data, ?Admission $admission = null): Claim
    {
        $serviceDate = $admission?->admission_date ?? ($data['claim_date'] ?? now());

        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $referral->enrollee_id,
            (int) $referral->receiving_facility_id,
            $serviceDate
        );

        return DB::transaction(function () use ($referral, $data, $admission, $serviceDate): Claim {
            $claim = Claim::create([
                'referral_id' => $referral->id,
                'admission_id' => $admission?->id,
                'enrollee_id' => $referral->enrollee_id,
                'facility_id' => $referral->receiving_facility_id,
                'claim_number' => Claim::generateClaimNumber(),
                'utn' => $referral->utn,
                'status' => Claim::STATUS_DRAFT,
                'claim_date' => $data['claim_date'] ?? now(),
                'service_date' => $serviceDate,
                'submitted_by' => auth()->id(),
            ]);

            [$bundleAmount, $ffsAmount] = $this->createClaimLineItems(
                $claim,
                $data['line_items'] ?? [],
                $referral,
                $admission,
                (float) ($data['bundle_amount'] ?? 0),
                isset($data['bundle_pa_code_id']) ? (int) $data['bundle_pa_code_id'] : null,
                $data['bundle_components'] ?? []
            );

            $totalAmount = $bundleAmount + $ffsAmount;
            $claim->update([
                'bundle_amount' => $bundleAmount,
                'ffs_amount' => $ffsAmount,
                'total_amount' => $totalAmount,
                'total_amount_claimed' => $totalAmount,
            ]);

            $this->writeAudit($claim, 'claim_created', "Claim {$claim->claim_number} created as draft.", [], [
                'status' => Claim::STATUS_DRAFT,
                'bundle_amount' => $bundleAmount,
                'ffs_amount' => $ffsAmount,
            ]);

            return $claim->fresh(['referral', 'admission', 'enrollee', 'facility', 'lineItems.paCode']);
        });
    }

    public function submitClaim(Claim $claim): Claim
    {
        if ($claim->status !== Claim::STATUS_DRAFT) {
            throw new InvalidArgumentException('Only DRAFT claims can be submitted');
        }

        if ($claim->lineItems()->count() === 0) {
            throw new InvalidArgumentException('Claim must have at least one line item');
        }

        app(EligibilityService::class)->assertFacilityMatchesCoverage(
            $claim->enrollee_id,
            (int) $claim->facility_id,
            $claim->service_date ?? $claim->claim_date ?? now()
        );

        $claim->update([
            'status' => Claim::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        if ($claim->referral) {
            $claim->referral->markClaimSubmitted();
        }

        $this->writeAudit($claim, 'claim_submitted', "Claim {$claim->claim_number} submitted for review.", [
            'status' => Claim::STATUS_DRAFT,
        ], [
            'status' => Claim::STATUS_SUBMITTED,
        ]);

        return $claim->fresh(['referral', 'admission', 'enrollee', 'facility', 'lineItems']);
    }

    public function validateClaim(Claim $claim): array
    {
        return $this->validationService->runChecks($claim);
    }

    public function approveClaim(Claim $claim, array $data = []): Claim
    {
        if (!$this->isReviewable($claim)) {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be approved. Current status: {$claim->status}"
            );
        }

        $this->assertDifferentAdjudicator($claim);

        $oldStatus = $claim->status;
        $claim->update([
            'status' => Claim::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'reviewed_by' => $claim->reviewed_by ?? auth()->id(),
            'reviewed_at' => $claim->reviewed_at ?? now(),
            'approved_amount' => $data['approved_amount'] ?? ($claim->approved_amount ?? $claim->total_amount_claimed),
            'approval_comments' => $data['approval_comments'] ?? $data['comments'] ?? null,
            'payment_code' => $data['payment_code'] ?? $claim->payment_code,
        ]);

        $this->writeAudit($claim, 'claim_approved', "Claim {$claim->claim_number} approved. Previous status: {$oldStatus}", [
            'status' => $oldStatus,
        ], [
            'status' => Claim::STATUS_APPROVED,
            'approved_amount' => $claim->approved_amount,
        ]);

        return $claim->fresh(['referral', 'admission', 'enrollee', 'facility', 'lineItems']);
    }

    public function rejectClaim(Claim $claim, array $data = []): Claim
    {
        if (!$this->isReviewable($claim)) {
            throw new InvalidArgumentException(
                "Only SUBMITTED or REVIEWING claims can be rejected. Current status: {$claim->status}"
            );
        }

        $this->assertDifferentAdjudicator($claim);

        if (empty($data['rejection_reason'])) {
            throw new InvalidArgumentException('A rejection reason is mandatory when rejecting a claim.');
        }

        $oldStatus = $claim->status;
        $claim->update([
            'status' => Claim::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => auth()->id(),
            'reviewed_by' => $claim->reviewed_by ?? auth()->id(),
            'reviewed_at' => $claim->reviewed_at ?? now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        $this->writeAudit($claim, 'claim_rejected', "Claim {$claim->claim_number} rejected.", [
            'status' => $oldStatus,
        ], [
            'status' => Claim::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return $claim->fresh(['referral', 'admission', 'enrollee', 'facility', 'lineItems']);
    }

    public function moveToReview(Claim $claim, array $data = []): Claim
    {
        if ($claim->status !== Claim::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only SUBMITTED claims can move to REVIEWING');
        }

        $this->assertDifferentAdjudicator($claim);

        $claim->update([
            'status' => Claim::STATUS_REVIEWING,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'approval_comments' => $data['comments'] ?? $claim->approval_comments,
        ]);

        $this->writeAudit($claim, 'claim_reviewing', "Claim {$claim->claim_number} moved to review.", [
            'status' => Claim::STATUS_SUBMITTED,
        ], [
            'status' => Claim::STATUS_REVIEWING,
        ]);

        return $claim->fresh(['referral', 'admission', 'enrollee', 'facility', 'lineItems']);
    }

    public function reviewClaim(Claim $claim, array $data): Claim
    {
        if (!$this->isReviewable($claim) && $claim->status !== Claim::STATUS_SUBMITTED) {
            throw new InvalidArgumentException('Only submitted or reviewing claims can be processed');
        }

        return DB::transaction(function () use ($claim, $data): Claim {
            $adjustedFfsAmount = $this->applyLineItemAdjustments($claim, $data['line_item_adjustments'] ?? []);
            $finalApprovedAmount = $data['approved_amount'] ?? (($claim->bundle_amount ?? 0) + $adjustedFfsAmount);

            return match ($data['status']) {
                Claim::STATUS_REVIEWING => $this->moveToReview($claim, $data),
                Claim::STATUS_APPROVED => $this->approveClaim($claim, [
                    'approved_amount' => $finalApprovedAmount,
                    'approval_comments' => $data['comments'] ?? null,
                    'payment_code' => $data['payment_code'] ?? null,
                ]),
                Claim::STATUS_REJECTED => $this->rejectClaim($claim, [
                    'rejection_reason' => $data['comments'] ?? $data['rejection_reason'] ?? null,
                ]),
                default => throw new InvalidArgumentException('Unsupported claim review status.'),
            };
        });
    }

    public function batchReviewClaims(array $claimIds, string $decision, array $data = []): array
    {
        $processed = 0;
        $errors = [];
        $claims = [];

        foreach ($claimIds as $claimId) {
            $claim = Claim::find($claimId);
            if (!$claim) {
                $errors[] = "Claim {$claimId} was not found.";
                continue;
            }

            try {
                $claims[] = match ($decision) {
                    Claim::STATUS_APPROVED => $this->approveClaim($claim, $data),
                    Claim::STATUS_REJECTED => $this->rejectClaim($claim, $data),
                    default => throw new InvalidArgumentException('Unsupported batch review decision.'),
                };
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = "Claim {$claim->claim_number}: {$e->getMessage()}";
            }
        }

        return [
            'processed_count' => $processed,
            'errors' => $errors,
            'claims' => $claims,
        ];
    }

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

    private function createClaimLineItems(
        Claim $claim,
        array $lineItems,
        Referral $referral,
        ?Admission $admission,
        float $bundleAmount,
        ?int $bundlePaCodeId,
        array $bundleComponents
    ): array {
        $ffsAmount = 0;

        if ($bundleAmount > 0) {
            ClaimLine::create([
                'claim_id' => $claim->id,
                'case_record_id' => $referral->service_bundle_id,
                'pa_code_id' => $bundlePaCodeId,
                'bundle_id' => $admission?->service_bundle_id ?? $referral->service_bundle_id,
                'bundle_component_id' => $bundleComponents[0]['bundle_component_id'] ?? null,
                'service_description' => $referral->serviceBundle?->name ?? 'Bundle Claim',
                'quantity' => 1,
                'unit_price' => $bundleAmount,
                'line_total' => $bundleAmount,
                'tariff_type' => 'BUNDLE',
                'service_type' => 'service',
                'reporting_type' => 'IN_BUNDLE',
            ]);
        }

        foreach ($lineItems as $lineItem) {
            $lineTotal = (float) ($lineItem['line_total'] ?? ((float) $lineItem['unit_price'] * (int) $lineItem['quantity']));
            $ffsAmount += $lineTotal;

            ClaimLine::create([
                'claim_id' => $claim->id,
                'pa_code_id' => $lineItem['pa_code_id'],
                'case_record_id' => $lineItem['case_record_id'] ?? null,
                'service_description' => $lineItem['service_description'],
                'quantity' => $lineItem['quantity'],
                'unit_price' => $lineItem['unit_price'],
                'line_total' => $lineTotal,
                'tariff_type' => $lineItem['tariff_type'],
                'service_type' => $lineItem['service_type'] ?? 'service',
                'reporting_type' => $lineItem['reporting_type'] ?? ($lineItem['tariff_type'] === 'BUNDLE' ? 'IN_BUNDLE' : 'FFS_TOP_UP'),
                'bundle_id' => $lineItem['bundle_id'] ?? null,
                'bundle_component_id' => $lineItem['bundle_component_id'] ?? null,
            ]);
        }

        return [$bundleAmount, $ffsAmount];
    }

    private function applyLineItemAdjustments(Claim $claim, array $adjustments): float
    {
        if ($adjustments === []) {
            return (float) $claim->lineItems()->where('tariff_type', 'FFS')->sum('line_total');
        }

        $adjustedFfsAmount = 0;
        foreach ($adjustments as $adjustment) {
            $lineItem = $claim->lineItems()->find($adjustment['id']);
            if (!$lineItem) {
                continue;
            }

            $approvedQty = !empty($adjustment['included']) ? (int) $adjustment['approved_quantity'] : 0;
            $approvedAmount = $approvedQty * (float) $lineItem->unit_price;

            $lineItem->update([
                'approved_quantity' => $approvedQty,
                'approved_amount' => $approvedAmount,
                'is_approved' => (bool) $adjustment['included'],
            ]);

            if ($lineItem->tariff_type === 'FFS' && !empty($adjustment['included'])) {
                $adjustedFfsAmount += $approvedAmount;
            }
        }

        return $adjustedFfsAmount;
    }

    private function admissionStatusIs(Admission $admission, string $expected): bool
    {
        return strtolower((string) $admission->status) === strtolower($expected);
    }

    private function isReviewable(Claim $claim): bool
    {
        return in_array($claim->status, [Claim::STATUS_SUBMITTED, Claim::STATUS_REVIEWING], true);
    }

    private function assertDifferentAdjudicator(Claim $claim): void
    {
        if ($claim->submitted_by && (int) $claim->submitted_by === (int) auth()->id()) {
            throw new InvalidArgumentException(
                'BR-06 violation: The user who submitted this claim cannot adjudicate it. A different officer must review it.'
            );
        }
    }

    private function writeAudit(Claim $claim, string $action, string $description, array $oldValues, array $newValues): void
    {
        AuditTrail::create([
            'auditable_type' => Claim::class,
            'auditable_id' => $claim->id,
            'action' => $action,
            'description' => $description,
            'user_id' => auth()->id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
        ]);
    }
}
