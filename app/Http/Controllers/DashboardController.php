<?php

namespace App\Http\Controllers;

use App\Models\Enrollee;
use App\Models\Premium;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_enrollees' => Enrollee::count(),
            'monthly_enrollees' => Enrollee::whereMonth('created_at', now()->month)
                                          ->whereYear('created_at', now()->year)
                                          ->count(),
            'pending_enrollees' => Enrollee::where('status', 'pending')->count(),
            'approved_enrollees' => Enrollee::where('status', 'approved')->count(),
            'total_facilities' => Facility::count(),
            'total_users' => User::count(),
            'premium_stats' => [
                'total' => Premium::count(),
                'available' => Premium::available()->count(),
                'used' => Premium::used()->count(),
                'expired' => Premium::expired()->count(),
                'total_value' => Premium::sum('amount'),
                'used_value' => Premium::used()->sum('amount'),
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
                      ->leftJoin('enrollees', 'facilities.id', '=', 'enrollees.facility_id')
                      ->groupBy('facilities.id', 'facilities.name')
                      ->orderBy('enrollee_count', 'desc')
                      ->limit(10)
                      ->get()
                      ->toArray();
    }
}