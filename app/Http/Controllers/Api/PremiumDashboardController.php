<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollee;
use App\Models\PremiumPin;
use App\Models\PremiumPlan;
use App\Models\PremiumPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class PremiumDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $activeCoverageQuery = Enrollee::query()
            ->where('status', Enrollee::STATUS_ACTIVE)
            ->whereDate('coverage_start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('coverage_end_date')
                    ->orWhereDate('coverage_end_date', '>=', now());
            });

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => PremiumPlan::count(),
                'pins_available' => PremiumPin::where('status', PremiumPin::STATUS_GENERATED)->count(),
                'pins_sold' => PremiumPin::where('status', PremiumPin::STATUS_SOLD)->count(),
                'pins_used' => PremiumPin::where('status', PremiumPin::STATUS_USED)->count(),
                'pending_purchases' => PremiumPurchase::where('payment_status', 'pending')->count(),
                'confirmed_purchases' => PremiumPurchase::where('payment_status', 'confirmed')->count(),
                'active_coverage' => (clone $activeCoverageQuery)->count(),
                'recent_coverages' => $this->recentCoverages($activeCoverageQuery),
                'waiting_period' => 0,
            ],
        ]);
    }

    private function recentCoverages(\Illuminate\Database\Eloquent\Builder $baseQuery): Collection
    {
        return (clone $baseQuery)
            ->with([
                'insuranceProgramme:id,name',
                'facility:id,name',
            ])
            ->orderByDesc('coverage_start_date')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function (Enrollee $enrollee): array {
                return [
                    'id' => $enrollee->id,
                    'enrollee' => [
                        'enrollee_id' => $enrollee->enrollee_id,
                        'full_name' => $enrollee->full_name,
                    ],
                    'programme' => [
                        'name' => $enrollee->insuranceProgramme?->name ?: 'N/A',
                    ],
                    'facility' => [
                        'name' => $enrollee->facility?->name ?: 'N/A',
                    ],
                    'status' => $this->statusLabel((int) $enrollee->status),
                    'coverage_start_date' => optional($enrollee->coverage_start_date)?->toDateString(),
                    'coverage_end_date' => optional($enrollee->coverage_end_date)?->toDateString(),
                ];
            })
            ->values();
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            Enrollee::STATUS_ACTIVE => 'active',
            Enrollee::STATUS_PENDING => 'pending',
            Enrollee::STATUS_REJECTED => 'rejected',
            Enrollee::STATUS_SUSPENDED => 'suspended',
            Enrollee::STATUS_EXPIRED => 'expired',
            default => 'unknown',
        };
    }
}
