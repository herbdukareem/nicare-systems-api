<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEnrolleeRequest;
use App\Http\Requests\UpdateEnrolleeRequest;
use App\Http\Resources\EnrolleeResource;
use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\EnrolleeDuplicateFlag;
use App\Models\EnrolleeFacilityTransfer;
use App\Models\Facility;
use App\Models\PremiumPin;
use App\Services\EnrolleeDuplicateDetectionService;
use App\Services\EnrolleeService;
use App\Services\NinVerificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Class EnrolleeController
 *
 * Handles CRUD operations for enrollees via API.
 */
class EnrolleeController extends BaseController
{
    /**
     * @var EnrolleeService
     */
    protected EnrolleeService $enrolleeService;

    protected EnrolleeDuplicateDetectionService $duplicateService;

    protected NinVerificationService $ninVerificationService;

    public function __construct(
        EnrolleeService $enrolleeService,
        EnrolleeDuplicateDetectionService $duplicateService,
        NinVerificationService $ninVerificationService
    ) {
        $this->enrolleeService  = $enrolleeService;
        $this->duplicateService = $duplicateService;
        $this->ninVerificationService = $ninVerificationService;
    }

    /**
     * Display a listing of the enrollees.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'first_name', 'last_name', 'status', 'gender', 'facility_id',
            'lga_id', 'ward_id', 'nin', 'enrollee_id', 'date_of_birth_from',
            'date_of_birth_to', 'enrollee_type_id', 'search', 'date_from',
            'date_to', 'approval_date_from', 'approval_date_to', 'age_from', 'age_to'
            , 'insurance_programme_id', 'enrollee_category_id', 'premium_plan_id',
            'funding_type_id', 'benefactor_id', 'enrollment_phase_id',
            'coverage_status', 'legacy_id', 'date_field'
        ]);

        // Handle array parameters
        $arrayFilters = ['status', 'lga_id', 'ward_id', 'facility_id', 'enrollee_type_id', 'gender', 'insurance_programme_id', 'enrollee_category_id', 'premium_plan_id', 'funding_type_id', 'benefactor_id', 'enrollment_phase_id'];
        foreach ($arrayFilters as $filter) {
            if ($request->has($filter) && is_string($request->$filter)) {
                $filters[$filter] = explode(',', $request->$filter);
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $enrollees = $this->enrolleeService->paginate($filters, $perPage, $sortBy, $sortDirection);
        $rows = $enrollees->getCollection()
            ->map(fn (Enrollee $enrollee): array => (new EnrolleeResource($enrollee))->resolve($request))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Enrollees retrieved successfully',
            'data' => [
                'data' => $rows,
                'meta' => [
                    'total' => $enrollees->total(),
                    'per_page' => $enrollees->perPage(),
                    'current_page' => $enrollees->currentPage(),
                    'last_page' => $enrollees->lastPage(),
                    'from' => $enrollees->firstItem(),
                    'to' => $enrollees->lastItem(),
                ],
                'summary' => $this->enrolleeService->summary($filters),
            ],
        ]);
    }

    /**
     * Store a newly created enrollee in storage.
     *
     * @param  StoreEnrolleeRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreEnrolleeRequest $request)
    {
        $data     = $request->validated();
        $enrollee = $this->enrolleeService->create($data);

        // T5: duplicate detection after creation
        $dupResult = $this->duplicateService->check(array_merge($data, ['gender' => $data['sex'] ?? null]));

        if ($dupResult['is_duplicate']) {
            // Flag the newly created enrollee
            $enrollee->update(['is_possible_duplicate' => true]);

            EnrolleeDuplicateFlag::create([
                'enrollee_id'         => $enrollee->id,
                'matched_enrollee_id' => $dupResult['matched_enrollee_id'],
                'match_type'          => $dupResult['match_type'],
                'flagged_by'          => auth()->id(),
            ]);
        }

        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee created successfully', 201);
    }

    /**
     * Display the specified enrollee.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Enrollee $enrollee)
    {
        // load related models
        $enrollee->load([
            'enrolleeType', 'insuranceProgramme', 'enrolleeCategory', 'premiumPlan',
            'benefitPackage', 'premiumPurchase', 'vulnerableGroup', 'fundingType', 'benefactor',
            'enrollmentPhase', 'facility', 'lga', 'ward', 'principal',
            'dependants', 'duplicateFlags.matchedEnrollee', 'facilityTransfers.fromFacility',
            'facilityTransfers.toFacility', 'createdBy', 'approvedBy', 'ninVerifiedBy',
        ])->loadCount('dependants');
        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee retrieved successfully');
    }

    /**
     * Update the specified enrollee in storage.
     *
     * @param  UpdateEnrolleeRequest  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateEnrolleeRequest $request, Enrollee $enrollee)
    {
        $data = $request->validated();
        $enrollee = $this->enrolleeService->update($enrollee, $data);
        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee updated successfully');
    }

    public function approve(Request $request, Enrollee $enrollee)
    {
        if ((int) $enrollee->status !== Enrollee::STATUS_PENDING) {
            return $this->sendError('Only pending enrollees can be approved.', [], 422);
        }

        if ($enrollee->duplicateFlags()->where('resolved', false)->exists()) {
            return $this->sendError('Resolve duplicate flags before approving this enrollee.', [], 422);
        }

        $plan = $enrollee->premiumPlan;
        if ($plan?->requiresPayment() && !$this->hasSatisfiedRequiredPayment($enrollee)) {
            return $this->sendError('This premium plan requires payment. Approve only after a paid invoice or used Premium PIN is linked to this enrollee.', [], 422);
        }

        $validated = $request->validate([
            'coverage_start_date' => ['nullable', 'date'],
            'coverage_end_date' => ['nullable', 'date', 'after_or_equal:coverage_start_date'],
            'capitation_start_date' => ['nullable', 'date'],
            'nin_merge_strategy' => ['nullable', Rule::in(['keep_provided', 'prefer_verified', 'manual'])],
            'nin_field_selection' => ['nullable', 'array'],
            'nin_field_selection.*' => ['nullable', Rule::in(['provided', 'verified'])],
        ]);

        try {
            $enrollee = $this->ninVerificationService->applyApprovalSelection($enrollee, $validated, $request->user());
        } catch (\RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        $approvalDate = now();
        if ($plan) {
            $coverageStart = $plan->calculateCoverageStartDate($approvalDate);
            $coverageEnd = $plan->calculateCoverageEndDate($coverageStart);
        } else {
            $coverageStart = isset($validated['coverage_start_date']) ? Carbon::parse($validated['coverage_start_date']) : $approvalDate;
            $coverageEnd = isset($validated['coverage_end_date']) ? Carbon::parse($validated['coverage_end_date']) : null;
        }

        $enrollee->update([
            'status' => Enrollee::STATUS_ACTIVE,
            'approved_by' => auth()->id(),
            'approval_date' => $approvalDate,
            'capitation_start_date' => $request->capitation_start_date ?? $coverageStart,
            'coverage_start_date' => $coverageStart->toDateString(),
            'coverage_end_date' => $coverageEnd?->toDateString(),
        ]);

        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id' => $enrollee->id,
            'action' => 'approved',
            'description' => 'Enrollee approved',
            'user_id' => auth()->id(),
            'new_values' => [
                'status' => Enrollee::STATUS_ACTIVE,
                'coverage_start_date' => $coverageStart->toDateString(),
                'coverage_end_date' => $coverageEnd?->toDateString(),
            ],
        ]);

        return $this->sendResponse(
            new EnrolleeResource($enrollee->fresh(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility', 'ninVerifiedBy'])),
            'Enrollee approved successfully'
        );
    }

    public function verifyNin(Request $request, Enrollee $enrollee)
    {
        $validated = $request->validate([
            'consent' => ['nullable', 'boolean'],
        ]);

        try {
            $verification = $this->ninVerificationService->verify(
                $enrollee,
                $request->user(),
                (bool) ($validated['consent'] ?? true)
            );
        } catch (\RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse([
            'enrollee' => new EnrolleeResource($enrollee->fresh(['facility', 'fundingType', 'benefactor', 'insuranceProgramme', 'premiumPlan', 'ninVerifiedBy'])),
            'verification' => $verification,
        ], 'NIN verified successfully.');
    }

    public function pendingApproval(Request $request)
    {
        $limit = min(max((int) $request->get('limit', 50), 1), 100);

        $query = Enrollee::query()
            ->with([
                'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'fundingType',
                'benefactor', 'enrollmentPhase', 'facility', 'lga', 'ward', 'principal', 'premiumPurchase',
                'duplicateFlags',
                'ninVerifiedBy',
            ])
            ->where('status', Enrollee::STATUS_PENDING);

        foreach ([
            'programme_id' => 'insurance_programme_id',
            'insurance_programme_id' => 'insurance_programme_id',
            'facility_id' => 'facility_id',
            'benefactor_id' => 'benefactor_id',
            'funding_type_id' => 'funding_type_id',
            'enrollment_phase_id' => 'enrollment_phase_id',
        ] as $param => $column) {
            if ($request->filled($param)) {
                $query->where($column, $request->input($param));
            }
        }

        $items = $request->boolean('random', true)
            ? $query->inRandomOrder()->limit($limit)->get()
            : $query->latest('created_at')->limit($limit)->get();

        return $this->sendResponse(EnrolleeResource::collection($items), 'Pending approval batch retrieved successfully');
    }

    public function bulkIdCard(Request $request)
    {
        $this->extendPdfExecutionWindow();

        $data = $request->validate([
            'benefactor_id'            => ['nullable', 'exists:benefactors,id'],
            'facility_id'              => ['nullable', 'exists:facilities,id'],
            'insurance_programme_id'   => ['nullable', 'exists:insurance_programmes,id'],
            'enrollee_category_id'     => ['nullable', 'exists:enrollee_categories,id'],
            'funding_type_id'          => ['nullable', 'exists:funding_types,id'],
            'enrollment_phase_id'      => ['nullable', 'exists:enrollment_phases,id'],
            'approval_status'          => ['nullable', 'in:pending,approved,all'],
            'date_from'                => ['nullable', 'date'],
            'date_to'                  => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $facilityId = $data['facility_id'] ?? null;
        if (empty($data['benefactor_id']) && empty($facilityId)) {
            return $this->sendError('Please select at least a Benefactor or Provider/Facility before generating bulk ID cards.', [], 422);
        }

        $query = Enrollee::query()->with(['premiumPlan', 'benefitPackage', 'facility']);

        foreach (['benefactor_id', 'insurance_programme_id', 'enrollee_category_id', 'funding_type_id', 'enrollment_phase_id'] as $key) {
            if (!empty($data[$key])) {
                $query->where($key, $data[$key]);
            }
        }

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        if (($data['approval_status'] ?? null) === 'pending') {
            $query->where('status', Enrollee::STATUS_PENDING);
        } elseif (($data['approval_status'] ?? null) === 'approved') {
            $query->whereNotNull('approval_date')->where('status', Enrollee::STATUS_ACTIVE);
        }

        if (!empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        $enrollees = $query
            ->orderBy('facility_id')
            ->orderBy('last_name')
            ->limit(200)
            ->get();
        $this->hydratePdfPhotoSources($enrollees);

        $w = round(85.6 * 72 / 25.4, 2);
        $h = round(54.0 * 72 / 25.4, 2);

        $pdf = Pdf::setOptions(['isRemoteEnabled' => true])
            ->loadView('pdf.bulk-id-card', [
                'enrollees'   => $enrollees,
                'generatedAt' => now(),
            ])
            ->setPaper([0, 0, $w, $h]);

        return $pdf->stream('bulk_id_cards_' . now()->format('Ymd_His') . '.pdf');
    }

    public function idCard(Enrollee $enrollee)
    {
        $this->extendPdfExecutionWindow();

        $enrollee->load([
            'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
            'facility', 'lga', 'ward',
        ]);
        $enrollee->setAttribute('pdf_photo_src', $this->resolvePdfPhotoSource($enrollee->image_url));

        // Fetch QR code as base64 so DomPDF can embed it without remote-URL issues
        $qrBase64 = null;
        try {
            $qrData = urlencode($enrollee->enrollee_id ?: "ID-{$enrollee->id}");
            $qrBytes = file_get_contents("https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={$qrData}");
            if ($qrBytes !== false) {
                $qrBase64 = 'data:image/png;base64,' . base64_encode($qrBytes);
            }
        } catch (\Throwable $e) {
            // QR code is decorative; continue without it
        }

        // CR80 landscape: 85.6 mm × 54 mm → points (72 pt/inch ÷ 25.4 mm/inch)
        $w = round(85.6 * 72 / 25.4, 2); // 242.39
        $h = round(54.0 * 72 / 25.4, 2); // 152.91

        $pdf = Pdf::setOptions(['isRemoteEnabled' => false])->loadView('pdf.enrollee-id-card', [
            'enrollee'    => $enrollee,
            'qrBase64'    => $qrBase64,
            'generatedAt' => now(),
        ])->setPaper([0, 0, $w, $h]);

        return $pdf->stream('enrollee_id_card_' . ($enrollee->enrollee_id ?: $enrollee->id) . '.pdf');
    }

    public function bulkEnrollmentSlip(Request $request)
    {
        $this->extendPdfExecutionWindow(120);

        $data = $request->validate([
            'benefactor_id' => ['nullable', 'exists:benefactors,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'provider_id' => ['nullable', 'exists:facilities,id'],
            'insurance_programme_id' => ['nullable', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'exists:enrollee_categories,id'],
            'funding_type_id' => ['nullable', 'exists:funding_types,id'],
            'enrollment_phase_id' => ['nullable', 'exists:enrollment_phases,id'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'approval_status' => ['nullable', 'in:pending,approved,all'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $facilityId = $data['facility_id'] ?? $data['provider_id'] ?? null;
        if (empty($data['benefactor_id']) && empty($facilityId)) {
            return $this->sendError('Please select at least a Benefactor or Provider/Facility before generating bulk slips.', [], 422);
        }

        $query = Enrollee::query()
            ->with([
                'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
                'fundingType', 'benefactor', 'enrollmentPhase', 'facility', 'lga', 'ward',
                'createdBy', 'approvedBy',
            ]);

        foreach ([
            'benefactor_id' => 'benefactor_id',
            'insurance_programme_id' => 'insurance_programme_id',
            'enrollee_category_id' => 'enrollee_category_id',
            'funding_type_id' => 'funding_type_id',
            'enrollment_phase_id' => 'enrollment_phase_id',
            'status' => 'status',
        ] as $key => $column) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $query->where($column, $data[$key]);
            }
        }

        if ($facilityId) {
            $query->where('facility_id', $facilityId);
        }

        if (($data['approval_status'] ?? null) === 'pending') {
            $query->where('status', Enrollee::STATUS_PENDING);
        } elseif (($data['approval_status'] ?? null) === 'approved') {
            $query->whereNotNull('approval_date')->where('status', Enrollee::STATUS_ACTIVE);
        }

        if (!empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        $enrollees = $query
            ->orderBy('facility_id')
            ->orderBy('benefactor_id')
            ->orderBy('last_name')
            ->limit(500)
            ->get();
        $this->hydratePdfPhotoSources($enrollees);

        $pdf = Pdf::setOptions(['isRemoteEnabled' => false])->loadView('pdf.bulk-enrollment-slip', [
            'enrollees' => $enrollees,
            'filters' => $data,
            'generatedBy' => auth()->user(),
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->stream('bulk_enrollment_slip_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Remove the specified enrollee from storage.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Enrollee $enrollee)
    {
        $this->enrolleeService->delete($enrollee);
        return $this->sendResponse([], 'Enrollee deleted successfully');
    }

    /**
     * Upload passport photo for enrollee.
     *
     * @param  Request  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPassport(Request $request, Enrollee $enrollee)
    {
        $validator = Validator::make($request->all(), [
            'passport' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $file = $request->file('passport');

            // Delete old image if exists
            if ($enrollee->image_url) {
                $oldPath = str_replace('/storage/', '', $enrollee->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Store new image
            $path = $file->store('enrollees/passports', 'public');
            $imageUrl = '/storage/' . $path;

            // Update enrollee record
            $enrollee->update(['image_url' => $imageUrl]);

            return $this->sendResponse([
                'image_url' => $imageUrl
            ], 'Passport photo uploaded successfully');

        } catch (\Exception $e) {
            return $this->sendError('Upload failed', [$e->getMessage()], 500);
        }
    }

    /**
     * Update enrollee status with comment.
     *
     * @param  Request  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, Enrollee $enrollee)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:0,1,2,3,4',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $oldStatus = $enrollee->status;
            $newStatus = (int) $request->status;

            $enrollee->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

            \App\Models\AuditTrail::create([
                'enrollee_id' => $enrollee->id,
                'action' => 'status_changed',
                'description' => 'Status changed from ' . $this->statusLabel((int) $oldStatus) . ' to ' . $this->statusLabel($newStatus),
                'user_id' => auth()->id(),
                'old_values' => json_encode(['status' => $oldStatus]),
                'new_values' => json_encode([
                    'status' => $newStatus,
                    'comment' => $request->comment
                ]),
            ]);

            return $this->sendResponse(
                new EnrolleeResource($enrollee->fresh()),
                'Enrollee status updated successfully'
            );

        } catch (\Exception $e) {
            return $this->sendError('Status update failed', [$e->getMessage()], 500);
        }
    }

    /**
     * Get enrollee statistics.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Enrollee $enrollee)
    {
        try {
            $baseQuery = \App\Models\Claim::where('enrollee_id', $enrollee->id);

            $totalClaims = (clone $baseQuery)->count();

            $totalBenefits = (clone $baseQuery)
                ->whereIn('status', [\App\Models\Claim::STATUS_APPROVED])
                ->sum('total_amount_claimed');

            $facilitiesVisited = (clone $baseQuery)
                ->whereNotNull('facility_id')
                ->distinct('facility_id')
                ->count('facility_id');

            $lastClaim = (clone $baseQuery)
                ->whereNotNull('service_date')
                ->orderByDesc('service_date')
                ->first();

            $lastVisitDays = $lastClaim?->service_date
                ? (int) $lastClaim->service_date->diffInDays(now())
                : null;

            $statistics = [
                'total_claims'      => $totalClaims,
                'total_benefits'    => (float) $totalBenefits,
                'facilities_visited' => $facilitiesVisited,
                'last_visit_days'   => $lastVisitDays,
            ];

            return $this->sendResponse($statistics, 'Statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to get statistics', [$e->getMessage()], 500);
        }
    }

    // =========================================================================
    // T5 — Duplicate Management
    // =========================================================================

    /**
     * GET /api/enrollees/duplicates
     * List all unresolved duplicate flags with matched enrollees.
     */
    public function listDuplicates()
    {
        $flags = EnrolleeDuplicateFlag::with(['enrollee', 'matchedEnrollee', 'flaggedBy'])
            ->where('resolved', false)
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->sendResponse($flags, 'Duplicate flags retrieved successfully');
    }

