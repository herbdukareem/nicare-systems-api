<?php

namespace App\Http\Controllers;

use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\PremiumPin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_enrollees' => Enrollee::count(),
            'active_covered_enrollees' => Enrollee::where('status', 1)
                ->whereDate('coverage_start_date', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('coverage_end_date')
                        ->orWhereDate('coverage_end_date', '>=', now());
                })
                ->count(),
            'monthly_enrollees' => Enrollee::whereMonth('created_at', now()->month)
                                          ->whereYear('created_at', now()->year)
                                          ->count(),
            'pending_enrollees' => Enrollee::where('status', 0)->count(),
            'approved_enrollees' => Enrollee::where('status', 1)->count(),
            'total_facilities' => Facility::count(),
            'total_users' => User::count(),
            'premium_stats' => [
                'total' => PremiumPin::count(),
                'available' => PremiumPin::whereIn('status', [PremiumPin::STATUS_GENERATED, PremiumPin::STATUS_SOLD])->count(),
                'used' => PremiumPin::where('status', PremiumPin::STATUS_USED)->count(),
                'expired' => PremiumPin::where('status', PremiumPin::STATUS_EXPIRED)->count(),
                'total_value' => PremiumPin::sum('amount'),
                'used_value' => PremiumPin::where('status', PremiumPin::STATUS_USED)->sum('amount'),
            ],
            'enrollment_by_lga' => $this->getEnrollmentByLga(),
            'enrollment_trend' => $this->getEnrollmentTrend(),
            'facility_utilization' => $this->getFacilityUtilization(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    private function getEnrollmentByLga(): array
    {
        return Enrollee::select('lgas.name', DB::raw('count(*) as total'))
                      ->join('lgas', 'enrollees.lga_id', '=', 'lgas.id')
                      ->groupBy('lgas.id', 'lgas.name')
                      ->orderBy('total', 'desc')
                      ->limit(10)
                      ->get()
                      ->toArray();
    }

    private function getEnrollmentTrend(): array
    {
        return Enrollee::select(
                         DB::raw('DATE(created_at) as date'),
                         DB::raw('count(*) as count')
                       )
                       ->where('created_at', '>=', now()->subDays(30))
                       ->groupBy('date')
                       ->orderBy('date')
                       ->get()
                       ->toArray();
    }

    private function getFacilityUtilization(): array
    {
        return Facility::select('facilities.name', DB::raw('count(enrollees.id) as enrollee_count'))
                      ->leftJoin('enrollees', function ($join) {
                          $join->on('facilities.id', '=', 'enrollees.facility_id')
                              ->where('enrollees.status', 1)
                              ->where('enrollees.coverage_start_date', '<=', now()->toDateString())
                              ->where(function ($query) {
                                  $query->whereNull('enrollees.coverage_end_date')
                                      ->orWhere('enrollees.coverage_end_date', '>=', now()->toDateString());
                              });
                      })
                      ->groupBy('facilities.id', 'facilities.name')
                      ->orderBy('enrollee_count', 'desc')
                      ->limit(10)
                      ->get()
                      ->toArray();
    }
}
