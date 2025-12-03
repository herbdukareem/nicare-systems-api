<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\PACode;

class ClaimValidationService
{
    /**
     * Runs automated policy checks on a submitted claim.
     * @param Claim $claim
     * @return array Compliance alerts found.
     */
    public function runChecks(Claim $claim): array
    {
        $alerts = [];
        $claimLines = $claim->lineItems; 

        // 1. CRITICAL: "One Admission, One Bundle" Enforcement
        $bundleLines = $claimLines->where('tariff_type', 'BUNDLE');

        if ($bundleLines->count() > 1) {
            $alerts[] = [
                'type' => 'CRITICAL',
                'code' => 'DOUBLE_BUNDLE',
                'message' => 'Claim contains ' . $bundleLines->count() . ' Bundles. Policy allows only ONE Bundle per episode of care.',
                'action' => 'REJECT_CLAIM',
            ];
            return $alerts; // Stop further checks if this critical error is found
        }
        
        $hasBundle = $bundleLines->count() === 1;
        $ffsLines = $claimLines->where('tariff_type', 'FFS');

        // 2. CRITICAL: UNAUTHORIZED FFS TOP-UP (The Malaria/Typhoid Policy Check)
        if ($hasBundle && $ffsLines->isNotEmpty()) {
            $bundlePaId = $bundleLines->first()->pa_code_id;
            
            // Check if any FFS line is using the BUNDLE's PA Code
            $ffsUsingBundlePa = $ffsLines->filter(function ($line) use ($bundlePaId) {
                return $line->pa_code_id === $bundlePaId;
            });
            
            if ($ffsUsingBundlePa->isNotEmpty()) {
                $alerts[] = [
                    'type' => 'CRITICAL',
                    'code' => 'UNAUTHORIZED_FFS_TOP_UP',
                    'message' => 'FFS items are incorrectly authorized by the primary Bundle PA (' . $bundlePaId . '). Secondary PA (FFS_TOP_UP type) is REQUIRED for complication costs. Reviewer must reject FFS lines or the entire claim.',
                    'action' => 'REJECT_FFS_LINES',
                ];
            } else {
                 // Check if all FFS lines have a valid FFS_TOP_UP PA
                 $unauthorizedFfs = $ffsLines->filter(function ($line) {
                     return !$line->paCode || $line->paCode->type !== PACode::TYPE_FFS_TOP_UP;
                 });

                 if ($unauthorizedFfs->isNotEmpty()) {
                     $alerts[] = [
                         'type' => 'CRITICAL',
                         'code' => 'MISSING_COMPLICATION_PA',
                         'message' => 'FFS Top-Up items found but are missing a valid complication PA code. Policy violation.',
                         'action' => 'REJECT_FFS_LINES',
                     ];
                 } else {
                     // 3. WARNING: Manual Review Trigger (Compliant Scenario)
                     $alerts[] = [
                         'type' => 'WARNING',
                         'code' => 'COMPLICATION_FFS_REVIEW',
                         'message' => 'Claim submitted with Bundle + FFS Top-Ups. Linked to separate PA codes. Requires manual review to confirm clinical justification.',
                         'action' => 'RESOLVE_ALERT',
                     ];
                 }
            }
        }

    

        return $alerts;
    }
}