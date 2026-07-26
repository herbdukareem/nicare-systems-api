<?php

namespace App\Console\Commands;

use App\Models\Capitation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairLegacyCapitationPeriodsCommand extends Command
{
    protected $signature = 'legacy:repair-capitation-periods {--dry-run : Preview changes without saving them}';

    protected $description = 'Repair imported legacy capitation periods whose capitation_month does not match the actual cutoff month.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $periods = Capitation::query()
            ->where('metadata->source_table', 'capitation_grouping')
            ->get();

        $checked = 0;
        $repaired = 0;
        $skipped = 0;

        $this->info(($dryRun ? 'Previewing' : 'Repairing') . " {$periods->count()} imported legacy capitation period(s).");

        DB::transaction(function () use ($periods, $dryRun, &$checked, &$repaired, &$skipped): void {
            foreach ($periods as $period) {
                $checked++;

                $cutoff = $this->resolveCutoffDate($period);
                if (!$cutoff) {
                    $skipped++;
                    continue;
                }

                $expectedMonth = (int) $cutoff->month;
                $expectedYear = (int) $cutoff->year;
                $expectedStart = $cutoff->copy()->setDate($expectedYear, $expectedMonth, 20)->toDateString();

                $currentMonth = (int) ($period->capitation_month ?? 0);
                $currentYear = (int) ($period->year ?? 0);
                $currentStart = $period->period_start ? Carbon::parse($period->period_start)->toDateString() : null;

                if ($currentMonth === $expectedMonth && $currentYear === $expectedYear && $currentStart === $expectedStart) {
                    continue;
                }

                $repaired++;

                if ($dryRun) {
                    $this->line(sprintf(
                        'Would repair capitation #%d [%s]: month %d -> %d, year %d -> %d, period_start %s -> %s',
                        $period->id,
                        $period->name,
                        $currentMonth,
                        $expectedMonth,
                        $currentYear,
                        $expectedYear,
                        $currentStart ?? 'NULL',
                        $expectedStart
                    ));
                    continue;
                }

                $period->forceFill([
                    'capitated_month' => $expectedMonth,
                    'capitation_month' => $expectedMonth,
                    'year' => $expectedYear,
                    'period_start' => $expectedStart,
                ])->save();
            }
        }, 3);

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['checked', $checked],
                ['repaired', $repaired],
                ['skipped (missing cutoff)', $skipped],
            ]
        );

        return self::SUCCESS;
    }

    private function resolveCutoffDate(Capitation $period): ?Carbon
    {
        $cutoff = data_get($period->metadata, 'legacy_enroled_on_before_date');

        if (is_string($cutoff) && trim($cutoff) !== '') {
            return Carbon::parse($cutoff);
        }

        if ($period->period_start) {
            return Carbon::parse($period->period_start);
        }

        return null;
    }
}
