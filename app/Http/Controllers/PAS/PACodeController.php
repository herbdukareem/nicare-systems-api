<?php

namespace App\Http\Controllers\PAS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PACode;
use App\Models\Facility;
use App\Models\Referral;
use App\Models\CaseRecord;
use App\Services\FileUploadService;
use Illuminate\Queue\Failed\NullFailedJobProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PACodeController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }
    /**
     * Get all PA codes with filters.
     */
    public function index(Request $request)
    {
        $query = PACode::with(['enrollee', 'facility', 'referral', 'serviceBundle', 'admission']);

        // Filter by facility (for facility users)
        if ($request->has('facility_requested') && $request->facility_requested) {
            $user = auth()->user();
            //Eager load assignedFacilities
            $user->load('assignedFacilities');
            $userFacility = $user->assignedFacilities->first() ?? null;
            if (!$userFacility) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any facility or facility is primary',
                    'data' => []
                ], 403);
            }
            

            $facility = Facility::find($userFacility->facility_id);
             if (!$facility || $facility->is_primary) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not associated with any facility or facility is primary',
                    'data' => []
                ], 403);
            }

            $query->where('facility_id', $facility->id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhereHas('enrollee', function ($eq) use ($search) {
                      $eq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('enrollee_id', 'like', "%{$search}%");
                  })
                  ->orWhereHas('referral', function ($rq) use ($search) {
                      $rq->where('utn', 'like', "%{$search}%");
                  });
            });
        }

        $paCodes = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $paCodes
        ]);
    }

    /**
     * Get a single PA code with all relationships.
     */
    public function show(PACode $paCode)
    {
        $paCode->load([
            'enrollee',
            'facility',
            'referral.documents.documentRequirement',
            'referral.documents.uploader',
            'admission',
            'serviceBundle',
            'documents'
        ]);

        return response()->json(['data' => $paCode]);
    }

    /**
     * Handles POST /v1/pas/pa-codes: Creates a new PA request.
     */
    public function store(Request $request) // Use RequestPACodeRequest for real validation
    {

        $caseRecordIdsRule = ['nullable'];
        if ($request->service_selection_type === 'direct') {
            $caseRecordIdsRule[] = 'required';
        }


        $validator = Validator::make($request->all(), [
            'enrollee_id' => 'required|exists:enrollees,id',
            'facility_id' => 'required|exists:facilities,id',
            'justification' => 'required|string|max:1000',
            'diagnosis_update' => 'nullable|string|max:1000',
            'referral_id' => 'required|exists:referrals,id',
            'admission_id' => 'nullable|exists:admissions,id',
            'service_selection_type' => ['nullable', 'in:bundle,direct'],
            'service_bundle_id' => [
                'nullable',
                'required_if:service_selection_type,bundle',
                'exists:case_records,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $caseRecord = CaseRecord::find($value);
                        if (!$caseRecord || !$caseRecord->is_bundle) {
                            $fail('The selected service bundle must be a bundle case record.');
                        }
                    }
                }
            ],
            'case_record_ids' => $caseRecordIdsRule,
            'case_record_ids.*' => ['exists:case_records,id'],
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

            // 1. POLICY CHECK: MUST have an approved Referral PA (RR) first.
            $referral = Referral::find($request->referral_id);
            if (!$referral || $referral->status !== 'APPROVED') {
                return response()->json([
                    'success' => false,
                    'message' => 'PA Code request denied. A valid, approved Referral Pre-Authorisation (RR) is required before issuing a Follow-up PA Code.',
                ], 403);
            }

            // 2. Determine PA type based on service selection type
            $paType = $request->service_selection_type === 'bundle'
                ? PACode::TYPE_BUNDLE
                : PACode::TYPE_FFS_TOP_UP;

            // 3. Check if there's an active admission for this referral (optional now)
            $admission = null;
            if ($request->admission_id) {
                $admission = \App\Models\Admission::find($request->admission_id);
            } else {
                $admission = \App\Models\Admission::where('referral_id', $request->referral_id)
                              ->where('status', 'active')
                              ->first();
            }

            // 4. Prevent duplicate bundle PA for the same episode (referral-specific)
            if ($paType === PACode::TYPE_BUNDLE) {
                $existingBundlePA = PACode::where('referral_id', $request->referral_id)
                                          ->where('type', PACode::TYPE_BUNDLE)
                                          ->where('status', 'APPROVED')
                                          ->exists();

                if ($existingBundlePA) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A bundle PA is already approved for this episode. Only one bundle PA is allowed per referral/admission.',
                    ], 409);
                }
            }

            $caseRecordIds = $request->case_record_ids ?? null;

            // Parse JSON fields
            $requestedItems = $request->requested_items ? json_decode($request->requested_items, true) : [];
            // ensure case_record_ids is an array
            if (is_string($caseRecordIds)) {
                $caseRecordIds = json_decode($request->case_record_ids, true);
            }

            $paCode = PACode::create([
                'enrollee_id' => $request->enrollee_id,
                'facility_id' => $request->facility_id,
                'referral_id' => $request->referral_id,
                'admission_id' => $admission?->id, // Link to active admission (optional)
                'code' => 'PA-' . strtoupper(bin2hex(random_bytes(3))), // Generates a unique code like PA-A5F8B9
                'type' => $paType,
                'status' => 'PENDING',
                'justification' => $request->justification,
                'diagnosis_update' => $request->diagnosis_update,
                'requested_services' => $requestedItems,
                'service_selection_type' => $request->service_selection_type,
                'service_bundle_id' => $request->service_selection_type === 'bundle' ? $request->service_bundle_id : null,
                'case_record_ids' => $request->service_selection_type === 'direct' ? $caseRecordIds : null,
            ]);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docType => $file) {
                    // Upload file to local storage
                    $uploadResult = $this->fileUploadService->uploadPASDocument(
                        $file,
                        $paCode->code,
                        $docType
                    );

                    if ($uploadResult['success']) {
                        // Find the document requirement
                        $docRequirement = \App\Models\DocumentRequirement::where('document_type', $docType)
                            ->where('request_type', 'pa_code')
                            ->first();

                        // Create document record
                        \App\Models\PACodeDocument::create([
                            'pa_code_id' => $paCode->id,
                            'document_requirement_id' => $docRequirement?->id,
                            'document_type' => $docType,
                            'file_name' => $uploadResult['filename'],
                            'file_path' => $uploadResult['path'],
                            'file_type' => $file->getClientOriginalExtension(),
                            'file_size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'original_filename' => $uploadResult['original_name'],
                            'uploaded_by' => $request->user()?->id,
                            'is_required' => $docRequirement?->is_required ?? false,
                        ]);
                    }
                }
            }

            // Create automatic feedback for FUP request
            $feedbackService = app(\App\Services\FeedbackService::class);
            $feedbackService->createFUPRequestedFeedback($paCode);

            DB::commit();

            $paCode->load(['enrollee', 'facility', 'referral', 'admission', 'serviceBundle', 'documents']);

            return response()->json([
                'success' => true,
                'message' => 'FU-PA Code request submitted successfully',
                'data' => $paCode
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit FU-PA Code request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handles POST /v1/pas/pa-codes/{id}/approve: Approves a PA.
     */
    public function approve(PACode $paCode)
    {
        $paCode->update([
            'status' => 'APPROVED',
            'approval_date' => now(),
        ]);

        // Create automatic feedback for FUP approval
        $feedbackService = app(\App\Services\FeedbackService::class);
        $feedbackService->createFUPApprovedFeedback($paCode);

        $paCode->load(['enrollee', 'facility', 'referral', 'admission', 'serviceBundle']);

        return response()->json(['message' => 'PA code approved successfully.', 'data' => $paCode]);
    }

    /**
     * Handles POST /v1/pas/pa-codes/{id}/reject: Rejects a PA.
     */
    public function reject(Request $request, PACode $paCode)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $paCode->update([
            'status' => 'REJECTED',
            'rejection_reason' => $request->rejection_reason,
        ]);

        $paCode->load(['enrollee', 'facility', 'referral', 'admission', 'serviceBundle']);

        return response()->json(['message' => 'PA code rejected successfully.', 'data' => $paCode]);
    }
}