<?php

namespace App\Jobs;

use App\Services\Legacy\LegacyEnrolleeMigrationService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLegacyEnrolleesJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 1800; // 30 minutes per chunk

    public function __construct(
        private readonly string $sourceTable,
        private readonly array $legacyIds,
        private readonly bool $dryRun = false,
    ) {
    }

    public function handle(LegacyEnrolleeMigrationService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = DB::connection('legacy_mysql')
            ->table($this->sourceTable)
            ->whereIn('id', $this->legacyIds)
            ->orderBy('id')
            ->get();

        $migrated = 0;
        $failed   = 0;

        foreach ($rows as $row) {
            try {
                $service->migrate($row, $this->sourceTable, $this->dryRun);
                $migrated++;
            } catch (\Throwable $e) {
                $failed++;
                if (!$this->dryRun) {
                    $service->logFailure($row, $this->sourceTable, $e);
                }
                Log::error("[ProcessLegacyEnrolleesJob] {$this->sourceTable}:{$row->id} — {$e->getMessage()}");
            }
        }

        Log::info("[ProcessLegacyEnrolleesJob] {$this->sourceTable} chunk done — migrated:{$migrated} failed:{$failed}");
    }
}
