<?php

namespace App\Jobs;

use App\Models\EnrolleeImportBatch;
use App\Services\EnrolleeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEnrolleeImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 600; // 10 minutes for large files

    public function __construct(public readonly EnrolleeImportBatch $batch)
    {
    }

    public function handle(EnrolleeImportService $importService): void
    {
        try {
            $this->batch->update(['status' => 'processing']);
            $importService->processFile($this->batch);
        } catch (\Throwable $e) {
            $this->batch->update([
                'status' => 'failed',
                'errors' => [['message' => $e->getMessage()]],
            ]);
        }
    }
}
