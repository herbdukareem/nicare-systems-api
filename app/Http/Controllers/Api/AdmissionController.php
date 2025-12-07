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

            return response()->json([
                'success' => true,
                'message' => 'Admission created successfully',
                'data' => $admission->load(['referral', 'bundle', 'enrollee', 'facility']),
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

            return response()->json([
                'success' => true,
                'message' => 'Patient discharged successfully',
                'data' => $admission,
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

