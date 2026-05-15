<?php

namespace App\Services;

use App\Jobs\MobileSyncProcessJob;
use App\Models\MobileSyncRecord;
use Illuminate\Support\Str;

class MobileSyncService
{
    /**
     * Create mobile sync records and dispatch processing jobs.
     *
     * @param  array  $records  Array of raw enrollee payloads
     * @param  int    $officerId
     * @param  string $deviceId
     * @param  string $ip
     * @return string  The sync_batch_id
     */
    public function push(array $records, int $officerId, string $deviceId, string $ip): string
    {
        $batchId = Str::uuid()->toString();

        foreach ($records as $payload) {
            $record = MobileSyncRecord::create([
                'sync_batch_id'  => $batchId,
                'device_id'      => $deviceId,
                'officer_user_id'=> $officerId,
                'payload'        => $payload,
                'status'         => 'pending',
                'ip_address'     => $ip,
            ]);

            MobileSyncProcessJob::dispatch($record);
        }

        return $batchId;
    }

    /**
     * Return aggregated status for a sync batch.
     */
    public function getStatus(string $batchId): array
    {
        $records = MobileSyncRecord::where('sync_batch_id', $batchId)->get();

        $counts = $records->groupBy('status')->map->count();

        return [
            'sync_batch_id' => $batchId,
            'total'         => $records->count(),
            'counts'        => $counts,
            'records'       => $records,
        ];
    }

    /**
     * Return failed records for the authenticated officer.
     */
    public function getFailedForOfficer(int $officerId)
    {
        return MobileSyncRecord::where('officer_user_id', $officerId)
            ->where('status', 'failed')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Reset a failed record with corrected payload and re-dispatch.
     */
    public function retry(MobileSyncRecord $record, array $newPayload): MobileSyncRecord
    {
        $record->update([
            'payload'        => $newPayload,
            'status'         => 'pending',
            'failure_reason' => null,
        ]);

        MobileSyncProcessJob::dispatch($record);

        return $record->fresh();
    }
}
