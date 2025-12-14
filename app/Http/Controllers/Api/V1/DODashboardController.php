<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DOFacility;
use App\Models\Facility;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Class DODashboardController
 * 
 * Handles Desk Officer dashboard functionality including:
 * - Facility-specific data retrieval
 * - Referral filtering based on facility assignments
 * - UTN validation for secondary/tertiary facilities
 * - PA code management for assigned facilities
 */
class DODashboardController extends Controller
{
    /**
     * Get dashboard overview for the authenticated desk officer
     */
    public function overview(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            //check if user has role of desk_officer, facility_admin, or facility_user
            if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Facility role required.'
                ], 403);
            }

            // Get assigned facilities
            $assignedFacilities = $this->getAssignedFacilities($user->id);
            
            if ($assignedFacilities->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No facilities assigned',
                    'data' => [
                        'assigned_facilities' => [],
                        'stats' => [
                            'total_facilities' => 0,
                            'total_referrals' => 0,
                            'pending_referrals' => 0,
                            'total_pa_codes' => 0,
                            'pending_utn_validations' => 0
                        ]
                    ]
                ]);
            }

            $facilityIds = $assignedFacilities->pluck('id')->toArray();

            // Get statistics
            $stats = $this->getDashboardStats($facilityIds, $user->id);
            
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => [
                    'assigned_facilities' => $assignedFacilities,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referrals for assigned facilities
     */
    public function getReferrals(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            //check if user has role of desk_officer, facility_admin, or facility_user
            if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Facility role required.'
                ], 403);
            }

            $assignedFacilities = $this->getAssignedFacilities($user->id);
           
            $facilityIds = $assignedFacilities->pluck('id');
            
            if ($facilityIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No facilities assigned',
                    'data' => []
                ]);
            }


            // Build referrals query based on facility level
            $referralsQuery = $this->buildReferralsQuery($request, $facilityIds, $assignedFacilities);
            
            // Apply filters
            $this->applyReferralFilters($referralsQuery, $request);
            
            // Paginate results
            $perPage = $request->get('per_page', 15);
            $referrals = $referralsQuery->with([
                'referringFacility',
                'receivingFacility',
                'enrollee',
                'serviceBundle',
                'caseRecord',
                'paCodes',
                'documents.documentRequirement',
                'documents.uploader'
            ])->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Referrals retrieved successfully',
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PA codes for assigned facilities
     */
    public function getPACodes(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            //check if user has role of desk_officer, facility_admin, or facility_user
            if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Facility role required.'
                ], 403);
            }

            $assignedFacilities = $this->getAssignedFacilities($user->id);
            $facilityIds = $assignedFacilities->pluck('id');

            if ($facilityIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No facilities assigned',
                    'data' => []
                ]);
            }

            // Get PA codes for referrals to assigned facilities
            $paCodesQuery = PACode::whereHas('referral', function ($query) use ($facilityIds) {
                $query->whereIn('receiving_facility_id', $facilityIds);
            });

            // Apply filters
            if ($request->has('status')) {
                $paCodesQuery->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $paCodesQuery->where(function ($query) use ($search) {
                    $query->where('pa_code', 'like', "%{$search}%")
                          ->orWhere('utn', 'like', "%{$search}%")
                          ->orWhere('enrollee_name', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 15);
            $paCodes = $paCodesQuery->with(['referral', 'issuedBy', 'claims'])
                                   ->orderBy('created_at', 'desc')
                                   ->paginate($perPage);

            // Add claims count to each PA code
            $paCodes->getCollection()->transform(function ($paCode) {
                $paCode->claims_count = $paCode->claims()->count();
                return $paCode;
            });

            return response()->json([
                'success' => true,
                'message' => 'PA codes retrieved successfully',
                'data' => $paCodes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve PA codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate UTN for a referral (for secondary/tertiary facilities)
     */
    public function validateUTN(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'utn' => 'required|string',
                'referral_id' => 'required|exists:referrals,id',
                'validation_notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            
            //check if user has role of desk_officer, facility_admin, or facility_user
            if (!$user->hasRole('desk_officer') && !$user->hasRole('facility_admin') && !$user->hasRole('facility_user')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Facility role required.'
                ], 403);
            }

            // Get the referral
            $referral = Referral::findOrFail($request->referral_id);
            
            // Check if user has access to this referral's receiving facility
            $assignedFacilities = $this->getAssignedFacilities($user->id);
            $facilityIds = $assignedFacilities->pluck('id');
            
            if (!$facilityIds->contains($referral->receiving_facility_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You are not assigned to this facility.'
                ], 403);
            }

            // Verify the UTN matches
            if ($referral->utn !== $request->utn) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid UTN provided'
                ], 400);
            }

            // Check if already validated
            if ($referral->utn_validated) {
                return response()->json([
                    'success' => false,
                    'message' => 'UTN has already been validated'
                ], 400);
            }

            // Check validity window (default 3 months from generation)
            if ($referral->valid_until && now()->gt($referral->valid_until)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referral validity has expired and cannot be validated',
                ], 400);
            }

            // Validate the UTN
            $referral->update([
                'utn_validated' => true,
                'utn_validated_at' => now(),
                'utn_validated_by' => $user->id,
                'utn_validation_notes' => $request->validation_notes
            ]);

            // Create automatic feedback for UTN validation
            $feedbackService = app(\App\Services\FeedbackService::class);
            $feedbackService->createUTNValidatedFeedback($referral);

            return response()->json([
                'success' => true,
                'message' => 'UTN validated successfully',
                'data' => $referral->fresh([
                    'referringFacility',
                    'receivingFacility',
                    'enrollee',
                    'serviceBundle',
                    'caseRecord'
                ])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate UTN',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assigned facilities for a user
     */
    private function getAssignedFacilities(int $userId)
    {
       
        $assignedFacilities = Facility::whereHas('assignedUsers', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with(['lga', 'ward'])->get();
 

        return $assignedFacilities;
    }

    /**
     * Get dashboard statistics
     */
    private function getDashboardStats(array $facilityIds, int $userId): array
    {
        // Desk officers should only see statistics for APPROVED referrals
        $totalReferrals = Referral::whereIn('receiving_facility_id', $facilityIds)
                                 ->where('status', 'approved')
                                 ->count();

        // For desk officers, "pending referrals" means approved referrals awaiting UTN validation
        $pendingReferrals = Referral::whereIn('receiving_facility_id', $facilityIds)
                                   ->where('status', 'approved')
                                   ->whereNotNull('utn')
                                   ->where('utn_validated', false)
                                   ->where(function ($q) {
                                        $q->whereNull('valid_until')
                                          ->orWhere('valid_until', '>=', now());
                                   })
                                   ->count();

        $totalPACodes = PACode::whereHas('referral', function ($query) use ($facilityIds) {
            $query->whereIn('receiving_facility_id', $facilityIds)
                  ->where('status', 'approved'); // Only count PA codes for approved referrals
        })->count();

        $pendingUTNValidations = Referral::whereIn('receiving_facility_id', $facilityIds)
                                        ->where('status', 'approved')
                                        ->whereNotNull('utn')
                                        ->where('utn_validated', false)
                                        ->where(function ($q) {
                                            $q->whereNull('valid_until')
                                              ->orWhere('valid_until', '>=', now());
                                        })
                                        ->count();

        return [
            'total_facilities' => count($facilityIds),
            'total_referrals' => $totalReferrals,
            'pending_referrals' => $pendingReferrals,
            'total_pa_codes' => $totalPACodes,
            'pending_utn_validations' => $pendingUTNValidations
        ];
    }

    /**
     * Build referrals query based on facility levels
     */
    private function buildReferralsQuery(Request $request, $facilityIds, $assignedFacilities)
    {
        $query = Referral::query();

        // Filter by UTN validated status
        if ($request->has('utn_validated')) {
            $query->where('utn_validated', filter_var($request->utn_validated, FILTER_VALIDATE_BOOLEAN));
        }

        // IMPORTANT: Desk officers should only see APPROVED referrals
        $query->where('status', 'approved');

        // For primary facilities: show referrals FROM their facility
        // For secondary/tertiary: show referrals TO their facility (with UTN validation check)
        $primaryFacilityIds = $assignedFacilities->where('level_of_care', 'Primary')->pluck('id');
        $secondaryTertiaryIds = $assignedFacilities->whereIn('level_of_care', ['Secondary', 'Tertiary'])->pluck('id');

        $query->where(function ($q) use ($primaryFacilityIds, $secondaryTertiaryIds) {
            // Primary facilities: referrals FROM their facility
            if ($primaryFacilityIds->isNotEmpty()) {
                $q->orWhereIn('referring_facility_id', $primaryFacilityIds);
            }

            // Secondary/Tertiary: referrals TO their facility (only if UTN is validated)
            if ($secondaryTertiaryIds->isNotEmpty()) {
                $q->orWhere(function ($subQuery) use ($secondaryTertiaryIds) {
                    $subQuery->whereIn('receiving_facility_id', $secondaryTertiaryIds)
                             ->where('utn_validated', true);
                });
            }
        });

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Apply filters to referrals query
     */
    private function applyReferralFilters($query, Request $request): void
    {
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // UTN
        if ($request->has('utn') && !empty($request->utn)) {
            $query->where('utn', $request->utn);
        }

        if ($request->has('severity_level')) {
            $query->where('severity_level', $request->severity_level);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                // Match by referral code
                $q->where('referral_code', 'like', "%{$search}%")

                  // Match by enrollee NiCare number or name via relationship
                  ->orWhereHas('enrollee', function ($enrolleeQuery) use ($search) {
                      $enrolleeQuery->where('enrollee_id', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('middle_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%");
                  })

                  // Match by referring facility
                  ->orWhereHas('referringFacility', function ($facilityQuery) use ($search) {
                      $facilityQuery->where('name', 'like', "%{$search}%")
                          ->orWhere('hcp_code', 'like', "%{$search}%");
                  })

                  // Match by receiving facility
                  ->orWhereHas('receivingFacility', function ($facilityQuery) use ($search) {
                      $facilityQuery->where('name', 'like', "%{$search}%")
                          ->orWhere('hcp_code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('referral_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('referral_date', '<=', $request->date_to);
        }
    }
}
