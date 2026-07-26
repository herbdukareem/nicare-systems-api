<?php

namespace App\Jobs;

use App\Services\EnrolleeDuplicateNinService;
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

    public int $tries = 3;
    public int $timeout = 1800;

    public function __construct(
        private readonly string $sourceTable,
        private readonly array $legacyIds,
        private readonly bool $dryRun = false,
    ) {
    }

    public function handle(
        LegacyEnrolleeMigrationService $service,
        EnrolleeDuplicateNinService $duplicateNinService
    ): void {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = DB::connection('legacy_mysql')
            ->table($this->sourceTable)
            ->whereIn('id', $this->legacyIds)
            ->orderBy('id')
            ->get();

        $migrated = 0;
        $failed = 0;
        $touchedNins = [];

        foreach ($rows as $row) {
            try {
                $result = $service->migrate($row, $this->sourceTable, $this->dryRun);
                $nin = $result['mapped']['enrollee']['nin'] ?? null;
                if (filled($nin)) {
                    $touchedNins[] = $nin;
                }
                $migrated++;
            } catch (\Throwable $e) {
                $failed++;
                if (!$this->dryRun) {
                    $service->logFailure($row, $this->sourceTable, $e);
                }
                Log::error("[ProcessLegacyEnrolleesJob] {$this->sourceTable}:{$row->id} - {$e->getMessage()}");
            }
        }

        if (!$this->dryRun && $touchedNins !== []) {
            $duplicateNinService->refreshForNins($touchedNins);
        }

        Log::info("[ProcessLegacyEnrolleesJob] {$this->sourceTable} chunk done - migrated:{$migrated} failed:{$failed}");
    }
}
