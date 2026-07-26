<?php

namespace App\Console\Commands;

use App\Models\Enrollee;
use App\Models\LegacyMigrationLog;
use App\Services\Legacy\LegacyEnrolleeMapper;
use App\Services\Legacy\LegacyEnrolleeMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairLegacyEnrolleeCollisionsCommand extends Command
{
    protected $signature = 'legacy:repair-enrollee-collisions {--dry-run : Preview repairs without writing changes}';

    protected $description = 'Repair legacy enrollee collisions where rows from different legacy source tables were linked to the same enrollee.';

    public function handle(
        LegacyEnrolleeMigrationService $migrationService,
        LegacyEnrolleeMapper $mapper
    ): int {
        if (!Schema::hasColumn('enrollees', 'legacy_source_table')) {
            $this->error('The enrollees.legacy_source_table column is missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $collisionIds = LegacyMigrationLog::query()
            ->whereNotNull('new_enrollee_id')
            ->groupBy('new_enrollee_id')
            ->havingRaw('COUNT(DISTINCT source_table) > 1')
            ->pluck('new_enrollee_id');

        $stats = [
            'collision_groups' => $collisionIds->count(),
            'keepers_synced' => 0,
            'created_enrollees' => 0,
            'skipped_logs' => 0,
            'failed' => 0,
        ];

        $this->info(($dryRun ? 'Previewing' : 'Repairing') . " {$stats['collision_groups']} collided legacy enrollee group(s).");

        foreach ($collisionIds as $enrolleeId) {
            $enrollee = Enrollee::find($enrolleeId);
            $logs = LegacyMigrationLog::where('new_enrollee_id', $enrolleeId)
                ->orderBy('id')
                ->get();

            if (!$enrollee || $logs->count() < 2) {
                $stats['skipped_logs'] += (int) $logs->count();
                continue;
            }

            $keeper = $this->determineKeeperLog($enrollee, $logs, $mapper);
            $others = $logs->reject(fn (LegacyMigrationLog $log) => $log->id === $keeper->id)->values();

            if ($dryRun) {
                $stats['keepers_synced']++;
                $stats['created_enrollees'] += $others->count();
                $this->line(sprintf(
                    'Would keep enrollee #%d for [%s:%d] and recreate %d additional enrollee(s).',
                    $enrollee->id,
                    $keeper->source_table,
                    $keeper->legacy_id,
                    $others->count()
                ));
                continue;
            }

            try {
                DB::transaction(function () use ($enrollee, $keeper, $others, $migrationService, &$stats): void {
                    $enrollee->forceFill([
                        'legacy_source_table' => $keeper->source_table,
                        'legacy_id' => $keeper->legacy_id,
                        'legacy_enrollee_id' => $keeper->legacy_enrolment_number,
                    ])->save();
                    $stats['keepers_synced']++;

                    foreach ($others as $log) {
                        $payload = $log->legacy_payload;
                        if (!is_array($payload) || empty($payload['id'])) {
                            $stats['skipped_logs']++;
                            continue;
                        }

                        $result = $migrationService->migrate((object) $payload, $log->source_table, false, true);
                        if (($result['status'] ?? null) === 'migrated') {
                            $stats['created_enrollees']++;
                        }
                    }
                }, 5);
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->error(sprintf(
                    'Failed repairing enrollee #%d: %s',
                    $enrollee->id,
                    $e->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['collision groups', $stats['collision_groups']],
                ['keepers synced', $stats['keepers_synced']],
                ['recreated enrollees', $stats['created_enrollees']],
                ['skipped logs', $stats['skipped_logs']],
                ['failed groups', $stats['failed']],
            ]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function determineKeeperLog(
        Enrollee $enrollee,
        Collection $logs,
        LegacyEnrolleeMapper $mapper
    ): LegacyMigrationLog {
        $directMatch = $logs->first(function (LegacyMigrationLog $log) use ($enrollee): bool {
            return $log->legacy_enrolment_number !== null
                && $log->legacy_enrolment_number === $enrollee->enrollee_id;
        });

        if ($directMatch) {
            return $directMatch;
        }

        $scored = $logs->map(function (LegacyMigrationLog $log) use ($enrollee, $mapper): array {
            $payload = is_array($log->legacy_payload) ? $log->legacy_payload : [];
            $mapped = $payload !== [] ? $mapper->map((object) $payload, $log->source_table) : null;
            $candidate = $mapped['enrollee'] ?? [];
            $score = 0;

            if (($candidate['enrollee_id'] ?? null) === $enrollee->enrollee_id) {
                $score += 8;
            }
            if (($candidate['first_name'] ?? null) === $enrollee->first_name) {
                $score += 3;
            }
            if (($candidate['last_name'] ?? null) === $enrollee->last_name) {
                $score += 3;
            }
            if (($candidate['phone'] ?? null) === $enrollee->phone) {
                $score += 2;
            }
            if (($candidate['nin'] ?? null) === $enrollee->nin) {
                $score += 2;
            }
            if (($candidate['funding_type_id'] ?? null) === $enrollee->funding_type_id) {
                $score += 2;
            }
            if (($candidate['benefactor_id'] ?? null) === $enrollee->benefactor_id) {
                $score += 1;
            }
            if (($candidate['insurance_programme_id'] ?? null) === $enrollee->insurance_programme_id) {
                $score += 1;
            }

            return ['log' => $log, 'score' => $score];
        })->sortByDesc(fn (array $entry) => sprintf('%08d-%08d', $entry['score'], $entry['log']->id))->values();

        return $scored->first()['log'] ?? $logs->sortByDesc('id')->first();
    }
}
