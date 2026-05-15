<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileSyncRecord;
use App\Services\MobileSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileSyncController extends Controller
{
    public function __construct(private readonly MobileSyncService $service)
    {
    }

    /**
     * POST /api/mobile-sync/push
     * Accept an array of enrollee records (max 100), queue processing jobs.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'records'              => ['required', 'array', 'max:100'],
            'records.*.first_name' => ['required', 'string'],
            'records.*.last_name'  => ['required', 'string'],
            'device_id'            => ['required', 'string'],
        ]);

        $batchId = $this->service->push(
            records:   $request->input('records'),
            officerId: auth()->id(),
            deviceId:  $request->input('device_id'),
            ip:        $request->ip(),
        );

        return response()->json([
            'success'       => true,
            'message'       => 'Records queued for processing.',
            'sync_batch_id' => $batchId,
            'queued'        => count($request->input('records')),
        ], 202);
    }

    /**
     * GET /api/mobile-sync/status/{syncBatchId}
     */
    public function status(Request $request, string $syncBatchId): JsonResponse
    {
        $status = $this->service->getStatus($syncBatchId);

        return response()->json([
            'success' => true,
            'data'    => $status,
        ]);
    }

    /**
     * GET /api/mobile-sync/failed
     * Return all failed records for the authenticated officer.
     */
    public function failed(Request $request): JsonResponse
    {
        $records = $this->service->getFailedForOfficer(auth()->id());

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    /**
     * POST /api/mobile-sync/retry/{record}
     * Supply a corrected payload and re-queue.
     */
    public function retry(Request $request, MobileSyncRecord $record): JsonResponse
    {
        $request->validate([
            'payload' => ['required', 'array'],
        ]);

        if ($record->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Only failed records can be retried.',
            ], 422);
        }

        $updated = $this->service->retry($record, $request->input('payload'));

        return response()->json([
            'success' => true,
            'message' => 'Record queued for retry.',
            'data'    => $updated,
        ]);
    }
}
