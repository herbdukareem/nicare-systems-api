<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Services\ClaimsAutomation\AdmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    private AdmissionService $admissionService;

    public function __construct(AdmissionService $admissionService)
    {
        $this->admissionService = $admissionService;
    }

    /**
     * Get all admissions with filters
     * GET /api/admissions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Admission::with(['referral', 'serviceBundle', 'enrollee', 'facility', 'paCodes']);

            // Filter by facility
            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->facility_id);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // filter by referral id
            if ($request->has('referral_id')) {
                $query->where('referral_id', $request->referral_id);
            }

            // Filter by enrollee
            if ($request->has('enrollee_id')) {
                $query->where('enrollee_id', $request->enrollee_id);
            }

            // Search by admission code or enrollee name
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('admission_code', 'like', "%{$search}%")
                      ->orWhere('nicare_number', 'like', "%{$search}%")
                      ->orWhereHas('enrollee', function ($eq) use ($search) {
                          $eq->where('first_name', 'like', "%{$search}%")
                             ->orWhere('last_name', 'like', "%{$search}%");
                      });
                });
            }

            $admissions = $query->latest()->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $admissions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new admission from a referral
     * POST /api/admissions
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'referral_id' => 'required|integer|exists:referrals,id',
                'admission_date' => 'required|date',
                'ward_type' => 'required|string',
                'principal_diagnosis_icd10' => 'required|string',
            ]);

            $admission = $this->admissionService->createAdmission(
                $validated['referral_id'],
                $validated
            );

            // Create automatic feedback for admission
            $feedbackService = app(\App\Services\FeedbackService::class);
            $feedbackService->createAdmissionFeedback($admission);

            return response()->json([
                'success' => true,
                'message' => 'Admission created successfully',
                'data' => $admission->load(['referral', 'serviceBundle', 'enrollee', 'facility']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get admission details
     * GET /api/admissions/{id}
     */
    public function show(Admission $admission): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $admission->load(['referral', 'bundle', 'enrollee', 'facility', 'paCodes']),
        ]);
    }

    /**
     * Get active admission for an enrollee
     * GET /api/admissions/enrollee/{enrolleeId}
     */
    public function getActiveAdmission($enrolleeId): JsonResponse
    {
        try {
            $admission = $this->admissionService->getActiveAdmission($enrolleeId);

            if (!$admission) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active admission found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $admission->load(['referral', 'bundle', 'enrollee', 'facility']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Discharge a patient
     * POST /api/admissions/{id}/discharge
     */
    public function discharge(Request $request, Admission $admission): JsonResponse
    {
        try {
            $validated = $request->validate([
                'discharge_date' => 'required|date',
                'discharge_summary' => 'required|string',
                'ward_days' => 'required|integer|min:1',
            ]);

            $admission = $this->admissionService->dischargePatient($admission, $validated);

            // Create automatic feedback for discharge
            $feedbackService = app(\App\Services\FeedbackService::class);
            $feedbackService->createDischargeFeedback($admission);

            return response()->json([
                'success' => true,
                'message' => 'Patient discharged successfully',
                'data' => $admission->load(['referral', 'serviceBundle', 'enrollee', 'facility', 'paCodes']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check if a referral can be admitted
     * GET /api/admissions/check/{referralId}
     */
    public function checkAdmissionEligibility($referralId): JsonResponse
    {
        try {
            $canAdmit = $this->admissionService->canAdmit($referralId);

            return response()->json([
                'success' => true,
                'can_admit' => $canAdmit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