    /**
     * POST /api/enrollees/duplicates/{flag}/resolve
     * Resolve a duplicate flag.
     */
    public function resolveDuplicate(Request $request, EnrolleeDuplicateFlag $flag)
    {
        $validator = Validator::make($request->all(), [
            'resolution' => ['required', 'in:confirmed_duplicate,confirmed_unique,merged'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        if ($flag->resolved) {
            return $this->sendError('This flag has already been resolved.', [], 422);
        }

        $resolution = $request->input('resolution');

        if ($resolution === 'confirmed_duplicate') {
            // Soft-delete the newer (higher id) enrollee
            $newerEnrollee = $flag->enrollee_id > $flag->matched_enrollee_id
                ? $flag->enrollee
                : $flag->matchedEnrollee;

            if ($newerEnrollee && method_exists($newerEnrollee, 'delete')) {
                $newerEnrollee->delete();
            }
        } elseif ($resolution === 'confirmed_unique') {
            // Clear duplicate flags on both enrollees
            Enrollee::whereIn('id', [$flag->enrollee_id, $flag->matched_enrollee_id])
                ->update(['is_possible_duplicate' => false, 'duplicate_reviewed' => true, 'duplicate_reviewed_by' => auth()->id(), 'duplicate_reviewed_at' => now()]);
        }

        $flag->update([
            'resolved'    => true,
            'resolution'  => $resolution,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        // BR-09 Audit trail
        AuditTrail::create([
            'auditable_type' => EnrolleeDuplicateFlag::class,
            'auditable_id'   => $flag->id,
            'action'         => 'duplicate_resolved',
            'description'    => "Duplicate flag #{$flag->id} resolved as: {$resolution}",
            'user_id'        => auth()->id(),
            'new_values'     => ['resolution' => $resolution],
        ]);

        return $this->sendResponse($flag->fresh(), 'Duplicate flag resolved successfully');
    }

    // =========================================================================
    // T12 — Facility Transfer
    // =========================================================================

    /**
     * POST /api/enrollees/{enrollee}/transfer
     */
    public function transfer(Request $request, Enrollee $enrollee)
    {
        $validator = Validator::make($request->all(), [
            'to_facility_id'  => ['required', 'integer', 'exists:facilities,id'],
            'transfer_reason' => ['required', 'string'],
            'effective_date'  => ['required', 'date', 'after_or_equal:today'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        // Enrollee must be active
        if ((string) $enrollee->status !== 'active' && $enrollee->status->value ?? null !== 1) {
            return $this->sendError('Enrollee must be active to initiate a transfer.', [], 422);
        }

        // No active admission
        $hasActiveAdmission = \App\Models\Admission::where('enrollee_id', $enrollee->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAdmission) {
            return $this->sendError('Cannot transfer enrollee with an active admission.', [], 422);
        }

        // Target facility must be accredited
        $targetFacility = Facility::findOrFail($request->to_facility_id);
        if ($targetFacility->accreditation_status !== 'active') {
            return $this->sendError('Target facility is not accredited (active).', [], 422);
        }

        $transfer = EnrolleeFacilityTransfer::create([
            'enrollee_id'     => $enrollee->id,
            'from_facility_id'=> $enrollee->facility_id,
            'to_facility_id'  => $request->to_facility_id,
            'transfer_reason' => $request->transfer_reason,
            'transferred_by'  => auth()->id(),
            'effective_date'  => $request->effective_date,
            'status'          => 'pending',
        ]);

        // BR-09
        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id'   => $enrollee->id,
            'action'         => 'transfer_initiated',
            'description'    => "Transfer request from facility {$enrollee->facility_id} to {$request->to_facility_id}",
            'user_id'        => auth()->id(),
            'new_values'     => $transfer->toArray(),
        ]);

        return $this->sendResponse($transfer->load(['fromFacility', 'toFacility']), 'Transfer request created successfully', 201);
    }

    /**
     * POST /api/enrollees/transfers/{transfer}/approve
     */
    public function approveTransfer(Request $request, EnrolleeFacilityTransfer $transfer)
    {
        // BR-06: approver cannot be the one who initiated
        if (auth()->id() === $transfer->transferred_by) {
            return $this->sendError('BR-06: You cannot approve a transfer you initiated.', [], 403);
        }

        if ($transfer->status !== 'pending') {
            return $this->sendError('Only pending transfers can be approved.', [], 422);
        }

        // Update enrollee facility
        $enrollee = $transfer->enrollee;
        $enrollee->update(['facility_id' => $transfer->to_facility_id]);

        $transfer->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // BR-09
        AuditTrail::create([
            'auditable_type' => Enrollee::class,
            'auditable_id'   => $enrollee->id,
            'action'         => 'transfer_approved',
            'description'    => "Enrollee transferred from facility {$transfer->from_facility_id} to {$transfer->to_facility_id}",
            'user_id'        => auth()->id(),
            'new_values'     => ['facility_id' => $transfer->to_facility_id],
        ]);

        return $this->sendResponse($transfer->fresh(['fromFacility', 'toFacility', 'enrollee']), 'Transfer approved successfully');
    }

    /**
     * GET /api/enrollees/{enrollee}/transfers
     */
    public function getTransferHistory(Enrollee $enrollee)
    {
        $transfers = EnrolleeFacilityTransfer::where('enrollee_id', $enrollee->id)
            ->with(['fromFacility', 'toFacility', 'transferredBy', 'approvedBy'])
            ->orderByDesc('created_at')
            ->get();

        return $this->sendResponse($transfers, 'Transfer history retrieved successfully');
    }

    private function extendPdfExecutionWindow(int $seconds = 120): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
    }

    private function hydratePdfPhotoSources(Collection $enrollees): void
    {
        $enrollees->each(function (Enrollee $enrollee): void {
            $enrollee->setAttribute('pdf_photo_src', $this->resolvePdfPhotoSource($enrollee->image_url));
        });
    }

    private function resolvePdfPhotoSource(?string $imageUrl): ?string
    {
        if (!$imageUrl) {
            return null;
        }

        if (preg_match('#^data:#i', $imageUrl)) {
            return $imageUrl;
        }

        $publicPath = ltrim((string) parse_url($imageUrl, PHP_URL_PATH), '/');
        if ($publicPath !== '' && str_starts_with($publicPath, 'storage/')) {
            $diskPath = substr($publicPath, strlen('storage/'));
            return $this->buildDiskImageDataUri('public', $diskPath);
        }

        $passportDisk = config('filesystems.enrollee_passport_disk', config('filesystems.default', 'public'));
        $passportBaseUrl = rtrim((string) config("filesystems.disks.{$passportDisk}.url"), '/');
        if ($passportBaseUrl !== '' && str_starts_with($imageUrl, $passportBaseUrl . '/')) {
            $diskPath = ltrim(substr($imageUrl, strlen($passportBaseUrl)), '/');
            return $this->buildDiskImageDataUri($passportDisk, $diskPath);
        }

        if (!preg_match('#^https?://#i', $imageUrl)) {
            return null;
        }

        try {
            $context = stream_context_create([
                'http' => ['timeout' => 5],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $bytes = @file_get_contents($imageUrl, false, $context);

            if ($bytes === false) {
                return null;
            }

            return $this->buildImageDataUri($bytes, $this->guessImageMimeType($imageUrl));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildDiskImageDataUri(string $disk, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        try {
            $bytes = Storage::disk($disk)->get($path);
            return $this->buildImageDataUri($bytes, $this->guessImageMimeType($path));
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildImageDataUri(string $bytes, string $mimeType): string
    {
        return 'data:' . $mimeType . ';base64,' . base64_encode($bytes);
    }

    private function guessImageMimeType(string $path): string
    {
        return match (strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?: $path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'image/jpeg',
        };
    }

    private function hasSatisfiedRequiredPayment(Enrollee $enrollee): bool
    {
        $hasPaidInvoice = $enrollee->invoices()
            ->where(function ($query) {
                $query->where('status', 'paid')->orWhere('status', 1);
            })
            ->exists();

        if ($hasPaidInvoice) {
            return true;
        }

        if ($enrollee->premiumPurchase && $enrollee->premiumPurchase->payment_status === 'confirmed') {
            return true;
        }

        return PremiumPin::where('used_by_enrollee_id', $enrollee->id)
            ->where('status', PremiumPin::STATUS_USED)
            ->exists();
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            Enrollee::STATUS_PENDING => 'Pending Approval',
            Enrollee::STATUS_ACTIVE => 'Approved',
            Enrollee::STATUS_REJECTED => 'Rejected',
            Enrollee::STATUS_SUSPENDED => 'Suspended',
            Enrollee::STATUS_EXPIRED => 'Inactive',
            default => 'Unknown',
        };
    }
}
