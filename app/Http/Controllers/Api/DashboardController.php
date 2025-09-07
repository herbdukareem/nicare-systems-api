<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use App\Models\Ward;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview statistics
     */
    public function overview(): JsonResponse
    {
        try {
            $totalEnrollees = Enrollee::count();
            $activeEnrollees = Enrollee::where('status', 1)->count(); // ACTIVE = 1
            $pendingEnrollees = Enrollee::where('status', 0)->count(); // PENDING = 0
            $totalFacilities = Facility::count();

            // Calculate percentage changes (mock data for now - in real app, compare with previous period)
            $enrolleeChange = 12.5; // Mock percentage change
            $activeChange = 8.2;
            $pendingChange = -3.1;
            $facilityChange = 2.3;

            $stats = [
                'totalEnrollees' => [
                    'value' => $totalEnrollees,
                    'change' => $enrolleeChange,
                    'title' => 'Total Enrollees',
                    'icon' => 'mdi-account-group',
                    'color' => 'blue'
                ],
                'activeEnrollees' => [
                    'value' => $activeEnrollees,
                    'change' => $activeChange,
                    'title' => 'Active Enrollees',
                    'icon' => 'mdi-account-check',
                    'color' => 'green'
                ],
                'pendingApplications' => [
                    'value' => $pendingEnrollees,
                    'change' => $pendingChange,
                    'title' => 'Pending Applications',
                    'icon' => 'mdi-clock-outline',
                    'color' => 'orange'
                ],
                'totalFacilities' => [
                    'value' => $totalFacilities,
                    'change' => $facilityChange,
                    'title' => 'Total Facilities',
                    'icon' => 'mdi-hospital-building',
                    'color' => 'purple'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get enrollee statistics
     */
    public function enrolleeStats(): JsonResponse
    {
        try {
            // Gender distribution
            $genderStats = Enrollee::select('gender', DB::raw('count(*) as count'))
                ->whereNotNull('gender')
                ->groupBy('gender')
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::whereNotNull('gender')->count();
                    return [
                        'gender' => $item->gender,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Enrollee type distribution
            $typeStats = Enrollee::select('enrollee_types.name', DB::raw('count(*) as count'))
                ->join('enrollee_types', 'enrollees.enrollee_type_id', '=', 'enrollee_types.id')
                ->groupBy('enrollee_types.id', 'enrollee_types.name')
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::count();
                    return [
                        'type' => $item->name,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Benefactor distribution
            $benefactorStats = Enrollee::select('benefactors.name', DB::raw('count(*) as count'))
                ->join('benefactors', 'enrollees.benefactor_id', '=', 'benefactors.id')
                ->groupBy('benefactors.id', 'benefactors.name')
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::count();
                    return [
                        'benefactor' => $item->name,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Monthly enrollment trend (last 6 months)
            $monthlyStats = Enrollee::select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => date('M', mktime(0, 0, 0, $item->month, 1)),
                    'count' => $item->count,
                ];
            })->reverse();

            // Age distribution
            $ageStats = [
                'average_age' => Enrollee::whereNotNull('date_of_birth')
                    ->selectRaw('AVG(TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE())) as avg_age')
                    ->first()->avg_age ?? 0,
                'age_groups' => [
                    'under_18' => Enrollee::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18')->count(),
                    '18_30' => Enrollee::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 18 AND 30')->count(),
                    '31_50' => Enrollee::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 50')->count(),
                    'over_50' => Enrollee::whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) > 50')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'byGender' => $genderStats,
                    'byType' => $typeStats,
                    'byBenefactor' => $benefactorStats,
                    'monthlyTrend' => $monthlyStats,
                    'ageStats' => $ageStats,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollee statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get facility statistics
     */
    public function facilityStats(): JsonResponse
    {
        try {
            // Enrollees by LGA
            $lgaStats = Enrollee::select('lgas.name', DB::raw('count(*) as count'))
                ->join('lgas', 'enrollees.lga_id', '=', 'lgas.id')
                ->groupBy('lgas.id', 'lgas.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::count();
                    return [
                        'lga' => $item->name,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Enrollees by Ward
            $wardStats = Enrollee::select('wards.name', DB::raw('count(*) as count'))
                ->join('wards', 'enrollees.ward_id', '=', 'wards.id')
                ->groupBy('wards.id', 'wards.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::count();
                    return [
                        'ward' => $item->name,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Enrollees by Facility
            $facilityStats = Enrollee::select('facilities.name', DB::raw('count(*) as count'))
                ->join('facilities', 'enrollees.facility_id', '=', 'facilities.id')
                ->groupBy('facilities.id', 'facilities.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $total = Enrollee::count();
                    return [
                        'facility' => $item->name,
                        'count' => $item->count,
                        'percentage' => $total > 0 ? round(($item->count / $total) * 100, 1) : 0,
                    ];
                });

            // Top performing facilities with capacity info
            $topFacilities = Facility::select(
                'facilities.name',
                'lgas.name as lga_name',
                DB::raw('COUNT(enrollees.id) as enrollee_count'),
                'facilities.capacity'
            )
            ->leftJoin('enrollees', 'facilities.id', '=', 'enrollees.facility_id')
            ->leftJoin('lgas', 'facilities.lga_id', '=', 'lgas.id')
            ->groupBy('facilities.id', 'facilities.name', 'lgas.name', 'facilities.capacity')
            ->orderBy('enrollee_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $utilization = $item->capacity > 0 ? round(($item->enrollee_count / $item->capacity) * 100, 1) : 0;
                return [
                    'name' => $item->name,
                    'lga' => $item->lga_name,
                    'enrollees' => $item->enrollee_count,
                    'capacity' => $item->capacity,
                    'utilization' => $utilization,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'byLga' => $lgaStats,
                    'byWard' => $wardStats,
                    'byFacility' => $facilityStats,
                    'topFacilities' => $topFacilities,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch facility statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get chart data for dashboard
     */
    public function chartData(): JsonResponse
    {
        try {
            // Enrollment trend data for line chart
            $enrollmentTrend = Enrollee::select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => date('M Y', strtotime($item->month . '-01')),
                    'enrollees' => $item->count,
                ];
            });

            // Gender distribution for doughnut chart
            $genderDistribution = Enrollee::select('gender', DB::raw('count(*) as count'))
                ->whereNotNull('gender')
                ->groupBy('gender')
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => ucfirst($item->gender),
                        'value' => $item->count,
                    ];
                });

            // Facilities by LGA for bar chart
            $facilitiesByLga = Facility::select('lgas.name', DB::raw('count(*) as count'))
                ->join('lgas', 'facilities.lga_id', '=', 'lgas.id')
                ->groupBy('lgas.id', 'lgas.name')
                ->orderBy('count', 'desc')
                ->limit(6)
                ->get()
                ->map(function ($item) {
                    return [
                        'lga' => $item->name,
                        'facilities' => $item->count,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'enrollmentTrend' => $enrollmentTrend,
                    'genderDistribution' => $genderDistribution,
                    'facilitiesByLga' => $facilitiesByLga,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chart data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent activities for dashboard
     */
    public function recentActivities(): JsonResponse
    {
        try {
            $recentEnrollees = Enrollee::with(['enrolleeType', 'lga'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($enrollee) {
                    return [
                        'id' => $enrollee->id,
                        'type' => 'enrollment',
                        'title' => 'New Enrollee',
                        'description' => $enrollee->first_name . ' ' . $enrollee->last_name . ' enrolled',
                        'time' => $enrollee->created_at->diffForHumans(),
                        'icon' => 'mdi-account-plus',
                        'color' => 'green'
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $recentEnrollees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent activities',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
