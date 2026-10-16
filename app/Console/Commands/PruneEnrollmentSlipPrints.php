<?php

namespace App\Console\Commands;

use App\Models\Enrollee;
use App\Models\EnrollmentSlipPrintBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneEnrollmentSlipPrints extends Command
{
    protected $signature = 'enrollment-slips:prune';

    protected $description = 'Release expired printing exercises and remove temporary PDFs while preserving generation history';

    public function handle(): int
    {
        EnrollmentSlipPrintBatch::where('status', 'preparing')->where('expires_at', '<=', now())
            ->eachById(function (EnrollmentSlipPrintBatch $candidate): void {
                DB::transaction(function () use ($candidate): void {
                    $batch = EnrollmentSlipPrintBatch::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                    if ($batch->status !== 'preparing' || $batch->expires_at->isFuture()) {
                        return;
                    }
                    Enrollee::query()->where('slip_reserved_batch_id', $batch->id)
                        ->update(['slip_reserved_batch_id' => null, 'slip_reserved_until' => null]);
                    $batch->update(['status' => 'expired']);
                });
            });

        EnrollmentSlipPrintBatch::where(function ($query): void {
            $query->where(fn ($q) => $q->where('status', 'completed')->where('completed_at', '<=', now()->subDays(7)))
                ->orWhere(fn ($q) => $q->whereIn('status', ['expired', 'cancelled'])->where('updated_at', '<=', now()->subDay()));
        })->whereNotNull('parts')->eachById(function (EnrollmentSlipPrintBatch $candidate): void {
            DB::transaction(function () use ($candidate): void {
                $batch = EnrollmentSlipPrintBatch::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                if (Storage::disk('local')->deleteDirectory('slip-printing/'.$batch->id)) {
                    $batch->update(['parts' => null]);
                }
            });
        });
        $this->info('Expired exercises released and temporary slip PDFs pruned. Generation history retained.');
        return self::SUCCESS;
    }
}
