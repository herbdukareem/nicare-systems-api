<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\EnrollmentSlipPrintBatch;
use App\Models\EnrollmentSlipPrintItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EnrollmentSlipPrintingService
{
    public const CHUNK_SIZE = 40;
    public const EXPIRY_MINUTES = 120;

    public function rules(): array
    {
        return [
            'identifier' => ['nullable', 'string', 'max:50'],
            'benefactor_id' => ['nullable', 'integer', 'exists:benefactors,id'],
            'facility_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'provider_id' => ['nullable', 'integer', 'exists:facilities,id'],
            'insurance_programme_id' => ['nullable', 'integer', 'exists:insurance_programmes,id'],
            'enrollee_category_id' => ['nullable', 'integer', 'exists:enrollee_categories,id'],
            'funding_type_id' => ['nullable', 'integer', 'exists:funding_types,id'],
            'enrollment_phase_id' => ['nullable', 'integer', 'exists:enrollment_phases,id'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'approval_status' => ['nullable', 'in:pending,approved,all'],
            'printing_status' => ['nullable', 'in:outstanding,generated,all'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'enrollee_ids' => ['nullable', 'array', 'min:1', 'max:1000'],
            'enrollee_ids.*' => ['required', 'integer', 'distinct'],
            'request_id' => ['nullable', 'uuid'],
        ];
    }

    public function query(array $filters): Builder
    {
        // Keep all enrollee access on the model so existing business scopes apply.
        $query = Enrollee::query();
        $identifier = trim((string) ($filters['identifier'] ?? ''));
        if ($identifier !== '') {
            $nin = preg_match('/^[\d\s-]+$/', $identifier) === 1 ? Enrollee::normalizeNin($identifier) : null;
            $query->where(function (Builder $q) use ($identifier, $nin): void {
                $q->where('enrollee_id', $identifier)->orWhere('legacy_enrollee_id', $identifier);
                if ($nin !== null) {
                    $q->orWhere('nin', $nin);
                }
            });
            return $query;
        }

        foreach (['benefactor_id', 'insurance_programme_id', 'enrollee_category_id', 'funding_type_id', 'enrollment_phase_id', 'status'] as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $query->where($column, $filters[$column]);
            }
        }
        if ($facilityId = ($filters['facility_id'] ?? $filters['provider_id'] ?? null)) {
            $query->where('facility_id', $facilityId);
        }
        $approval = $filters['approval_status'] ?? 'approved';
        if ($approval === 'approved') {
            $query->where('status', Enrollee::STATUS_ACTIVE)->whereNotNull('approval_date');
        } elseif ($approval === 'pending') {
            $query->where('status', Enrollee::STATUS_PENDING);
        }
        $printing = $filters['printing_status'] ?? 'outstanding';
        if ($printing === 'outstanding') {
            $query->whereNull('slip_first_generated_at');
            $this->available($query);
        } elseif ($printing === 'generated') {
            $query->whereNotNull('slip_first_generated_at');
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
        return $query;
    }

    private function available(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('slip_reserved_until')->orWhere('slip_reserved_until', '<=', now()));
    }

    public function prepare(array $filters, int $userId): EnrollmentSlipPrintBatch
    {
        return DB::transaction(function () use ($filters, $userId) {
            // Serialize repeated prepare requests by this user, including network retries.
            User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $requestId = $filters['request_id'] ?? (string) Str::uuid();
            $existing = EnrollmentSlipPrintBatch::where('user_id', $userId)->where('request_id', $requestId)->first();
            if ($existing) {
                $this->assertVisible($existing);
                return $existing;
            }
            $query = $this->query($filters);
            if (trim((string) ($filters['identifier'] ?? '')) !== '') {
                $matches = (clone $query)->limit(2)->pluck('id');
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['identifier' => 'More than one enrollee matches. Use the unique Enrollment Number.']);
                }
            }
            if (!empty($filters['enrollee_ids'])) {
                $query->whereKey($filters['enrollee_ids']);
            }
            $ids = $this->available($query)->orderBy('id')->lockForUpdate()->pluck('id');
            abort_if($ids->isEmpty(), 409, 'No available slips match these filters. Another printing exercise may already include them.');
            abort_if(!empty($filters['enrollee_ids']) && $ids->count() !== count($filters['enrollee_ids']), 409, 'Some selected slips are no longer available. Refresh the list and select again.');

            $batch = EnrollmentSlipPrintBatch::create([
                'id' => (string) Str::uuid(), 'user_id' => $userId, 'request_id' => $requestId,
                'status' => 'preparing', 'filters' => $filters, 'parts' => [],
                'enrollee_count' => $ids->count(), 'chunk_size' => self::CHUNK_SIZE,
                'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            ]);
            // These are the exact IDs in the exercise, rather than a moving query or max-ID boundary.
            foreach ($ids->chunk(500) as $chunk) {
                $rows = [];
                foreach ($chunk as $index => $id) {
                    $rows[] = ['batch_id' => $batch->id, 'enrollee_id' => $id, 'part' => intdiv($index, self::CHUNK_SIZE) + 1];
                }
                EnrollmentSlipPrintItem::insert($rows);
                Enrollee::query()->whereKey($chunk->all())->update(['slip_reserved_batch_id' => $batch->id, 'slip_reserved_until' => $batch->expires_at]);
            }
            return $batch;
        }, 3);
    }

    public function batch(string $id, int $userId, bool $lock = false): EnrollmentSlipPrintBatch
    {
        $query = EnrollmentSlipPrintBatch::where('user_id', $userId)->whereKey($id);
        $batch = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        $this->assertVisible($batch);
        return $batch;
    }

    private function assertVisible(EnrollmentSlipPrintBatch $batch): void
    {
        // Batch ownership alone must never bypass an enrollee model's access scopes.
        $visible = Enrollee::query()->whereIn('id', $batch->items()->select('enrollee_id'))->count();
        abort_unless($visible === $batch->enrollee_count, 403, 'Some enrolments in this batch are no longer accessible.');
    }

    public function manifest(EnrollmentSlipPrintBatch $batch): array
    {
        $status = $batch->status === 'preparing' && $batch->expires_at->isPast() ? 'expired' : $batch->status;
        return [
            'id' => $batch->id, 'token' => $batch->id, 'status' => $status,
            'enrollee_count' => $batch->enrollee_count, 'chunk_size' => $batch->chunk_size,
            'total_parts' => (int) ceil($batch->enrollee_count / $batch->chunk_size),
            'generated_parts' => $status === 'completed' ? (int) ceil($batch->enrollee_count / $batch->chunk_size) : count($batch->parts ?? []),
            'created_at' => $batch->created_at->toIso8601String(),
            'expires_at' => $batch->expires_at->toIso8601String(),
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'can_download' => $status === 'preparing' || ($status === 'completed' && $batch->completed_at?->greaterThan(now()->subDays(7))),
            'file_name' => 'enrollment_slips_'.$batch->id.'.zip',
            'filters' => $batch->filters,
        ];
    }

    private function assertUsable(EnrollmentSlipPrintBatch $batch): void
    {
        abort_if(in_array($batch->status, ['cancelled', 'expired'], true) || ($batch->status !== 'completed' && $batch->expires_at->isPast()), 410, 'This batch has expired or was cancelled. Its outstanding slips are available for a new exercise.');
    }

    public function part(string $id, int $userId, int $part, callable $render): array
    {
        return DB::transaction(function () use ($id, $userId, $part, $render) {
            $batch = $this->batch($id, $userId, true);
            $this->assertUsable($batch);
            $ids = $batch->items()->where('part', $part)->orderBy('enrollee_id')->pluck('enrollee_id');
            abort_if($ids->isEmpty(), 422, 'This PDF part does not exist.');
            $path = 'slip-printing/'.$batch->id.'/part_'.$part.'.pdf';
            $disk = Storage::disk('local');
            $parts = $batch->parts ?? [];
            if (isset($parts[$part]) && $disk->exists($path)) {
                $bytes = $disk->get($path);
            } else {
                abort_if($batch->status === 'completed', 410, 'The saved PDF has expired. Start a reprint to generate a new copy.');
                $enrollees = Enrollee::query()->whereKey($ids->all())->with([
                    'insuranceProgramme', 'enrolleeCategory', 'premiumPlan', 'benefitPackage',
                    'fundingType', 'vulnerableGroup', 'facility', 'lga', 'ward',
                ])->orderBy('id')->get();
                abort_unless($enrollees->count() === $ids->count(), 403);
                $bytes = $render($enrollees, $batch, $part);
                $this->assertUsable($batch);
                if (!$disk->put($path, $bytes)) {
                    throw new \RuntimeException('Could not save the generated PDF. Retry this batch.');
                }
                $parts[$part] = $path;
                $batch->update(['parts' => $parts]);
            }
            if (($batch->filters['auto_complete'] ?? false) && count($parts) === (int) ceil($batch->enrollee_count / $batch->chunk_size)) {
                $this->complete($batch->id, $userId);
            }
            return ['bytes' => $bytes, 'filename' => 'enrollment_slips_'.$batch->id.'_part_'.$part.'.pdf', 'count' => $ids->count(), 'cursor' => $ids->last(), 'total_parts' => (int) ceil($batch->enrollee_count / $batch->chunk_size)];
        });
    }

    public function complete(string $id, int $userId): EnrollmentSlipPrintBatch
    {
        return DB::transaction(function () use ($id, $userId) {
            $batch = $this->batch($id, $userId, true);
            if ($batch->status === 'completed') {
                return $batch; // A lost completion response must not count as a reprint.
            }
            $this->assertUsable($batch);
            $total = (int) ceil($batch->enrollee_count / $batch->chunk_size);
            for ($part = 1; $part <= $total; $part++) {
                abort_unless(isset($batch->parts[$part]) && Storage::disk('local')->exists($batch->parts[$part]), 409, 'All PDF parts must be prepared before completing this exercise.');
            }
            $ids = $batch->items()->orderBy('enrollee_id')->pluck('enrollee_id');
            $enrollees = Enrollee::query()->whereKey($ids->all())->orderBy('id')->lockForUpdate()->get([
                'id', 'slip_generation_count', 'slip_reserved_batch_id', 'slip_reserved_until',
            ]);
            abort_unless($enrollees->count() === $batch->enrollee_count, 403);
            foreach ($enrollees as $enrollee) {
                abort_unless($enrollee->slip_reserved_batch_id === $batch->id && $enrollee->slip_reserved_until?->isFuture(), 409, 'This batch no longer holds all its slips. Start a new printing exercise.');
            }
            $now = now();
            $audits = [];
            foreach ($enrollees as $enrollee) {
                $audits[] = [
                    'auditable_type' => Enrollee::class, 'auditable_id' => $enrollee->id,
                    'action' => $enrollee->slip_generation_count ? 'enrollment_slip_reprinted' : 'enrollment_slip_generated',
                    'description' => 'Enrollment slip generated for printing', 'user_id' => $userId,
                    'old_values' => json_encode(['generation_count' => $enrollee->slip_generation_count]),
                    'new_values' => json_encode(['batch_id' => $batch->id, 'generation_count' => $enrollee->slip_generation_count + 1]),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach ($ids->chunk(500) as $chunk) {
                Enrollee::query()->whereKey($chunk->all())->whereNull('slip_first_generated_at')->update(['slip_first_generated_at' => $now]);
                Enrollee::query()->whereKey($chunk->all())->update([
                    'slip_last_generated_at' => $now, 'slip_last_generated_by' => $userId,
                    'slip_last_batch_id' => $batch->id,
                    'slip_generation_count' => DB::raw('slip_generation_count + 1'),
                    'slip_reserved_batch_id' => null, 'slip_reserved_until' => null,
                ]);
            }
            foreach (array_chunk($audits, 500) as $chunk) {
                AuditTrail::insert($chunk);
            }
            $batch->update(['status' => 'completed', 'completed_at' => $now]);
            return $batch;
        }, 3);
    }

    public function cancel(string $id, int $userId): EnrollmentSlipPrintBatch
    {
        return DB::transaction(function () use ($id, $userId) {
            $batch = $this->batch($id, $userId, true);
            if ($batch->status === 'preparing') {
                Enrollee::query()->where('slip_reserved_batch_id', $batch->id)->update(['slip_reserved_batch_id' => null, 'slip_reserved_until' => null]);
                $batch->update(['status' => 'cancelled']);
            }
            return $batch;
        }, 3);
    }
}
