<?php

namespace App\Console\Commands;

use App\Models\Capitation;
use App\Services\CapitationService;
use Illuminate\Console\Command;

class BackfillCapitationEnrolleeSnapshotsCommand extends Command
{
    protected $signature = 'capitation:backfill-enrollee-snapshots
        {--period-id=* : One or more capitation period IDs to backfill}
        {--year= : Backfill only periods in a specific capitation year}
        {--funding-type-id= : Limit the backfill to a specific funding type}
        {--facility-id= : Limit the backfill to a specific facility}
        {--all : Process every generated capitation period}
        {--chunk=25 : Number of capitation periods to process per batch}
        {--dry-run : Preview what would be backfilled without writing rows}';

    protected $description = 'Backfill missing capitation enrollee snapshot rows for generated capitation periods.';

    public function handle(CapitationService $service): int
    {
        $periodIds = collect((array) $this->option('period-id'))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();
        $year = $this->option('year');
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(1, (int) ($this->option('chunk') ?: 25));

        if (!$this->option('all') && $periodIds->isEmpty() && $year === null) {
            $this->error('Choose at least one scope: --period-id=ID, --year=YYYY, or --all.');
            $this->line('Example dry-run: php artisan capitation:backfill-enrollee-snapshots --period-id=62 --facility-id=299 --funding-type-id=1 --dry-run');

            return self::FAILURE;
        }

        $filters = array_filter([
            'funding_type_id' => $this->option('funding-type-id') !== null ? (int) $this->option('funding-type-id') : null,
            'facility_id' => $this->option('facility-id') !== null ? (int) $this->option('facility-id') : null,
        ], static fn ($value) => $value !== null && $value > 0);

        $query = Capitation::query()
            ->whereHas('capitationDetails')
            ->when($periodIds->isNotEmpty(), fn ($builder) => $builder->whereIn('id', $periodIds->all()))
            ->when($year !== null && $year !== '', fn ($builder) => $builder->where('year', (int) $year))
            ->orderBy('id');

        $matchedPeriods = (clone $query)->count();
        if ($matchedPeriods === 0) {
            $this->warn('No capitation periods matched the selected scope.');
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Running %s capitation enrollee snapshot backfill for %d period(s).',
            $dryRun ? 'dry-run' : 'write',
            $matchedPeriods
        ));

        if ($filters !== []) {
            $this->line(sprintf(
                'Detail filters: funding_type_id=%s, facility_id=%s',
                $filters['funding_type_id'] ?? 'any',
                $filters['facility_id'] ?? 'any'
            ));
        }

        $totals = [
            'periods_scanned' => 0,
            'periods_with_missing' => 0,
            'details_in_scope' => 0,
            'missing_details' => 0,
            'expected_rows' => 0,
            'existing_rows' => 0,
            'stored_rows' => 0,
        ];

        $query->chunkById($chunkSize, function ($periods) use ($service, $filters, $dryRun, &$totals): void {
            foreach ($periods as $period) {
                $totals['periods_scanned']++;

                $report = $dryRun
                    ? $service->inspectEnrolleeSnapshotBackfill($period, $filters)
                    : $service->backfillEnrolleeSnapshots($period, $filters);

                $totals['details_in_scope'] += (int) $report['detail_count_in_scope'];
                $totals['missing_details'] += (int) $report['missing_detail_count'];
                $totals['expected_rows'] += (int) $report['expected_snapshot_row_count'];
                $totals['existing_rows'] += (int) $report['existing_snapshot_row_count'];
                $totals['stored_rows'] += (int) ($report['stored_snapshot_row_count'] ?? 0);

                if ((int) $report['missing_detail_count'] > 0) {
                    $totals['periods_with_missing']++;
                }

                $this->line(sprintf(
                    '#%d %s [%s] details_in_scope=%d missing_details=%d expected_rows=%d existing_rows=%d%s',
                    $period->id,
                    $period->name,
                    $report['is_legacy_import'] ? 'legacy' : 'current',
                    (int) $report['detail_count_in_scope'],
                    (int) $report['missing_detail_count'],
                    (int) $report['expected_snapshot_row_count'],
                    (int) $report['existing_snapshot_row_count'],
                    $dryRun ? '' : sprintf(' stored_rows=%d', (int) ($report['stored_snapshot_row_count'] ?? 0))
                ));
            }
        }, 'id');

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $dryRun ? 'dry-run' : 'write'],
                ['Periods scanned', (string) $totals['periods_scanned']],
                ['Periods with missing snapshots', (string) $totals['periods_with_missing']],
                ['Details in scope', (string) $totals['details_in_scope']],
                ['Missing details', (string) $totals['missing_details']],
                ['Expected rows for missing details', (string) $totals['expected_rows']],
                ['Existing rows before backfill', (string) $totals['existing_rows']],
                ['Stored rows after backfill', $dryRun ? 'n/a' : (string) $totals['stored_rows']],
            ]
        );

        return self::SUCCESS;
    }
}
