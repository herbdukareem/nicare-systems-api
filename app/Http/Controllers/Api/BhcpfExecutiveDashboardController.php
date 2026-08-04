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

        $overallTarget = (int) round((float) $targets->sum('proposed_enrolments'));
        $totalEnrolled = (int) $captureCounts->sum();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $enrolledToday = now()->lt($campaignStart)
            ? 0
            : (int) $this->campaignCaptureQuery($todayStart, $todayEnd)->count();

        $lgaRows = $targets
            ->map(function (BhcpfExecutiveTarget $target) use ($captureCounts): array {
                $captured = (int) ($captureCounts[$target->lga_id] ?? 0);
                $campaignTarget = (int) $target->proposed_enrolments;
                $progress = $campaignTarget > 0
                    ? round(($captured / $campaignTarget) * 100, 1)
                    : 0.0;
                $status = $this->progressStatus($progress);

                return [
                    'lga_id' => $target->lga_id,
                    'lga_name' => $target->lga?->name ?? 'Unknown LGA',
                    'ward_count' => (int) $target->ward_count,
                    'current_enrollee_count' => (int) $target->current_enrollee_count,
                    'poverty_index' => (int) $target->poverty_index,
                    'proposed_enrolments' => (int) $target->proposed_enrolments,
                    'target' => $campaignTarget,
                    'captured' => $captured,
                    'remaining' => max($campaignTarget - $captured, 0),
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
        $demographics = $this->demographicBreakdowns($dateFrom, $dateTo, $targets, collect($lgaRows));

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
                    'approved' => $dailyRows->pluck('approved')->all(),
                ],
                'demographics' => [
                    'overall' => $demographics['overall'],
                    'by_lga' => $demographics['by_lga'],
                ],
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
            ->whereBetween(DB::raw("DATE(COALESCE(enrollees.enrollment_date, enrollees.created_at))"), [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ]);
    }

    private function buildDailyRows(Carbon $dateFrom, Carbon $dateTo): Collection
    {
        $rows = $this->campaignCaptureQuery($dateFrom, $dateTo)
            ->selectRaw("DATE(COALESCE(enrollees.enrollment_date, enrollees.created_at)) as capture_date")
            ->selectRaw('COUNT(*) as captured_count')
            ->selectRaw('SUM(CASE WHEN enrollees.status = ? THEN 1 ELSE 0 END) as approved_count', [Enrollee::STATUS_ACTIVE])
            ->groupBy('capture_date')
            ->orderBy('capture_date')
            ->get()
            ->keyBy('capture_date');

        $items = collect();
        $cursor = $dateFrom->copy();

        while ($cursor->lte($dateTo)) {
            $dateKey = $cursor->toDateString();
            $captured = (int) data_get($rows, "{$dateKey}.captured_count", 0);
            $approved = (int) data_get($rows, "{$dateKey}.approved_count", 0);

            $items->push([
                'date' => $dateKey,
                'date_label' => $cursor->format('d M'),
                'captured' => $captured,
                'approved' => $approved,
            ]);

            $cursor->addDay();
        }

        return $items;
    }

    private function demographicBreakdowns(
        Carbon $dateFrom,
        Carbon $dateTo,
        Collection $targets,
        Collection $lgaRows
    ): array
    {
        $referenceDate = now()->startOfDay();
        $records = $this->campaignCaptureQuery($dateFrom, $dateTo)
            ->leftJoin('vulnerable_groups as vg', 'vg.id', '=', 'enrollees.vulnerable_group_id')
            ->get([
                'enrollees.lga_id',
                'enrollees.date_of_birth',
                'enrollees.sex',
                'enrollees.disability',
                'vg.code as vulnerable_group_code',
            ]);

        $overallCounts = $this->emptyDemographicCounts();
        $countsByLga = [];

        foreach ($records as $record) {
            $lgaId = $record->lga_id ? (int) $record->lga_id : null;

            if ($this->hasPlwdStatus($record->disability)) {
                $overallCounts['plwd']++;
                if ($lgaId !== null) {
                    $countsByLga[$lgaId] = $countsByLga[$lgaId] ?? $this->emptyDemographicCounts();
                    $countsByLga[$lgaId]['plwd']++;
                }
            }

            if ($this->isUnderFive($record->date_of_birth, $referenceDate)) {
                $overallCounts['under_5']++;
                if ($lgaId !== null) {
                    $countsByLga[$lgaId] = $countsByLga[$lgaId] ?? $this->emptyDemographicCounts();
                    $countsByLga[$lgaId]['under_5']++;
                }
            }

            if ($this->isFemaleReproductive($record->sex, $record->date_of_birth, $referenceDate)) {
                $overallCounts['female_reproductive']++;
                if ($lgaId !== null) {
                    $countsByLga[$lgaId] = $countsByLga[$lgaId] ?? $this->emptyDemographicCounts();
                    $countsByLga[$lgaId]['female_reproductive']++;
                }
            }

            if ($this->isElderly($record->date_of_birth, $referenceDate)) {
                $overallCounts['elderly']++;
                if ($lgaId !== null) {
                    $countsByLga[$lgaId] = $countsByLga[$lgaId] ?? $this->emptyDemographicCounts();
                    $countsByLga[$lgaId]['elderly']++;
                }
            }

            if (($record->vulnerable_group_code ?? null) === 'others') {
                $overallCounts['others']++;
                if ($lgaId !== null) {
                    $countsByLga[$lgaId] = $countsByLga[$lgaId] ?? $this->emptyDemographicCounts();
                    $countsByLga[$lgaId]['others']++;
                }
            }
        }

        $overallTargets = [
            'plwd_target' => (int) $targets->sum('plwd_target'),
            'under_5_target' => (int) $targets->sum('under_5_target'),
            'female_reproductive_target' => (int) $targets->sum('female_reproductive_target'),
            'elderly_target' => (int) $targets->sum('elderly_target'),
            'others_target' => (int) $targets->sum('others_target'),
        ];

        $byLga = $lgaRows
            ->mapWithKeys(function (array $lgaRow) use ($countsByLga): array {
                $lgaId = (int) $lgaRow['lga_id'];

                return [
                    $lgaId => [
                        'lga_id' => $lgaId,
                        'lga_name' => $lgaRow['lga_name'],
                        'rows' => $this->formatDemographicRows(
                            $countsByLga[$lgaId] ?? $this->emptyDemographicCounts(),
                            [
                                'plwd_target' => (int) $lgaRow['plwd_target'],
                                'under_5_target' => (int) $lgaRow['under_5_target'],
                                'female_reproductive_target' => (int) $lgaRow['female_reproductive_target'],
                                'elderly_target' => (int) $lgaRow['elderly_target'],
                                'others_target' => (int) $lgaRow['others_target'],
                            ]
                        ),
                    ],
                ];
            })
            ->all();

        return [
            'overall' => $this->formatDemographicRows($overallCounts, $overallTargets),
            'by_lga' => $byLga,
        ];
    }

    /**
     * @return array{plwd:int,under_5:int,female_reproductive:int,elderly:int,others:int}
     */
    private function emptyDemographicCounts(): array
    {
        return [
            'plwd' => 0,
            'under_5' => 0,
            'female_reproductive' => 0,
            'elderly' => 0,
            'others' => 0,
        ];
    }

    private function hasPlwdStatus(?string $disability): bool
    {
        if ($disability === null) {
            return false;
        }

        $normalized = strtolower(trim($disability));

        return $normalized !== '' && !in_array($normalized, ['none', 'not stated'], true);
    }

    private function isUnderFive($dateOfBirth, Carbon $referenceDate): bool
    {
        if (!$dateOfBirth) {
            return false;
        }

        return Carbon::parse($dateOfBirth)->gt($referenceDate->copy()->subYears(5));
    }

    private function isFemaleReproductive($sex, $dateOfBirth, Carbon $referenceDate): bool
    {
        if ((int) $sex !== 2 || !$dateOfBirth) {
            return false;
        }

        $dob = Carbon::parse($dateOfBirth);

        return $dob->between(
            $referenceDate->copy()->subYears(45),
            $referenceDate->copy()->subYears(15)
        );
    }

    private function isElderly($dateOfBirth, Carbon $referenceDate): bool
    {
        if (!$dateOfBirth) {
            return false;
        }

        return Carbon::parse($dateOfBirth)->lte($referenceDate->copy()->subYears(85));
    }

    private function formatDemographicRows(array $capturedCounts, array $targetCounts): array
    {
        return [
            [
                'label' => 'PLWD',
                'captured' => (int) ($capturedCounts['plwd'] ?? 0),
                'target' => (int) ($targetCounts['plwd_target'] ?? 0),
            ],
            [
                'label' => 'Children <5',
                'captured' => (int) ($capturedCounts['under_5'] ?? 0),
                'target' => (int) ($targetCounts['under_5_target'] ?? 0),
            ],
            [
                'label' => 'Female Reproductive',
                'captured' => (int) ($capturedCounts['female_reproductive'] ?? 0),
                'target' => (int) ($targetCounts['female_reproductive_target'] ?? 0),
            ],
            [
                'label' => 'Elderly',
                'captured' => (int) ($capturedCounts['elderly'] ?? 0),
                'target' => (int) ($targetCounts['elderly_target'] ?? 0),
            ],
            [
                'label' => 'Others',
                'captured' => (int) ($capturedCounts['others'] ?? 0),
                'target' => (int) ($targetCounts['others_target'] ?? 0),
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
