<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Claim;
use App\Models\ClaimLine;
use App\Models\PACode;
use App\Models\TariffItem;
use InvalidArgumentException;

/**
 * BundleClassificationService
 * 
 * Handles classification of claim line items as Bundle or FFS.
 * Enforces policy: One bundle per claim, FFS requires separate PA code
 */
class BundleClassificationService
{
    /**
     * Add a bundle treatment line to a claim
     * 
     * @param Claim $claim
     * @param int $paCodeId - BUNDLE type PA code
     * @param array $data - service_description, quantity, unit_price, etc.
     * @return ClaimLine
     * @throws InvalidArgumentException
     */
    public function addBundleTreatment(Claim $claim, int $paCodeId, array $data): ClaimLine
    {
        // Validate PA code is BUNDLE type
        $paCode = PACode::find($paCodeId);
        if (!$paCode || $paCode->type !== PACode::TYPE_BUNDLE) {
            throw new InvalidArgumentException("PA code must be of type BUNDLE");
        }

        // Enforce: Only one bundle per claim
        $existingBundle = $claim->lineItems()
            ->where('tariff_type', 'BUNDLE')
            ->first();

        if ($existingBundle) {
            throw new InvalidArgumentException("Claim already has a bundle line. Only one bundle allowed per claim.");
        }

        // Create bundle line
        $lineTotal = ($data['quantity'] ?? 1) * ($data['unit_price'] ?? 0);

        $claimLine = new ClaimLine([
            'claim_id' => $claim->id,
            'pa_code_id' => $paCodeId,
            'tariff_type' => 'BUNDLE',
            'service_description' => $data['service_description'] ?? 'Bundle Service',
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'] ?? 0,
            'line_total' => $lineTotal,
            'reporting_type' => 'IN_BUNDLE',
        ]);

        $claimLine->save();

        // Update claim totals
        $this->updateClaimTotals($claim);

        return $claimLine;
    }

    /**
     * Add an FFS (Fee-For-Service) treatment line to a claim
     * 
     * @param Claim $claim
     * @param int $paCodeId - FFS_TOP_UP type PA code
     * @param array $data - service_description, quantity, unit_price, etc.
     * @return ClaimLine
     * @throws InvalidArgumentException
     */
    public function addFFSTreatment(Claim $claim, int $paCodeId, array $data): ClaimLine
    {
        // Validate PA code is FFS_TOP_UP type
        $paCode = PACode::find($paCodeId);
        if (!$paCode || $paCode->type !== PACode::TYPE_FFS_TOP_UP) {
            throw new InvalidArgumentException("PA code must be of type FFS_TOP_UP for FFS treatments");
        }

        // Check if claim has a bundle - if yes, FFS must use different PA
        $bundleLine = $claim->lineItems()
            ->where('tariff_type', 'BUNDLE')
            ->first();

        if ($bundleLine && $bundleLine->pa_code_id === $paCodeId) {
            throw new InvalidArgumentException(
                "FFS treatment cannot use the same PA code as the bundle. Must use separate FFS_TOP_UP PA code."
            );
        }

        // Create FFS line
        $lineTotal = ($data['quantity'] ?? 1) * ($data['unit_price'] ?? 0);

        $claimLine = new ClaimLine([
            'claim_id' => $claim->id,
            'pa_code_id' => $paCodeId,
            'tariff_type' => 'FFS',
            'service_description' => $data['service_description'] ?? 'FFS Service',
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'] ?? 0,
            'line_total' => $lineTotal,
            'reporting_type' => $bundleLine ? 'FFS_TOP_UP' : 'FFS_STANDALONE',
        ]);

        $claimLine->save();

        // Update claim totals
        $this->updateClaimTotals($claim);

        return $claimLine;
    }

    /**
     * Classify treatments in a claim (bundle vs FFS)
     * 
     * @param Claim $claim
     * @return array - ['bundle_count' => int, 'ffs_count' => int, 'total_amount' => decimal]
     */
    public function classifyTreatments(Claim $claim): array
    {
        $bundleLines = $claim->lineItems()->where('tariff_type', 'BUNDLE')->get();
        $ffsLines = $claim->lineItems()->where('tariff_type', 'FFS')->get();

        $bundleTotal = $bundleLines->sum('line_total');
        $ffsTotal = $ffsLines->sum('line_total');

        return [
            'bundle_count' => $bundleLines->count(),
            'ffs_count' => $ffsLines->count(),
            'bundle_total' => $bundleTotal,
            'ffs_total' => $ffsTotal,
            'total_amount' => $bundleTotal + $ffsTotal,
        ];
    }

    /**
     * Update claim totals based on line items
     * 
     * @param Claim $claim
     * @return void
     */
    private function updateClaimTotals(Claim $claim): void
    {
        $bundleTotal = $claim->lineItems()
            ->where('tariff_type', 'BUNDLE')
            ->sum('line_total');

        $ffsTotal = $claim->lineItems()
            ->where('tariff_type', 'FFS')
            ->sum('line_total');

        $claim->update([
            'bundle_amount' => $bundleTotal,
            'ffs_amount' => $ffsTotal,
            'total_amount_claimed' => $bundleTotal + $ffsTotal,
        ]);
    }
}

