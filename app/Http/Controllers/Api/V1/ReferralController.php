<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Resources\ReferralResource;
use App\Services\ReferralService;
use App\Services\FileUploadService;
use App\Models\DocumentRequirement;
use App\Models\ReferralDocument;
use App\Models\Referral;
use App\Models\CaseRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReferralController extends BaseController
{
    private ReferralService $service;
    private FileUploadService $fileUploadService;

    public function __construct(ReferralService $service, FileUploadService $fileUploadService)
    {
        $this->service = $service;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * List referrals with basic filtering.
     */
    public function index(Request $request)
    {
        // Determine eager loading based on 'with' parameter
        $eagerLoad = ['enrollee', 'referringFacility', 'receivingFacility','serviceBundle'];
        if ($request->has('with')) {
            $additionalRelations = explode(',', $request->with);
            $eagerLoad = array_merge($eagerLoad, $additionalRelations);
        }

        $query = Referral::with($eagerLoad)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->severity_level, fn($q) => $q->where('severity_level', $request->severity_level))
            // Filter by UTN validated status
            ->when($request->has('utn_validated'), function ($q) use ($request) {
                $q->where('utn_validated', filter_var($request->utn_validated, FILTER_VALIDATE_BOOLEAN));
            })
            // Filter by claim submitted status
            ->when($request->has('claim_submitted'), function ($q) use ($request) {
                $q->where('claim_submitted', filter_var($request->claim_submitted, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where('referral_code', 'like', "%{$search}%")
                    ->orWhere('utn', 'like', "%{$search}%")
                    ->orWhereHas('enrollee', function ($eq) use ($search) {
                        $eq->where('enrollee_id', 'like', "%{$search}%")
                           ->orWhere('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });

        $referrals = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return $this->sendResponse(
            ReferralResource::collection($referrals),
            'Referrals retrieved successfully'
        );
    }

    /**
     * Create a referral. Requested services are optional.
     */
    public function store(Request $request)
    {
        // Handle case_record_ids - convert JSON string to array if needed
        if ($request->has('case_record_ids') && is_string($request->case_record_ids)) {
            $request->merge([
                'case_record_ids' => json_decode($request->case_record_ids, true) ?? []
            ]);
        }

        // BUSINESS RULE 1: Check if enrollee has an active admission/episode
        $enrolleeId = $request->input('enrollee_id');
        if ($enrolleeId) {
            // Check for active admission first (episode not completed/closed)
            $activeAdmission = \App\Models\Admission::where('enrollee_id', $enrolleeId)
                ->where('status', 'active')
                ->first();

            if ($activeAdmission) {
                return $this->sendError(
                    'This enrollee has an active admission/episode (Admission Code: ' . $activeAdmission->admission_code . '). The current episode must be closed/completed (patient discharged) before a new referral can be created.',
                    ['active_admission' => [
                        'id' => $activeAdmission->id,
                        'admission_code' => $activeAdmission->admission_code,
                        'admission_date' => $activeAdmission->admission_date,
                        'status' => $activeAdmission->status,
                    ]],
                    422
                );
            }

            // BUSINESS RULE 2: Check if enrollee has pending referral without submitted claim
            $pendingReferral = Referral::where('enrollee_id', $enrolleeId)
                ->where('status', 'APPROVED')
                ->where('claim_submitted', false)
                ->first();

            if ($pendingReferral) {
                return $this->sendError(
                    'This enrollee has an approved referral (UTN: ' . $pendingReferral->utn . ') without a submitted claim. Please submit a claim for that referral before creating a new one.',
                    ['pending_referral' => [
                        'id' => $pendingReferral->id,
                        'utn' => $pendingReferral->utn,
                        'referral_code' => $pendingReferral->referral_code,
                    ]],
                    422
                );
            }
        }

        // If service_selection_type is bundle, case_record_ids can be empty array
        $caseRecordIdsRule = ['nullable', 'array'];
        if ($request->service_selection_type === 'direct') {
            $caseRecordIdsRule[] = 'required';
        }

        $validated = $request->validate([
            'enrollee_id' => ['required', 'integer', 'exists:enrollees,id'],
            'referring_facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'receiving_facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'presenting_complains' => ['required', 'string'],
            'reasons_for_referral' => ['required', 'string'],
            'treatments_given' => ['required', 'string'],
            'investigations_done' => ['required', 'string'],
            'examination_findings' => ['required', 'string'],
            'preliminary_diagnosis' => ['required', 'string'],
            'medical_history' => ['nullable', 'string'],
            'medication_history' => ['nullable', 'string'],
            'severity_level' => ['required', 'string'],
            'referring_person_name' => ['required', 'string'],
            'referring_person_specialisation' => ['required', 'string'],
            'referring_person_cadre' => ['required', 'string'],
            'contact_person_name' => ['nullable', 'string'],
            'contact_person_phone' => ['nullable', 'string'],
            'contact_person_email' => ['nullable', 'email'],
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
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpeg,jpg,png,doc,docx,xls,xlsx'],
        ]);

        DB::beginTransaction();
        try {
            // Create the referral
            $referral = $this->service->create($validated);

            // Handle document uploads
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $docType => $file) {
                    // Upload file to local storage
                    $uploadResult = $this->fileUploadService->uploadPASDocument(
                        $file,
                        $referral->referral_code,
                        $docType
                    );

                    if ($uploadResult['success']) {
                        // Find the document requirement
                        $docRequirement = DocumentRequirement::where('document_type', $docType)
                            ->where('request_type', 'referral')
                            ->first();

                        // Create document record
                        ReferralDocument::create([
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

            return $this->sendResponse(
                new ReferralResource($referral->load([
                    'enrollee',
                    'referringFacility',
                    'receivingFacility',
                    'serviceBundle.components.caseRecord',
                    'documents'
                ])),
                'Referral created successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->sendError('Failed to create referral: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Show a single referral.
     */
    public function show(\App\Models\Referral $referral)
    {
        $referral->load([
            'enrollee',
            'referringFacility',
            'receivingFacility',
            'serviceBundle',
            'documents.documentRequirement',
            'documents.uploader',
            'feedbackRecords.feedbackOfficer',
            'feedbackRecords.creator'
        ]);

        return $this->sendResponse(
            new ReferralResource($referral),
            'Referral retrieved successfully'
        );
    }

    /**
     * Approve a referral.
     */
    public function approve(\App\Models\Referral $referral)
    {
        if ($referral->status !== 'PENDING') {
            return $this->sendError('Only pending referrals can be approved', [], 400);
        }

        $referral->update([
            'status' => 'APPROVED',
            'approval_date' => now(),
        ]);

        // Auto-create PA code if referral has service bundle selected
        // BUSINESS RULE 3: Only one bundle PA per referral
        if ($referral->service_bundle_id && !$referral->hasBundlePACode()) {
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

        return $this->sendResponse(
            new ReferralResource($referral->fresh(['enrollee', 'referringFacility', 'receivingFacility'])),
            'Referral approved successfully. UTN is now active.'
        );
    }

    /**
     * Reject a referral.
     */
    public function reject(Request $request, \App\Models\Referral $referral)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($referral->status !== 'PENDING') {
            return $this->sendError('Only pending referrals can be rejected', [], 400);
        }

        $referral->update([
            'status' => 'REJECTED',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return $this->sendResponse(
            new ReferralResource($referral->fresh(['enrollee', 'referringFacility', 'receivingFacility'])),
            'Referral rejected successfully'
        );
    }
}
