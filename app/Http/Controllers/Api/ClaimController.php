<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Get list of claims with filters
     * GET /api/claims
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Claim::with(['admission.referral', 'enrollee', 'facility', 'lineItems']);

            // Filter by referral_id (through admission)
            if ($request->filled('referral_id')) {
                $query->whereHas('admission', function ($q) use ($request) {
                    $q->where('referral_id', $request->referral_id);
                });
            }

            // Filter by admission_id
            if ($request->filled('admission_id')) {
                $query->where('admission_id', $request->admission_id);
            }

            // Filter by enrollee_id
            if ($request->filled('enrollee_id')) {
                $query->where('enrollee_id', $request->enrollee_id);
            }

            // Filter by facility_id
            if ($request->filled('facility_id')) {
                $query->where('facility_id', $request->facility_id);
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Filter by payment_status
            if ($request->filled('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            // Search by claim_number
            if ($request->filled('search')) {
                $query->where('claim_number', 'like', '%' . $request->search . '%');
            }

            // Order by
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate or get all
            $perPage = $request->get('per_page', 15);
            $claims = $perPage > 0 ? $query->paginate($perPage) : $query->get();

            return response()->json([
                'success' => true,
                'data' => $claims,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve claims: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new claim from an admission
     * POST /api/claims
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'admission_id' => 'required|integer|exists:admissions,id',
                'claim_date' => 'required|date',
            ]);

            $claim = $this->claimProcessingService->createClaim(
                $validated['admission_id'],
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'Claim created successfully',
                'data' => $claim->load(['admission', 'enrollee', 'facility', 'lineItems']),
            ], 201);
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

