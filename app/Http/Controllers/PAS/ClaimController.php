<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClaimController extends Controller
{
    private ClaimProcessingService $claimProcessingService;
    private ClaimValidationService $validationService;

    public function __construct(
        ClaimProcessingService $claimProcessingService,
        ClaimValidationService $validationService
    ) {
        $this->claimProcessingService = $claimProcessingService;
        $this->validationService = $validationService;
    }

    /**
     * Create a new claim using UTN and a list of line items
     * POST /api/claims
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // 1. Validate the full payload required by the PAS system
            $validated = $request->validate([
                'utn_key' => 'required|string|exists:referrals,utn', // Must link to an existing Referral
                'claim_date' => 'required|date',
                'clinical_summary' => 'required|string',
                'total_amount' => 'required|numeric|min:0',
                'claim_type' => 'required|in:BUNDLE,FFS,HYBRID',
                'enrollee_id' => 'required|integer|exists:enrollees,id', // Required from frontend context
                'facility_id' => 'required|integer|exists:facilities,id', // Required from frontend context
                
                // Validate the array of line items
                'claim_lines' => 'required|array|min:1',
                // Ensure array fields link to valid IDs in the database
                'claim_lines.*.pa_code_id' => 'required|integer|exists:pa_codes,id', 
                'claim_lines.*.case_record_id' => 'required|integer|exists:cases,id', // CaseRecord ID is the tariff item ID
                'claim_lines.*.reporting_type' => 'required|in:IN_BUNDLE,FFS_TOP_UP', // Critical for compliance
                'claim_lines.*.quantity' => 'required|numeric|min:0.01',
                'claim_lines.*.unit_price' => 'required|numeric|min:0',
            ]);

            // 2. The service handles creating the claim, linking the lines, and initial validation
            $claim = $this->claimProcessingService->createClaimWithLines($validated);

            return response()->json([
                'success' => true,
                'message' => 'Claim saved successfully. Pending validation.',
                'data' => $claim->load(['enrollee', 'facility', 'lineItems']),
            ], 201);
        } catch (ValidationException $e) {
             return response()->json([
                'success' => false,
                'message' => 'Validation Failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get claim details
     * GET /api/claims/{id}
     */
    public function show(Claim $claim): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $claim->load(['admission', 'enrollee', 'facility', 'lineItems', 'alerts']),
        ]);
    }

    /**
     * Submit a claim for review
     * POST /api/claims/{id}/submit
     */
    public function submit(Claim $claim): JsonResponse
    {
        try {
            $claim = $this->claimProcessingService->submitClaim($claim);

            return response()->json([
                'success' => true,
                'message' => 'Claim submitted successfully',
                'data' => $claim,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate a claim
     * POST /api/claims/{id}/validate
     */
    public function validate(Claim $claim): JsonResponse
    {
        try {
            $alerts = $this->claimProcessingService->validateClaim($claim);

            return response()->json([
                'success' => true,
                'alerts' => $alerts,
                'has_critical_alerts' => collect($alerts)->where('severity', 'CRITICAL')->count() > 0,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve a claim
     * POST /api/claims/{id}/approve
     */
    public function approve(Request $request, Claim $claim): JsonResponse
    {
        try {
            $validated = $request->validate([
                'approval_comments' => 'nullable|string',
            ]);

            $claim = $this->claimProcessingService->approveClaim($claim, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Claim approved successfully',
                'data' => $claim,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a claim
     * POST /api/claims/{id}/reject
     */
    public function reject(Request $request, Claim $claim): JsonResponse
    {
        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string',
            ]);

            $claim = $this->claimProcessingService->rejectClaim($claim, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Claim rejected successfully',
                'data' => $claim,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get claim summary
     * GET /api/claims/{id}/summary
     */
    public function summary(Claim $claim): JsonResponse
    {
        $summary = $this->claimProcessingService->getClaimSummary($claim);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}