<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnrollmentIntelligenceController extends BaseController
{
    public function ninVerificationReport(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'lga_id' => ['nullable', 'exists:lgas,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'source' => ['nullable', Rule::in(['mobile_officer', 'self_service', 'staff'])],
            'status' => ['nullable', Rule::in([Enrollee::NIN_VERIFICATION_VERIFIED, Enrollee::NIN_VERIFICATION_FAILED])],
            'provider' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(29)->startOfDay();

        $activityBase = $this->verificationActivityQuery($validated, $dateFrom, $dateTo);
        $eligibleBase = $this->eligibleVerificationQuery($validated);

        $verifiedCount = (clone $activityBase)
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_VERIFIED)
            ->count();
        $failedCount = (clone $activityBase)
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_FAILED)
            ->count();
        $totalAttempts = $verifiedCount + $failedCount;
        $pendingBacklog = (clone $eligibleBase)
            ->where(function (Builder $query): void {
                $query->whereNull('nin_verification_status')
                    ->orWhere('nin_verification_status', Enrollee::NIN_VERIFICATION_NOT_STARTED);
            })
            ->count();
        $distinctNins = (clone $activityBase)->distinct('nin')->count('nin');
        $mobileVerifiedCount = (clone $activityBase)
            ->where('enrollment_source', 'mobile_officer')
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_VERIFIED)
            ->count();

        $trendRows = (clone $activityBase)
            ->selectRaw("DATE(nin_verified_at) as verification_date")
            ->selectRaw("SUM(CASE WHEN nin_verification_status = 'verified' THEN 1 ELSE 0 END) as verified_count")
            ->selectRaw("SUM(CASE WHEN nin_verification_status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->groupBy(DB::raw('DATE(nin_verified_at)'))
            ->orderBy('verification_date')
            ->get()
            ->keyBy('verification_date');

        $trendLabels = [];
        $trendVerified = [];
        $trendFailed = [];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            $key = $cursor->toDateString();
            $trendLabels[] = $cursor->format('d M');
            $trendVerified[] = (int) data_get($trendRows, "{$key}.verified_count", 0);
            $trendFailed[] = (int) data_get($trendRows, "{$key}.failed_count", 0);
            $cursor->addDay();
        }

        $sourceBreakdown = (clone $activityBase)
            ->selectRaw("COALESCE(enrollment_source, 'unknown') as source, COUNT(*) as aggregate")
            ->groupBy('source')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->sourceLabel((string) $row->source),
                'value' => (int) $row->aggregate,
                'source' => (string) $row->source,
            ])
            ->values();

        $providerBreakdown = (clone $activityBase)
            ->selectRaw("COALESCE(nin_verification_provider, 'Unknown') as provider_name, COUNT(*) as aggregate")
            ->groupBy('provider_name')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->provider_name,
                'value' => (int) $row->aggregate,
            ])
            ->values();

        $tableQuery = $this->verificationActivityQuery($validated, $dateFrom, $dateTo)
            ->with([
                'facility:id,name,lga_id',
                'lga:id,name',
                'insuranceProgramme:id,name',
                'premiumPlan:id,name',
                'ninVerifiedBy:id,name',
            ])
            ->when(!empty($validated['status']), fn (Builder $query) => $query->where('nin_verification_status', $validated['status']))
            ->when(!empty($validated['search']), function (Builder $query) use ($validated): void {
                $search = trim((string) $validated['search']);
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('enrollee_id', 'like', "%{$search}%")
                        ->orWhere('nin', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('nin_verified_at');

        $rows = $tableQuery
            ->paginate((int) ($validated['per_page'] ?? 25))
            ->through(function (Enrollee $enrollee): array {
                return [
                    'id' => $enrollee->id,
                    'enrollee_id' => $enrollee->enrollee_id,
                    'full_name' => $enrollee->full_name,
                    'nin' => $enrollee->nin,
                    'status' => $enrollee->nin_verification_status,
                    'status_label' => $this->statusLabel((string) $enrollee->nin_verification_status),
                    'provider' => $enrollee->nin_verification_provider ?: 'Unknown',
                    'verified_at' => optional($enrollee->nin_verified_at)?->toIso8601String(),
                    'source' => $enrollee->enrollment_source ?: 'unknown',
                    'source_label' => $this->sourceLabel((string) ($enrollee->enrollment_source ?: 'unknown')),
                    'facility_name' => $enrollee->facility?->name,
                    'lga_name' => $enrollee->lga?->name,
                    'programme_name' => $enrollee->insuranceProgramme?->name,
                    'premium_plan_name' => $enrollee->premiumPlan?->name,
                    'phone' => $enrollee->phone,
                    'verified_by' => $enrollee->ninVerifiedBy?->name,
                    'failure_message' => data_get($enrollee->nin_verification_meta, 'message'),
                ];
            });

        return $this->sendResponse([
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'lga_id' => $validated['lga_id'] ?? null,
                'facility_id' => $validated['facility_id'] ?? null,
                'source' => $validated['source'] ?? null,
                'status' => $validated['status'] ?? null,
                'provider' => $validated['provider'] ?? null,
                'search' => $validated['search'] ?? null,
            ],
            'summary' => [
                'total_attempts' => $totalAttempts,
                'verified' => $verifiedCount,
                'failed' => $failedCount,
                'success_rate' => $totalAttempts > 0 ? round(($verifiedCount / $totalAttempts) * 100, 1) : 0,
                'pending_backlog' => $pendingBacklog,
                'distinct_nins' => $distinctNins,
                'mobile_verified' => $mobileVerifiedCount,
            ],
            'charts' => [
                'trend' => [
                    'labels' => $trendLabels,
                    'verified' => $trendVerified,
                    'failed' => $trendFailed,
                ],
                'status_breakdown' => [
                    ['label' => 'Verified', 'value' => $verifiedCount],
                    ['label' => 'Failed', 'value' => $failedCount],
                ],
                'source_breakdown' => $sourceBreakdown,
                'provider_breakdown' => $providerBreakdown,
            ],
            'table' => [
                'data' => $rows->items(),
                'meta' => [
                    'total' => $rows->total(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                    'from' => $rows->firstItem(),
                    'to' => $rows->lastItem(),
                ],
            ],
            'lookups' => [
                'lgas' => Lga::query()->orderBy('name')->get(['id', 'name']),
                'facilities' => Facility::query()
                    ->when(!empty($validated['lga_id']), fn (Builder $query) => $query->where('lga_id', $validated['lga_id']))
                    ->where('status', 1)
                    ->orderBy('name')
                    ->get(['id', 'name', 'lga_id']),
                'sources' => [
                    ['label' => 'Mobile Officer', 'value' => 'mobile_officer'],
                    ['label' => 'Public Self Enrollment', 'value' => 'self_service'],
                    ['label' => 'Staff Enrollment', 'value' => 'staff'],
                ],
                'statuses' => [
                    ['label' => 'Verified', 'value' => Enrollee::NIN_VERIFICATION_VERIFIED],
                    ['label' => 'Failed', 'value' => Enrollee::NIN_VERIFICATION_FAILED],
                ],
                'providers' => $providerBreakdown,
            ],
        ], 'NIN verification intelligence retrieved successfully.');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function eligibleVerificationQuery(array $filters): Builder
    {
        return Enrollee::query()
            ->whereNotNull('nin')
            ->where('nin', '!=', '')
            ->when(!empty($filters['lga_id']), fn (Builder $query) => $query->where('lga_id', $filters['lga_id']))
            ->when(!empty($filters['facility_id']), fn (Builder $query) => $query->where('facility_id', $filters['facility_id']))
            ->when(!empty($filters['source']), fn (Builder $query) => $query->where('enrollment_source', $filters['source']))
            ->when(!empty($filters['provider']), fn (Builder $query) => $query->where('nin_verification_provider', $filters['provider']));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function verificationActivityQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return $this->eligibleVerificationQuery($filters)
            ->whereIn('nin_verification_status', [
                Enrollee::NIN_VERIFICATION_VERIFIED,
                Enrollee::NIN_VERIFICATION_FAILED,
            ])
            ->whereNotNull('nin_verified_at')
            ->whereBetween('nin_verified_at', [$dateFrom, $dateTo]);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Enrollee::NIN_VERIFICATION_VERIFIED => 'Verified',
            Enrollee::NIN_VERIFICATION_FAILED => 'Failed',
            Enrollee::NIN_VERIFICATION_NOT_STARTED => 'Not Verified',
            Enrollee::NIN_VERIFICATION_NOT_PROVIDED => 'Not Provided',
            default => 'Unknown',
        };
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            'mobile_officer' => 'Mobile Officer',
            'self_service' => 'Public Self Enrollment',
            'staff' => 'Staff Enrollment',
            default => 'Unknown',
        };
    }
}
