<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            $query = Claim::with(['referral', 'admission', 'enrollee', 'facility', 'lineItems']);

            // Filter by referral_id (direct relationship)
            if ($request->filled('referral_id')) {
                $query->where('referral_id', $request->referral_id);
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
     * Create a new claim with optional admission and FFS line items
     * POST /api/claims-automation/claims
     *
     * Supports:
     * - Bundle-only claims (admission with bundle, no FFS)
     * - FFS-only claims (no admission, or admission without bundle)
     * - Bundle + FFS claims (admission with bundle + FFS line items)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'referral_id' => 'required|integer|exists:referrals,id',
                'admission_id' => 'nullable|integer|exists:admissions,id',
                'claim_date' => 'nullable|date',
                'line_items' => 'nullable|array',
                'line_items.*.pa_code_id' => 'required|integer|exists:pa_codes,id',
                'line_items.*.service_description' => 'required|string',
                'line_items.*.quantity' => 'required|integer|min:1',
                'line_items.*.unit_price' => 'required|numeric|min:0',
                'line_items.*.total_price' => 'required|numeric|min:0',
            ]);

            // 1. Validate UTN
            $utnValidation = $this->validationService->validateUTN($validated['referral_id']);
            if (!$utnValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $utnValidation['message'],
                ], 400);
            }

            $referral = $utnValidation['referral'];

            // 2. Check for duplicate claim
            $duplicateCheck = $this->validationService->checkDuplicateClaim($validated['referral_id']);
            if ($duplicateCheck['exists']) {
                return response()->json([
                    'success' => false,
                    'message' => 'A claim has already been submitted for this referral (UTN: ' . $referral->utn . ')',
                    'existing_claim' => $duplicateCheck['claim'],
                ], 400);
            }

            // 3. Get admission if provided (optional)
            $admission = null;
            $bundleAmount = 0;
            if ($validated['admission_id']) {
                $admission = \App\Models\Admission::with('serviceBundle')->find($validated['admission_id']);

                // Validate admission belongs to the same referral
                if ($admission->referral_id !== $validated['referral_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Admission does not belong to the selected referral',
                    ], 400);
                }

                // Get bundle amount if admission has a service bundle
                if ($admission->service_bundle_id && $admission->serviceBundle) {
                    $bundleAmount = $admission->serviceBundle->fixed_price;
                }
            }

            // 4. Validate PA codes for FFS line items
            $lineItems = $validated['line_items'] ?? [];
            $paCodeIds = array_column($lineItems, 'pa_code_id');

            if (!empty($paCodeIds)) {
                $paValidation = $this->validationService->validatePACodes($paCodeIds, $validated['referral_id']);
                if (!$paValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $paValidation['message'],
                    ], 400);
                }
            }

            // 5. Calculate totals
            $totals = $this->validationService->calculateClaimTotals($bundleAmount, $lineItems);

            // 6. Validate at least one amount is present
            if ($totals['total_amount'] <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Claim must have at least one of: bundle amount or FFS line items',
                ], 400);
            }

            // 7. Create claim in transaction
            DB::beginTransaction();
            try {
                $claim = Claim::create([
                    'referral_id' => $validated['referral_id'],
                    'admission_id' => $validated['admission_id'],
                    'enrollee_id' => $referral->enrollee_id,
                    'facility_id' => $referral->receiving_facility_id,
                    'claim_number' => Claim::generateClaimNumber(),
                    'utn' => $referral->utn,
                    'bundle_amount' => $totals['bundle_amount'],
                    'ffs_amount' => $totals['ffs_amount'],
                    'total_amount' => $totals['total_amount'],
                    'total_amount_claimed' => $totals['total_amount'],
                    'status' => 'SUBMITTED',
                    'claim_date' => $validated['claim_date'] ?? now(),
                    'service_date' => $admission ? $admission->admission_date : now(),
                    'submitted_at' => now(),
                    'submitted_by' => auth()->id() ?? null,
                ]);

                // 8. Create claim line items
                foreach ($lineItems as $lineItem) {
                    \App\Models\ClaimLine::create([
                        'claim_id' => $claim->id,
                        'pa_code_id' => $lineItem['pa_code_id'],
                        'service_description' => $lineItem['service_description'],
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['unit_price'],
                        'line_total' => $lineItem['total_price'],
                        'tariff_type' => 'FFS',
                        'service_type' => 'service',
                        'reporting_type' => 'FFS_TOP_UP',
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Claim submitted successfully',
                    'data' => $claim->load(['referral', 'admission', 'enrollee', 'facility', 'lineItems.paCode']),
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create claim: ' . $e->getMessage(),
            ], 500);
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
    public function validateClaim(Claim $claim): JsonResponse
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

