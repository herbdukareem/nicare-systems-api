<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\ClaimPaymentBatch;
use App\Services\ClaimsAutomation\ClaimProcessingService;
use App\Services\ClaimValidationService;
use App\Services\FeedbackService;
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

            // 4. Get bundle amount (fixed price from service bundle)
            $bundleComponents = $validated['bundle_components'] ?? [];
            $bundleAmount = (float) ($validated['bundle_amount'] ?? 0);

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

                // 9. Create claim line items for selected bundle components (for tracking)
                foreach ($bundleComponents as $component) {
                    $unitPrice = (float) ($component['unit_price'] ?? 0);
                    $quantity = (int) ($component['quantity'] ?? 1);

                    \App\Models\ClaimLine::create([
                        'claim_id' => $claim->id,
                        'bundle_component_id' => $component['bundle_component_id'],
                        'case_record_id' => $component['case_record_id'] ?? null,
                        'service_description' => 'Bundle Component',
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $unitPrice * $quantity, // Component cost for tracking
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
                        'case_record_id' => $lineItem['case_record_id'] ?? null,
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

                // 12. Create automatic feedback for claim submission
                $this->feedbackService->createClaimSubmittedFeedback($claim);

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
                'status' => 'required|in:APPROVED,REJECTED,REVIEWING',
                'approved_amount' => 'nullable|numeric|min:0',
                'comments' => 'nullable|string',
                'line_item_adjustments' => 'nullable|array',
                'line_item_adjustments.*.id' => 'required|integer|exists:claim_lines,id',
                'line_item_adjustments.*.included' => 'required|boolean',
                'line_item_adjustments.*.approved_quantity' => 'required|integer|min:0',
            ]);

            if (!in_array($claim->status, ['SUBMITTED', 'REVIEWING'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted or reviewing claims can be processed',
                ], 400);
            }

            DB::beginTransaction();

            // Process line item adjustments if provided
            $adjustedFfsAmount = 0;
            if (!empty($validated['line_item_adjustments'])) {
                foreach ($validated['line_item_adjustments'] as $adjustment) {
                    $lineItem = $claim->lineItems()->find($adjustment['id']);
                    if ($lineItem) {
                        // Update line item with approved quantity
                        $approvedQty = $adjustment['included'] ? $adjustment['approved_quantity'] : 0;
                        $lineItem->update([
                            'approved_quantity' => $approvedQty,
                            'approved_amount' => $approvedQty * $lineItem->unit_price,
                            'is_approved' => $adjustment['included'],
                        ]);

                        if ($adjustment['included']) {
                            $adjustedFfsAmount += $approvedQty * $lineItem->unit_price;
                        }
                    }
                }
            }

            // Calculate final approved amount
            $bundleAmount = $claim->bundle_amount ?? 0;
            $finalApprovedAmount = $validated['approved_amount'] ?? ($bundleAmount + $adjustedFfsAmount);

            // Prepare update data based on decision
            $updateData = [
                'status' => $validated['status'],
                'approval_comments' => $validated['comments'] ?? null,
            ];

            if ($validated['status'] === 'APPROVED') {
                $updateData['approved_amount'] = $finalApprovedAmount;
                $updateData['approved_by'] = auth()->id();
                $updateData['approved_at'] = now();
            } elseif ($validated['status'] === 'REJECTED') {
                $updateData['rejection_reason'] = $validated['comments'] ?? null;
                $updateData['rejected_by'] = auth()->id();
                $updateData['rejected_at'] = now();
            } elseif ($validated['status'] === 'REVIEWING') {
                $updateData['reviewed_by'] = auth()->id();
                $updateData['reviewed_at'] = now();
            }

            $claim->update($updateData);

            // Create feedback record for the decision
            if (in_array($validated['status'], ['APPROVED', 'REJECTED'])) {
                $this->createClaimReviewFeedback($claim, $validated['status'], $validated['comments'] ?? null);
            }

            DB::commit();

            $statusMessages = [
                'APPROVED' => 'Claim approved successfully',
                'REJECTED' => 'Claim rejected',
                'REVIEWING' => 'Claim marked for review',
            ];

            return response()->json([
                'success' => true,
                'message' => $statusMessages[$validated['status']] ?? 'Claim updated',
                'data' => $claim->fresh(['referral', 'enrollee', 'facility', 'lineItems']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
            ]);

            DB::beginTransaction();

            $approvedCount = 0;
            $errors = [];

            foreach ($validated['claim_ids'] as $claimId) {
                $claim = Claim::find($claimId);

                if (!in_array($claim->status, ['SUBMITTED', 'REVIEWING'])) {
                    $errors[] = "Claim {$claim->claim_number} is not in a reviewable state";
                    continue;
                }

                $claim->update([
                    'status' => 'APPROVED',
                    'approved_amount' => $claim->approved_amount ?? $claim->total_amount_claimed,
                    'approval_comments' => $validated['approval_comments'] ?? null,
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                $approvedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$approvedCount} claims approved successfully",
                'approved_count' => $approvedCount,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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

            DB::beginTransaction();

            $rejectedCount = 0;

            foreach ($validated['claim_ids'] as $claimId) {
                $claim = Claim::find($claimId);

                if (!in_array($claim->status, ['SUBMITTED', 'REVIEWING'])) {
                    continue;
                }

                $claim->update([
                    'status' => 'REJECTED',
                    'rejection_reason' => $validated['rejection_reason'],
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                ]);

                $rejectedCount++;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "{$rejectedCount} claims rejected",
                'rejected_count' => $rejectedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to batch reject claims: ' . $e->getMessage(),
            ], 500);
        }
    }
}

