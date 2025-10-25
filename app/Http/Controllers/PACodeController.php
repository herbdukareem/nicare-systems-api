<?php

namespace App\Http\Controllers;

use App\Models\PACode;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PACodeController extends Controller
{
    /**
     * Display a listing of PA codes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PACode::with(['referral', 'issuedBy']);

            // Apply filters
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('pa_code', 'like', "%{$search}%")
                      ->orWhere('utn', 'like', "%{$search}%")
                      ->orWhere('enrollee_name', 'like', "%{$search}%")
                      ->orWhere('nicare_number', 'like', "%{$search}%")
                      ->orWhere('facility_name', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $paCodes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $paCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch PA codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PA code from approved referral
     */
    public function generateFromReferral(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_type' => 'required|string|max:255',
                'service_description' => 'required|string',
                'approved_amount' => 'nullable|numeric|min:0',
                'conditions' => 'nullable|string',
                'validity_days' => 'required|numeric|min:1|max:365',
                'max_usage' => 'required|numeric|min:1|max:10',
                'issuer_comments' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'PA code can only be generated for approved referrals'
                ], 400);
            }

            DB::beginTransaction();

            // Generate PA code and UTN
            $paCode = PACode::generatePACode();
            $utn = PACode::generateUTN();

            // Create PA code record
            $paCodeRecord = PACode::create([
                'pa_code' => $paCode,
                'utn' => $utn,
                'referral_id' => $referral->id,
                'nicare_number' => $referral->nicare_number,
                'enrollee_name' => $referral->enrollee_full_name,
                'facility_name' => $referral->receiving_facility_name,
                'facility_nicare_code' => $referral->receiving_nicare_code,
                'service_type' => $request->service_type,
                'service_description' => $request->service_description,
                'approved_amount' => $request->approved_amount,
                'conditions' => $request->conditions,
                'status' => 1,
                'issued_at' => now(),
                'expires_at' => now()->addDays((int) $request->validity_days),
                'usage_count' => 0,
                'max_usage' => (int) $request->max_usage,
                'issued_by' => Auth::id(),
                'issuer_comments' => $request->issuer_comments
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'PA code generated successfully',
                'data' => $paCodeRecord->fresh(['referral', 'issuedBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PA code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified PA code
     */
    public function show(PACode $paCode): JsonResponse
    {
        try {
            $paCode->load(['referral', 'issuedBy']);

            return response()->json([
                'success' => true,
                'data' => $paCode
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch PA code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark PA code as used
     */
    public function markAsUsed(Request $request, PACode $paCode): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'claim_reference' => 'nullable|string|max:255',
                'usage_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$paCode->can_be_used) {
                return response()->json([
                    'success' => false,
                    'message' => 'PA code cannot be used (expired, inactive, or usage limit reached)'
                ], 400);
            }

            $paCode->markAsUsed($request->claim_reference);

            if ($request->usage_notes) {
                $paCode->update(['usage_notes' => $request->usage_notes]);
            }

            return response()->json([
                'success' => true,
                'message' => 'PA code marked as used successfully',
                'data' => $paCode->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark PA code as used',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel/expire PA code
     */
    public function cancel(PACode $paCode): JsonResponse
    {
        try {
            if ($paCode->status === 'used') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel a used PA code'
                ], 400);
            }

            $paCode->cancel();

            return response()->json([
                'success' => true,
                'message' => 'PA code cancelled successfully',
                'data' => $paCode->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel PA code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PA code statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_pa_codes' => PACode::count(),
                'active_pa_codes' => PACode::where('status', 'active')->count(),
                'used_pa_codes' => PACode::where('status', 'used')->count(),
                'expired_pa_codes' => PACode::where('status', 'expired')->count(),
                'cancelled_pa_codes' => PACode::where('status', 'cancelled')->count(),
                'expiring_soon' => PACode::where('status', 'active')
                    ->where('expires_at', '<=', now()->addDays(7))
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate UTN for existing PA code
     */
    public function generateUTN(PACode $paCode): JsonResponse
    {
        try {
            if ($paCode->utn) {
                return response()->json([
                    'success' => false,
                    'message' => 'UTN already exists for this PA code'
                ], 400);
            }

            if ($paCode->status !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'UTN can only be generated for active PA codes'
                ], 400);
            }

            DB::beginTransaction();

            // Generate new UTN
            $utn = PACode::generateUTN();
            $paCode->update(['utn' => $utn]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'UTN generated successfully',
                'data' => $paCode->fresh(['referral', 'issuedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate UTN',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify PA code by code or UTN
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $code = $request->code;

            // Try to find by PA code or UTN
            $paCode = PACode::where('pa_code', $code)
                ->orWhere('utn', $code)
                ->with(['referral', 'issuedBy'])
                ->first();

            if (!$paCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'PA code not found'
                ], 404);
            }

            $verification = [
                'pa_code' => $paCode,
                'is_valid' => $paCode->is_active,
                'can_be_used' => $paCode->can_be_used,
                'is_expired' => $paCode->is_expired,
                'usage_remaining' => $paCode->max_usage - $paCode->usage_count,
                'expires_in_days' => $paCode->expires_at->diffInDays(now(), false)
            ];

            return response()->json([
                'success' => true,
                'data' => $verification
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify PA code',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
