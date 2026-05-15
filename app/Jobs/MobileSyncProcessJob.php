<?php

namespace App\Jobs;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\MobileSyncRecord;
use App\Services\EnrolleeDuplicateDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Validator;

class MobileSyncProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly MobileSyncRecord $record)
    {
    }

    public function handle(EnrolleeDuplicateDetectionService $duplicateService): void
    {
        try {
            // 1. Mark as processing
            $this->record->update(['status' => 'processing']);

            $payload = $this->record->payload;

            // 2. Validate required fields
            $validator = Validator::make($payload, [
                'first_name'    => ['required', 'string'],
                'last_name'     => ['required', 'string'],
                'date_of_birth' => ['required', 'date'],
                'gender'        => ['required'],
                'facility_id'   => ['required', 'integer'],
            ]);

            if ($validator->fails()) {
                $this->record->update([
                    'status'         => 'failed',
                    'failure_reason' => implode('; ', $validator->errors()->all()),
                ]);

                $this->writeAudit('mobile_sync_failed', 'Validation failed: ' . implode('; ', $validator->errors()->all()));
                return;
            }

            // 3. Duplicate detection
            $dupResult = $duplicateService->check($payload);

            if ($dupResult['is_duplicate']) {
                $this->record->update([
                    'status'                   => 'duplicate',
                    'duplicate_of_enrollee_id' => $dupResult['matched_enrollee_id'],
                ]);

                $this->writeAudit(
                    'mobile_sync_duplicate',
                    "Duplicate detected ({$dupResult['match_type']}). Matched enrollee: {$dupResult['matched_enrollee_id']}"
                );
                return;
            }

            // 4. Create enrollee
            $enrollee = Enrollee::create([
                'first_name'           => $payload['first_name'],
                'last_name'            => $payload['last_name'],
                'middle_name'          => $payload['middle_name'] ?? null,
                'date_of_birth'        => $payload['date_of_birth'],
                'sex'                  => $payload['gender'],
                'facility_id'          => $payload['facility_id'],
                'nin'                  => $payload['nin'] ?? null,
                'phone'                => $payload['phone'] ?? null,
                'lga_id'               => $payload['lga_id'] ?? null,
                'ward_id'              => $payload['ward_id'] ?? null,
                'enrollee_type_id'     => $payload['enrollee_type_id'] ?? null,
                'funding_type_id'      => $payload['funding_type_id'] ?? null,
                'capitation_start_date'=> now(),
                'created_by'           => $this->record->officer_user_id,
                'status'               => 'active',
            ]);

            $this->record->update([
                'status'      => 'synced',
                'enrollee_id' => $enrollee->id,
                'synced_at'   => now(),
            ]);

            $this->writeAudit('mobile_sync_synced', "Enrollee created: {$enrollee->id} from mobile sync batch {$this->record->sync_batch_id}");

        } catch (\Throwable $e) {
            $this->record->update([
                'status'         => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    private function writeAudit(string $action, string $description): void
    {
        AuditTrail::create([
            'auditable_type' => MobileSyncRecord::class,
            'auditable_id'   => $this->record->id,
            'action'         => $action,
            'description'    => $description,
            'user_id'        => $this->record->officer_user_id,
        ]);
    }
}
