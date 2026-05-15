<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\EnrolleeDuplicateFlag;
use App\Models\EnrolleeFacilityTransfer;
use App\Models\Facility;
use App\Models\PremiumPlan;
use App\Models\PremiumPin;
use App\Exports\EnrolleesExport;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request as FacadeRequest;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class EnrolleeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Enrollee::with([
            'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
            'fundingType', 'benefactor', 'vulnerableGroup', 'enrollmentPhase',
            'facility', 'lga', 'ward', 'principal',
        ]);

        // Apply filters with array support
        if ($request->has('status')) {
            $status = $request->status;
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('lga_id')) {
            $lgaIds = $request->lga_id;
            if (is_array($lgaIds)) {
                $query->whereIn('lga_id', $lgaIds);
            } else {
                $query->where('lga_id', $lgaIds);
            }
        }

        if ($request->has('ward_id')) {
            $wardIds = $request->ward_id;
            if (is_array($wardIds)) {
                $query->whereIn('ward_id', $wardIds);
            } else {
                $query->where('ward_id', $wardIds);
            }
        }

        if ($request->has('facility_id')) {
            $facilityIds = $request->facility_id;
            if (is_array($facilityIds)) {
                $query->whereIn('facility_id', $facilityIds);
            } else {
                $query->where('facility_id', $facilityIds);
            }
        }

        if ($request->has('enrollee_type_id')) {
            $enrolleeTypeIds = $request->enrollee_type_id;
            if (is_array($enrolleeTypeIds)) {
                $query->whereIn('enrollee_type_id', $enrolleeTypeIds);
            } else {
                $query->where('enrollee_type_id', $enrolleeTypeIds);
            }
        }

        if ($request->has('gender')) {
            $genders = $request->gender;
            if (is_array($genders)) {
                $query->whereIn('gender', $genders);
            } else {
                $query->where('gender', $genders);
            }
        }

        // Date range filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('approval_date_from')) {
            $query->whereDate('approval_date', '>=', $request->approval_date_from);
        }

        if ($request->has('approval_date_to')) {
            $query->whereDate('approval_date', '<=', $request->approval_date_to);
        }

        // Age range filter
        if ($request->has('age_from') || $request->has('age_to')) {
            $query->where(function($q) use ($request) {
                if ($request->has('age_from')) {
                    $dateFrom = now()->subYears($request->age_from)->format('Y-m-d');
                    $q->where('date_of_birth', '<=', $dateFrom);
                }
                if ($request->has('age_to')) {
                    $dateTo = now()->subYears($request->age_to)->format('Y-m-d');
                    $q->where('date_of_birth', '>=', $dateTo);
                }
            });
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('enrollee_id', 'like', "%{$search}%")
                  ->orWhere('nin', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $enrollees = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $enrollees,
            'meta' => [
                'total' => $enrollees->total(),
                'per_page' => $enrollees->perPage(),
                'current_page' => $enrollees->currentPage(),
                'last_page' => $enrollees->lastPage(),
                'from' => $enrollees->firstItem(),
                'to' => $enrollees->lastItem(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->normalizeIncomingEnrolleeData($request->all());
        $validator = Validator::make($input, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|unique:enrollees,phone',
            'nin' => 'nullable|string|unique:enrollees,nin',
            'email' => 'nullable|email|unique:enrollees,email',
            'date_of_birth' => 'required|date',
            'sex' => 'required|integer|in:1,2',
            'marital_status' => 'nullable|integer|in:1,2,3,4',
            'address' => 'nullable|string',
            'village' => 'nullable|string|max:255',
            'pregnant' => 'nullable|boolean',
            'disability' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'facility_id' => 'required|exists:facilities,id',
            'lga_id' => 'required|exists:lgas,id',
            'ward_id' => 'required|exists:wards,id',
            'insurance_programme_id' => 'nullable|exists:insurance_programmes,id',
            'enrollee_category_id' => 'nullable|exists:enrollee_categories,id',
            'premium_plan_id' => 'nullable|exists:premium_plans,id',
            'premium_pin_id' => 'nullable|exists:premium_pins,id',
            'benefit_package_id' => 'nullable|exists:benefit_packages,id',
            'funding_type_id' => 'nullable|exists:funding_types,id',
            'benefactor_id' => 'nullable|exists:benefactors,id',
            'vulnerable_group_id' => 'nullable|exists:vulnerable_groups,id',
            'principal_enrollee_id' => 'nullable|exists:enrollees,id',
            'relationship_to_principal' => 'nullable|integer|in:1,2,3,4',
            'coverage_start_date' => 'nullable|date',
            'coverage_end_date' => 'nullable|date|after_or_equal:coverage_start_date',
        ]);

        $validator->after(function ($validator) use ($input) {
            $this->validateDependantPlanRules($validator, $input);
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $enrolleeData = $validator->validated();
            $enrolleeData['enrollee_id'] = $this->generateEnrolleeId();
            $enrolleeData['created_by'] = auth()->id();
            $enrolleeData['status'] = Enrollee::STATUS_PENDING;

            $enrollee = Enrollee::create($enrolleeData);

            // Create audit trail
            AuditTrail::create([
                'enrollee_id' => $enrollee->id,
                'action' => 'created',
                'description' => 'Enrollee created',
                'user_id' => auth()->id(),
                'old_values' => null,
                'new_values' => json_encode($enrollee->toArray()),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Enrollee created successfully',
                'data' => $enrollee->load(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility', 'lga', 'ward']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create enrollee',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Enrollee $enrollee): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $enrollee->load([
                'enrolleeType', 'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
                'fundingType', 'benefactor', 'vulnerableGroup', 'enrollmentPhase',
                'facility', 'lga', 'ward', 'principal', 'dependants', 'invoices'
            ]),
        ]);
    }

    public function update(Request $request, Enrollee $enrollee): JsonResponse
    {
        $input = $this->normalizeIncomingEnrolleeData($request->all());
        $validator = Validator::make($input, [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'phone' => 'sometimes|string|unique:enrollees,phone,' . $enrollee->id,
            'nin' => 'nullable|string|unique:enrollees,nin,' . $enrollee->id,
            'email' => 'nullable|email|unique:enrollees,email,' . $enrollee->id,
            'date_of_birth' => 'sometimes|date',
            'sex' => 'sometimes|integer|in:1,2',
            'marital_status' => 'nullable|integer|in:1,2,3,4',
            'address' => 'nullable|string',
            'village' => 'nullable|string|max:255',
            'pregnant' => 'nullable|boolean',
            'disability' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'facility_id' => 'sometimes|exists:facilities,id',
            'lga_id' => 'sometimes|exists:lgas,id',
            'ward_id' => 'sometimes|exists:wards,id',
            'insurance_programme_id' => 'nullable|exists:insurance_programmes,id',
            'enrollee_category_id' => 'nullable|exists:enrollee_categories,id',
            'premium_plan_id' => 'nullable|exists:premium_plans,id',
            'premium_pin_id' => 'nullable|exists:premium_pins,id',
            'benefit_package_id' => 'nullable|exists:benefit_packages,id',
            'funding_type_id' => 'nullable|exists:funding_types,id',
            'benefactor_id' => 'nullable|exists:benefactors,id',
            'vulnerable_group_id' => 'nullable|exists:vulnerable_groups,id',
            'principal_enrollee_id' => 'nullable|exists:enrollees,id',
            'relationship_to_principal' => 'nullable|integer|in:1,2,3,4',
            'coverage_start_date' => 'nullable|date',
            'coverage_end_date' => 'nullable|date|after_or_equal:coverage_start_date',
        ]);

        $validator->after(function ($validator) use ($input, $enrollee) {
            $this->validateDependantPlanRules($validator, $input + $enrollee->toArray(), $enrollee->id);
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldValues = $enrollee->toArray();
        $enrollee->update($validator->validated());

        // Create audit trail
        AuditTrail::create([
            'enrollee_id' => $enrollee->id,
            'action' => 'updated',
            'description' => 'Enrollee updated',
            'user_id' => auth()->id(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($enrollee->fresh()->toArray()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrollee updated successfully',
            'data' => $enrollee->fresh()->load(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage', 'facility', 'lga', 'ward']),
        ]);
    }

    public function approve(Request $request, Enrollee $enrollee): JsonResponse
    {
        if ((int) $enrollee->status !== Enrollee::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending enrollees can be approved',
            ], 400);
        }

        $validated = $request->validate([
            'coverage_start_date' => ['nullable', 'date'],
            'coverage_end_date' => ['nullable', 'date', 'after_or_equal:coverage_start_date'],
        ]);

        $plan = $enrollee->premiumPlan;
        if ($plan?->requiresPayment() && !$this->hasSatisfiedRequiredPayment($enrollee)) {
            return response()->json([
                'success' => false,
                'message' => 'This premium plan requires payment. Approve only after a paid invoice or used Premium PIN is linked to this enrollee.',
            ], 422);
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

        // Create audit trail
        AuditTrail::create([
            'enrollee_id' => $enrollee->id,
            'action' => 'approved',
            'description' => 'Enrollee approved',
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Enrollee approved successfully',
            'data' => $enrollee->fresh(['insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage']),
        ]);
    }

    public function auditTrail(Enrollee $enrollee): JsonResponse
    {
        $auditTrails = $enrollee->auditTrails()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $auditTrails,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $filename = 'enrollees_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new EnrolleesExport($request), $filename);
    }

    public function exportPdf(Enrollee $enrollee)
    {
        $enrollee->load([
            'enrolleeType', 'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
            'facility', 'lga', 'ward', 'employmentDetail', 'fundingType', 'benefactor',
            'createdBy', 'approvedBy'
        ]);

        $pdf = Pdf::loadView('enrollee-profile', compact('enrollee'));
        $filename = 'enrollee_' . $enrollee->enrollee_id . '_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Facility Transfer (T12)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/enrollees/{enrollee}/transfer
     */
    public function transfer(Request $request, Enrollee $enrollee): JsonResponse
    {
        $validated = $request->validate([
            'target_facility_id' => 'required|integer|exists:facilities,id',
            'transfer_reason'    => 'required|string|max:1000',
            'effective_date'     => 'required|date',
        ]);

        if ($enrollee->facility_id === (int) $validated['target_facility_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Target facility is the same as the current facility.',
            ], 422);
        }

        // Block transfer while enrollee has an active admission
        $hasActiveAdmission = Admission::where('enrollee_id', $enrollee->id)
            ->where('status', 'active')
            ->exists();

        if ($hasActiveAdmission) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot transfer an enrollee with an active admission.',
            ], 422);
        }

        $targetFacility = Facility::find($validated['target_facility_id']);
        if (!$targetFacility || $targetFacility->accreditation_status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Target facility is not accredited.',
            ], 422);
        }

        $transfer = EnrolleeFacilityTransfer::create([
            'enrollee_id'      => $enrollee->id,
            'from_facility_id' => $enrollee->facility_id,
            'to_facility_id'   => $validated['target_facility_id'],
            'transfer_reason'  => $validated['transfer_reason'],
            'effective_date'   => $validated['effective_date'],
            'status'           => 'pending',
            'transferred_by'   => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transfer request submitted. Awaiting approval.',
            'data'    => $transfer->load(['fromFacility', 'toFacility', 'transferredBy']),
        ], 201);
    }

    /**
     * POST /api/enrollees/transfers/{transfer}/approve
     * BR-06: The officer who requested the transfer cannot approve it.
     */
    public function approveTransfer(Request $request, EnrolleeFacilityTransfer $transfer): JsonResponse
    {
        if ($transfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending transfers can be approved.',
            ], 422);
        }

        // BR-06: four-eyes principle
        if ($transfer->transferred_by === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'BR-06 violation: The officer who requested this transfer cannot approve it.',
            ], 403);
        }

        DB::transaction(function () use ($transfer) {
            $enrollee = $transfer->enrollee;

            $transfer->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $oldFacility = $enrollee->facility_id;
            $enrollee->update(['facility_id' => $transfer->to_facility_id]);

            AuditTrail::create([
                'auditable_type' => Enrollee::class,
                'auditable_id'   => $enrollee->id,
                'action'         => 'facility_transfer_approved',
                'description'    => "Enrollee {$enrollee->enrollee_id} transferred from facility {$oldFacility} to {$transfer->to_facility_id}",
                'user_id'        => auth()->id(),
                'old_values'     => ['facility_id' => $oldFacility],
                'new_values'     => ['facility_id' => $transfer->to_facility_id],
                'ip_address'     => FacadeRequest::ip(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Transfer approved and enrollee facility updated.',
            'data'    => $transfer->fresh()->load(['enrollee', 'fromFacility', 'toFacility']),
        ]);
    }

    /**
     * GET /api/enrollees/{enrollee}/transfers
     */
    public function getTransferHistory(Enrollee $enrollee): JsonResponse
    {
        $transfers = $enrollee->facilityTransfers()
            ->with(['fromFacility', 'toFacility', 'transferredBy', 'approvedBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $transfers,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Duplicate Detection (T5)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/enrollees/duplicates
     * Returns unresolved duplicate flags.
     */
    public function listDuplicates(Request $request): JsonResponse
    {
        $flags = EnrolleeDuplicateFlag::with([
                'enrollee:id,enrollee_id,first_name,last_name,facility_id',
                'matchedEnrollee:id,enrollee_id,first_name,last_name,facility_id',
            ])
            ->where('resolved', false)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $flags,
        ]);
    }

    /**
     * POST /api/enrollees/duplicates/{flag}/resolve
     * Body: { "action": "keep_both" | "soft_delete_newer" | "merge" }
     */
    public function resolveDuplicate(Request $request, EnrolleeDuplicateFlag $flag): JsonResponse
    {
        if ($flag->resolved) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate flag is already resolved.',
            ], 422);
        }

        $validated = $request->validate([
            'action' => 'required|in:keep_both,soft_delete_newer,merge',
            'notes'  => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($flag, $validated) {
            if ($validated['action'] === 'soft_delete_newer') {
                // Soft-delete the newer enrollee record
                $newer = $flag->enrollee->created_at > $flag->matchedEnrollee->created_at
                    ? $flag->enrollee
                    : $flag->matchedEnrollee;

                $newer->update(['is_possible_duplicate' => true, 'duplicate_reviewed' => true]);
                $newer->delete();
            }

            $resolution = $validated['action'];
            if (!empty($validated['notes'])) {
                $resolution .= ': ' . $validated['notes'];
            }

            $flag->update([
                'resolved'    => true,
                'resolution'  => $resolution,
                'resolved_by' => auth()->id(),
                'resolved_at' => now(),
            ]);

            AuditTrail::create([
                'auditable_type' => EnrolleeDuplicateFlag::class,
                'auditable_id'   => $flag->id,
                'action'         => 'duplicate_resolved',
                'description'    => "Duplicate flag #{$flag->id} resolved with action: {$validated['action']}",
                'user_id'        => auth()->id(),
                'old_values'     => ['resolved' => false],
                'new_values'     => ['resolved' => true, 'resolution' => $resolution],
                'ip_address'     => FacadeRequest::ip(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Duplicate flag resolved successfully.',
            'data'    => $flag->fresh(),
        ]);
    }

    private function generateEnrolleeId(): string
    {
        $prefix = 'NGSCHA';
        $lastEnrollee = Enrollee::where('enrollee_id', 'like', $prefix . '%')
            ->orderBy('enrollee_id', 'desc')
            ->first();

        if ($lastEnrollee) {
            $lastNumber = (int) substr($lastEnrollee->enrollee_id, strlen($prefix));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 9, '0', STR_PAD_LEFT);
    }

    private function normalizeIncomingEnrolleeData(array $data): array
    {
        if (!isset($data['sex']) && isset($data['gender'])) {
            $data['sex'] = match (strtolower((string) $data['gender'])) {
                'male', 'm' => 1,
                'female', 'f' => 2,
                default => null,
            };
            unset($data['gender']);
        }

        if (($data['relationship_to_principal'] ?? 1) == 1) {
            $data['principal_enrollee_id'] = null;
        }

        return $data;
    }

    private function validateDependantPlanRules($validator, array $data, ?int $currentEnrolleeId = null): void
    {
        $relationship = (int) ($data['relationship_to_principal'] ?? 1);
        if ($relationship === 1) {
            return;
        }

        $planId = $data['premium_plan_id'] ?? null;
        if (!$planId) {
            return;
        }

        $plan = PremiumPlan::find($planId);
        if (!$plan) {
            return;
        }

        if (!$plan->isFamilyPlan()) {
            $validator->errors()->add('relationship_to_principal', 'Dependants are not allowed for the selected premium plan.');
            return;
        }

        if (empty($data['principal_enrollee_id'])) {
            $validator->errors()->add('principal_enrollee_id', 'Principal enrollee is required for dependant enrollment.');
            return;
        }

        $dependantCount = Enrollee::where('principal_enrollee_id', $data['principal_enrollee_id'])
            ->when($currentEnrolleeId, fn ($query) => $query->where('id', '!=', $currentEnrolleeId))
            ->count();

        if ($dependantCount >= $plan->getEffectiveMaximumDependants()) {
            $validator->errors()->add('principal_enrollee_id', 'The selected principal has reached the dependant limit for this plan.');
        }
    }

    private function hasSatisfiedRequiredPayment(Enrollee $enrollee): bool
    {
        $hasPaidInvoice = $enrollee->invoices()
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidInvoice) {
            return true;
        }

        return PremiumPin::where('used_by_enrollee_id', $enrollee->id)
            ->where('status', PremiumPin::STATUS_USED)
            ->exists();
    }
}
