<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\BenefitPackage;
use App\Models\Benefactor;
use App\Models\EnrolleeCategory;
use App\Models\Facility;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use App\Models\Lga;
use App\Models\MobileEnrollmentRecord;
use App\Models\PremiumPlan;
use App\Models\Ward;
use App\Services\EnrollmentFormSchemaService;
use App\Services\MobileEnrollmentService;
use App\Services\NinVerificationService;
use App\Services\OfficerDeviceService;
use App\Services\PremiumCoverageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use RuntimeException;

class MobileV1Controller extends BaseController
{
    public function __construct(
        private OfficerDeviceService $deviceService,
        private EnrollmentFormSchemaService $schemaService,
        private MobileEnrollmentService $mobileEnrollmentService,
        private PremiumCoverageService $premiumCoverageService,
        private NinVerificationService $ninVerificationService
    ) {
    }

    public function registerDevice(Request $request)
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            $device = $this->deviceService->register($request->user(), $validated);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 403);
        }

        return $this->sendResponse($device, 'Device registered successfully.');
    }

    public function bootstrap(Request $request)
    {
        $device = $this->activeDevice($request);
        $scope = $this->officerEnrollmentScope($request);

        return $this->sendResponse([
            'user' => $request->user()->load(['roles:id,name,label', 'roles.permissions:id,name,label,category']),
            'device' => $device,
            'officer_enrollment' => $scope,
            'local_statuses' => MobileEnrollmentRecord::LOCAL_STATUSES,
            'backend_statuses' => [
                MobileEnrollmentRecord::STATUS_RECEIVED,
                MobileEnrollmentRecord::STATUS_PENDING_NIN,
                MobileEnrollmentRecord::STATUS_NIN_FAILED,
                MobileEnrollmentRecord::STATUS_DUPLICATE_SUSPECTED,
                MobileEnrollmentRecord::STATUS_PENDING_APPROVAL,
                MobileEnrollmentRecord::STATUS_REQUIRES_REVIEW,
                MobileEnrollmentRecord::STATUS_APPROVED,
                MobileEnrollmentRecord::STATUS_REJECTED,
                MobileEnrollmentRecord::STATUS_SYNC_FAILED,
            ],
            'security' => [
                'auto_lock_seconds' => 300,
                'encrypt_local_data' => true,
                'pin_purchase_requires_online' => true,
                'final_pin_consumption_on_backend' => true,
            ],
        ], 'Mobile bootstrap retrieved successfully.');
    }

    public function metadata(Request $request)
    {
        $this->activeDevice($request);
        $scope = $this->officerEnrollmentScope($request);
        $since = $request->query('since');
        $schemas = $this->schemaService->publishedForMobile(is_string($since) ? $since : null)
            ->when($scope['schema_ids'] !== null, fn ($items) => $items->whereIn('id', $scope['schema_ids'])->values());

        $schemaProgrammeIds = $schemas->pluck('insurance_programme_id')->filter()->unique()->values()->all();
        $schemaPlanIds = $schemas->pluck('premium_plan_id')->filter()->unique()->values()->all();

        return $this->sendResponse([
            'server_time' => now()->toIso8601String(),
            'sync_mode' => $since ? 'incremental' : 'full',
            'officer_enrollment' => $scope,
            'insurance_programmes' => $this->changed(InsuranceProgramme::query()->orderBy('name'), $since)
                ->when($schemaProgrammeIds !== [], fn (Builder $query) => $query->whereIn('id', $schemaProgrammeIds))
                ->get(),
            'enrollee_categories' => $this->changed(EnrolleeCategory::query()->orderBy('name'), $since)->get(),
            'premium_plans' => $this->changed(PremiumPlan::with(['programme', 'benefitPackage'])->where('status', 'active')->orderBy('name'), $since)
                ->when($schemaPlanIds !== [], fn (Builder $query) => $query->whereIn('id', $schemaPlanIds))
                ->get(),
            'benefit_packages' => $this->changed(BenefitPackage::query()->orderBy('name'), $since)->get(),
            'funding_types' => $this->changed(FundingType::query()->orderBy('name'), $since)->get(),
            'benefactors' => $this->changed(Benefactor::query()->orderBy('name'), $since)->get(),
            'lgas' => $this->changed(Lga::query()->orderBy('name'), $since)
                ->when($scope['lga_ids'] !== null, fn (Builder $query) => $query->whereIn('id', $scope['lga_ids']))
                ->get(),
            'wards' => $this->changed(Ward::query()->orderBy('name'), $since)
                ->when($scope['lga_ids'] !== null, fn (Builder $query) => $query->whereIn('lga_id', $scope['lga_ids']))
                ->get(),
            'facilities' => $this->changed(Facility::query()->where('status', 1)->orderBy('name'), $since)
                ->when($scope['lga_ids'] !== null, fn (Builder $query) => $query->whereIn('lga_id', $scope['lga_ids']))
                ->get(),
            'schemas' => $schemas,
            'default_schema' => $this->schemaService->makeVirtualDefaultSchema(),
        ], 'Mobile metadata retrieved successfully.');
    }

    public function createPinPurchase(Request $request)
    {
        $device = $this->activeDevice($request);
        $validated = $request->validate([
            'premium_plan_id' => ['required', 'exists:premium_plans,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:255'],
            'payer_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $result = $this->mobileEnrollmentService->initializePinPurchase($request->user(), $device, $validated);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse($result, 'Mobile PIN purchase initialized.', 201);
    }

    public function verifyPinPurchase(Request $request, string $reference)
    {
        $device = $this->activeDevice($request);

        try {
            $result = $this->mobileEnrollmentService->verifyPinPurchase($request->user(), $device, $reference);
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse($result, 'Mobile PIN purchase verified.');
    }

    public function validatePin(Request $request)
    {
        $this->activeDevice($request);
        $validated = $request->validate([
            'pin' => ['required', 'string'],
            'premium_plan_id' => ['nullable', 'exists:premium_plans,id'],
        ]);

        try {
            $pin = $this->premiumCoverageService->validatePin($validated['pin']);
            if (!empty($validated['premium_plan_id']) && (int) $pin->premium_plan_id !== (int) $validated['premium_plan_id']) {
                return $this->sendError('Premium PIN does not belong to the selected premium plan.', [], 422);
            }
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse($pin->load(['plan.programme', 'plan.benefitPackage']), 'Premium PIN is valid.');
    }

    public function verifyNin(Request $request)
    {
        $this->activeDevice($request);
        $validated = $request->validate([
            'nin' => ['required', 'string', 'size:11'],
            'consent' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->ninVerificationService->verifyRaw(
                $validated['nin'],
                $request->user(),
                (bool) ($validated['consent'] ?? true)
            );
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 422);
        }

        return $this->sendResponse($result, 'NIN verified successfully.');
    }

    public function syncEnrollments(Request $request)
    {
        $device = $this->activeDevice($request);
        $validated = $request->validate([
            'records' => ['required', 'array', 'max:100'],
            'records.*.client_record_id' => ['required', 'string', 'max:120'],
            'records.*.schema_id' => ['nullable', 'integer', 'exists:enrollment_form_schemas,id'],
            'records.*.schema_version' => ['nullable', 'integer', 'min:1'],
            'records.*.captured_at' => ['nullable', 'date'],
            'records.*.captured_offline' => ['nullable', 'boolean'],
            'records.*.data' => ['required', 'array'],
            'records.*.extra_fields' => ['nullable', 'array'],
            'records.*.nin_verified_values' => ['nullable', 'array'],
            'records.*.verified_field_edit_reasons' => ['nullable', 'array'],
            'app_version' => ['nullable', 'string', 'max:40'],
            'gps' => ['nullable', 'array'],
        ]);

        $result = $this->mobileEnrollmentService->syncBatch(
            $request->user(),
            $device,
            $validated['records'],
            [
                'app_version' => $validated['app_version'] ?? null,
                'gps' => $validated['gps'] ?? null,
                'ip_address' => $request->ip(),
            ]
        );

        return $this->sendResponse($result, 'Mobile enrollment sync processed.', 202);
    }

    public function syncStatus(Request $request, string $batch)
    {
        $device = $this->activeDevice($request);
        $records = MobileEnrollmentRecord::with(['enrollee:id,enrollee_id,first_name,last_name,status', 'duplicateOf:id,enrollee_id,first_name,last_name'])
            ->where('officer_device_id', $device->id)
            ->where('sync_batch_id', $batch)
            ->get();

        $records->each(function (MobileEnrollmentRecord $record) {
            if ($record->enrollee && (int) $record->enrollee->status === \App\Models\Enrollee::STATUS_ACTIVE && $record->status !== MobileEnrollmentRecord::STATUS_APPROVED) {
                $record->forceFill([
                    'status' => MobileEnrollmentRecord::STATUS_APPROVED,
                    'status_reason' => 'Enrollee approved on web. Coverage is active.',
                    'synced_at' => now(),
                ])->save();
            }
        });

        $records = $records->fresh(['enrollee:id,enrollee_id,first_name,last_name,status', 'duplicateOf:id,enrollee_id,first_name,last_name']);

        return $this->sendResponse([
            'sync_batch_id' => $batch,
            'total' => $records->count(),
            'counts' => $records->groupBy('status')->map->count(),
            'records' => $records,
        ], 'Mobile sync status retrieved.');
    }

    public function failed(Request $request)
    {
        $device = $this->activeDevice($request);
        $records = MobileEnrollmentRecord::where('officer_device_id', $device->id)
            ->whereIn('status', [
                MobileEnrollmentRecord::STATUS_SYNC_FAILED,
                MobileEnrollmentRecord::STATUS_NIN_FAILED,
                MobileEnrollmentRecord::STATUS_DUPLICATE_SUSPECTED,
                MobileEnrollmentRecord::STATUS_REQUIRES_REVIEW,
            ])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse($records, 'Failed mobile enrollment records retrieved.');
    }

    public function uploadAttachment(Request $request, MobileEnrollmentRecord $record)
    {
        $this->activeDevice($request);
        $validated = $request->validate([
            'kind' => ['nullable', 'string', 'max:40'],
            'file' => ['required', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ]);

        try {
            $attachment = $this->mobileEnrollmentService->uploadAttachment(
                $record,
                $request->user(),
                $validated['file'],
                $validated['kind'] ?? 'passport'
            );
        } catch (RuntimeException $exception) {
            return $this->sendError($exception->getMessage(), [], 403);
        }

        return $this->sendResponse($attachment, 'Attachment uploaded successfully.', 201);
    }

    private function activeDevice(Request $request)
    {
        $deviceUuid = $request->header('X-Device-UUID') ?: $request->input('device_uuid');
        if (!$deviceUuid) {
            abort(422, 'X-Device-UUID header is required for mobile API requests.');
        }

        try {
            return $this->deviceService->activeDeviceFor($request->user(), (string) $deviceUuid);
        } catch (RuntimeException $exception) {
            abort(403, $exception->getMessage());
        }
    }

    private function changed(Builder $query, mixed $since): Builder
    {
        return is_string($since) && $since !== ''
            ? $query->where('updated_at', '>', $since)
            : $query;
    }

    private function officerEnrollmentScope(Request $request): array
    {
        $user = $request->user();
        if (!$user->mobileEnrollmentEnabled()) {
            abort(403, 'Mobile enrollment has been disabled for this officer.');
        }

        $assignments = $user->activeOfficerEnrollmentAssignments()
            ->with(['lga:id,name,code', 'schema:id,name,version,status'])
            ->get();

        if ($assignments->isEmpty()) {
            if ($user->hasRole('mobile-enrollment-officer')) {
                abort(403, 'This officer has no active enrollment assignment.');
            }

            return [
                'enabled' => true,
                'restricted' => false,
                'lga_ids' => null,
                'schema_ids' => null,
                'assignments' => [],
            ];
        }

        $lgaIds = $assignments->contains(fn ($assignment) => $assignment->lga_id === null)
            ? null
            : $assignments->pluck('lga_id')->filter()->unique()->values()->all();
        $schemaIds = $assignments->contains(fn ($assignment) => $assignment->enrollment_form_schema_id === null)
            ? null
            : $assignments->pluck('enrollment_form_schema_id')->filter()->unique()->values()->all();

        return [
            'enabled' => true,
            'restricted' => true,
            'lga_ids' => $lgaIds,
            'schema_ids' => $schemaIds,
            'assignments' => $assignments,
        ];
    }
}
