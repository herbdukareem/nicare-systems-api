<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Referral;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ReferralController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of referrals
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Referral::with(['referringFacility', 'receivingFacility', 'enrollee', 'approvedBy', 'deniedBy']);

            // Apply filters
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('severity_level') && !empty($request->severity_level)) {
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

            // Pagination
            $perPage = $request->get('per_page', 15);
            $referrals = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handles POST /v1/pas/referrals: Submits a new Referral Request (RR).
     */
    public function store(Request $request)
    {
        // 1. Validation (Ensures all fields from the RR Template are present)
        $validator = Validator::make($request->all(), [
            'enrollee_id' => 'required|exists:enrollees,id',
            'referring_facility_id' => 'required|exists:facilities,id',
            'receiving_facility_id' => 'required|exists:facilities,id',
            'presenting_complains' => 'required|string',
            'reasons_for_referral' => 'required|string',
            'treatments_given' => 'required|string',
            'investigations_done' => 'required|string',
            'examination_findings' => 'required|string',
            'preliminary_diagnosis' => 'required|string',
            'medical_history' => 'nullable|string',
            'medication_history' => 'nullable|string',
            'severity_level' => 'required|in:Routine,Urgent/Expidited,Emergency',
            'referring_person_name' => 'required|string',
            'referring_person_specialisation' => 'required|string',
            'referring_person_cadre' => 'required|string',
            'contact_person_name' => 'nullable|string',
            'contact_person_phone' => 'nullable|string',
            'contact_person_email' => 'nullable|email',
            'requested_services' => 'required|json',
            'flow_type' => 'nullable|in:new,followup',
            'utn' => 'nullable|string|exists:referrals,utn',
            'service_selection_type' => 'nullable|in:bundle,direct',
            'service_bundle_id' => 'nullable|exists:service_bundles,id',
            'case_record_ids' => 'nullable|json',
            'documents.*' => 'nullable|file|max:10240', // Max 10MB per file
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

            // Get facility code for referral code generation
            $facility = \App\Models\Facility::findOrFail($request->referring_facility_id);
            $facilityCode = $facility->nicare_code ?? $facility->hcp_code ?? 'FAC001';
            $serial = Referral::where('referring_facility_id', $request->referring_facility_id)->count() + 1;

            // Parse requested services
            $requestedServices = json_decode($request->requested_services, true);
            $caseRecordIds = $request->case_record_ids ? json_decode($request->case_record_ids, true) : null;

            // Create referral
            $referral = Referral::create([
                // Core Fields
                'enrollee_id' => $request->enrollee_id,
                'referring_facility_id' => $request->referring_facility_id,
                'receiving_facility_id' => $request->receiving_facility_id,
                'request_date' => now(),

                // Generated Codes (RR Template)
                'referral_code' => 'NGSCHA/' . $facilityCode . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT),
                'utn' => 'UTN-' . strtoupper(bin2hex(10)), // Unique Transaction Number
                'valid_until' => now()->addMonths(3), // UTN valid for 3 months

                // Clinical Data
                'presenting_complains' => $request->presenting_complains,
                'reasons_for_referral' => $request->reasons_for_referral,
                'treatments_given' => $request->treatments_given,
                'investigations_done' => $request->investigations_done,
                'examination_findings' => $request->examination_findings,
                'preliminary_diagnosis' => $request->preliminary_diagnosis,
                'medical_history' => $request->medical_history,
                'medication_history' => $request->medication_history,
                'severity_level' => $request->severity_level,

                // Referring Personnel
                'referring_person_name' => $request->referring_person_name,
                'referring_person_specialisation' => $request->referring_person_specialisation,
                'referring_person_cadre' => $request->referring_person_cadre,

                // Contact Information
                'contact_person_name' => $request->contact_person_name,
                'contact_person_phone' => $request->contact_person_phone,
                'contact_person_email' => $request->contact_person_email,

                // Service Selection
                'requested_services' => $requestedServices,
                'service_selection_type' => $request->service_selection_type,
                'service_bundle_id' => $request->service_bundle_id,
                'case_record_ids' => $caseRecordIds,

                // Status
                'status' => 'PENDING',
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docType => $file) {
                    // Upload file to S3/Wasabi
                    $uploadResult = $this->fileUploadService->uploadPASDocument(
                        $file,
                        $referral->referral_code,
                        $docType
                    );

                    if ($uploadResult['success']) {
                        // Find the document requirement
                        $docRequirement = \App\Models\DocumentRequirement::where('document_type', $docType)
                            ->where('request_type', 'referral')
                            ->first();

                        // Create document record
                        \App\Models\ReferralDocument::create([
                            'referral_id' => $referral->id,
                            'document_requirement_id' => $docRequirement?->id,
                            'document_type' => $docType,
                            'file_name' => $uploadResult['filename'],
                            'file_path' => $uploadResult['path'],
                            'file_type' => $file->getClientOriginalExtension(),
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'original_filename' => $uploadResult['original_name'],
                            'uploaded_by' => Auth::id(),
                            'is_required' => $docRequirement?->is_required ?? false,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Referral Request submitted for review.',
                'data' => $referral->load(['referringFacility', 'receivingFacility', 'enrollee', 'documents'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit referral request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handles POST /v1/pas/referrals/{id}/approve: Approves a Referral.
     */
    public function approve(Referral $referral)
    {
        $referral->update(['status' => 'APPROVED', 'approval_date' => now()]);

        // Auto-create PA code if referral has service bundle selected
        if ($referral->service_bundle_id) {
            \App\Models\PACode::create([
                'enrollee_id' => $referral->enrollee_id,
                'facility_id' => $referral->receiving_facility_id,
                'referral_id' => $referral->id,
                'admission_id' => null, // Will be linked when admission is created
                'code' => 'PA-REF-' . $referral->id,
                'type' => \App\Models\PACode::TYPE_BUNDLE,
                'status' => 'APPROVED',
                'justification' => 'Auto-generated from approved referral with service bundle',
                'requested_services' => [],
                'service_selection_type' => 'bundle',
                'service_bundle_id' => $referral->service_bundle_id,
                'case_record_ids' => null,
            ]);
        }

        return response()->json([
            'message' => 'Referral PA approved. Receiving facility can now request Follow-up PA Codes.',
            'referral_code' => $referral->referral_code,
            'utn' => $referral->utn
        ]);
    }

    /**
     * Display the specified referral
     */
    public function show(Referral $referral): JsonResponse
    {
        try {
            $referral->load(['approvedBy', 'deniedBy', 'paCodes']);

            // Add file URLs if files exist
            if ($referral->enrollee_id_card_path) {
                $referral->enrollee_id_card_url = $this->fileUploadService->getFileUrl($referral->enrollee_id_card_path);
            }

            if ($referral->referral_letter_path) {
                $referral->referral_letter_url = $this->fileUploadService->getFileUrl($referral->referral_letter_path);
            }

            return response()->json([
                'success' => true,
                'data' => $referral
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Deny a referral
     */
    public function deny(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending referrals can be denied'
                ], 400);
            }

            $referral->deny(Auth::user(), $request->comments);

            return response()->json([
                'success' => true,
                'message' => 'Referral denied successfully',
                'data' => $referral->fresh(['deniedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deny referral',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending referrals by facility
     * For modify referral flow - shows referrals FROM the selected facility
     */
    public function getPendingByFacility($facilityId): JsonResponse
    {
        try {
            $referrals = Referral::where('status', 'pending')
                ->where(function ($query) use ($facilityId) {
                    // For modify referral, we want referrals FROM this facility (referring_facility_id)
                    // Check if facilityId is numeric (ID) or string (NiCare code)
                    if (is_numeric($facilityId)) {
                        $query->where('referring_facility_id', $facilityId);
                    } else {
                        // Look up facility by NiCare code (hcp_code) and then filter by its ID
                        $facility = \App\Models\Facility::where('hcp_code', $facilityId)->first();

                        if ($facility) {
                            $query->where('referring_facility_id', $facility->id);
                        } else {
                            // If facility cannot be resolved, force an empty result set
                            $query->whereRaw('1 = 0');
                        }
                    }
                })
                ->with(['referringFacility', 'receivingFacility', 'enrollee', 'approvedBy', 'deniedBy'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($referral) {
                    $referral->loadMissing('enrollee');
                    $enrollee = $referral->enrollee;

                    $nameParts = [];
                    if ($enrollee) {
                        if (!empty($enrollee->first_name)) {
                            $nameParts[] = $enrollee->first_name;
                        }
                        if (!empty($enrollee->middle_name)) {
                            $nameParts[] = $enrollee->middle_name;
                        }
                        if (!empty($enrollee->last_name)) {
                            $nameParts[] = $enrollee->last_name;
                        }
                    }

                    return [
                        'id' => $referral->id,
                        'referral_code' => $referral->referral_code,
                        'patient_name' => !empty($nameParts) ? implode(' ', $nameParts) : null,
                        'current_service' => $referral->service_description ?? 'General Consultation',
                        'severity_level' => $referral->severity_level,
                        'created_at' => $referral->created_at,
                        'nicare_number' => $enrollee?->enrollee_id,
                        'presenting_complaints' => $referral->presenting_complaints,
                        'preliminary_diagnosis' => $referral->preliminary_diagnosis,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $referrals
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending referrals',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Modify referral service
     */
    public function modifyService(Request $request, Referral $referral): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'new_service_id' => 'required|integer|exists:services,id',
                'modification_reason' => 'required|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if ($referral->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending referrals can be modified'
                ], 400);
            }

            DB::beginTransaction();

            // Store the old service information for history tracking
            $oldServiceData = [
                'old_service_id' => $referral->service_id,
                'old_service_description' => $referral->service_description,
                'modified_at' => now(),
                'modified_by' => Auth::id(),
                'modification_reason' => $request->modification_reason
            ];

            // Update referral with new service
            $referral->update([
                'service_id' => $request->new_service_id,
                'service_description' => $request->service_description ?? 'Updated Service',
                'modification_history' => json_encode(array_merge(
                    json_decode($referral->modification_history ?? '[]', true),
                    [$oldServiceData]
                ))
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Referral service modified successfully',
                'data' => $referral->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to modify referral service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get referral statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_referrals' => Referral::count(),
                'pending_requests' => Referral::where('status', 'pending')->count(),
                'approved_referrals' => Referral::where('status', 'approved')->count(),
                'denied_referrals' => Referral::where('status', 'denied')->count(),
                'emergency_cases' => Referral::where('severity_level', 'emergency')->count(),
                'urgent_cases' => Referral::where('severity_level', 'urgent')->count(),
                'routine_cases' => Referral::where('severity_level', 'routine')->count(),
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
}
