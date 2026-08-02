<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Benefactor;
use App\Models\BhcpfExecutiveTarget;
use App\Models\Enrollee;
use App\Models\FundingType;
use App\Models\InsuranceProgramme;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BhcpfExecutiveDashboardController extends BaseController
{
    private const CAMPAIGN_START_DATE = '2026-08-03';

    public function overview(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $campaignStart = Carbon::parse(self::CAMPAIGN_START_DATE)->startOfDay();
        $rangeEndDefault = now()->lt($campaignStart)
            ? $campaignStart->copy()->endOfDay()
            : now()->endOfDay();

        $dateFrom = !empty($validated['date_from'])
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : $campaignStart->copy();
        if ($dateFrom->lt($campaignStart)) {
            $dateFrom = $campaignStart->copy();
        }

        $dateTo = !empty($validated['date_to'])
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : $rangeEndDefault;
        if ($dateTo->lt($campaignStart)) {
            $dateTo = $campaignStart->copy()->endOfDay();
        }

        $targets = BhcpfExecutiveTarget::query()
            ->with('lga:id,name')
            ->orderBy('final_target')
            ->get();

        $captureCounts = $this->campaignCaptureQuery($dateFrom, $dateTo)
            ->selectRaw('lga_id, COUNT(*) as aggregate')
            ->groupBy('lga_id')
            ->pluck('aggregate', 'lga_id');

        $overallTarget = (int) round((float) $targets->sum('final_target'));
        $totalEnrolled = (int) $captureCounts->sum();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $enrolledToday = now()->lt($campaignStart)
            ? 0
            : (int) $this->campaignCaptureQuery($todayStart, $todayEnd)->count();

        $lgaRows = $targets
            ->map(function (BhcpfExecutiveTarget $target) use ($captureCounts): array {
                $captured = (int) ($captureCounts[$target->lga_id] ?? 0);
                $progress = $target->final_target > 0
                    ? round(($captured / (int) $target->final_target) * 100, 1)
                    : 0.0;
                $status = $this->progressStatus($progress);

                return [
                    'lga_id' => $target->lga_id,
                    'lga_name' => $target->lga?->name ?? 'Unknown LGA',
                    'ward_count' => (int) $target->ward_count,
                    'current_enrollee_count' => (int) $target->current_enrollee_count,
                    'poverty_index' => (int) $target->poverty_index,
                    'proposed_enrolments' => (int) $target->proposed_enrolments,
                    'target' => (int) $target->final_target,
                    'captured' => $captured,
                    'remaining' => max((int) $target->final_target - $captured, 0),
                    'progress_percent' => $progress,
                    'status' => $status['label'],
                    'status_tone' => $status['tone'],
                    'status_color' => $status['color'],
                    'plwd_target' => (int) $target->plwd_target,
                    'under_5_target' => (int) $target->under_5_target,
                    'female_reproductive_target' => (int) $target->female_reproductive_target,
                    'elderly_target' => (int) $target->elderly_target,
                    'others_target' => (int) $target->others_target,
                ];
            })
            ->sortByDesc('captured')
            ->values();

        $bestPerforming = collect($lgaRows)->sortByDesc('progress_percent')->first();
        $lowestPerforming = collect($lgaRows)->sortBy('progress_percent')->first();
        $topPerformers = collect($lgaRows)->sortByDesc('progress_percent')->take(5)->values();
        $supportList = collect($lgaRows)->sortBy('progress_percent')->take(5)->values();

        $dailyRows = $this->buildDailyRows($dateFrom, $dateTo);
        $demographics = $this->demographicBreakdown($dateFrom, $dateTo, $targets);

        return $this->sendResponse([
            'campaign' => [
                'name' => 'BHCPF Vulnerable Group Enrollment Drive',
                'start_date' => $campaignStart->toDateString(),
                'today' => now()->toDateString(),
                'campaign_started' => !now()->lt($campaignStart),
            ],
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
            'summary' => [
                'overall_target' => $overallTarget,
                'enrolled_today' => $enrolledToday,
                'total_enrolled' => $totalEnrolled,
                'remaining' => max($overallTarget - $totalEnrolled, 0),
                'overall_progress_percent' => $overallTarget > 0 ? round(($totalEnrolled / $overallTarget) * 100, 1) : 0,
                'best_performing_lga' => $bestPerforming,
                'lowest_performing_lga' => $lowestPerforming,
                'total_lgas' => $targets->count(),
                'total_wards' => (int) $targets->sum('ward_count'),
            ],
            'charts' => [
                'lga_progress' => $lgaRows,
                'daily_trend' => [
                    'labels' => $dailyRows->pluck('date_label')->all(),
                    'captured' => $dailyRows->pluck('captured')->all(),
                    'cumulative' => $dailyRows->pluck('cumulative')->all(),
                ],
                'demographics' => $demographics,
            ],
            'tables' => [
                'lga_progress' => $lgaRows,
                'top_performing' => $topPerformers,
                'needs_support' => $supportList,
                'daily_performance' => $dailyRows->all(),
            ],
        ], 'BHCPF executive dashboard retrieved successfully.');
    }

    private function campaignCaptureQuery(Carbon $dateFrom, Carbon $dateTo): Builder
    {
        $programmeIds = InsuranceProgramme::query()
            ->where(function (Builder $query): void {
                $query->where('code', 'vulnerable_groups')
                    ->orWhere('name', 'like', '%vulnerable%');
            })
            ->pluck('id');

        $benefactorIds = Benefactor::query()
            ->where('name', 'like', '%BHCPF%')
            ->pluck('id');

        $fundingTypeIds = FundingType::query()
            ->where(function (Builder $query): void {
                $query->where('name', 'like', '%Basic Healthcare Provision Fund%')
                    ->orWhere('name', 'like', '%BHCPF%');
            })
            ->pluck('id');

        return Enrollee::query()
            ->when($programmeIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('insurance_programme_id', $programmeIds))
            ->when($benefactorIds->isNotEmpty() || $fundingTypeIds->isNotEmpty(), function (Builder $query) use ($benefactorIds, $fundingTypeIds): void {
                $query->where(function (Builder $nested) use ($benefactorIds, $fundingTypeIds): void {
                    if ($benefactorIds->isNotEmpty()) {
                        $nested->orWhereIn('benefactor_id', $benefactorIds);
                    }
                    if ($fundingTypeIds->isNotEmpty()) {
                        $nested->orWhereIn('funding_type_id', $fundingTypeIds);
                    }
                });
            })
            ->whereBetween(DB::raw("DATE(COALESCE(enrollment_date, created_at))"), [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ]);
    }

    private function buildDailyRows(Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $rows = $this->campaignCaptureQuery($dateFrom, $dateTo)
            ->selectRaw("DATE(COALESCE(enrollment_date, created_at)) as capture_date")
            ->selectRaw('COUNT(*) as captured_count')
            ->groupBy('capture_date')
            ->orderBy('capture_date')
            ->get()
            ->keyBy('capture_date');

        $items = collect();
        $cursor = $dateFrom->copy();
        $cumulative = 0;

        while ($cursor->lte($dateTo)) {
            $dateKey = $cursor->toDateString();
            $captured = (int) data_get($rows, "{$dateKey}.captured_count", 0);
            $cumulative += $captured;

            $items->push([
                'date' => $dateKey,
                'date_label' => $cursor->format('d M'),
                'captured' => $captured,
                'cumulative' => $cumulative,
            ]);

            $cursor->addDay();
        }

        return $items;
    }

    private function demographicBreakdown(Carbon $dateFrom, Carbon $dateTo, Collection $targets): array
    {
        $baseQuery = $this->campaignCaptureQuery($dateFrom, $dateTo);

        $plwdCaptured = (clone $baseQuery)
            ->whereNotNull('disability')
            ->whereRaw("LOWER(TRIM(disability)) NOT IN ('', 'none', 'not stated')")
            ->count();

        $under5Captured = (clone $baseQuery)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '>', now()->subYears(5)->toDateString())
            ->count();

        $femaleReproductiveCaptured = (clone $baseQuery)
            ->where('sex', 2)
            ->whereNotNull('date_of_birth')
            ->whereBetween('date_of_birth', [
                now()->subYears(45)->toDateString(),
                now()->subYears(15)->toDateString(),
            ])
            ->count();

        $elderlyCaptured = (clone $baseQuery)
            ->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', now()->subYears(85)->toDateString())
            ->count();

        $othersCaptured = (clone $baseQuery)
            ->whereHas('vulnerableGroup', fn (Builder $query) => $query->where('code', 'others'))
            ->count();

        return [
            [
                'label' => 'PLWD',
                'captured' => (int) $plwdCaptured,
                'target' => (int) $targets->sum('plwd_target'),
            ],
            [
                'label' => 'Children <5',
                'captured' => (int) $under5Captured,
                'target' => (int) $targets->sum('under_5_target'),
            ],
            [
                'label' => 'Female Reproductive',
                'captured' => (int) $femaleReproductiveCaptured,
                'target' => (int) $targets->sum('female_reproductive_target'),
            ],
            [
                'label' => 'Elderly',
                'captured' => (int) $elderlyCaptured,
                'target' => (int) $targets->sum('elderly_target'),
            ],
            [
                'label' => 'Others',
                'captured' => (int) $othersCaptured,
                'target' => (int) $targets->sum('others_target'),
            ],
        ];
    }

    /**
     * @return array{label: string, tone: string, color: string}
     */
    private function progressStatus(float $progress): array
    {
        return match (true) {
            $progress >= 100 => ['label' => 'Completed', 'tone' => 'info', 'color' => '#2563eb'],
            $progress >= 80 => ['label' => 'On Track', 'tone' => 'success', 'color' => '#15803d'],
            $progress >= 50 => ['label' => 'In Progress', 'tone' => 'warning', 'color' => '#f59e0b'],
            $progress >= 25 => ['label' => 'Needs Support', 'tone' => 'warning', 'color' => '#ea580c'],
            default => ['label' => 'Needs Push', 'tone' => 'danger', 'color' => '#dc2626'],
        };
    }
}
