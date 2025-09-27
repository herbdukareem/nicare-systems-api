<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimTreatment;
use App\Models\ClaimAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PharmacistReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('claims.role:pharmacist');
    }

    /**
     * Get claims pending pharmacist review
     */
    public function pendingReview(Request $request)
    {
        $query = Claim::with(['facility', 'treatments' => function($q) {
                $q->where('service_type', 'medication');
            }, 'submittedBy'])
            ->where('status', 'pharmacist_review')
            ->whereHas('treatments', function($q) {
                $q->where('service_type', 'medication');
            });

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('doctor_reviewed_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Get claims reviewed by the current pharmacist
     */
    public function reviewedClaims(Request $request)
    {
        $query = Claim::with(['facility', 'treatments' => function($q) {
                $q->where('service_type', 'medication');
            }])
            ->where('pharmacist_reviewed_by', Auth::id());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->orderBy('pharmacist_reviewed_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Get medications for a specific claim
     */
    public function getClaimMedications(Claim $claim)
    {
        if ($claim->status !== 'pharmacist_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in pharmacist review status'
            ], 422);
        }

        $medications = $claim->treatments()
            ->where('service_type', 'medication')
            ->with(['attachments', 'pharmacistValidatedBy'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'claim' => $claim,
                'medications' => $medications
            ]
        ]);
    }

    /**
     * Validate a medication
     */
    public function validateMedication(Request $request, ClaimTreatment $treatment)
    {
        if ($treatment->service_type !== 'medication') {
            return response()->json([
                'success' => false,
                'message' => 'This treatment is not a medication'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'validated' => 'required|boolean',
            'comments' => 'nullable|string|max:1000',
            'dosage_approved' => 'nullable|boolean',
            'quantity_approved' => 'nullable|integer|min:0',
            'alternative_medication' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->validated) {
                // Update quantity if pharmacist approved different amount
                if ($request->has('quantity_approved') && $request->quantity_approved !== null) {
                    $treatment->update([
                        'quantity' => $request->quantity_approved,
                        'total_amount' => $request->quantity_approved * $treatment->unit_price
                    ]);
                }

                $treatment->validateByPharmacist(Auth::user(), $request->comments);
                
                $message = 'Medication validated successfully';
            } else {
                // Handle rejection or modification
                $comments = $request->comments;
                if ($request->alternative_medication) {
                    $comments .= "\nAlternative medication suggested: " . $request->alternative_medication;
                }
                
                $treatment->update([
                    'pharmacist_validation_comments' => $comments
                ]);
                
                $message = 'Medication validation updated';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $treatment->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate medication',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a claim after pharmacist review
     */
    public function approveClaim(Request $request, Claim $claim)
    {
        if ($claim->status !== 'pharmacist_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in pharmacist review status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'comments' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if all medications are validated
            $unvalidatedMedications = $claim->treatments()
                ->where('service_type', 'medication')
                ->where('pharmacist_validated', false)
                ->count();

            if ($unvalidatedMedications > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All medications must be validated before approval',
                    'unvalidated_medications' => $unvalidatedMedications
                ], 422);
            }

            $claim->update([
                'status' => 'claim_review',
                'pharmacist_reviewed_at' => now(),
                'pharmacist_reviewed_by' => Auth::id(),
                'pharmacist_comments' => $request->comments,
            ]);

            // Recalculate totals after any quantity changes
            $claim->calculateTotalAmounts();

            // Log the approval
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'pharmacist_approved',
                Auth::user(),
                'status',
                'pharmacist_review',
                'claim_review',
                'Pharmacist approved claim',
                $request->comments
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Claim approved successfully',
                'data' => $claim->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a claim after pharmacist review
     */
    public function rejectClaim(Request $request, Claim $claim)
    {
        if ($claim->status !== 'pharmacist_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in pharmacist review status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'comments' => 'required|string|max:1000',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $claim->update([
                'status' => 'pharmacist_rejected',
                'pharmacist_reviewed_at' => now(),
                'pharmacist_reviewed_by' => Auth::id(),
                'pharmacist_comments' => $request->comments,
            ]);

            // Log the rejection
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'pharmacist_rejected',
                Auth::user(),
                'status',
                'pharmacist_review',
                'pharmacist_rejected',
                $request->reason,
                $request->comments
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Claim rejected successfully',
                'data' => $claim->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pharmacist statistics
     */
    public function statistics()
    {
        $user = Auth::user();

        $stats = [
            'pending_review' => Claim::where('status', 'pharmacist_review')->count(),
            'reviewed_today' => Claim::where('pharmacist_reviewed_by', $user->id)
                ->whereDate('pharmacist_reviewed_at', today())->count(),
            'approved_this_month' => Claim::where('pharmacist_reviewed_by', $user->id)
                ->where('status', '!=', 'pharmacist_rejected')
                ->whereMonth('pharmacist_reviewed_at', now()->month)->count(),
            'rejected_this_month' => Claim::where('pharmacist_reviewed_by', $user->id)
                ->where('status', 'pharmacist_rejected')
                ->whereMonth('pharmacist_reviewed_at', now()->month)->count(),
            'medications_validated_today' => ClaimTreatment::where('pharmacist_validated_by', $user->id)
                ->whereDate('pharmacist_validated_at', today())->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
