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
                // Bundle components with actual amounts
                'bundle_components' => 'nullable|array',
                'bundle_components.*.bundle_component_id' => 'required|integer|exists:bundle_components,id',
                'bundle_components.*.case_record_id' => 'nullable|integer|exists:case_records,id',
                'bundle_components.*.quantity' => 'required|integer|min:1',
                'bundle_components.*.unit_price' => 'required|numeric|min:0',
                'bundle_components.*.actual_amount' => 'required|numeric|min:0',
                // FFS line items
                'line_items' => 'nullable|array',
                'line_items.*.pa_code_id' => 'required|integer|exists:pa_codes,id',
                'line_items.*.service_description' => 'required|string',
                'line_items.*.quantity' => 'required|integer|min:1',
                'line_items.*.unit_price' => 'required|numeric|min:0',
                'line_items.*.line_total' => 'required|numeric|min:0',
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
            if (!empty($validated['admission_id'])) {
                $admission = \App\Models\Admission::with('serviceBundle')->find($validated['admission_id']);

                // Validate admission belongs to the same referral
                if ($admission && $admission->referral_id !== $validated['referral_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Admission does not belong to the selected referral',
                    ], 400);
                }
            }

            // 4. Calculate bundle amount from bundle components
            $bundleComponents = $validated['bundle_components'] ?? [];
            $bundleAmount = array_reduce($bundleComponents, function ($sum, $comp) {
                return $sum + (float) ($comp['actual_amount'] ?? 0);
            }, 0);

            // 5. Validate PA codes for FFS line items
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

            // 6. Calculate FFS total
            $ffsAmount = array_reduce($lineItems, function ($sum, $item) {
                return $sum + (float) ($item['line_total'] ?? 0);
            }, 0);

            $totalAmount = $bundleAmount + $ffsAmount;

            // 7. Validate at least one amount is present
            if ($totalAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Claim must have at least one of: bundle amount or FFS line items',
                ], 400);
            }

            // 8. Create claim in transaction
            DB::beginTransaction();
            try {
                $claim = Claim::create([
                    'referral_id' => $validated['referral_id'],
                    'admission_id' => $validated['admission_id'] ?? null,
                    'enrollee_id' => $referral->enrollee_id,
                    'facility_id' => $referral->receiving_facility_id,
                    'claim_number' => Claim::generateClaimNumber(),
                    'utn' => $referral->utn,
                    'bundle_amount' => $bundleAmount,
                    'ffs_amount' => $ffsAmount,
                    'total_amount' => $totalAmount,
                    'total_amount_claimed' => $totalAmount,
                    'status' => 'SUBMITTED',
                    'claim_date' => $validated['claim_date'] ?? now(),
                    'service_date' => $admission ? $admission->admission_date : now(),
                    'submitted_at' => now(),
                    'submitted_by' => auth()->id() ?? null,
                ]);

                // 9. Create claim line items for bundle components
                foreach ($bundleComponents as $component) {
                    \App\Models\ClaimLine::create([
                        'claim_id' => $claim->id,
                        'bundle_component_id' => $component['bundle_component_id'],
                        'case_record_id' => $component['case_record_id'] ?? null,
                        'service_description' => 'Bundle Component',
                        'quantity' => $component['quantity'],
                        'unit_price' => $component['unit_price'],
                        'line_total' => $component['actual_amount'],
                        'tariff_type' => 'BUNDLE',
                        'service_type' => 'bundle_component',
                        'reporting_type' => 'IN_BUNDLE',
                    ]);
                }

                // 10. Create claim line items for FFS
                foreach ($lineItems as $lineItem) {
                    \App\Models\ClaimLine::create([
                        'claim_id' => $claim->id,
                        'pa_code_id' => $lineItem['pa_code_id'],
                        'service_description' => $lineItem['service_description'],
                        'quantity' => $lineItem['quantity'],
                        'unit_price' => $lineItem['unit_price'],
                        'line_total' => $lineItem['line_total'],
                        'tariff_type' => 'FFS',
                        'service_type' => 'service',
                        'reporting_type' => 'FFS_TOP_UP',
                    ]);
                }

                // 11. Mark referral as claim submitted
                $referral->markClaimSubmitted();

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

