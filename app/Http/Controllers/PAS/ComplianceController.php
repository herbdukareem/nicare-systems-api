<?php

namespace App\Http\Controllers\PAS;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Services\ClaimValidationService;

class ComplianceController extends Controller
{
    protected $claimValidator;

    public function __construct(ClaimValidationService $claimValidator)
    {
        $this->claimValidator = $claimValidator;
    }

    /**
     * Handles GET /v1/pas/claims/automation/{claimId}/alerts
     * Retrieves compliance alerts for a claim.
     */
    public function getComplianceAlerts(Claim $claim)
    {
        // Reruns the validation service or fetches saved alerts
        $alerts = $this->claimValidator->runChecks($claim);

        return response()->json($alerts);
    }

    /**
     * Handles POST /v1/pas/claims/automation/alerts/{alertId}/resolve
     * Simulates a reviewer resolving an alert after manual verification.
     */
    public function resolveAlert(Claim $claim) // Assuming 'alertId' resolves to a claim
    {
        // In a real system, this would update the status of the specific alert record.
        // For the purpose of this mock, we assume resolution means allowing approval.
        
        $claim->update(['status' => 'REVIEWED_COMPLIANT']);
        return response()->json(['message' => 'Compliance alert resolved. Claim status updated to REVIEWED_COMPLIANT.', 'claim_status' => $claim->status]);
    }
}