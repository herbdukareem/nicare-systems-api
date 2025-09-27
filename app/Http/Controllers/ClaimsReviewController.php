<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClaimsReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get claims pending review (for claim reviewers)
     */
    public function pendingReview(Request $request)
    {
        $this->middleware('claims.role:claim_reviewer');

        $query = Claim::with(['facility', 'diagnoses', 'treatments', 'submittedBy', 'doctorReviewedBy', 'pharmacistReviewedBy'])
            ->where('status', 'claim_review');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('pharmacist_reviewed_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Get claims pending confirmation (for claim confirmers)
     */
    public function pendingConfirmation(Request $request)
    {
        $this->middleware('claims.role:claim_confirmer');

        $query = Claim::with(['facility', 'diagnoses', 'treatments', 'claimReviewedBy'])
            ->where('status', 'claim_review');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('claim_reviewed_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Get claims pending final approval (for claim approvers)
     */
    public function pendingApproval(Request $request)
    {
        $this->middleware('claims.role:claim_approver');

        $query = Claim::with(['facility', 'diagnoses', 'treatments', 'claimConfirmedBy'])
            ->where('status', 'claim_confirmed');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('claim_confirmed_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Review a claim (claim reviewer action)
     */
    public function reviewClaim(Request $request, Claim $claim)
    {
        $this->middleware('claims.role:claim_reviewer');

        if ($claim->status !== 'claim_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in review status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'comments' => 'required|string|max:1000',
            'tariff_adjustments' => 'nullable|array',
            'tariff_adjustments.*.treatment_id' => 'required|exists:claim_treatments,id',
            'tariff_adjustments.*.approved_amount' => 'required|numeric|min:0',
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

            if ($request->action === 'approve') {
                // Apply tariff adjustments if provided
                if ($request->has('tariff_adjustments')) {
                    foreach ($request->tariff_adjustments as $adjustment) {
                        $treatment = $claim->treatments()->find($adjustment['treatment_id']);
                        if ($treatment) {
                            $treatment->validateTariff($adjustment['approved_amount'], 'Tariff adjustment by reviewer');
                        }
                    }
                }

                $claim->update([
                    'status' => 'claim_confirmed',
                    'claim_reviewed_at' => now(),
                    'claim_reviewed_by' => Auth::id(),
                    'claim_reviewer_comments' => $request->comments,
                ]);

                $action = 'claim_reviewed_approved';
                $message = 'Claim reviewed and approved successfully';
            } else {
                $claim->update([
                    'status' => 'claim_rejected',
                    'claim_reviewed_at' => now(),
                    'claim_reviewed_by' => Auth::id(),
                    'claim_reviewer_comments' => $request->comments,
                ]);

                $action = 'claim_reviewed_rejected';
                $message = 'Claim reviewed and rejected';
            }

            // Recalculate totals after any adjustments
            $claim->calculateTotalAmounts();

            // Log the action
            ClaimAuditLog::logClaimAction(
                $claim->id,
                $action,
                Auth::user(),
                'status',
                'claim_review',
                $claim->status,
                'Claim reviewer action',
                $request->comments
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $claim->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to review claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm a claim (claim confirmer action)
     */
    public function confirmClaim(Request $request, Claim $claim)
    {
        $this->middleware('claims.role:claim_confirmer');

        if ($claim->status !== 'claim_confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in confirmation status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:confirm,reject',
            'comments' => 'required|string|max:1000',
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

            if ($request->action === 'confirm') {
                $claim->update([
                    'status' => 'claim_approved',
                    'claim_confirmed_at' => now(),
                    'claim_confirmed_by' => Auth::id(),
                    'claim_confirmer_comments' => $request->comments,
                ]);

                $action = 'claim_confirmed';
                $message = 'Claim confirmed successfully';
            } else {
                $claim->update([
                    'status' => 'claim_rejected',
                    'claim_confirmed_at' => now(),
                    'claim_confirmed_by' => Auth::id(),
                    'claim_confirmer_comments' => $request->comments,
                ]);

                $action = 'claim_rejected';
                $message = 'Claim rejected';
            }

            // Log the action
            ClaimAuditLog::logClaimAction(
                $claim->id,
                $action,
                Auth::user(),
                'status',
                'claim_confirmed',
                $claim->status,
                'Claim confirmer action',
                $request->comments
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $claim->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm claim',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Final approval of a claim (claim approver action)
     */
    public function approveClaim(Request $request, Claim $claim)
    {
        $this->middleware('claims.role:claim_approver');

        if ($claim->status !== 'claim_approved') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in approval status'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,reject',
            'comments' => 'required|string|max:1000',
            'payment_authorized' => 'nullable|boolean',
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

            if ($request->action === 'approve') {
                $nextStatus = $request->payment_authorized ? 'paid' : 'claim_approved';
                
                $claim->update([
                    'status' => $nextStatus,
                    'claim_approved_at' => now(),
                    'claim_approved_by' => Auth::id(),
                    'claim_approver_comments' => $request->comments,
                ]);

                if ($request->payment_authorized) {
                    $claim->update(['total_amount_paid' => $claim->total_amount_approved]);
                }

                $action = 'claim_final_approved';
                $message = 'Claim finally approved successfully';
            } else {
                $claim->update([
                    'status' => 'claim_rejected',
                    'claim_approved_at' => now(),
                    'claim_approved_by' => Auth::id(),
                    'claim_approver_comments' => $request->comments,
                ]);

                $action = 'claim_final_rejected';
                $message = 'Claim finally rejected';
            }

            // Log the action
            ClaimAuditLog::logClaimAction(
                $claim->id,
                $action,
                Auth::user(),
                'status',
                'claim_approved',
                $claim->status,
                'Final claim approval action',
                $request->comments
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
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
}
