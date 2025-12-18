<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeedbackRecord;
use App\Models\Enrollee;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\EnrolleeRelation;
use App\Models\PrimaryEncounter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    /**
     * Get all feedback records with filtering and pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FeedbackRecord::with([
                'enrollee:id,enrollee_id,first_name,middle_name,last_name,phone,sex',
                'referral:id,referral_code,status',
                'paCode:id,code,status',
                'feedbackOfficer:id,name,email'
            ]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('feedback_type')) {
                $query->where('feedback_type', $request->feedback_type);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('feedback_officer_id')) {
                $query->where('feedback_officer_id', $request->feedback_officer_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('feedback_code', 'like', "%{$search}%")
                      ->orWhereHas('enrollee', function ($eq) use ($search) {
                          $eq->where('enrollee_id', 'like', "%{$search}%")
                             ->orWhere('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $perPage = $request->get('per_page', 15);
            $feedbacks = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $feedbacks,
                'message' => 'Feedback records retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive enrollee data for feedback
     */
    public function getEnrolleeComprehensiveData(Request $request, $enrolleeId): JsonResponse
    {
        try {
            $enrollee = Enrollee::with([
                'facility:id,name,hcp_code,level_of_care,phone,address',
                'facility.lga:id,name',
                'benefactor:id,name',
                'ward:id,name'
            ])->findOrFail($enrolleeId);

            // Get enrollee relations (NOK, family members)
            $relations = EnrolleeRelation::where('enrollee_id', $enrolleeId)
                ->active()
                ->orderBy('is_next_of_kin', 'desc')
                ->orderBy('is_primary_contact', 'desc')
                ->get();

            // Get recent primary encounters
            $primaryEncounters = PrimaryEncounter::where('enrollee_id', $enrolleeId)
                ->with(['facility:id,name,hcp_code', 'provider:id,name'])
                ->orderBy('encounter_date', 'desc')
                ->limit(10)
                ->get();

            // Get referral history
            $referralHistory = Referral::where('enrollee_id', $enrolleeId)
                ->with([
                    'fromFacility:id,name,hcp_code',
                    'toFacility:id,name,hcp_code',
                    'services'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Get PA code history
            $paCodeHistory = PACode::where('enrollee_id', $enrolleeId)
                ->with(['referral.services'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Get last diagnosis from recent encounters
            $lastDiagnosis = PrimaryEncounter::where('enrollee_id', $enrolleeId)
                ->whereNotNull('diagnosis')
                ->orderBy('encounter_date', 'desc')
                ->first()?->diagnosis;

            // Calculate medical summary
            $medicalSummary = [
                'total_referrals' => $referralHistory->count(),
                'active_referrals' => $referralHistory->where('status', 'approved')->count(),
                'total_pa_codes' => $paCodeHistory->count(),
                'active_pa_codes' => $paCodeHistory->where('status', 'active')->count(),
                'total_encounters' => $primaryEncounters->count(),
                'last_encounter_date' => $primaryEncounters->first()?->encounter_date,
                'last_diagnosis' => $lastDiagnosis
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'enrollee' => $enrollee,
                    'relations' => $relations,
                    'primary_encounters' => $primaryEncounters,
                    'referral_history' => $referralHistory,
                    'pa_code_history' => $paCodeHistory,
                    'medical_summary' => $medicalSummary
                ],
                'message' => 'Enrollee comprehensive data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve enrollee data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new feedback record
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
            'referral_id' => 'nullable|exists:referrals,id',
            'pa_code_id' => 'nullable|exists:pa_codes,id',
            'feedback_type' => 'required|in:referral,pa_code,general,enrollee_verification,service_delivery,claims_guidance,medical_history,complaint,utn_validation,facility_coordination,document_verification,treatment_progress',
            'priority' => 'required|in:low,medium,high,urgent',
            'feedback_comments' => 'nullable|string',
            'officer_observations' => 'nullable|string',
            'claims_guidance' => 'nullable|string',
            // Optional referral status change
            'new_referral_status' => 'nullable|string|in:PENDING,APPROVED,REJECTED,CANCELLED,COMPLETED',
        ]);

        try {
            DB::beginTransaction();

            $referralStatusBefore = null;
            $referralStatusAfter = null;

            // Handle referral status change if provided
            if ($request->filled('referral_id') && $request->filled('new_referral_status')) {
                $referral = Referral::find($request->referral_id);
                if ($referral) {
                    $referralStatusBefore = $referral->status;
                    $referral->update(['status' => $request->new_referral_status]);
                    $referralStatusAfter = $request->new_referral_status;
                }
            }

            $feedback = FeedbackRecord::create([
                'enrollee_id' => $request->enrollee_id,
                'referral_id' => $request->referral_id,
                'pa_code_id' => $request->pa_code_id,
                'feedback_officer_id' => Auth::id(),
                'feedback_type' => $request->feedback_type,
                'event_type' => null, // Manual feedback, not system generated
                'is_system_generated' => false,
                'priority' => $request->priority,
                'feedback_comments' => $request->feedback_comments,
                'officer_observations' => $request->officer_observations,
                'claims_guidance' => $request->claims_guidance,
                'referral_status_before' => $referralStatusBefore,
                'referral_status_after' => $referralStatusAfter,
                'status' => 'pending',
                'feedback_date' => now(),
                'created_by' => Auth::id()
            ]);

            DB::commit();

            $feedback->load([
                'enrollee:id,enrollee_id,first_name,middle_name,last_name',
                'referral:id,referral_code,status',
                'paCode:id,code',
                'feedbackOfficer:id,name'
            ]);

            return response()->json([
                'success' => true,
                'data' => $feedback,
                'message' => 'Feedback record created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create feedback record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get approved referrals for feedback creation
     * Only show referrals where claims have not been submitted yet
     */
    public function getApprovedReferrals(Request $request): JsonResponse
    {
        try {
            $query = Referral::with([
                'enrollee:id,enrollee_id,first_name,middle_name,last_name,phone',
                'referringFacility:id,name',
                'receivingFacility:id,name',
            ])
            ->where('status', 'APPROVED')
            ->where('claim_submitted', false); // Only show referrals without submitted claims

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('referral_code', 'like', "%{$search}%")
                      ->orWhere('utn', 'like', "%{$search}%")
                      ->orWhereHas('enrollee', function ($eq) use ($search) {
                          $eq->where('enrollee_id', 'like', "%{$search}%")
                             ->orWhere('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            $referrals = $query->orderBy('created_at', 'desc')->limit(200)->get();

            return response()->json([
                'success' => true,
                'data' => $referrals,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get referrals: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific feedback record
     */
    public function show($id): JsonResponse
    {
        try {
            $feedback = FeedbackRecord::with([
                'enrollee',
                'referral.services',
                'paCode',
                'feedbackOfficer:id,name,email',
                'creator:id,name',
                'updater:id,name'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $feedback,
                'message' => 'Feedback record retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a feedback record
     */
    public function update(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:pending,in_progress,completed,escalated',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'feedback_comments' => 'nullable|string',
            'officer_observations' => 'nullable|string',
            'claims_guidance' => 'nullable|string',
            'enrollee_verification_data' => 'nullable|array',
            'medical_history_summary' => 'nullable|array',
            'additional_information' => 'nullable|array'
        ]);

        try {
            $feedback = FeedbackRecord::findOrFail($id);

            $updateData = $request->only([
                'status', 'priority', 'feedback_comments', 'officer_observations',
                'claims_guidance', 'enrollee_verification_data', 'medical_history_summary',
                'additional_information'
            ]);

            $updateData['updated_by'] = Auth::id();

            if ($request->status === 'completed' && !$feedback->completed_at) {
                $updateData['completed_at'] = now();
            }

            $feedback->update($updateData);

            $feedback->load([
                'enrollee:id,enrollee_id,first_name,middle_name,last_name',
                'referral:id,referral_code',
                'paCode:id,code',
                'feedbackOfficer:id,name'
            ]);

            return response()->json([
                'success' => true,
                'data' => $feedback,
                'message' => 'Feedback record updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feedback record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get feedback statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_feedbacks' => FeedbackRecord::count(),
                'pending_feedbacks' => FeedbackRecord::where('status', 'pending')->count(),
                'in_progress_feedbacks' => FeedbackRecord::where('status', 'in_progress')->count(),
                'completed_feedbacks' => FeedbackRecord::where('status', 'completed')->count(),
                'escalated_feedbacks' => FeedbackRecord::where('status', 'escalated')->count(),
                'by_type' => [
                    'referral' => FeedbackRecord::where('feedback_type', 'referral')->count(),
                    'pa_code' => FeedbackRecord::where('feedback_type', 'pa_code')->count(),
                    'general' => FeedbackRecord::where('feedback_type', 'general')->count(),
                    'enrollee_verification' => FeedbackRecord::where('feedback_type', 'enrollee_verification')->count(),
                    'service_delivery' => FeedbackRecord::where('feedback_type', 'service_delivery')->count(),
                    'claims_guidance' => FeedbackRecord::where('feedback_type', 'claims_guidance')->count(),
                    'medical_history' => FeedbackRecord::where('feedback_type', 'medical_history')->count(),
                    'complaint' => FeedbackRecord::where('feedback_type', 'complaint')->count(),
                    'utn_validation' => FeedbackRecord::where('feedback_type', 'utn_validation')->count(),
                    'facility_coordination' => FeedbackRecord::where('feedback_type', 'facility_coordination')->count(),
                    'document_verification' => FeedbackRecord::where('feedback_type', 'document_verification')->count(),
                    'treatment_progress' => FeedbackRecord::where('feedback_type', 'treatment_progress')->count(),
                ],
                'by_priority' => [
                    'low' => FeedbackRecord::where('priority', 'low')->count(),
                    'medium' => FeedbackRecord::where('priority', 'medium')->count(),
                    'high' => FeedbackRecord::where('priority', 'high')->count(),
                    'urgent' => FeedbackRecord::where('priority', 'urgent')->count(),
                ],
                'recent_feedbacks' => FeedbackRecord::where('created_at', '>=', now()->subDays(7))->count(),
                'avg_completion_time' => FeedbackRecord::whereNotNull('completed_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours')
                    ->value('avg_hours')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Feedback statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search enrollees for feedback creation
     */
    public function searchEnrollees(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2'
        ]);

        try {
            $search = $request->search;

            $enrollees = Enrollee::with(['facility:id,name,hcp_code'])
                ->where(function ($query) use ($search) {
                    $query->where('enrollee_id', 'like', "%{$search}%")
                          ->orWhere('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('phone', 'like', "%{$search}%");
                })
                ->where('status', 1) // Only active enrollees
                ->limit(20)
                ->get(['id', 'enrollee_id', 'first_name', 'middle_name', 'last_name', 'phone', 'sex', 'facility_id']);

            return response()->json([
                'success' => true,
                'data' => $enrollees,
                'message' => 'Enrollees found successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search enrollees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get feedback officers (users with feedback role)
     */
    public function getFeedbackOfficers(): JsonResponse
    {
        try {
            $officers = \App\Models\User::whereHas('roles', function ($query) {
                $query->where('name', 'feedback_officer');
            })
            ->orWhereHas('permissions', function ($query) {
                $query->where('name', 'manage_feedback');
            })
            ->select('id', 'name', 'email')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $officers,
                'message' => 'Feedback officers retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve feedback officers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign feedback to officer
     */
    public function assignToOfficer(Request $request, $id): JsonResponse
    {
        $request->validate([
            'feedback_officer_id' => 'required|exists:users,id'
        ]);

        try {
            $feedback = FeedbackRecord::findOrFail($id);

            $feedback->update([
                'feedback_officer_id' => $request->feedback_officer_id,
                'status' => 'in_progress',
                'updated_by' => Auth::id()
            ]);

            $feedback->load(['feedbackOfficer:id,name,email']);

            return response()->json([
                'success' => true,
                'data' => $feedback,
                'message' => 'Feedback assigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get my assigned feedbacks (for feedback officers)
     */
    public function getMyFeedbacks(Request $request): JsonResponse
    {
        try {
            $query = FeedbackRecord::with([
                'enrollee:id,enrollee_id,first_name,middle_name,last_name,phone,sex',
                'referral:id,referral_code,status',
                'paCode:id,code,status'
            ])
            ->where('feedback_officer_id', Auth::id());

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            // Sort by priority and date
            $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low')")
                  ->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 15);
            $feedbacks = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $feedbacks,
                'message' => 'My feedback records retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve my feedback records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
