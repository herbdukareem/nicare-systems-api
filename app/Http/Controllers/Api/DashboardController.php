<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Benefactor;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\Lga;
use App\Models\PremiumPin;
use App\Models\Ward;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/overview
     */
    public function overview(): JsonResponse
    {
        try {
            $today = now()->toDateString();
            $totalEnrollees = Enrollee::count();
            $activeCovered = $this->activeCoveredQuery($today)->count();
            $pendingApproval = $this->statusCount(Enrollee::STATUS_PENDING);
            $suspended = $this->statusCount(Enrollee::STATUS_SUSPENDED);
            $expiredStatus = $this->statusCount(Enrollee::STATUS_EXPIRED);
            $rejected = $this->statusCount(Enrollee::STATUS_REJECTED);
            $inactiveOrExpiredCoverage = $this->expiredCoverageCount($today);
            $noExpiryCoverage = $this->activeCoveredQuery($today)->whereNull('coverage_end_date')->count();
            $expiringSoon = $this->activeCoveredQuery($today)
                ->whereNotNull('coverage_end_date')
                ->whereBetween('coverage_end_date', [$today, now()->addDays(30)->toDateString()])
                ->count();
            $vulnerableCovered = $this->activeCoveredQuery($today)->whereNotNull('vulnerable_group_id')->count();
            $totalFacilities = Facility::count();
            $activeFacilities = $this->facilityStatusCount('active');
            $totalLgas = Lga::count();
            $coveredLgas = Enrollee::whereNotNull('lga_id')->distinct('lga_id')->count('lga_id');

            $coverageRate = $this->percent($activeCovered, $totalEnrollees);
            $approvalRate = $this->percent($totalEnrollees - $pendingApproval, $totalEnrollees);
            $geoReachRate = $this->percent($coveredLgas, $totalLgas);

            $data = [
                'generated_at' => now()->toIso8601String(),
                'reporting_period' => [
                    'label' => now()->format('F Y'),
                    'start' => now()->startOfMonth()->toDateString(),
                    'end' => now()->endOfMonth()->toDateString(),
                ],
                'executive_summary' => [
                    [
                        'label' => 'Total Enrollees',
                        'value' => $totalEnrollees,
                        'helper' => 'All captured lives in the scheme',
                        'icon' => 'mdi-account-group-outline',
                        'tone' => 'primary',
                    ],
                    [
                        'label' => 'Active Coverage',
                        'value' => $activeCovered,
                        'helper' => $coverageRate . '% of enrolled lives currently eligible',
                        'icon' => 'mdi-shield-check-outline',
                        'tone' => 'success',
                    ],
                    [
                        'label' => 'Pending Approval',
                        'value' => $pendingApproval,
                        'helper' => 'Awaiting review before care eligibility',
                        'icon' => 'mdi-account-clock-outline',
                        'tone' => 'warning',
                    ],
                    [
                        'label' => 'Vulnerable Covered',
                        'value' => $vulnerableCovered,
                        'helper' => 'Active lives linked to vulnerable groups',
                        'icon' => 'mdi-hand-heart-outline',
                        'tone' => 'info',
                    ],
                    [
                        'label' => 'Facilities',
                        'value' => $totalFacilities,
                        'helper' => $activeFacilities . ' active/accredited providers',
                        'icon' => 'mdi-hospital-building',
                        'tone' => 'indigo',
                    ],
                    [
                        'label' => 'LGA Reach',
                        'value' => $coveredLgas . ' / ' . $totalLgas,
                        'helper' => $geoReachRate . '% of LGAs have captured enrollees',
                        'icon' => 'mdi-map-marker-radius-outline',
                        'tone' => 'teal',
                    ],
                ],
                'performance' => [
                    'coverage_rate' => $coverageRate,
                    'approval_rate' => $approvalRate,
                    'geographic_reach_rate' => $geoReachRate,
                    'active_facility_rate' => $this->percent($activeFacilities, $totalFacilities),
                    'pending_rate' => $this->percent($pendingApproval, $totalEnrollees),
                    'vulnerable_share' => $this->percent($vulnerableCovered, max($activeCovered, 1)),
                ],
                'coverage' => [
                    'active' => $activeCovered,
                    'no_expiry' => $noExpiryCoverage,
                    'expiring_30_days' => $expiringSoon,
                    'expired_or_inactive' => $inactiveOrExpiredCoverage + $expiredStatus,
                    'suspended' => $suspended,
                    'rejected' => $rejected,
                    'pending' => $pendingApproval,
                ],
                'status_breakdown' => $this->statusBreakdown($totalEnrollees),
                'programme_mix' => $this->dimensionBreakdown(
                    'insurance_programmes',
                    'insurance_programme_id',
                    'programme',
                    $totalEnrollees
                ),
                'category_mix' => $this->dimensionBreakdown(
                    'enrollee_categories',
                    'enrollee_category_id',
                    'category',
                    $totalEnrollees
                ),
                'funding_mix' => $this->dimensionBreakdown(
                    'funding_types',
                    'funding_type_id',
                    'funding_type',
                    $totalEnrollees
                ),
                'benefactor_mix' => $this->dimensionBreakdown(
                    'benefactors',
                    'benefactor_id',
                    'benefactor',
                    $totalEnrollees,
                    'Self / Not Specified'
                ),
                'vulnerable_groups' => $this->dimensionBreakdown(
                    'vulnerable_groups',
                    'vulnerable_group_id',
                    'group',
                    max($vulnerableCovered, 1),
                    'Not Classified',
                    true
                ),
                'geography' => [
                    'lga_reach' => [
                        'covered' => $coveredLgas,
                        'total' => $totalLgas,
                        'percentage' => $geoReachRate,
                    ],
                    'top_lgas' => $this->topLgas($totalEnrollees),
                    'top_wards' => $this->topWards($totalEnrollees),
                ],
                'facilities' => [
                    'summary' => [
                        'total' => $totalFacilities,
                        'active' => $activeFacilities,
                        'suspended' => $this->facilityStatusCount('suspended'),
                        'revoked' => $this->facilityStatusCount('revoked'),
                    ],
                    'ownership' => $this->facilityBreakdown('ownership', 'ownership', $totalFacilities),
                    'type' => $this->facilityBreakdown('type', 'type', $totalFacilities),
                    'top_by_active_lives' => $this->topFacilities(),
                ],
                'pipeline' => [
                    'monthly_enrollment' => $this->monthlyTrend('created_at'),
                    'monthly_approvals' => $this->monthlyTrend('approval_date'),
                    'recent_approvals' => $this->recentApprovals(),
                ],
                'financials' => [
                    'pin_inventory' => $this->pinInventory(),
                    'invoices' => $this->invoiceSummary(),
                ],

                // Compatibility for older dashboard/report callers.
                'totalEnrollees' => $this->legacyCard('Total Enrollees', $totalEnrollees, 'mdi-account-group', 'blue'),
                'activeEnrollees' => $this->legacyCard('Active Enrollees', $activeCovered, 'mdi-account-check', 'green'),
                'notActiveEnrollees' => $this->legacyCard('Enrollees Not Active', max($totalEnrollees - $activeCovered, 0), 'mdi-clock-outline', 'orange'),
                'totalFacilities' => $this->legacyCard('Total Facilities', $totalFacilities, 'mdi-hospital-building', 'purple'),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/enrollee-stats
     */
    public function enrolleeStats(): JsonResponse
    {
        try {
            $total = Enrollee::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'byGender' => $this->genderBreakdown($total),
                    'byType' => $this->dimensionBreakdown('insurance_programmes', 'insurance_programme_id', 'type', $total),
                    'byBenefactor' => $this->dimensionBreakdown('benefactors', 'benefactor_id', 'benefactor', $total, 'Self / Not Specified'),
                    'byFundingType' => $this->dimensionBreakdown('funding_types', 'funding_type_id', 'funding_type', $total),
                    'byWard' => $this->topWards($total, 20),
                    'byFacility' => $this->topFacilities(20),
                    'monthlyTrend' => $this->monthlyTrend('created_at', 6),
                    'ageStats' => $this->ageStats(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollee statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/facility-stats
     */
    public function facilityStats(): JsonResponse
    {
        try {
            $total = Enrollee::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'byLga' => $this->topLgas($total, 25),
                    'byWard' => $this->topWards($total, 25),
                    'byFacility' => $this->topFacilities(25),
                    'topFacilities' => $this->topFacilities(10),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch facility statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/chart-data
     */
    public function chartData(): JsonResponse
    {
        try {
            $total = Enrollee::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'enrollmentTrend' => $this->monthlyTrend('created_at', 12),
                    'genderDistribution' => $this->genderBreakdown($total),
                    'enrolleesByLga' => $this->topLgas($total, 30),
                    'enrolleesByBenefactor' => $this->dimensionBreakdown('benefactors', 'benefactor_id', 'benefactor', $total, 'Self / Not Specified'),
                    'enrolleesByFundingType' => $this->dimensionBreakdown('funding_types', 'funding_type_id', 'funding_type', $total),
                    'enrolleesByWard' => $this->topWards($total, 30),
                    'enrolleesByFacility' => $this->topFacilities(30),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/recent-activities
     */
    public function recentActivities(): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->recentApprovals(10)]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/status-options
     */
    public function getStatusOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => Enrollee::STATUS_PENDING, 'label' => 'Pending'],
                ['value' => Enrollee::STATUS_ACTIVE, 'label' => 'Active / Approved'],
                ['value' => Enrollee::STATUS_REJECTED, 'label' => 'Rejected'],
                ['value' => Enrollee::STATUS_SUSPENDED, 'label' => 'Suspended'],
                ['value' => Enrollee::STATUS_EXPIRED, 'label' => 'Expired / Inactive'],
            ],
        ]);
    }

    /**
     * GET /api/dashboard/enrollment-trend
     * Without ?year=YYYY → yearly aggregates; with year → monthly breakdown.
     */
    public function enrollmentTrend(Request $request): JsonResponse
    {
        try {
            $year = $request->integer('year', 0);

            if ($year > 0) {
                $rows = Enrollee::query()
                    ->selectRaw('MONTH(created_at) as m, COUNT(*) as total')
                    ->whereNotNull('created_at')
                    ->whereYear('created_at', $year)
                    ->groupBy('m')
                    ->orderBy('m')
                    ->get()
                    ->keyBy('m');

                $data = collect(range(1, 12))->map(function (int $month) use ($rows, $year): array {
                    $row = $rows->get($month);

                    return [
                        'year'  => $year,
                        'month' => $month,
                        'label' => Carbon::createFromDate($year, $month, 1)->format('M'),
                        'count' => $row ? (int) $row->total : 0,
                    ];
                })->values()->all();
            } else {
                $rows = Enrollee::query()
                    ->selectRaw('YEAR(created_at) as y, COUNT(*) as total')
                    ->whereNotNull('created_at')
                    ->groupBy('y')
                    ->orderBy('y')
                    ->get();

                $data = $rows->map(fn ($row): array => [
                    'year'  => (int) $row->y,
                    'label' => (string) $row->y,
                    'count' => (int) $row->total,
                ])->values()->all();
            }

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/dashboard/wards-by-lga?lga_id=X
     */
    public function wardsByLga(Request $request): JsonResponse
    {
        try {
            $lgaId = $request->integer('lga_id', 0);

            if (!$lgaId) {
                return response()->json(['success' => false, 'message' => 'lga_id is required'], 422);
            }

            $total = Enrollee::query()
                ->join('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->where('wards.lga_id', $lgaId)
                ->count();

            $wards = Enrollee::query()
                ->leftJoin('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->where('wards.lga_id', $lgaId)
                ->select('wards.id as ward_id', 'wards.name as ward', DB::raw('COUNT(enrollees.id) as total'))
                ->whereNotNull('wards.name')
                ->groupBy('wards.id', 'wards.name')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($row): array => [
                    'ward_id'    => (int) $row->ward_id,
                    'ward'       => $row->ward,
                    'label'      => $row->ward,
                    'count'      => (int) $row->total,
                    'enrollees'  => (int) $row->total,
                    'percentage' => $this->percent((int) $row->total, max($total, 1)),
                ])
                ->all();

            $lga = Lga::find($lgaId);

            return response()->json([
                'success' => true,
                'data' => [
                    'lga_id'        => $lgaId,
                    'lga_name'      => $lga?->name,
                    'total_enrolled' => $total,
                    'wards'         => $wards,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function activeCoveredQuery(?string $date = null): Builder
    {
        $date ??= now()->toDateString();

        return Enrollee::query()
            ->where('status', Enrollee::STATUS_ACTIVE)
            ->whereNotNull('coverage_start_date')
            ->whereDate('coverage_start_date', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('coverage_end_date')
                    ->orWhereDate('coverage_end_date', '>=', $date);
            });
    }

    private function statusCount(int $status): int
    {
        return Enrollee::where('status', $status)->count();
    }

    private function expiredCoverageCount(string $date): int
    {
        return Enrollee::whereNotNull('coverage_end_date')
            ->whereDate('coverage_end_date', '<', $date)
            ->count();
    }

    private function facilityStatusCount(string $status): int
    {
        if (!Schema::hasColumn('facilities', 'accreditation_status')) {
            return $status === 'active' ? Facility::count() : 0;
        }

        return Facility::where('accreditation_status', $status)->count();
    }

    private function statusBreakdown(int $total): array
    {
        $labels = [
            Enrollee::STATUS_PENDING => 'Pending',
            Enrollee::STATUS_ACTIVE => 'Active',
            Enrollee::STATUS_REJECTED => 'Rejected',
            Enrollee::STATUS_SUSPENDED => 'Suspended',
            Enrollee::STATUS_EXPIRED => 'Expired / Inactive',
        ];

        return collect($labels)->map(function (string $label, int $status) use ($total): array {
            $count = $this->statusCount($status);

            return [
                'label' => $label,
                'count' => $count,
                'percentage' => $this->percent($count, $total),
            ];
        })->values()->all();
    }

    private function dimensionBreakdown(
        string $table,
        string $foreignKey,
        string $labelKey,
        int $total,
        string $emptyLabel = 'Not Specified',
        bool $excludeEmpty = false,
        int $limit = 10
    ): array {
        if (!Schema::hasTable($table) || !Schema::hasColumn('enrollees', $foreignKey)) {
            return [];
        }

        $rows = Enrollee::query()
            ->leftJoin($table, "enrollees.{$foreignKey}", '=', "{$table}.id")
            ->select("{$table}.name as label", DB::raw('COUNT(enrollees.id) as total'))
            ->groupBy("{$table}.id", "{$table}.name")
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return $rows
            ->filter(fn ($row): bool => !$excludeEmpty || (bool) $row->label)
            ->map(fn ($row): array => [
                $labelKey => $row->label ?: $emptyLabel,
                'label' => $row->label ?: $emptyLabel,
                'count' => (int) $row->total,
                'percentage' => $this->percent((int) $row->total, $total),
            ])
            ->values()
            ->all();
    }

    private function topLgas(int $total, int $limit = 12): array
    {
        return Enrollee::query()
            ->leftJoin('lgas', 'enrollees.lga_id', '=', 'lgas.id')
            ->select('lgas.id as lga_id', 'lgas.name as lga', DB::raw('COUNT(enrollees.id) as total'))
            ->whereNotNull('lgas.name')
            ->groupBy('lgas.id', 'lgas.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'lga_id' => (int) $row->lga_id,
                'lga' => $row->lga,
                'label' => $row->lga,
                'count' => (int) $row->total,
                'enrollees' => (int) $row->total,
                'percentage' => $this->percent((int) $row->total, $total),
            ])
            ->all();
    }

    private function topWards(int $total, int $limit = 12): array
    {
        return Enrollee::query()
            ->leftJoin('wards', 'enrollees.ward_id', '=', 'wards.id')
            ->leftJoin('lgas', 'wards.lga_id', '=', 'lgas.id')
            ->select('wards.name as ward', 'lgas.name as lga', DB::raw('COUNT(enrollees.id) as total'))
            ->whereNotNull('wards.name')
            ->groupBy('wards.id', 'wards.name', 'lgas.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'ward' => $row->ward,
                'lga' => $row->lga,
                'label' => trim($row->ward . ' / ' . $row->lga, ' /'),
                'count' => (int) $row->total,
                'enrollees' => (int) $row->total,
                'percentage' => $this->percent((int) $row->total, $total),
            ])
            ->all();
    }

    private function topFacilities(int $limit = 12): array
    {
        $today = now()->toDateString();

        return Facility::query()
            ->leftJoin('lgas', 'facilities.lga_id', '=', 'lgas.id')
            ->leftJoin('enrollees', function ($join) use ($today): void {
                $join->on('facilities.id', '=', 'enrollees.facility_id')
                    ->where('enrollees.status', Enrollee::STATUS_ACTIVE)
                    ->whereNotNull('enrollees.coverage_start_date')
                    ->whereDate('enrollees.coverage_start_date', '<=', $today)
                    ->where(function ($query) use ($today): void {
                        $query->whereNull('enrollees.coverage_end_date')
                            ->orWhereDate('enrollees.coverage_end_date', '>=', $today);
                    });
            })
            ->select(
                'facilities.name',
                'facilities.hcp_code',
                'facilities.capacity',
                'facilities.accreditation_status',
                'lgas.name as lga',
                DB::raw('COUNT(enrollees.id) as active_lives')
            )
            ->groupBy('facilities.id', 'facilities.name', 'facilities.hcp_code', 'facilities.capacity', 'facilities.accreditation_status', 'lgas.name')
            ->orderByDesc('active_lives')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $capacity = (int) ($row->capacity ?? 0);
                $activeLives = (int) $row->active_lives;

                return [
                    'name' => $row->name,
                    'facility' => $row->name,
                    'hcp_code' => $row->hcp_code,
                    'lga' => $row->lga,
                    'enrollees' => $activeLives,
                    'active_lives' => $activeLives,
                    'capacity' => $capacity,
                    'utilization' => $capacity > 0 ? $this->percent($activeLives, $capacity) : 0,
                    'status' => $row->accreditation_status ?: 'active',
                ];
            })
            ->all();
    }

    private function facilityBreakdown(string $column, string $labelKey, int $total): array
    {
        if (!Schema::hasColumn('facilities', $column)) {
            return [];
        }

        return Facility::query()
            ->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                $labelKey => $row->{$column} ?: 'Not Specified',
                'label' => $row->{$column} ?: 'Not Specified',
                'count' => (int) $row->total,
                'percentage' => $this->percent((int) $row->total, $total),
            ])
            ->all();
    }

    private function monthlyTrend(string $column, int $months = 12): array
    {
        if (!Schema::hasColumn('enrollees', $column)) {
            return [];
        }

        $start = now()->subMonths($months - 1)->startOfMonth();
        $end = now()->endOfMonth();

        $rows = Enrollee::query()
            ->selectRaw("YEAR({$column}) as y, MONTH({$column}) as m, COUNT(*) as total")
            ->whereNotNull($column)
            ->whereBetween($column, [$start, $end])
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($row): string => sprintf('%04d-%02d', $row->y, $row->m));

        return collect(CarbonPeriod::create($start, '1 month', $end))
            ->map(function (Carbon $date) use ($rows): array {
                $key = $date->format('Y-m');
                $row = $rows->get($key);

                return [
                    'month' => $date->format('M Y'),
                    'label' => $date->format('M'),
                    'count' => $row ? (int) $row->total : 0,
                    'enrollees' => $row ? (int) $row->total : 0,
                ];
            })
            ->values()
            ->all();
    }

    private function genderBreakdown(int $total): array
    {
        return Enrollee::query()
            ->select('sex', DB::raw('COUNT(*) as total'))
            ->groupBy('sex')
            ->get()
            ->map(function ($row) use ($total): array {
                $label = match ((string) $row->sex) {
                    '1', 'male', 'Male', 'M' => 'Male',
                    '2', 'female', 'Female', 'F' => 'Female',
                    default => 'Not Specified',
                };

                return [
                    'gender' => $label,
                    'label' => $label,
                    'count' => (int) $row->total,
                    'value' => (int) $row->total,
                    'percentage' => $this->percent((int) $row->total, $total),
                ];
            })
            ->all();
    }

    private function ageStats(): array
    {
        if (!Schema::hasColumn('enrollees', 'date_of_birth')) {
            return ['average_age' => 0, 'age_groups' => []];
        }

        return [
            'average_age' => round((float) Enrollee::whereNotNull('date_of_birth')
                ->selectRaw('AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as average_age')
                ->value('average_age'), 1),
            'age_groups' => [
                'under_5' => Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 5')->count(),
                '5_17' => Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 5 AND 17')->count(),
                '18_45' => Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 45')->count(),
                '46_84' => Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 46 AND 84')->count(),
                '85_plus' => Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 85')->count(),
            ],
        ];
    }

    private function recentApprovals(int $limit = 8): array
    {
        return Enrollee::query()
            ->with(['insuranceProgramme:id,name', 'facility:id,name'])
            ->where('status', Enrollee::STATUS_ACTIVE)
            ->whereNotNull('approval_date')
            ->latest('approval_date')
            ->limit($limit)
            ->get()
            ->map(function (Enrollee $enrollee): array {
                return [
                    'id' => $enrollee->id,
                    'enrollee_id' => $enrollee->enrollee_id,
                    'name' => trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->last_name ?? '')),
                    'programme' => $enrollee->insuranceProgramme?->name,
                    'facility' => $enrollee->facility?->name,
                    'approved_at' => optional($enrollee->approval_date)->toDateTimeString(),
                    'time' => optional($enrollee->approval_date)->diffForHumans(),
                    'type' => 'approval',
                    'title' => 'Enrollee approved',
                    'description' => trim(($enrollee->first_name ?? '') . ' ' . ($enrollee->last_name ?? '')) . ' approved for coverage',
                    'icon' => 'mdi-account-check-outline',
                    'color' => 'green',
                ];
            })
            ->all();
    }

    private function pinInventory(): array
    {
        if (!Schema::hasTable('premium_pins')) {
            return [];
        }

        return [
            'total' => PremiumPin::count(),
            'generated' => PremiumPin::where('status', PremiumPin::STATUS_GENERATED)->count(),
            'sold' => PremiumPin::where('status', PremiumPin::STATUS_SOLD)->count(),
            'used' => PremiumPin::where('status', PremiumPin::STATUS_USED)->count(),
            'expired' => PremiumPin::where('status', PremiumPin::STATUS_EXPIRED)->count(),
            'total_value' => (float) PremiumPin::sum('amount'),
            'used_value' => (float) PremiumPin::where('status', PremiumPin::STATUS_USED)->sum('amount'),
        ];
    }

    private function invoiceSummary(): array
    {
        if (!Schema::hasTable('invoices')) {
            return [];
        }

        return [
            'total' => Invoice::count(),
            'paid' => Invoice::where('status', 1)->count(),
            'pending' => Invoice::where('status', '<>', 1)->count(),
            'paid_value' => (float) Invoice::where('status', 1)->sum('amount'),
            'pending_value' => (float) Invoice::where('status', '<>', 1)->sum('amount'),
        ];
    }

    private function legacyCard(string $title, int $value, string $icon, string $color): array
    {
        return [
            'value' => $value,
            'change' => 0,
            'title' => $title,
            'icon' => $icon,
            'color' => $color,
        ];
    }

    private function percent(int|float $value, int|float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 1);
    }
}
