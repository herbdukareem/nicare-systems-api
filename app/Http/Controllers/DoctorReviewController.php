<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use App\Models\ClaimTreatment;
use App\Models\ClaimAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DoctorReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('claims.role:doctor');
    }

    /**
     * Get claims pending doctor review
     */
    public function pendingReview(Request $request)
    {
        $query = Claim::with(['facility', 'diagnoses', 'treatments', 'submittedBy'])
            ->where('status', 'doctor_review');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%")
                  ->orWhere('nicare_number', 'like', "%{$search}%")
                  ->orWhere('enrollee_name', 'like', "%{$search}%");
            });
        }

        $claims = $query->orderBy('submitted_at', 'asc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Get claims reviewed by the current doctor
     */
    public function reviewedClaims(Request $request)
    {
        $query = Claim::with(['facility', 'diagnoses', 'treatments'])
            ->where('doctor_reviewed_by', Auth::id());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->orderBy('doctor_reviewed_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * Validate a diagnosis
     */
    public function validateDiagnosis(Request $request, ClaimDiagnosis $diagnosis)
    {
        $validator = Validator::make($request->all(), [
            'validated' => 'required|boolean',
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
            if ($request->validated) {
                $diagnosis->validate(Auth::user(), $request->comments);
                $message = 'Diagnosis validated successfully';
            } else {
                // Handle rejection logic if needed
                $message = 'Diagnosis validation updated';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $diagnosis->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate diagnosis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate a treatment
     */
    public function validateTreatment(Request $request, ClaimTreatment $treatment)
    {
        $validator = Validator::make($request->all(), [
            'validated' => 'required|boolean',
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
            if ($request->validated) {
                $treatment->validateByDoctor(Auth::user(), $request->comments);
                $message = 'Treatment validated successfully';
            } else {
                // Handle rejection logic if needed
                $message = 'Treatment validation updated';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $treatment->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate treatment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a claim after doctor review
     */
    public function approveClaim(Request $request, Claim $claim)
    {
        if ($claim->status !== 'doctor_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in doctor review status'
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

            // Check if all diagnoses and treatments are validated
            $unvalidatedDiagnoses = $claim->diagnoses()->where('doctor_validated', false)->count();
            $unvalidatedTreatments = $claim->treatments()->where('doctor_validated', false)->count();

            if ($unvalidatedDiagnoses > 0 || $unvalidatedTreatments > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'All diagnoses and treatments must be validated before approval',
                    'unvalidated_diagnoses' => $unvalidatedDiagnoses,
                    'unvalidated_treatments' => $unvalidatedTreatments
                ], 422);
            }

            // Determine next status
            $hasMedications = $claim->treatments()->where('service_type', 'medication')->exists();
            $nextStatus = $hasMedications ? 'pharmacist_review' : 'claim_review';

            $claim->update([
                'status' => $nextStatus,
                'doctor_reviewed_at' => now(),
                'doctor_reviewed_by' => Auth::id(),
                'doctor_comments' => $request->comments,
            ]);

            // Log the approval
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'doctor_approved',
                Auth::user(),
                'status',
                'doctor_review',
                $nextStatus,
                'Doctor approved claim',
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
     * Reject a claim after doctor review
     */
    public function rejectClaim(Request $request, Claim $claim)
    {
        if ($claim->status !== 'doctor_review') {
            return response()->json([
                'success' => false,
                'message' => 'Claim is not in doctor review status'
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
                'status' => 'doctor_rejected',
                'doctor_reviewed_at' => now(),
                'doctor_reviewed_by' => Auth::id(),
                'doctor_comments' => $request->comments,
            ]);

            // Log the rejection
            ClaimAuditLog::logClaimAction(
                $claim->id,
                'doctor_rejected',
                Auth::user(),
                'status',
                'doctor_review',
                'doctor_rejected',
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
     * Get claim statistics for doctor dashboard
     */
    public function statistics()
    {
        $user = Auth::user();

        $stats = [
            'pending_review' => Claim::where('status', 'doctor_review')->count(),
            'reviewed_today' => Claim::where('doctor_reviewed_by', $user->id)
                ->whereDate('doctor_reviewed_at', today())->count(),
            'approved_this_month' => Claim::where('doctor_reviewed_by', $user->id)
                ->where('status', '!=', 'doctor_rejected')
                ->whereMonth('doctor_reviewed_at', now()->month)->count(),
            'rejected_this_month' => Claim::where('doctor_reviewed_by', $user->id)
                ->where('status', 'doctor_rejected')
                ->whereMonth('doctor_reviewed_at', now()->month)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}
