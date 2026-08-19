<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\AuditTrail;
use App\Models\MobileEnrollmentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileEnrollmentMonitorController extends BaseController
{
    private const READY_STATUSES = [
        MobileEnrollmentRecord::STATUS_PENDING_APPROVAL,
        MobileEnrollmentRecord::STATUS_APPROVED,
    ];

    private const ATTENTION_STATUSES = [
        MobileEnrollmentRecord::STATUS_PENDING_NIN,
        MobileEnrollmentRecord::STATUS_NIN_FAILED,
        MobileEnrollmentRecord::STATUS_DUPLICATE_SUSPECTED,
        MobileEnrollmentRecord::STATUS_REQUIRES_REVIEW,
        MobileEnrollmentRecord::STATUS_REJECTED,
        MobileEnrollmentRecord::STATUS_SYNC_FAILED,
    ];

    public function index(Request $request)
    {
        $baseQuery = $this->recordsQuery($request);
        $summaryQuery = $this->recordsQuery($request, false);

        $records = (clone $baseQuery)
            ->latest('received_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return $this->sendResponse([
            'records' => $records,
            'summary' => $this->summary($summaryQuery),
            'officer_options' => $this->officerOptions($request),
            'status_options' => [
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
        ], 'Mobile enrollment sync monitor retrieved.');
    }

    public function show(Request $request, MobileEnrollmentRecord $record)
    {
        $this->authorizeRecord($request->user(), $record);

        $record->load($this->detailRelations())->loadCount('attachments');

        $auditTrail = AuditTrail::query()
            ->with('user:id,name,email')
            ->where('auditable_type', MobileEnrollmentRecord::class)
            ->where('auditable_id', $record->id)
            ->orderBy('created_at')
            ->get();

        return $this->sendResponse([
            'record' => $record,
            'audit_trail' => $auditTrail,
        ], 'Mobile enrollment sync record retrieved.');
    }

    private function recordsQuery(Request $request, bool $decorate = true, array $ignoredFilters = []): Builder
    {
        $query = MobileEnrollmentRecord::query();

        if ($decorate) {
            $query->with($this->listRelations())
                ->withCount('attachments');
        }

        if (!$this->canViewAll($request->user())) {
            $query->where('officer_user_id', $request->user()->id);
        }

        return $this->applyFilters($query, $request, $ignoredFilters);
    }

    private function applyFilters(Builder $query, Request $request, array $ignoredFilters = []): Builder
    {
        $search = trim((string) $request->string('search', ''));
        $status = trim((string) $request->string('status', ''));
        $officerUserId = (int) $request->integer('officer_user_id');
        $batchId = trim((string) $request->string('batch_id', ''));
        $deviceUuid = trim((string) $request->string('device_uuid', ''));
        $dateFrom = trim((string) $request->string('date_from', ''));
        $dateTo = trim((string) $request->string('date_to', ''));
        $capturedDateFrom = trim((string) $request->string('captured_date_from', ''));
        $capturedDateTo = trim((string) $request->string('captured_date_to', ''));
        $capturedTimeFrom = trim((string) $request->string('captured_time_from', ''));
        $capturedTimeTo = trim((string) $request->string('captured_time_to', ''));

        if (!in_array('status', $ignoredFilters, true) && $status !== '') {
            $query->where('status', $status);
        }

        if (!in_array('officer_user_id', $ignoredFilters, true) && $officerUserId > 0) {
            $query->where('officer_user_id', $officerUserId);
        }

        if (!in_array('batch_id', $ignoredFilters, true) && $batchId !== '') {
            $query->where('sync_batch_id', 'like', '%' . $batchId . '%');
        }

        if (!in_array('device_uuid', $ignoredFilters, true) && $deviceUuid !== '') {
            $query->whereHas('device', function (Builder $deviceQuery) use ($deviceUuid): void {
                $deviceQuery
                    ->where('device_uuid', 'like', '%' . $deviceUuid . '%')
                    ->orWhere('device_name', 'like', '%' . $deviceUuid . '%');
            });
        }

        if (!in_array('date_from', $ignoredFilters, true) && $dateFrom !== '') {
            $query->whereDate('received_at', '>=', $dateFrom);
        }

        if (!in_array('date_to', $ignoredFilters, true) && $dateTo !== '') {
            $query->whereDate('received_at', '<=', $dateTo);
        }

        if (!in_array('captured_date_from', $ignoredFilters, true) && $capturedDateFrom !== '') {
            $query->whereDate('captured_at', '>=', $capturedDateFrom);
        }

        if (!in_array('captured_date_to', $ignoredFilters, true) && $capturedDateTo !== '') {
            $query->whereDate('captured_at', '<=', $capturedDateTo);
        }

        if (
            !in_array('captured_time_from', $ignoredFilters, true)
            && $capturedTimeFrom !== ''
            && $this->isValidTimeValue($capturedTimeFrom)
        ) {
            $query->whereTime('captured_at', '>=', $capturedTimeFrom);
        }

        if (
            !in_array('captured_time_to', $ignoredFilters, true)
            && $capturedTimeTo !== ''
            && $this->isValidTimeValue($capturedTimeTo)
        ) {
            $query->whereTime('captured_at', '<=', $capturedTimeTo);
        }

        if (!in_array('search', $ignoredFilters, true) && $search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('client_record_id', 'like', '%' . $search . '%')
                    ->orWhere('sync_batch_id', 'like', '%' . $search . '%')
                    ->orWhere('status_reason', 'like', '%' . $search . '%')
                    ->orWhereHas('officer', function (Builder $officerQuery) use ($search): void {
                        $officerQuery
                            ->where('name', 'like', '%' . $search . '%')
                            ->orWhere('username', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('device', function (Builder $deviceQuery) use ($search): void {
                        $deviceQuery
                            ->where('device_uuid', 'like', '%' . $search . '%')
                            ->orWhere('device_name', 'like', '%' . $search . '%')
                            ->orWhere('platform', 'like', '%' . $search . '%')
                            ->orWhere('app_version', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('enrollee', function (Builder $enrolleeQuery) use ($search): void {
                        $enrolleeQuery
                            ->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('enrollee_id', 'like', '%' . $search . '%')
                            ->orWhere('nin', 'like', '%' . $search . '%');
                    });
            });
        }

        return $query;
    }

    private function summary(Builder $query): array
    {
        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status');

        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('received_at', now()->toDateString())->count(),
            'ready' => (clone $query)->whereIn('status', self::READY_STATUSES)->count(),
            'attention' => (clone $query)->whereIn('status', self::ATTENTION_STATUSES)->count(),
            'batches' => (clone $query)->distinct()->count('sync_batch_id'),
            'officers' => (clone $query)->distinct()->count('officer_user_id'),
            'devices' => (clone $query)->distinct()->count('officer_device_id'),
            'status_counts' => $statusCounts->all(),
        ];
    }

    private function officerOptions(Request $request): array
    {
        $officerIds = $this->recordsQuery($request, false, ['officer_user_id'])
            ->whereNotNull('officer_user_id')
            ->distinct()
            ->pluck('officer_user_id')
            ->filter()
            ->values();

        if ($officerIds->isEmpty()) {
            return [];
        }

        return User::query()
            ->whereIn('id', $officerIds->all())
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email'])
            ->map(fn (User $officer) => [
                'id' => $officer->id,
                'name' => $officer->name,
                'subtitle' => $officer->email ?: $officer->username,
            ])
            ->values()
            ->all();
    }

    private function isValidTimeValue(string $value): bool
    {
        return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) === 1;
    }

    private function authorizeRecord(User $user, MobileEnrollmentRecord $record): void
    {
        if ($this->canViewAll($user) || (int) $record->officer_user_id === (int) $user->id) {
            return;
        }

        abort(Response::HTTP_FORBIDDEN, 'You are not allowed to view this mobile enrollment sync record.');
    }

    private function canViewAll(User $user): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'super-admin', 'admin'])) {
            return true;
        }

        foreach (['users.view', 'settings.edit', 'settings.mobile-device.manage', 'enrollees.view', 'enrollee.approve'] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    private function listRelations(): array
    {
        return [
            'officer:id,name,username,email',
            'device:id,user_id,device_uuid,device_name,platform,app_version,last_seen_at,revoked_at',
            'schema:id,name,version,status,insurance_programme_id,premium_plan_id',
            'schema.programme:id,name,code',
            'schema.plan:id,name,code',
            'enrollee:id,enrollee_id,first_name,last_name,nin,status',
            'duplicateOf:id,enrollee_id,first_name,last_name,nin,status',
        ];
    }

    private function detailRelations(): array
    {
        return [
            ...$this->listRelations(),
            'attachments:id,mobile_enrollment_record_id,kind,original_name,mime_type,size,status,failure_reason,created_at',
        ];
    }
}
