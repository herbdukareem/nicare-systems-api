<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/overview
     */
    public function overview(): JsonResponse
    {
        try {
            // Current month counts
            $totalEnrollees     = Enrollee::count();
            $activeEnrollees    = Enrollee::where('status', 1)->count();   // ACTIVE = 1
            $notActiveEnrollees = Enrollee::where('status', 0)->count();   // PENDING = 0
            $totalFacilities    = Facility::count();

            // Previous month counts for comparison
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            $lastMonthTotalEnrollees = Enrollee::where('created_at', '<=', $lastMonthEnd)->count();
            $lastMonthActiveEnrollees = Enrollee::where('status', 1)->where('created_at', '<=', $lastMonthEnd)->count();
            $lastMonthNotActiveEnrollees = Enrollee::where('status', 0)->where('created_at', '<=', $lastMonthEnd)->count();
            $lastMonthTotalFacilities = Facility::where('created_at', '<=', $lastMonthEnd)->count();

            // Calculate percentage changes
            $enrolleeChange = $lastMonthTotalEnrollees > 0 ?
                round((($totalEnrollees - $lastMonthTotalEnrollees) / $lastMonthTotalEnrollees) * 100, 1) : 0;
            $activeChange = $lastMonthActiveEnrollees > 0 ?
                round((($activeEnrollees - $lastMonthActiveEnrollees) / $lastMonthActiveEnrollees) * 100, 1) : 0;
            $pendingChange = $lastMonthNotActiveEnrollees > 0 ?
                round((($notActiveEnrollees - $lastMonthNotActiveEnrollees) / $lastMonthNotActiveEnrollees) * 100, 1) : 0;
            $facilityChange = $lastMonthTotalFacilities > 0 ?
                round((($totalFacilities - $lastMonthTotalFacilities) / $lastMonthTotalFacilities) * 100, 1) : 0;

            $stats = [
                'totalEnrollees' => [
                    'value' => $totalEnrollees,
                    'change' => $enrolleeChange,
                    'title' => 'Total Enrollees',
                    'icon'  => 'mdi-account-group',
                    'color' => 'blue',
                ],
                'activeEnrollees' => [
                    'value' => $activeEnrollees,
                    'change' => $activeChange,
                    'title' => 'Active Enrollees',
                    'icon'  => 'mdi-account-check',
                    'color' => 'green',
                ],
                'notActiveEnrollees' => [
                    'value' => $notActiveEnrollees,
                    // BUGFIX: previously used ($totalEnrollees - $notActiveEnrollees)
                    'change' => $pendingChange,
                    'title' => 'Enrollees Not Active',
                    'icon'  => 'mdi-clock-outline',
                    'color' => 'orange',
                ],
                'totalFacilities' => [
                    'value' => $totalFacilities,
                    'change' => $facilityChange,
                    'title' => 'Total Facilities',
                    'icon'  => 'mdi-hospital-building',
                    'color' => 'purple',
                ],
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/enrollees-stat
     */
    public function enrolleeStats(): JsonResponse
    {
        try {
            $totalEnrollees = Enrollee::count();

            // Monthly enrollment trend (last 6 calendar months, inclusive) — ONLY_FULL_GROUP_BY safe
                $start = now()->subMonths(5)->startOfMonth(); // e.g., if today is Sep, start from May 1
                $end   = now()->endOfMonth();

            // ----- Gender distribution (1=Male, 2=Female, else Other)
            $genderRows = Enrollee::select('sex', DB::raw('COUNT(*) AS count'))
                ->whereNotNull('sex')
                ->groupBy('sex')
                ->get();

            $genderTotal = $genderRows->sum('count') ?: 1; // avoid /0
            $genderStats = $genderRows->map(function ($r) use ($genderTotal) {
                $label = ($r->sex == 1) ? 'Male' : (($r->sex == 2) ? 'Female' : 'Other');
                return [
                    'gender'     => $label,
                    'count'      => (int)$r->count,
                    'percentage' => round(($r->count / $genderTotal) * 100, 1),
                ];
            });

            // ----- Sector distribution
            $typeRows = Enrollee::select('sectors.name AS name', DB::raw('COUNT(*) AS count'))
                ->join('sectors', 'sectors.id', '=', 'enrollees.sector_id')
                ->groupBy('sectors.id', 'sectors.name')
                ->get();

            $typeStats = $typeRows->map(function ($r) use ($totalEnrollees) {
                $den = max($totalEnrollees, 1);
                return [
                    'type'       => $r->name,
                    'count'      => (int)$r->count,
                    'percentage' => round(($r->count / $den) * 100, 1),
                ];
            });

            // ----- Benefactor distribution
            $benefactorRows = Enrollee::select('benefactors.name AS name', DB::raw('COUNT(*) AS count'))
                ->join('benefactors', 'enrollees.benefactor_id', '=', 'benefactors.id')
                ->groupBy('benefactors.id', 'benefactors.name')
                ->get();

            $benefactorStats = $benefactorRows->map(function ($r) use ($totalEnrollees) {
                $den = max($totalEnrollees, 1);
                return [
                    'benefactor' => $r->name,
                    'count'      => (int)$r->count,
                    'percentage' => round(($r->count / $den) * 100, 1),
                ];
            });

            // ----- Monthly enrollment trend (last 6 months), chronological
            $rawTrend = Enrollee::selectRaw('YEAR(created_at) AS y, MONTH(created_at) AS m, COUNT(*) AS cnt')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('y', 'm')
                ->orderBy('y')->orderBy('m')
                ->get()
                ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));
            $monthlyStats = collect(CarbonPeriod::create($start, '1 month', $end)->toArray())
            ->map(function (Carbon $dt) use ($rawTrend) {
                $key = $dt->format('Y-m');
                $row = $rawTrend->get($key);
                return [
                    'month' => $dt->format('M'),
                    'count' => $row ? (int)$row->cnt : 0,
                ];
            })
            ->values();

            // ----- Age distribution
            $avgRow = Enrollee::whereNotNull('date_of_birth')
                ->selectRaw('AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) AS avg_age')
                ->first();

            $ageStats = [
                'average_age' => $avgRow && $avgRow->avg_age !== null ? round((float)$avgRow->avg_age, 2) : 0.0,
                'age_groups'  => [
                    'under_18' => (int) Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18')->count(),
                    '18_30'    => (int) Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30')->count(),
                    '31_50'    => (int) Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50')->count(),
                    'over_50'  => (int) Enrollee::whereNotNull('date_of_birth')->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 50')->count(),
                ],
            ];

            // ----- Funding type distribution
            $fundingRows = Enrollee::select('funding_types.name AS name', DB::raw('COUNT(*) AS count'))
                ->leftJoin('funding_types', 'enrollees.funding_type_id', '=', 'funding_types.id')
                ->groupBy('funding_types.id', 'funding_types.name')
                ->get();

            $fundingTypeStats = $fundingRows->map(function ($r) use ($totalEnrollees) {
                $name = $r->name ?: 'Not Specified';
                $den  = max($totalEnrollees, 1);
                return [
                    'funding_type' => $name,
                    'count'        => (int)$r->count,
                    'percentage'   => round(($r->count / $den) * 100, 1),
                ];
            });

            // ----- Top 10 wards (with LGA)
            $wardStats = Enrollee::select(
                    'wards.name AS ward',
                    'lgas.name AS lga',
                    DB::raw('COUNT(*) AS count')
                )
                ->leftJoin('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->leftJoin('lgas', 'wards.lga_id', '=', 'lgas.id')
                ->whereNotNull('wards.name')
                ->groupBy('wards.id', 'wards.name', 'lgas.name')
                ->orderBy('count', 'desc')
                ->get()
                ->map(fn ($r) => ['ward' => $r->ward, 'lga' => $r->lga, 'count' => (int)$r->count]);

            // ----- Top 10 facilities (with LGA)
            $facilityStats = Enrollee::select(
                    'facilities.name AS facility',
                    'lgas.name AS lga',
                    DB::raw('COUNT(*) AS count')
                )
                ->leftJoin('facilities', 'enrollees.facility_id', '=', 'facilities.id')
                ->leftJoin('lgas', 'facilities.lga_id', '=', 'lgas.id')
                ->whereNotNull('facilities.name')
                ->groupBy('facilities.id', 'facilities.name', 'lgas.name')
                ->orderBy('count', 'desc')
                ->get()
                ->map(fn ($r) => ['facility' => $r->facility, 'lga' => $r->lga, 'count' => (int)$r->count]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'byGender'      => $genderStats,
                    'byType'        => $typeStats,
                    'byBenefactor'  => $benefactorStats,
                    'byFundingType' => $fundingTypeStats,
                    'byWard'        => $wardStats,
                    'byFacility'    => $facilityStats,
                    'monthlyTrend'  => $monthlyStats,
                    'ageStats'      => $ageStats,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollee statistics',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/facility-stats
     */
    public function facilityStats(): JsonResponse
    {
        try {
            $totalEnrollees = Enrollee::count();
            $den = max($totalEnrollees, 1);

            // Enrollees by LGA (top 10)
            $lgaStats = Enrollee::select('lgas.name AS lga', DB::raw('COUNT(*) AS count'))
                ->leftJoin('lgas', 'enrollees.lga_id', '=', 'lgas.id')
                ->whereNotNull('lgas.name')
                ->groupBy('lgas.id', 'lgas.name')
                ->orderBy('count', 'desc')
             
                ->get()
                ->map(fn ($r) => [
                    'lga'        => $r->lga,
                    'count'      => (int)$r->count,
                    'percentage' => round(($r->count / $den) * 100, 1),
                ]);

            // Enrollees by Ward (top 10)
            $wardStats = Enrollee::select('wards.name AS ward', DB::raw('COUNT(*) AS count'))
                ->leftJoin('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->whereNotNull('wards.name')
                ->groupBy('wards.id', 'wards.name')
                ->orderBy('count', 'desc')
              
                ->get()
                ->map(fn ($r) => [
                    'ward'       => $r->ward,
                    'count'      => (int)$r->count,
                    'percentage' => round(($r->count / $den) * 100, 1),
                ]);

            // Enrollees by Facility (top 10)
            $facilityRows = Enrollee::select('facilities.name AS facility', DB::raw('COUNT(*) AS count'))
                ->leftJoin('facilities', 'enrollees.facility_id', '=', 'facilities.id')
                ->whereNotNull('facilities.name')
                ->groupBy('facilities.id', 'facilities.name')
                ->orderBy('count', 'desc')
              
                ->get();

            $byFacility = $facilityRows->map(fn ($r) => [
                'facility'   => $r->facility,
                'count'      => (int)$r->count,
                'percentage' => round(($r->count / $den) * 100, 1),
            ]);

            // Top performing facilities (name, lga, enrollees, capacity, utilization)
            $topFacilities = Facility::select(
                    'facilities.name',
                    'lgas.name AS lga_name',
                    DB::raw('COUNT(enrollees.id) AS enrollee_count'),
                    'facilities.capacity'
                )
                ->leftJoin('enrollees', 'facilities.id', '=', 'enrollees.facility_id')
                ->leftJoin('lgas', 'facilities.lga_id', '=', 'lgas.id')
                ->groupBy('facilities.id', 'facilities.name', 'lgas.name', 'facilities.capacity')
                ->orderBy('enrollee_count', 'desc')
               
                ->get()
                ->map(function ($r) {
                    $cap = (int)($r->capacity ?? 0);
                    $util = $cap > 0 ? round(((int)$r->enrollee_count / $cap) * 100, 1) : 0.0;
                    return [
                        'name'        => $r->name,
                        'lga'         => $r->lga_name,
                        'enrollees'   => (int)$r->enrollee_count,
                        'capacity'    => $cap,
                        'utilization' => $util,
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => [
                    'byLga'        => $lgaStats,
                    'byWard'       => $wardStats,
                    'byFacility'   => $byFacility,
                    'topFacilities'=> $topFacilities,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch facility statistics',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/chart-data
     */
    public function chartData(): JsonResponse
    {
        try {

                // Enrollment trend data for line chart — ONLY_FULL_GROUP_BY safe
                $enrollmentTrend = Enrollee::selectRaw('YEAR(created_at) AS y, MONTH(created_at) AS m, COUNT(*) AS count')
                    ->where('created_at', '>=', now()->subMonths(12))
                    ->groupBy('y', 'm')
                    ->orderBy('y')
                    ->orderBy('m')
                    ->get()
                    ->map(function ($row) {
                        return [
                            'month' => date('M Y', mktime(0, 0, 0, (int)$row->m, 1, (int)$row->y)),
                            'enrollees' => (int)$row->count,
                        ];
                    });


            // Gender distribution
            $genderDistribution = Enrollee::select('sex', DB::raw('COUNT(*) AS count'))
                ->whereNotNull('sex')
                ->groupBy('sex')
                ->get()
                ->map(function ($r) {
                    $label = ($r->sex == 1) ? 'Male' : (($r->sex == 2) ? 'Female' : 'Other');
                    return ['label' => $label, 'value' => (int)$r->count];
                });

            // Enrollees by LGA
            $enrolleesByLga = Enrollee::select('lgas.name AS lga', DB::raw('COUNT(*) AS cnt'))
                ->leftJoin('lgas', 'enrollees.lga_id', '=', 'lgas.id')
                ->whereNotNull('lgas.name')
                ->groupBy('lgas.id', 'lgas.name')
                ->orderBy('cnt', 'desc')
                ->get()
                ->map(fn ($r) => ['lga' => $r->lga, 'enrollees' => (int)$r->cnt]);

            // Enrollees by Benefactor
            $enrolleesByBenefactor = Enrollee::select('benefactors.name AS name', DB::raw('COUNT(*) AS cnt'))
                ->leftJoin('benefactors', 'enrollees.benefactor_id', '=', 'benefactors.id')
                ->groupBy('benefactors.id', 'benefactors.name')
                ->orderBy('cnt', 'desc')
                ->get()
                ->map(fn ($r) => ['benefactor' => ($r->name ?: 'Self-Funded'), 'enrollees' => (int)$r->cnt]);

            // Enrollees by Funding Type
            $enrolleesByFundingType = Enrollee::select('funding_types.name AS name', DB::raw('COUNT(*) AS cnt'))
                ->leftJoin('funding_types', 'enrollees.funding_type_id', '=', 'funding_types.id')
                ->groupBy('funding_types.id', 'funding_types.name')
                ->orderBy('cnt', 'desc')
                ->get()
                ->map(fn ($r) => ['funding_type' => ($r->name ?: 'Not Specified'), 'enrollees' => (int)$r->cnt]);

            // Enrollees by Ward
            $enrolleesByWard = Enrollee::select('wards.name AS ward', 'lgas.name AS lga', DB::raw('COUNT(*) AS cnt'))
                ->leftJoin('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->leftJoin('lgas', 'wards.lga_id', '=', 'lgas.id')
                ->whereNotNull('wards.name')
                ->groupBy('wards.id', 'wards.name', 'lgas.name')
                ->orderBy('cnt', 'desc')
                ->get()
                ->map(fn ($r) => ['ward' => $r->ward, 'lga' => $r->lga, 'enrollees' => (int)$r->cnt]);

            // Enrollees by Facility
            $enrolleesByFacility = Enrollee::select('facilities.name AS facility', 'lgas.name AS lga', DB::raw('COUNT(*) AS cnt'))
                ->leftJoin('facilities', 'enrollees.facility_id', '=', 'facilities.id')
                ->leftJoin('lgas', 'facilities.lga_id', '=', 'lgas.id')
                ->whereNotNull('facilities.name')
                ->groupBy('facilities.id', 'facilities.name', 'lgas.name')
                ->orderBy('cnt', 'desc')
                ->get()
                ->map(fn ($r) => ['facility' => $r->facility, 'lga' => $r->lga, 'enrollees' => (int)$r->cnt]);

            return response()->json([
                'success' => true,
                'data' => [
                    'enrollmentTrend'       => $enrollmentTrend,
                    'genderDistribution'    => $genderDistribution,
                    'enrolleesByLga'        => $enrolleesByLga,
                    'enrolleesByBenefactor' => $enrolleesByBenefactor,
                    'enrolleesByFundingType'=> $enrolleesByFundingType,
                    'enrolleesByWard'       => $enrolleesByWard,
                    'enrolleesByFacility'   => $enrolleesByFacility,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/recent-activities
     */
    public function recentActivities(): JsonResponse
    {
        try {
            $recentEnrollees = Enrollee::with(['enrolleeType', 'lga'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($e) {
                    return [
                        'id'          => $e->id,
                        'type'        => 'enrollment',
                        'title'       => 'New Enrollee',
                        'description' => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')) . ' enrolled',
                        'time'        => optional($e->created_at)->diffForHumans(),
                        'icon'        => 'mdi-account-plus',
                        'color'       => 'green',
                    ];
                });

            return response()->json(['success' => true, 'data' => $recentEnrollees]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activities',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/dashboard/status-options
     */
    public function getStatusOptions(): JsonResponse
    {
        try {
            $statusOptions = \App\Enums\Status::options();
            return response()->json(['success' => true, 'data' => $statusOptions]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch status options',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
