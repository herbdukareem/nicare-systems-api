<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimPaymentBatch;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use App\Services\FeedbackService;
use App\Services\EligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    private ClaimProcessingService $claimProcessingService;
    private ClaimValidationService $validationService;
    private FeedbackService $feedbackService;

    public function __construct(
        ClaimProcessingService $claimProcessingService,
        ClaimValidationService $validationService,
        FeedbackService $feedbackService
    ) {
        $this->claimProcessingService = $claimProcessingService;
        $this->validationService = $validationService;
        $this->feedbackService = $feedbackService;
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




public function showFullDetails($claimId): JsonResponse
{
    try {
        $claim = \App\Models\Claim::with([
            'enrollee', 
            'facility', 
            'referral.serviceBundle',
            'lineItems.caseRecord'
        ])->findOrFail($claimId);

        // Fetch the bundle components using the new traversal method
        $bundleComponents = null;
        if ($claim->referral && $claim->referral->serviceBundle) {
            // Use the serviceBundleComponents method on the Claim model
            $bundleComponents = $claim->serviceBundleComponents()->get(); 
        }

        // Return the claim data along with the dynamically fetched components
        return response()->json([
            'success' => true,
            'data' => array_merge($claim->toArray(), [
                'bundle_components' => $bundleComponents,
            ])
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch claim details',
            'error' => $e->getMessage()
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
                // Bundle amount (fixed price from service bundle)
                'bundle_amount' => 'nullable|numeric|min:0',
                'bundle_pa_code_id' => 'nullable|integer|exists:pa_codes,id',
                // Selected bundle components (for tracking which components were used)
                'bundle_components' => 'nullable|array',
                'bundle_components.*.bundle_component_id' => 'required|integer|exists:bundle_components,id',
                'bundle_components.*.case_record_id' => 'nullable|integer|exists:case_records,id',
                'bundle_components.*.quantity' => 'nullable|integer|min:1',
                'bundle_components.*.unit_price' => 'nullable|numeric|min:0',
                // FFS line items
                'line_items' => 'nullable|array',
                'line_items.*.pa_code_id' => 'required|integer|exists:pa_codes,id',
                'line_items.*.case_record_id' => 'nullable|integer|exists:case_records,id',
                'line_items.*.service_description' => 'required|string',
                'line_items.*.quantity' => 'required|integer|min:1',
                'line_items.*.unit_price' => 'required|numeric|min:0',
                'line_items.*.line_total' => 'required|numeric|min:0',
                //tariff_type
                'line_items.*.tariff_type' => 'required|in:BUNDLE,FFS',

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

            // 2. Get admission if provided (optional) before checking service-date coverage.
            $admission = null;
            if (!empty($validated['admission_id'])) {
                $admission = \App\Models\Admission::with('serviceBundle')->find($validated['admission_id']);

                if ($admission && $admission->referral_id !== $validated['referral_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Admission does not belong to the selected referral',
                    ], 400);
                }
            }

            $serviceDate = $admission?->admission_date ?? ($validated['claim_date'] ?? now());

            try {
                app(EligibilityService::class)->assertFacilityMatchesCoverage(
                    $referral->enrollee_id,
                    (int) $referral->receiving_facility_id,
                    $serviceDate
                );
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            // 3. Check for duplicate claim
            $duplicateCheck = $this->validationService->checkDuplicateClaim($validated['referral_id']);
            if ($duplicateCheck['exists']) {
                return response()->json([
                    'success' => false,
                    'message' => 'A claim has already been submitted for this referral (UTN: ' . $referral->utn . ')',
                    'existing_claim' => $duplicateCheck['claim'],
                ], 400);
            }

            // 4. Get bundle amount (fixed price from service bundle)
            $bundleComponents = $validated['bundle_components'] ?? [];
            $bundleAmount = (float) ($validated['bundle_amount'] ?? 0);

            // 5. Validate PA codes for all line items (bundle + FFS)
            $lineItems = $validated['line_items'] ?? [];
            $paCodeIds = array_unique(array_column($lineItems, 'pa_code_id'));

            // Add bundle PA code if present
            if (!empty($validated['bundle_pa_code_id'])) {
                $paCodeIds[] = $validated['bundle_pa_code_id'];
                $paCodeIds = array_unique($paCodeIds);
            }

            if (!empty($paCodeIds)) {
                $paValidation = $this->validationService->validatePACodes($paCodeIds, $validated['referral_id']);
                if (!$paValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $paValidation['message'],
                    ], 400);
                }
            }

            $ffsAmount = array_reduce($lineItems, function ($sum, $item) {
                return $sum + (float) ($item['line_total'] ?? 0);
            }, 0);

            $totalAmount = $bundleAmount + $ffsAmount;
            if ($totalAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Claim must have at least one of: bundle amount or FFS line items',
                ], 400);
            }

            $claim = $this->claimProcessingService->createDraftClaimFromReferral($referral, $validated, $admission);

            return response()->json([
                'success' => true,
                'message' => 'Claim draft created successfully',
                'data' => $claim,
            ], 201);

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
            'data' => $claim->load(['admission', 'referral', 'enrollee', 'facility', 'lineItems', 'alerts']),
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
            $this->feedbackService->createClaimSubmittedFeedback($claim);

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
                'has_critical_alerts' => collect($alerts)->where('type', 'CRITICAL')->count() > 0,
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

    /**
     * Download claim submission slip as PDF
     * GET /api/claims-automation/claims/{id}/slip
     */
    public function downloadSlip(Claim $claim)
    {
        try {
            // Load relationships needed for the slip
            $claim->load([
                'referral.enrollee.lga',
                'referral.receivingFacility',
                'enrollee',
                'facility',
                'lineItems.paCode',
                'lineItems.bundleComponent.caseRecord',
            ]);
            // dd($claim);

            $data = [
                'claim' => $claim,
                'generated_at' => now()->format('d M Y, H:i:s'),
                'bundle_items' => $claim->lineItems->where('tariff_type', 'BUNDLE'),
                'ffs_items' => $claim->lineItems->where('tariff_type', 'FFS'),
            ];

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.claim-slip', $data);
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download("claim-slip-{$claim->claim_number}.pdf");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate claim slip: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Review a claim with approval/rejection decision
     * POST /api/claims-automation/claims/{id}/review
     *
     * Supports:
     * - Setting claim status to APPROVED or REJECTED
     * - Adjusting line item quantities
     * - Excluding line items from approval
     * - Creating feedback records for the decision
     */
    public function review(Request $request, Claim $claim): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:DRAFT,SUBMITTED,REVIEWING,APPROVED,REJECTED',
                'approved_amount' => 'nullable|numeric|min:0',
                'comments' => 'nullable|string',
                'line_item_adjustments' => 'nullable|array',
                'line_item_adjustments.*.id' => 'required|integer|exists:claim_lines,id',
                'line_item_adjustments.*.included' => 'required|boolean',
                'line_item_adjustments.*.approved_quantity' => 'required|integer|min:0',
            ]);
            $claim = $this->claimProcessingService->reviewClaim($claim, $validated);

            // Create feedback record for the decision
            if (in_array($validated['status'], ['APPROVED', 'REJECTED'])) {
                $this->createClaimReviewFeedback($claim, $validated['status'], $validated['comments'] ?? null);
            }

            $statusMessages = [
                'APPROVED' => 'Claim approved successfully',
                'REJECTED' => 'Claim rejected',
                'REVIEWING' => 'Claim marked for review',
            ];

            return response()->json([
                'success' => true,
                'message' => $statusMessages[$validated['status']] ?? 'Claim updated',
                'data' => $claim,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to review claim: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create feedback record for claim review decision
     */
    private function createClaimReviewFeedback(Claim $claim, string $status, ?string $comments): void
    {
        $referral = $claim->referral;
        if (!$referral) {
            return;
        }

        $eventType = $status === 'APPROVED' ? 'CLAIM_APPROVED' : 'CLAIM_REJECTED';
        $feedbackComment = $status === 'APPROVED'
            ? "Claim {$claim->claim_number} approved. Amount: ₦" . number_format($claim->approved_amount, 2)
            : "Claim {$claim->claim_number} rejected.";

        \App\Models\FeedbackRecord::create([
            'enrollee_id' => $referral->enrollee_id,
            'referral_id' => $referral->id,
            'feedback_officer_id' => auth()->id(),
            'feedback_type' => 'referral',
            'event_type' => $eventType,
            'is_system_generated' => false,
            'status' => 'completed',
            'priority' => 'medium',
            'feedback_comments' => $comments ?? $feedbackComment,
            'officer_observations' => $feedbackComment,
            'feedback_date' => now(),
            'completed_at' => now(),
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Batch approve multiple claims
     * POST /api/claims-automation/claims/batch-approve
     */
    public function batchApprove(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'claim_ids' => 'required|array|min:1',
                'claim_ids.*' => 'integer|exists:claims,id',
                'approval_comments' => 'nullable|string',
                'payment_code' => 'required|string',
                'generate_approval_letter' => 'nullable|boolean',
                'generate_payment_receipts' => 'nullable|boolean',
            ]);

            $result = $this->claimProcessingService->batchReviewClaims($validated['claim_ids'], Claim::STATUS_APPROVED, [
                'approval_comments' => $validated['approval_comments'] ?? null,
                'payment_code' => $validated['payment_code'],
            ]);

            // Generate approval letter if requested
            if ($validated['generate_approval_letter'] ?? false) {
                // TODO: Generate approval letter for all claims
                // This will be a batch approval letter document
            }

            // Generate payment receipts if requested
            if ($validated['generate_payment_receipts'] ?? false) {
                // TODO: Generate individual payment receipts for each claim
                // Each receipt will include the payment code
            }

            return response()->json([
                'success' => true,
                'message' => "{$result['processed_count']} claims approved successfully",
                'approved_count' => $result['processed_count'],
                'errors' => $result['errors'],
                'payment_code' => $validated['payment_code'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch approve claims: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch reject multiple claims
     * POST /api/claims-automation/claims/batch-reject
     */
    public function batchReject(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'claim_ids' => 'required|array|min:1',
                'claim_ids.*' => 'integer|exists:claims,id',
                'rejection_reason' => 'required|string',
            ]);

            $result = $this->claimProcessingService->batchReviewClaims($validated['claim_ids'], Claim::STATUS_REJECTED, [
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$result['processed_count']} claims rejected",
                'rejected_count' => $result['processed_count'],
                'errors' => $result['errors'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch reject claims: ' . $e->getMessage(),
            ], 500);
        }
    }
}
