<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use Illuminate\Http\JsonResponse;

class PremiumDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'plans' => PremiumPlan::count(),
                'pins_available' => PremiumPin::where('status', PremiumPin::STATUS_GENERATED)->count(),
                'pins_sold' => PremiumPin::where('status', PremiumPin::STATUS_SOLD)->count(),
                'pins_used' => PremiumPin::where('status', PremiumPin::STATUS_USED)->count(),
                'pending_purchases' => PremiumPurchase::where('payment_status', 'pending')->count(),
                'confirmed_purchases' => PremiumPurchase::where('payment_status', 'confirmed')->count(),
                'active_coverage' => Enrollee::where('status', 1)
                    ->whereDate('coverage_start_date', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('coverage_end_date')
                            ->orWhereDate('coverage_end_date', '>=', now());
                    })
                    ->count(),
                'waiting_period' => 0,
            ],
        ]);
    }
}
