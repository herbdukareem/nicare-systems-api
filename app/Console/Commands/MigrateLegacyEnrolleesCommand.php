<?php

namespace App\Console\Commands;

use App\Services\Legacy\LegacyEnrolleeMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyEnrolleesCommand extends Command
{
    protected $signature = 'legacy:migrate-enrollees
        {--source=all : all, informal, or formal}
        {--dry-run : Inspect and print without writing}
        {--chunk=500 : Number of legacy rows per chunk}
        {--from-id= : Start from a legacy id}
        {--limit= : Maximum rows to process}';

    protected $description = 'Safely migrate legacy informal/formal enrollees into enrollees, purchases, and coverage periods';

    public function handle(LegacyEnrolleeMigrationService $service): int
    {
        $source = strtolower((string) $this->option('source'));
        if (!in_array($source, ['all', 'informal', 'formal'], true)) {
            $this->error('--source must be one of: all, informal, formal');
            return self::FAILURE;
        }

        $chunk = max((int) $this->option('chunk'), 1);
        $fromId = $this->option('from-id') !== null ? (int) $this->option('from-id') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        $tables = match ($source) {
            'informal' => ['tbl_enrolee'],
            'formal' => ['tbl_enrolee_formal2'],
            default => ['tbl_enrolee', 'tbl_enrolee_formal'],
        };

        if (!$this->schemaReady($tables)) {
            return self::FAILURE;
        }

        $stats = [
            'processed' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'duplicate_matched' => 0,
            'missing_facility' => 0,
            'missing_lga_ward' => 0,
            'missing_funding_type' => 0,
        ];

        $this->info(($dryRun ? 'Dry-running' : 'Migrating') . ' legacy enrollees from: ' . implode(', ', $tables));

        foreach ($tables as $table) {
            $remaining = $limit;
            $lastId = $fromId ? $fromId - 1 : 0;

            while ($remaining === null || $remaining > 0) {
                $take = $remaining === null ? $chunk : min($chunk, $remaining);
                $rows = DB::connection('legacy_mysql')
                    ->table($table)
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit($take)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                foreach ($rows as $row) {
                    $lastId = (int) $row->id;
                    $stats['processed']++;

                    try {
                        $result = $service->migrate($row, $table, $dryRun);
                        $mapped = $result['mapped'] ?? [];
                        $flags = $mapped['flags'] ?? [];

                        $stats[$dryRun ? 'skipped' : 'migrated']++;
                        if ($result['duplicate_matched'] ?? false) {
                            $stats['duplicate_matched']++;
                        }
                        if ($flags['missing_facility'] ?? false) {
                            $stats['missing_facility']++;
                        }
                        if (($flags['missing_lga'] ?? false) || ($flags['missing_ward'] ?? false)) {
                            $stats['missing_lga_ward']++;
                        }
                        if ($flags['missing_funding_type'] ?? false) {
                            $stats['missing_funding_type']++;
                        }

                        $this->line(sprintf(
                            '[%s:%s] %s',
                            $table,
                            $row->id,
                            $result['message']
                        ));
                    } catch (\Throwable $e) {
                        $stats['failed']++;
                        if (!$dryRun) {
                            $service->logFailure($row, $table, $e);
                        }
                        $this->error("[{$table}:{$row->id}] failed: {$e->getMessage()}");
                    }
                }

                if ($remaining !== null) {
                    $remaining -= $rows->count();
                }
            }
        }

        $this->newLine();
        $this->info('Legacy enrollee migration summary');
        $this->table(
            ['Metric', 'Count'],
            [
                ['total processed', $stats['processed']],
                ['migrated', $stats['migrated']],
                ['skipped', $stats['skipped']],
                ['failed', $stats['failed']],
                ['duplicate matched', $stats['duplicate_matched']],
                ['missing facility', $stats['missing_facility']],
                ['missing LGA/Ward', $stats['missing_lga_ward']],
                ['missing funding type', $stats['missing_funding_type']],
            ]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function schemaReady(array $legacyTables): bool
    {
        $requiredNewTables = [
            'enrollees',
            'funding_types',
            'benefactors',
            'enrollment_phases',
            'insurance_programmes',
            'enrollee_categories',
            'premium_plans',
            'premium_purchases',
            'legacy_migration_logs',
        ];

        foreach ($requiredNewTables as $table) {
            if (!Schema::hasTable($table)) {
                $this->error("Required new-system table [{$table}] is missing. Run php artisan migrate before legacy:migrate-enrollees.");
                return false;
            }
        }

        foreach ($legacyTables as $table) {
            if (!Schema::connection('legacy_mysql')->hasTable($table)) {
                $this->error("Required legacy table [{$table}] is missing on the legacy_mysql connection.");
                return false;
            }
        }

        return true;
    }
}
