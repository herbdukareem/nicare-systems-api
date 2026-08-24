<?php

namespace App\Http\Controllers\Api;

use App\Exports\EnrollmentIntelligenceExport;
use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use App\Models\MobileEnrollmentRecord;
use App\Models\User;
use App\Models\Ward;
use App\Services\NinProviderConfigService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class EnrollmentIntelligenceController extends BaseController
{
    private const MINIMUM_INTELLIGENCE_DATE = '2026-08-03';

    public function __construct(private readonly NinProviderConfigService $ninProviderConfigService)
    {
    }

    public function ninVerificationReport(Request $request)
    {
        [$validated, $dateFrom, $dateTo] = $this->resolveValidatedFilters($request);

        $activityBase = $this->verificationActivityQuery($validated, $dateFrom, $dateTo);
        $eligibleBase = $this->eligibleVerificationQuery($validated);
        $enrollmentBase = $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo);
        $verificationValueAmount = round((float) ($this->ninProviderConfigService->getConfig()['verification_value_amount'] ?? 0), 2);

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
        $capturedCount = (clone $enrollmentBase)->count();
        $pendingApprovalCount = (clone $enrollmentBase)->where('status', Enrollee::STATUS_PENDING)->count();
        $approvedCount = (clone $enrollmentBase)->where('status', Enrollee::STATUS_ACTIVE)->count();
        $rejectedCount = (clone $enrollmentBase)->where('status', Enrollee::STATUS_REJECTED)->count();
        $duplicateCount = (clone $enrollmentBase)
            ->where(function (Builder $query): void {
                $query->where('is_possible_duplicate', true);

                if (Schema::hasColumn('enrollees', 'has_duplicate_nin')) {
                    $query->orWhere('has_duplicate_nin', 1);
                }
            })
            ->count();
        $mobileDuplicateCount = (clone $this->mobileDuplicateRecordsQuery($validated, $dateFrom, $dateTo))->count();
        $duplicateCount += $mobileDuplicateCount;
        $totalValue = (float) round((float) $this->enrollmentValueQuery($validated, $dateFrom, $dateTo)->sum('premium_plans.amount'), 2);
        $totalNinValue = $this->ninValueFromCount($totalAttempts, $verificationValueAmount);
        $summaryValueBreakdown = [
            'captured' => $this->ninValueFromCount($capturedCount, $verificationValueAmount),
            'pending_approval' => $this->ninValueFromCount($pendingApprovalCount, $verificationValueAmount),
            'approved' => $this->ninValueFromCount($approvedCount, $verificationValueAmount),
            'rejected' => $this->ninValueFromCount($rejectedCount, $verificationValueAmount),
            'duplicates' => $this->ninValueFromCount($duplicateCount, $verificationValueAmount),
            'total_attempts' => $this->ninValueFromCount($totalAttempts, $verificationValueAmount),
            'verified' => $this->ninValueFromCount($verifiedCount, $verificationValueAmount),
            'failed' => $this->ninValueFromCount($failedCount, $verificationValueAmount),
        ];

        $trendRows = (clone $activityBase)
            ->selectRaw("DATE(nin_verified_at) as verification_date")
            ->selectRaw("SUM(CASE WHEN nin_verification_status = 'verified' THEN 1 ELSE 0 END) as verified_count")
            ->selectRaw("SUM(CASE WHEN nin_verification_status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->groupBy(DB::raw('DATE(nin_verified_at)'))
            ->orderBy('verification_date')
            ->get()
            ->keyBy('verification_date');

        $enrollmentTrendRows = $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo)
            ->selectRaw($this->captureDateSql() . ' as enrollment_date_key')
            ->selectRaw('COUNT(*) as captured_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count', [Enrollee::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count', [Enrollee::STATUS_REJECTED])
            ->selectRaw($this->duplicateAggregateSql() . ' as duplicate_count')
            ->groupBy('enrollment_date_key')
            ->orderBy('enrollment_date_key')
            ->get()
            ->keyBy('enrollment_date_key');

        $mobileDuplicateRows = $this->mobileDuplicateRecordsQuery($validated, $dateFrom, $dateTo)
            ->selectRaw("DATE(COALESCE(captured_at, received_at)) as duplicate_date_key")
            ->selectRaw('COUNT(*) as duplicate_count')
            ->groupBy('duplicate_date_key')
            ->orderBy('duplicate_date_key')
            ->get()
            ->keyBy('duplicate_date_key');

        $trendLabels = [];
        $trendVerified = [];
        $trendFailed = [];
        $enrollmentTrendCaptured = [];
        $enrollmentTrendPending = [];
        $enrollmentTrendApproved = [];
        $enrollmentTrendRejected = [];
        $dailyOverviewRows = [];
        $dailyOverviewGrandTotal = [
            'captured' => 0,
            'verified' => 0,
            'failed' => 0,
            'duplicates' => 0,
            'nin_value' => 0,
        ];
        $cursor = $dateFrom->copy();
        while ($cursor->lte($dateTo)) {
            $key = $cursor->toDateString();
            $capturedForDay = (int) data_get($enrollmentTrendRows, "{$key}.captured_count", 0);
            $verifiedForDay = (int) data_get($trendRows, "{$key}.verified_count", 0);
            $failedForDay = (int) data_get($trendRows, "{$key}.failed_count", 0);
            $duplicateForDay = (int) data_get($enrollmentTrendRows, "{$key}.duplicate_count", 0)
                + (int) data_get($mobileDuplicateRows, "{$key}.duplicate_count", 0);
            $ninValueForDay = $this->ninValueFromCount($verifiedForDay + $failedForDay, $verificationValueAmount);

            $trendLabels[] = $cursor->format('d M');
            $trendVerified[] = $verifiedForDay;
            $trendFailed[] = $failedForDay;
            $enrollmentTrendCaptured[] = (int) data_get($enrollmentTrendRows, "{$key}.captured_count", 0);
            $enrollmentTrendPending[] = (int) data_get($enrollmentTrendRows, "{$key}.pending_count", 0);
            $enrollmentTrendApproved[] = (int) data_get($enrollmentTrendRows, "{$key}.approved_count", 0);
            $enrollmentTrendRejected[] = (int) data_get($enrollmentTrendRows, "{$key}.rejected_count", 0);

            $dailyOverviewRows[] = [
                'date' => $key,
                'day' => $cursor->format('D, d M Y'),
                'captured' => $capturedForDay,
                'verified' => $verifiedForDay,
                'failed' => $failedForDay,
                'duplicates' => $duplicateForDay,
                'nin_value' => $ninValueForDay,
            ];

            $dailyOverviewGrandTotal['captured'] += $capturedForDay;
            $dailyOverviewGrandTotal['verified'] += $verifiedForDay;
            $dailyOverviewGrandTotal['failed'] += $failedForDay;
            $dailyOverviewGrandTotal['duplicates'] += $duplicateForDay;
            $dailyOverviewGrandTotal['nin_value'] += $ninValueForDay;
            $cursor->addDay();
        }

        $dailyOverviewGrandTotal['nin_value'] = (float) round($dailyOverviewGrandTotal['nin_value'], 2);

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

        $lgaBreakdown = $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo)
            ->leftJoin('lgas', 'lgas.id', '=', 'enrollees.lga_id')
            ->selectRaw("COALESCE(lgas.name, 'Unassigned') as label")
            ->selectRaw('COUNT(enrollees.id) as captured_count')
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as pending_count', [Enrollee::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as rejected_count', [Enrollee::STATUS_REJECTED])
            ->groupBy('label')
            ->orderByDesc('captured_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'captured' => (int) $row->captured_count,
                'pending_approval' => (int) $row->pending_count,
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
            ])
            ->values();

        $wardBreakdown = $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo)
            ->leftJoin('wards', 'wards.id', '=', 'enrollees.ward_id')
            ->leftJoin('lgas', 'lgas.id', '=', 'wards.lga_id')
            ->selectRaw("COALESCE(wards.name, 'Unassigned') as ward_name")
            ->selectRaw("COALESCE(lgas.name, 'No LGA') as lga_name")
            ->selectRaw('COUNT(enrollees.id) as captured_count')
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as pending_count', [Enrollee::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as rejected_count', [Enrollee::STATUS_REJECTED])
            ->groupBy('ward_name', 'lga_name')
            ->orderByDesc('captured_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->ward_name,
                'lga_name' => (string) $row->lga_name,
                'captured' => (int) $row->captured_count,
                'pending_approval' => (int) $row->pending_count,
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
            ])
            ->values();

        $facilityBreakdown = $this->facilitySummaryQuery($validated, $dateFrom, $dateTo)
            ->orderByDesc('captured_count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->facility_name,
                'lga_name' => (string) $row->lga_name,
                'captured' => (int) $row->captured_count,
                'pending_approval' => (int) $row->pending_count,
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
                'value' => (float) round(((int) $row->nin_attempts) * $verificationValueAmount, 2),
            ])
            ->values();

        $facilitySummary = $this->facilitySummaryQuery($validated, $dateFrom, $dateTo)
            ->orderByDesc('captured_count')
            ->paginate((int) ($validated['per_page'] ?? 25), ['*'], 'facility_page')
            ->through(fn ($row) => [
                'facility_name' => (string) $row->facility_name,
                'lga_name' => (string) $row->lga_name,
                'captured' => (int) $row->captured_count,
                'pending_approval' => (int) $row->pending_count,
                'approved' => (int) $row->approved_count,
                'rejected' => (int) $row->rejected_count,
                'duplicates' => (int) $row->duplicate_count,
                'nin_attempts' => (int) $row->nin_attempts,
                'nin_verified' => (int) $row->nin_verified,
                'nin_failed' => (int) $row->nin_failed,
                'value' => (float) round(((int) $row->nin_attempts) * $verificationValueAmount, 2),
            ]);

        $officerSummary = $this->officerSummaryQuery($validated, $dateFrom, $dateTo)
            ->orderByDesc('captured_count')
            ->paginate((int) ($validated['per_page'] ?? 25), ['*'], 'officer_page')
            ->through(function ($row) use ($verificationValueAmount): array {
                return [
                    'officer_name' => (string) $row->officer_name,
                    'source_label' => $this->sourceLabel((string) $row->enrollment_source),
                    'captured' => (int) $row->captured_count,
                    'pending_approval' => (int) $row->pending_count,
                    'approved' => (int) $row->approved_count,
                    'rejected' => (int) $row->rejected_count,
                    'duplicates' => (int) $row->duplicate_count,
                    'nin_attempts' => (int) $row->nin_attempts,
                    'nin_verified' => (int) $row->nin_verified,
                    'nin_failed' => (int) $row->nin_failed,
                    'value' => (float) round(((int) $row->nin_attempts) * $verificationValueAmount, 2),
                ];
            });

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
                'captured' => $capturedCount,
                'pending_approval' => $pendingApprovalCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'duplicates' => $duplicateCount,
                'total_value' => $totalValue,
                'total_nin_value' => $totalNinValue,
                'total_attempts' => $totalAttempts,
                'verified' => $verifiedCount,
                'failed' => $failedCount,
                'success_rate' => $totalAttempts > 0 ? round(($verifiedCount / $totalAttempts) * 100, 1) : 0,
                'pending_backlog' => $pendingBacklog,
                'distinct_nins' => $distinctNins,
                'mobile_verified' => $mobileVerifiedCount,
                'verification_value_amount' => $verificationValueAmount,
                'value_breakdown' => $summaryValueBreakdown,
            ],
            'charts' => [
                'trend' => [
                    'labels' => $trendLabels,
                    'verified' => $trendVerified,
                    'failed' => $trendFailed,
                ],
                'enrollment_trend' => [
                    'labels' => $trendLabels,
                    'captured' => $enrollmentTrendCaptured,
                    'pending_approval' => $enrollmentTrendPending,
                    'approved' => $enrollmentTrendApproved,
                    'rejected' => $enrollmentTrendRejected,
                ],
                'status_breakdown' => [
                    ['label' => 'Pending Approval', 'value' => $pendingApprovalCount],
                    ['label' => 'Approved', 'value' => $approvedCount],
                    ['label' => 'Rejected', 'value' => $rejectedCount],
                ],
                'nin_status_breakdown' => [
                    ['label' => 'Verified', 'value' => $verifiedCount],
                    ['label' => 'Failed', 'value' => $failedCount],
                ],
                'source_breakdown' => $sourceBreakdown,
                'provider_breakdown' => $providerBreakdown,
                'lga_breakdown' => $lgaBreakdown,
                'ward_breakdown' => $wardBreakdown,
                'facility_breakdown' => $facilityBreakdown,
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
            'tables' => [
                'daily_overview' => [
                    'data' => $dailyOverviewRows,
                    'grand_total' => $dailyOverviewGrandTotal,
                    'meta' => [
                        'total' => count($dailyOverviewRows),
                        'per_page' => count($dailyOverviewRows),
                        'current_page' => 1,
                        'last_page' => 1,
                        'from' => count($dailyOverviewRows) > 0 ? 1 : null,
                        'to' => count($dailyOverviewRows) > 0 ? count($dailyOverviewRows) : null,
                    ],
                ],
                'recent_verifications' => [
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
                'facility_summary' => [
                    'data' => $facilitySummary->items(),
                    'meta' => [
                        'total' => $facilitySummary->total(),
                        'per_page' => $facilitySummary->perPage(),
                        'current_page' => $facilitySummary->currentPage(),
                        'last_page' => $facilitySummary->lastPage(),
                        'from' => $facilitySummary->firstItem(),
                        'to' => $facilitySummary->lastItem(),
                    ],
                ],
                'officer_summary' => [
                    'data' => $officerSummary->items(),
                    'meta' => [
                        'total' => $officerSummary->total(),
                        'per_page' => $officerSummary->perPage(),
                        'current_page' => $officerSummary->currentPage(),
                        'last_page' => $officerSummary->lastPage(),
                        'from' => $officerSummary->firstItem(),
                        'to' => $officerSummary->lastItem(),
                    ],
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
            'constraints' => [
                'minimum_date' => self::MINIMUM_INTELLIGENCE_DATE,
                'maximum_date' => now()->toDateString(),
            ],
        ], 'NIN verification intelligence retrieved successfully.');
    }

    public function exportNinVerificationReport(Request $request)
    {
        [$validated, $dateFrom, $dateTo] = $this->resolveValidatedFilters($request);

        $verificationValueAmount = round((float) ($this->ninProviderConfigService->getConfig()['verification_value_amount'] ?? 0), 2);

        $verifiedCount = (clone $this->verificationActivityQuery($validated, $dateFrom, $dateTo))
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_VERIFIED)
            ->count();
        $failedCount = (clone $this->verificationActivityQuery($validated, $dateFrom, $dateTo))
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_FAILED)
            ->count();
        $totalAttempts = $verifiedCount + $failedCount;
        $pendingBacklog = (clone $this->eligibleVerificationQuery($validated))
            ->where(function (Builder $query): void {
                $query->whereNull('nin_verification_status')
                    ->orWhere('nin_verification_status', Enrollee::NIN_VERIFICATION_NOT_STARTED);
            })
            ->count();
        $distinctNins = (clone $this->verificationActivityQuery($validated, $dateFrom, $dateTo))->distinct('nin')->count('nin');
        $mobileVerifiedCount = (clone $this->verificationActivityQuery($validated, $dateFrom, $dateTo))
            ->where('enrollment_source', 'mobile_officer')
            ->where('nin_verification_status', Enrollee::NIN_VERIFICATION_VERIFIED)
            ->count();
        $capturedCount = (clone $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo))->count();
        $pendingApprovalCount = (clone $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo))->where('status', Enrollee::STATUS_PENDING)->count();
        $approvedCount = (clone $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo))->where('status', Enrollee::STATUS_ACTIVE)->count();
        $rejectedCount = (clone $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo))->where('status', Enrollee::STATUS_REJECTED)->count();
        $duplicateCount = (clone $this->enrollmentActivityQuery($validated, $dateFrom, $dateTo))
            ->where(function (Builder $query): void {
                $query->where('is_possible_duplicate', true);

                if (Schema::hasColumn('enrollees', 'has_duplicate_nin')) {
                    $query->orWhere('has_duplicate_nin', 1);
                }
            })
            ->count();
        $duplicateCount += (clone $this->mobileDuplicateRecordsQuery($validated, $dateFrom, $dateTo))->count();
        $totalValue = (float) round((float) $this->enrollmentValueQuery($validated, $dateFrom, $dateTo)->sum('premium_plans.amount'), 2);

        $summaryRows = [
            ['Filters', 'Date From', $dateFrom->toDateString()],
            ['Filters', 'Date To', $dateTo->toDateString()],
            ['Filters', 'LGA', $this->resolveLgaName($validated['lga_id'] ?? null)],
            ['Filters', 'Facility', $this->resolveFacilityName($validated['facility_id'] ?? null)],
            ['Filters', 'Enrollment Source', $validated['source'] ? $this->sourceLabel((string) $validated['source']) : 'All'],
            ['Filters', 'NIN Status', $validated['status'] ? $this->statusLabel((string) $validated['status']) : 'All'],
            ['Filters', 'Provider', $validated['provider'] ?? 'All'],
            ['Filters', 'Search', $validated['search'] ?? 'All'],
            ['Summary', 'Captured', $capturedCount],
            ['Summary', 'Pending Approval', $pendingApprovalCount],
            ['Summary', 'Approved', $approvedCount],
            ['Summary', 'Rejected', $rejectedCount],
            ['Summary', 'Duplicates', $duplicateCount],
            ['Summary', 'Total Verification Attempts', $totalAttempts],
            ['Summary', 'Verified', $verifiedCount],
            ['Summary', 'Failed', $failedCount],
            ['Summary', 'Pending Backlog', $pendingBacklog],
            ['Summary', 'Distinct NINs', $distinctNins],
            ['Summary', 'Mobile Verified', $mobileVerifiedCount],
            ['Summary', 'Enrollment Value', $totalValue],
            ['Summary', 'Verification Value Per Attempt', $verificationValueAmount],
        ];

        $verificationRows = $this->verificationActivityQuery($validated, $dateFrom, $dateTo)
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
            ->orderByDesc('nin_verified_at')
            ->get()
            ->map(function (Enrollee $enrollee): array {
                return [
                    $enrollee->enrollee_id,
                    $enrollee->full_name,
                    $enrollee->nin,
                    $this->statusLabel((string) $enrollee->nin_verification_status),
                    $this->sourceLabel((string) ($enrollee->enrollment_source ?: 'unknown')),
                    $enrollee->nin_verification_provider ?: 'Unknown',
                    $enrollee->facility?->name,
                    $enrollee->lga?->name,
                    $enrollee->insuranceProgramme?->name,
                    $enrollee->premiumPlan?->name,
                    $enrollee->phone,
                    $enrollee->ninVerifiedBy?->name,
                    optional($enrollee->nin_verified_at)?->format('Y-m-d H:i:s'),
                    data_get($enrollee->nin_verification_meta, 'message'),
                ];
            })
            ->values()
            ->all();

        $facilityRows = $this->facilitySummaryQuery($validated, $dateFrom, $dateTo)
            ->orderByDesc('captured_count')
            ->get()
            ->map(function ($row) use ($verificationValueAmount): array {
                return [
                    (string) $row->facility_name,
                    (string) $row->lga_name,
                    (int) $row->captured_count,
                    (int) $row->pending_count,
                    (int) $row->approved_count,
                    (int) $row->rejected_count,
                    (int) $row->duplicate_count,
                    (int) $row->nin_attempts,
                    (int) $row->nin_verified,
                    (int) $row->nin_failed,
                    (float) round(((int) $row->nin_attempts) * $verificationValueAmount, 2),
                ];
            })
            ->values()
            ->all();

        $officerRows = $this->officerSummaryQuery($validated, $dateFrom, $dateTo)
            ->orderByDesc('captured_count')
            ->get()
            ->map(function ($row) use ($verificationValueAmount): array {
                return [
                    (string) $row->officer_name,
                    $this->sourceLabel((string) $row->enrollment_source),
                    (int) $row->captured_count,
                    (int) $row->pending_count,
                    (int) $row->approved_count,
                    (int) $row->rejected_count,
                    (int) $row->duplicate_count,
                    (int) $row->nin_attempts,
                    (int) $row->nin_verified,
                    (int) $row->nin_failed,
                    (float) round(((int) $row->nin_attempts) * $verificationValueAmount, 2),
                ];
            })
            ->values()
            ->all();

        return Excel::download(
            new EnrollmentIntelligenceExport($summaryRows, $verificationRows, $facilityRows, $officerRows),
            'enrollment_intelligence_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
        );
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

    /**
     * @param  array<string, mixed>  $filters
     */
    private function enrollmentActivityQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return Enrollee::query()
            ->when(!empty($filters['lga_id']), fn (Builder $query) => $query->where('lga_id', $filters['lga_id']))
            ->when(!empty($filters['facility_id']), fn (Builder $query) => $query->where('facility_id', $filters['facility_id']))
            ->when(!empty($filters['source']), fn (Builder $query) => $query->where('enrollment_source', $filters['source']))
            ->when(!empty($filters['provider']), fn (Builder $query) => $query->where('nin_verification_provider', $filters['provider']))
            ->where(function (Builder $query) use ($dateFrom, $dateTo): void {
                $query->whereBetween('enrollees.enrollment_date', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $fallback) use ($dateFrom, $dateTo): void {
                        $fallback->whereNull('enrollees.enrollment_date')
                            ->whereBetween('enrollees.created_at', [$dateFrom, $dateTo]);
                    });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function enrollmentValueQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        return $this->enrollmentActivityQuery($filters, $dateFrom, $dateTo)
            ->leftJoin('premium_plans', 'premium_plans.id', '=', 'enrollees.premium_plan_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function mobileDuplicateRecordsQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $query = MobileEnrollmentRecord::query()
            ->where('status', MobileEnrollmentRecord::STATUS_DUPLICATE_SUSPECTED)
            ->where(function (Builder $dateQuery) use ($dateFrom, $dateTo): void {
                $dateQuery->whereBetween('captured_at', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $fallbackQuery) use ($dateFrom, $dateTo): void {
                        $fallbackQuery->whereNull('captured_at')
                            ->whereBetween('received_at', [$dateFrom, $dateTo]);
                    });
            });

        if (!empty($filters['source']) && $filters['source'] !== 'mobile_officer') {
            return $query->whereKey(-1);
        }

        if (!empty($filters['provider'])) {
            return $query->whereKey(-1);
        }

        if (!empty($filters['lga_id'])) {
            $lgaId = (int) $filters['lga_id'];
            $query->where(function (Builder $lgaQuery) use ($lgaId): void {
                $lgaQuery->where('core_data->lga_id', $lgaId)
                    ->orWhere('payload->data->lga_id', $lgaId);
            });
        }

        if (!empty($filters['facility_id'])) {
            $facilityId = (int) $filters['facility_id'];
            $query->where(function (Builder $facilityQuery) use ($facilityId): void {
                $facilityQuery->where('core_data->facility_id', $facilityId)
                    ->orWhere('payload->data->facility_id', $facilityId);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function facilitySummaryQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $duplicateSql = Schema::hasColumn('enrollees', 'has_duplicate_nin')
            ? 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 OR enrollees.has_duplicate_nin = 1 THEN 1 ELSE 0 END)'
            : 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 THEN 1 ELSE 0 END)';

        return $this->enrollmentActivityQuery($filters, $dateFrom, $dateTo)
            ->leftJoin('facilities', 'facilities.id', '=', 'enrollees.facility_id')
            ->leftJoin('lgas', 'lgas.id', '=', 'facilities.lga_id')
            ->selectRaw("COALESCE(facilities.name, 'Unassigned') as facility_name")
            ->selectRaw("COALESCE(lgas.name, 'No LGA') as lga_name")
            ->selectRaw('COUNT(enrollees.id) as captured_count')
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as pending_count', [Enrollee::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as rejected_count', [Enrollee::STATUS_REJECTED])
            ->selectRaw("{$duplicateSql} as duplicate_count")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status IN ('verified', 'failed') THEN 1 ELSE 0 END) as nin_attempts")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status = 'verified' THEN 1 ELSE 0 END) as nin_verified")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status = 'failed' THEN 1 ELSE 0 END) as nin_failed")
            ->groupBy('facility_name', 'lga_name');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function officerSummaryQuery(array $filters, Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $duplicateSql = Schema::hasColumn('enrollees', 'has_duplicate_nin')
            ? 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 OR enrollees.has_duplicate_nin = 1 THEN 1 ELSE 0 END)'
            : 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 THEN 1 ELSE 0 END)';

        return $this->enrollmentActivityQuery($filters, $dateFrom, $dateTo)
            ->leftJoin('users', 'users.id', '=', 'enrollees.created_by')
            ->selectRaw("
                COALESCE(
                    users.name,
                    CASE
                        WHEN enrollees.enrollment_source = 'self_service' THEN 'Self Service / Applicant'
                        WHEN enrollees.enrollment_source = 'mobile_officer' THEN 'Mobile Officer'
                        WHEN enrollees.enrollment_source = 'staff' THEN 'Staff Enrollment'
                        ELSE 'Unassigned Officer'
                    END
                ) as officer_name
            ")
            ->selectRaw("COALESCE(enrollees.enrollment_source, 'unknown') as enrollment_source")
            ->selectRaw('COUNT(enrollees.id) as captured_count')
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as pending_count', [Enrollee::STATUS_PENDING])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as rejected_count', [Enrollee::STATUS_REJECTED])
            ->selectRaw("{$duplicateSql} as duplicate_count")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status IN ('verified', 'failed') THEN 1 ELSE 0 END) as nin_attempts")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status = 'verified' THEN 1 ELSE 0 END) as nin_verified")
            ->selectRaw("SUM(CASE WHEN enrollees.nin_verification_status = 'failed' THEN 1 ELSE 0 END) as nin_failed")
            ->groupBy('officer_name', 'enrollment_source');
    }

    private function captureDateSql(): string
    {
        return "DATE(COALESCE(enrollees.enrollment_date, enrollees.created_at))";
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

    /**
     * @return array{0: array<string, mixed>, 1: Carbon, 2: Carbon}
     */
    private function resolveValidatedFilters(Request $request): array
    {
        $validated = $request->validate($this->filterRules());
        $minimumDate = $this->minimumIntelligenceDate();

        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : now()->endOfDay();
        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(29)->startOfDay();
        $dateFrom = $dateFrom->lt($minimumDate) ? $minimumDate->copy() : $dateFrom;

        return [$validated, $dateFrom, $dateTo];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return [
            'date_from' => ['nullable', 'date', 'after_or_equal:' . self::MINIMUM_INTELLIGENCE_DATE],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'after_or_equal:' . self::MINIMUM_INTELLIGENCE_DATE],
            'lga_id' => ['nullable', 'exists:lgas,id'],
            'facility_id' => ['nullable', 'exists:facilities,id'],
            'source' => ['nullable', Rule::in(['mobile_officer', 'self_service', 'staff'])],
            'status' => ['nullable', Rule::in([Enrollee::NIN_VERIFICATION_VERIFIED, Enrollee::NIN_VERIFICATION_FAILED])],
            'provider' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    private function resolveLgaName(mixed $lgaId): string
    {
        if (blank($lgaId)) {
            return 'All';
        }

        return (string) (Lga::query()->whereKey($lgaId)->value('name') ?: 'Selected');
    }

    private function resolveFacilityName(mixed $facilityId): string
    {
        if (blank($facilityId)) {
            return 'All';
        }

        return (string) (Facility::query()->whereKey($facilityId)->value('name') ?: 'Selected');
    }

    private function duplicateAggregateSql(): string
    {
        return Schema::hasColumn('enrollees', 'has_duplicate_nin')
            ? 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 OR enrollees.has_duplicate_nin = 1 THEN 1 ELSE 0 END)'
            : 'SUM(CASE WHEN enrollees.is_possible_duplicate = 1 THEN 1 ELSE 0 END)';
    }

    private function ninValueFromCount(int $count, float $verificationValueAmount): float
    {
        return (float) round($count * $verificationValueAmount, 2);
    }

    private function minimumIntelligenceDate(): Carbon
    {
        return Carbon::parse(self::MINIMUM_INTELLIGENCE_DATE)->startOfDay();
    }
}
