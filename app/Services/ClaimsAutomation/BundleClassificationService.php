<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Bundle;
use App\Models\Claim;
use App\Models\ClaimTreatment;
use App\Models\PACode;
use App\Models\TariffItem;
use InvalidArgumentException;

/**
 * Simplified Bundle Classification Service
 *
 * Simple rules:
 * - Bundle items: Covered by the bundle tariff (no individual PA needed)
 * - FFS items: Require an approved PA code before they can be added
 */
class BundleClassificationService
{
    /**
     * Find bundle for a diagnosis
     */
    public function findBundleForDiagnosis(string $icd10Code, ?string $levelOfCare = null): ?Bundle
    {
        return Bundle::findByDiagnosis($icd10Code, $levelOfCare);
    }

    /**
     * Add a bundle treatment line to a claim
     * Bundle items don't require individual PA codes
     */
    public function addBundleTreatment(Claim $claim, array $data): ClaimTreatment
    {
        return ClaimTreatment::create([
            'claim_id' => $claim->id,
            'item_type' => 'bundle',
            'pa_code_id' => null,  // Bundle items don't need individual PA
            'tariff_item_id' => $data['tariff_item_id'] ?? null,
            'service_date' => $data['service_date'] ?? now(),
            'service_type' => $data['service_type'],
            'service_code' => $data['service_code'] ?? null,
            'service_description' => $data['service_description'],
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'],
            'total_amount' => ($data['quantity'] ?? 1) * $data['unit_price'],
        ]);
    }

    /**
     * Add an FFS treatment line to a claim
     *
     * ENFORCES: FFS items REQUIRE an approved PA code
     */
    public function addFFSTreatment(Claim $claim, int $paCodeId, array $data): ClaimTreatment
    {
        // ENFORCE: PA code must exist and be approved
        $paCode = PACode::find($paCodeId);

        if (!$paCode) {
            throw new InvalidArgumentException('PA code not found');
        }

        if (!in_array($paCode->status, ['active', 'used'])) {
            throw new InvalidArgumentException('PA code must be approved');
        }

        if ($paCode->pa_type !== 'ffs') {
            throw new InvalidArgumentException('PA code must be of type FFS for FFS treatments');
        }

        // Get price from tariff item if provided
        $unitPrice = $data['unit_price'] ?? 0;
        if (!empty($data['tariff_item_id'])) {
            $tariffItem = TariffItem::find($data['tariff_item_id']);
            if ($tariffItem) {
                $unitPrice = $tariffItem->price;
            }
        }

        $treatment = ClaimTreatment::create([
            'claim_id' => $claim->id,
            'item_type' => 'ffs',
            'pa_code_id' => $paCodeId,  // REQUIRED for FFS
            'tariff_item_id' => $data['tariff_item_id'] ?? null,
            'service_date' => $data['service_date'] ?? now(),
            'service_type' => $data['service_type'],
            'service_code' => $data['service_code'] ?? null,
            'service_description' => $data['service_description'],
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $unitPrice,
            'total_amount' => ($data['quantity'] ?? 1) * $unitPrice,
        ]);

        // Update claim totals
        $this->updateClaimTotals($claim);

        return $treatment;
    }

    /**
     * Update claim bundle and FFS totals
     */
    public function updateClaimTotals(Claim $claim): void
    {
        $bundleAmount = $claim->treatments()
            ->bundle()
            ->sum('total_amount');

        $ffsAmount = $claim->treatments()
            ->ffs()
            ->sum('total_amount');

        $claim->update([
            'bundle_amount' => $bundleAmount,
            'ffs_amount' => $ffsAmount,
            'total_amount_claimed' => $bundleAmount + $ffsAmount,
        ]);
    }

    /**
     * Validate that all FFS treatments have valid PA codes
     */
    public function validateFFSTreatments(Claim $claim): array
    {
        $errors = [];

        $ffsTreatments = $claim->treatments()->ffs()->get();

        foreach ($ffsTreatments as $treatment) {
            if (!$treatment->pa_code_id) {
                $errors[] = [
                    'treatment_id' => $treatment->id,
                    'error' => 'FFS treatment missing PA code',
                    'service' => $treatment->service_description,
                ];
            } elseif (!$treatment->hasValidPA()) {
                $errors[] = [
                    'treatment_id' => $treatment->id,
                    'error' => 'FFS treatment has invalid or unapproved PA code',
                    'service' => $treatment->service_description,
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}

