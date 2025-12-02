<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Claim;
use App\Models\ClaimDiagnosis;
use App\Models\ClaimTreatment;
use App\Models\ComplianceAlert;
use App\Services\ClaimsAutomation\AdmissionService;
use App\Services\ClaimsAutomation\BundleClassificationService;
use App\Services\ClaimsAutomation\ClaimsAutomationService;
use App\Services\ClaimsAutomation\ComplianceValidationService;
use App\Services\ClaimsAutomation\PAAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClaimsAutomationController extends Controller
{
    protected ClaimsAutomationService $automationService;
    protected AdmissionService $admissionService;
    protected BundleClassificationService $bundleService;
    protected PAAutomationService $paService;
    protected ComplianceValidationService $complianceService;

    public function __construct(
        ClaimsAutomationService $automationService,
        AdmissionService $admissionService,
        BundleClassificationService $bundleService,
        PAAutomationService $paService,
        ComplianceValidationService $complianceService
    ) {
        $this->automationService = $automationService;
        $this->admissionService = $admissionService;
        $this->bundleService = $bundleService;
        $this->paService = $paService;
        $this->complianceService = $complianceService;
    }

    /**
     * Create a new admission
     */
    public function createAdmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
            'facility_id' => 'required|exists:facilities,id',
            'referral_id' => 'nullable|exists:referrals,id',
            'pa_code_id' => 'nullable|exists:p_a_codes,id',
            'admission_date' => 'nullable|date',
            'admission_type' => 'nullable|in:elective,emergency,transfer',
            'principal_diagnosis_code' => 'nullable|string',
            'principal_diagnosis_description' => 'nullable|string',
            'admission_reason' => 'nullable|string',
            'ward_type' => 'nullable|string',
            'planned_ward_days' => 'nullable|integer|min:1',
            'attending_physician_name' => 'required|string',
            'attending_physician_license' => 'nullable|string',
            'attending_physician_specialization' => 'nullable|string',
        ]);

        // Check if patient can be admitted
        $canAdmit = $this->admissionService->canAdmit($validated['enrollee_id']);
        if (!$canAdmit['can_admit']) {
            return response()->json([
                'success' => false,
                'message' => $canAdmit['reason'],
                'active_admission' => $canAdmit['active_admission'] ?? null,
            ], 422);
        }

        $admission = $this->admissionService->createAdmission($validated);

        return response()->json([
            'success' => true,
            'message' => 'Admission created successfully',
            'data' => $admission->load(['enrollee', 'facility', 'referral', 'principalPACode']),
        ], 201);
    }

    /**
     * Discharge a patient
     */
    public function dischargePatient(Request $request, Admission $admission): JsonResponse
    {
        $validated = $request->validate([
            'discharge_date' => 'nullable|date',
            'discharge_summary' => 'nullable|string',
            'discharge_diagnosis' => 'nullable|string',
        ]);

        if (!$admission->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Patient is not currently admitted',
            ], 422);
        }

        $admission = $this->admissionService->dischargePatient($admission, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Patient discharged successfully',
            'data' => $admission,
        ]);
    }

    /**
     * Process a claim through the automation pipeline
     */
    public function processClaim(Claim $claim): JsonResponse
    {
        $result = $this->automationService->processClaim($claim);

        return response()->json([
            'success' => true,
            'message' => 'Claim processed successfully',
            'data' => $result,
        ]);
    }

    /**
     * Get claim preview with structured sections
     */
    public function getClaimPreview(Claim $claim): JsonResponse
    {
        $preview = $this->automationService->getClaimPreview($claim);

        return response()->json([
            'success' => true,
            'data' => $preview,
        ]);
    }

    /**
     * Validate a claim for compliance
     */
    public function validateClaim(Claim $claim): JsonResponse
    {
        $result = $this->complianceService->validateClaim($claim);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Classify all treatments in a claim
     */
    public function classifyTreatments(Claim $claim): JsonResponse
    {
        $results = $this->bundleService->classifyAllTreatments($claim);

        return response()->json([
            'success' => true,
            'message' => 'Treatments classified successfully',
            'data' => [
                'classifications' => $results,
                'bundle_total' => $claim->fresh()->bundle_total,
                'ffs_total' => $claim->fresh()->ffs_total,
            ],
        ]);
    }

    /**
     * Add a new diagnosis to a claim (handles complication detection)
     */
    public function addDiagnosis(Request $request, Claim $claim): JsonResponse
    {
        $validated = $request->validate([
            'icd_10_code' => 'required|string',
            'description' => 'required|string',
            'is_complication' => 'nullable|boolean',
        ]);

        $result = $this->automationService->handleNewDiagnosis($claim, $validated);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * Detect missing PA codes for a claim
     */
    public function detectMissingPAs(Claim $claim): JsonResponse
    {
        $missingPAs = $this->paService->detectMissingPAs($claim);

        return response()->json([
            'success' => true,
            'data' => [
                'missing_count' => $missingPAs->count(),
                'missing_items' => $missingPAs,
            ],
        ]);
    }

    /**
     * Convert a bundle treatment to FFS
     */
    public function convertToFFS(Request $request, ClaimTreatment $treatment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|in:complication,secondary_diagnosis,extended_stay,emergency_procedure,extra_diagnostics,additional_medication,specialist_review,consumables',
        ]);

        $treatment = $this->automationService->convertBundleToFFS($treatment, $validated['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment converted to FFS successfully',
            'data' => $treatment,
        ]);
    }

    /**
     * Get compliance alerts for a claim
     */
    public function getComplianceAlerts(Claim $claim): JsonResponse
    {
        $alerts = $claim->complianceAlerts()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $alerts->count(),
                'open' => $alerts->where('status', 'open')->count(),
                'alerts' => $alerts,
            ],
        ]);
    }

    /**
     * Resolve a compliance alert
     */
    public function resolveAlert(Request $request, ComplianceAlert $alert): JsonResponse
    {
        $validated = $request->validate([
            'resolution_notes' => 'required|string',
        ]);

        $alert->resolve(auth()->id(), $validated['resolution_notes']);

        return response()->json([
            'success' => true,
            'message' => 'Alert resolved successfully',
            'data' => $alert->fresh(),
        ]);
    }

    /**
     * Override a compliance alert (with justification)
     */
    public function overrideAlert(Request $request, ComplianceAlert $alert): JsonResponse
    {
        $validated = $request->validate([
            'justification' => 'required|string|min:20',
        ]);

        $alert->override(auth()->id(), $validated['justification']);

        return response()->json([
            'success' => true,
            'message' => 'Alert overridden successfully',
            'data' => $alert->fresh(),
        ]);
    }

    /**
     * Get admission history for an enrollee
     */
    public function getAdmissionHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enrollee_id' => 'required|exists:enrollees,id',
        ]);

        $history = $this->admissionService->getAdmissionHistory($validated['enrollee_id']);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Build claim sections (A, B, C)
     */
    public function buildSections(Claim $claim): JsonResponse
    {
        $sections = $this->automationService->buildClaimSections($claim);

        return response()->json([
            'success' => true,
            'message' => 'Claim sections built successfully',
            'data' => [
                'sections' => $sections,
                'claim' => $claim->fresh()->load('sections'),
            ],
        ]);
    }
}

