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

        // 0. CRITICAL: Claim must be linked to valid, validated UTN
        // Updated: Admission is OPTIONAL, but referral is REQUIRED
        if (!$claim->referral) {
            $alerts[] = [
                'type' => 'CRITICAL',
                'code' => 'MISSING_REFERRAL',
                'message' => 'Claim is not linked to a valid referral. All claims must be linked to an approved referral with validated UTN.',
                'action' => 'REJECT_CLAIM',
            ];
            return $alerts;
        }

        $referral = $claim->referral;
        if (!$referral->utn_validated) {
            $alerts[] = [
                'type' => 'CRITICAL',
                'code' => 'UTN_NOT_VALIDATED',
                'message' => 'UTN has not been validated. Claims can only be submitted for validated UTNs.',
                'action' => 'REJECT_CLAIM',
            ];
            return $alerts;
        }

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

        // 1.5. CRITICAL: Bundle must match principal ICD-10 diagnosis from referral
        if ($hasBundle) {
            $bundleLine = $bundleLines->first();
            $admission = $claim->admission;

            // Check if bundle matches the principal diagnosis
            if ($admission->bundle_id && $bundleLine->bundle_id !== $admission->bundle_id) {
                $alerts[] = [
                    'type' => 'CRITICAL',
                    'code' => 'BUNDLE_DIAGNOSIS_MISMATCH',
                    'message' => 'Bundle in claim does not match the principal ICD-10 diagnosis from the referral. Expected bundle ID: ' . $admission->bundle_id . ', but got: ' . $bundleLine->bundle_id,
                    'action' => 'REJECT_CLAIM',
                ];
                return $alerts;
            }
        }

        // 2. CRITICAL: All FFS items must have approved PA codes
        if ($ffsLines->isNotEmpty()) {
            $ffsWithoutPA = $ffsLines->filter(function ($line) {
                return !$line->pa_code_id;
            });

            if ($ffsWithoutPA->isNotEmpty()) {
                $alerts[] = [
                    'type' => 'CRITICAL',
                    'code' => 'FFS_WITHOUT_PA',
                    'message' => 'FFS line items found without PA codes. All FFS services require pre-approved PA codes.',
                    'action' => 'REJECT_CLAIM',
                ];
                return $alerts;
            }

            // Check if all FFS PA codes are approved
            $ffsWithUnapprovedPA = $ffsLines->filter(function ($line) {
                return $line->paCode && $line->paCode->status !== 'APPROVED';
            });

            if ($ffsWithUnapprovedPA->isNotEmpty()) {
                $alerts[] = [
                    'type' => 'CRITICAL',
                    'code' => 'FFS_PA_NOT_APPROVED',
                    'message' => 'FFS line items found with unapproved PA codes. All PA codes must be approved before claim submission.',
                    'action' => 'REJECT_CLAIM',
                ];
                return $alerts;
            }
        }

        // 3. CRITICAL: UNAUTHORIZED FFS TOP-UP (The Malaria/Typhoid Policy Check)
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
                     // 4. WARNING: Manual Review Trigger (Compliant Scenario)
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

    /**
     * Validate that referral has a validated UTN
     *
     * @param int $referralId
     * @return array ['valid' => bool, 'message' => string, 'referral' => Referral|null]
     */
    public function validateUTN(int $referralId): array
    {
        $referral = \App\Models\Referral::find($referralId);

        if (!$referral) {
            return [
                'valid' => false,
                'message' => 'Referral not found',
                'referral' => null,
            ];
        }

        if (!$referral->utn) {
            return [
                'valid' => false,
                'message' => 'Referral does not have a UTN. UTN must be generated first.',
                'referral' => $referral,
            ];
        }

        if (!$referral->utn_validated) {
            return [
                'valid' => false,
                'message' => 'UTN has not been validated. Claims can only be submitted for validated UTNs.',
                'referral' => $referral,
            ];
        }

        return [
            'valid' => true,
            'message' => 'UTN is valid and validated',
            'referral' => $referral,
        ];
    }

    /**
     * Check if a claim already exists for this referral
     *
     * @param int $referralId
     * @return array ['exists' => bool, 'claim' => Claim|null]
     */
    public function checkDuplicateClaim(int $referralId): array
    {
        $claim = Claim::where('referral_id', $referralId)->first();

        return [
            'exists' => $claim !== null,
            'claim' => $claim,
        ];
    }

    /**
     * Validate PA codes for FFS line items
     *
     * @param array $paCodeIds Array of PA code IDs
     * @param int $referralId Referral ID to validate against
     * @return array ['valid' => bool, 'message' => string, 'paCodes' => Collection]
     */
    public function validatePACodes(array $paCodeIds, int $referralId): array
    {
        if (empty($paCodeIds)) {
            return [
                'valid' => true,
                'message' => 'No PA codes to validate',
                'paCodes' => collect([]),
            ];
        }

        $paCodes = PACode::whereIn('id', $paCodeIds)->get();

        // Check if all PA codes exist
        if ($paCodes->count() !== count($paCodeIds)) {
            return [
                'valid' => false,
                'message' => 'One or more PA codes not found',
                'paCodes' => $paCodes,
            ];
        }

        // Check if all PA codes are approved
        $unapprovedPACodes = $paCodes->where('status', '!=', 'APPROVED');
        if ($unapprovedPACodes->isNotEmpty()) {
            return [
                'valid' => false,
                'message' => 'All PA codes must be APPROVED. Found ' . $unapprovedPACodes->count() . ' unapproved PA code(s).',
                'paCodes' => $paCodes,
            ];
        }

        // Check if all PA codes belong to the same referral
        $wrongReferralPACodes = $paCodes->where('referral_id', '!=', $referralId);
        if ($wrongReferralPACodes->isNotEmpty()) {
            return [
                'valid' => false,
                'message' => 'All PA codes must belong to the same referral. Found ' . $wrongReferralPACodes->count() . ' PA code(s) from different referral(s).',
                'paCodes' => $paCodes,
            ];
        }

        return [
            'valid' => true,
            'message' => 'All PA codes are valid',
            'paCodes' => $paCodes,
        ];
    }

    /**
     * Calculate claim totals
     *
     * @param float $bundleAmount
     * @param array $claimLines Array of line items with 'total_price' key
     * @return array ['bundle_amount' => float, 'ffs_amount' => float, 'total_amount' => float]
     */
    public function calculateClaimTotals(float $bundleAmount, array $claimLines): array
    {
        $ffsAmount = array_sum(array_column($claimLines, 'total_price'));

        return [
            'bundle_amount' => $bundleAmount,
            'ffs_amount' => $ffsAmount,
            'total_amount' => $bundleAmount + $ffsAmount,
        ];
    }
}