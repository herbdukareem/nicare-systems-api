<?php

namespace App\Services\ClaimsAutomation;

use App\Models\Admission;
use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use App\Models\ClaimTreatment;
use App\Models\PACode;
use App\Models\PACodeLineItem;
use App\Models\Referral;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PAAutomationService
{
    /**
     * Generate PA code for a new diagnosis during admission
     */
    public function generatePAForDiagnosis(Admission $admission, ClaimDiagnosis $diagnosis, bool $isComplication = false): PACode
    {
        // Get the next PA sequence for this admission
        $sequence = $this->getNextPASequence($admission);

        // Determine tariff type based on whether it's a complication
        $tariffType = $isComplication ? 'ffs' : 'bundle';

        // Get parent PA if this is a complication
        $parentPA = $isComplication ? $admission->principalPACode : null;

        $paCode = PACode::create([
            'referral_id' => $admission->referral_id,
            'admission_id' => $admission->id,
            'pa_sequence' => $sequence,
            'diagnosis_code_at_issue' => $diagnosis->icd_10_code,
            'diagnosis_description_at_issue' => $diagnosis->diagnosis_description,
            'parent_pa_id' => $parentPA?->id,
            'is_for_complication' => $isComplication,
            'tariff_type' => $tariffType,
            'utn' => $this->generateUTN(),
            'status' => 'pending',
            'valid_from' => now(),
            'valid_until' => now()->addDays(30),
        ]);

        return $paCode;
    }

    /**
     * Get the next PA sequence number for an admission
     */
    protected function getNextPASequence(Admission $admission): int
    {
        $maxSequence = PACode::where('admission_id', $admission->id)
            ->max('pa_sequence');

        return ($maxSequence ?? 0) + 1;
    }

    /**
     * Generate a unique tracking number
     */
    protected function generateUTN(): string
    {
        $prefix = 'UTN';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        return "{$prefix}{$date}{$random}";
    }

    /**
     * Link a PA code to specific line items
     */
    public function linkPAToLineItems(PACode $paCode, array $items): Collection
    {
        $lineItems = collect();

        foreach ($items as $item) {
            $lineItem = PACodeLineItem::create([
                'pa_code_id' => $paCode->id,
                'pa_sequence' => $paCode->pa_sequence,
                'billable_type' => $item['billable_type'],
                'billable_id' => $item['billable_id'],
                'item_code' => $item['item_code'],
                'item_description' => $item['item_description'],
                'tariff_type' => $item['tariff_type'] ?? 'ffs',
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'] ?? 1,
                'total_amount' => $item['unit_price'] * ($item['quantity'] ?? 1),
                'ffs_category' => $item['ffs_category'] ?? null,
                'ffs_justification' => $item['ffs_justification'] ?? null,
                'diagnosis_code' => $paCode->diagnosis_code_at_issue,
                'diagnosis_description' => $paCode->diagnosis_description_at_issue,
                'is_complication' => $paCode->is_for_complication,
                'diagnosis_detected_at' => now(),
                'status' => 'pending',
            ]);

            $lineItems->push($lineItem);
        }

        return $lineItems;
    }

    /**
     * Check if a claim treatment has a valid PA
     */
    public function treatmentHasValidPA(ClaimTreatment $treatment): bool
    {
        if (!$treatment->pa_code_line_item_id) {
            return false;
        }

        $lineItem = $treatment->paCodeLineItem;
        if (!$lineItem) {
            return false;
        }

        $paCode = $lineItem->paCode;
        if (!$paCode) {
            return false;
        }

        // Check if PA is approved and not expired
        return $paCode->status === 'approved' && 
               $paCode->valid_until >= now();
    }

    /**
     * Detect treatments that need PA codes
     */
    public function detectMissingPAs(Claim $claim): Collection
    {
        $missingPAs = collect();

        foreach ($claim->treatments as $treatment) {
            // Skip if already has PA
            if ($treatment->pa_code_line_item_id) {
                continue;
            }

            // Check if treatment requires PA
            if ($this->treatmentRequiresPA($treatment)) {
                $missingPAs->push([
                    'treatment_id' => $treatment->id,
                    'treatment_name' => $treatment->treatment_name,
                    'service_type' => $treatment->service_type,
                    'tariff_type' => $treatment->tariff_type,
                    'amount' => $treatment->total_amount,
                    'reason' => $this->getPARequirementReason($treatment),
                ]);
            }
        }

        return $missingPAs;
    }

    /**
     * Check if a treatment requires PA
     */
    protected function treatmentRequiresPA(ClaimTreatment $treatment): bool
    {
        // All bundle items require PA
        if ($treatment->tariff_type === 'bundle') {
            return true;
        }

        // FFS items above threshold require PA
        $ffsThreshold = config('claims.ffs_pa_threshold', 50000);
        if ($treatment->total_amount > $ffsThreshold) {
            return true;
        }

        // Certain service types always require PA
        $paRequiredServices = ['professional_service', 'radiology'];
        if (in_array($treatment->service_type, $paRequiredServices)) {
            return true;
        }

        return false;
    }

    /**
     * Get the reason why PA is required
     */
    protected function getPARequirementReason(ClaimTreatment $treatment): string
    {
        if ($treatment->tariff_type === 'bundle') {
            return 'Bundle items require pre-authorization';
        }

        $ffsThreshold = config('claims.ffs_pa_threshold', 50000);
        if ($treatment->total_amount > $ffsThreshold) {
            return "Amount exceeds FFS threshold of {$ffsThreshold}";
        }

        return 'Service type requires pre-authorization';
    }

    /**
     * Auto-generate PA request for a new complication
     */
    public function autoGeneratePAForComplication(Claim $claim, ClaimDiagnosis $complication): ?PACode
    {
        $admission = $claim->admission;
        if (!$admission) {
            return null;
        }

        // Generate PA for the complication
        $paCode = $this->generatePAForDiagnosis($admission, $complication, true);

        // Get treatments linked to this complication
        $treatments = $claim->treatments()
            ->where('linked_diagnosis_code', $complication->icd_10_code)
            ->get();

        // Create line items for each treatment
        $items = $treatments->map(function ($treatment) {
            return [
                'billable_type' => ClaimTreatment::class,
                'billable_id' => $treatment->id,
                'item_code' => $treatment->treatment_code ?? 'TRT-' . $treatment->id,
                'item_description' => $treatment->treatment_name,
                'tariff_type' => 'ffs',
                'unit_price' => $treatment->unit_price,
                'quantity' => $treatment->quantity,
                'ffs_category' => 'complication_treatment',
                'ffs_justification' => "Treatment for complication: {$complication->diagnosis_description}",
            ];
        })->toArray();

        if (!empty($items)) {
            $this->linkPAToLineItems($paCode, $items);
        }

        return $paCode;
    }
}

