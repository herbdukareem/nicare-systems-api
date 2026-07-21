<?php

namespace App\Services;

use App\Models\Configuration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnrolleeIdGenerator
{
    public const PREFIX = 'NGSCHA';
    private const CONFIG_KEY = 'ENROLLEE_ID_LAST_SERIAL';
    private const MIN_SERIAL_LENGTH = 6;

    public function generate(): string
    {
        $this->ensureSequenceRowExists();

        $nextSerial = DB::transaction(function (): int {
            $sequence = Configuration::query()
                ->where('variable_name', self::CONFIG_KEY)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException('Enrollee ID sequence row could not be locked.');
            }

            $currentSerial = max((int) $sequence->variable_value, $this->currentMaxSerial());
            $nextSerial = $currentSerial + 1;

            $sequence->forceFill([
                'variable_value' => (string) $nextSerial,
                'description' => 'Last issued serial for unified non-legacy enrollee IDs.',
            ])->save();

            return $nextSerial;
        }, 5);

        return $this->format($nextSerial);
    }

    public function format(int $serial): string
    {
        return self::PREFIX . str_pad((string) $serial, self::MIN_SERIAL_LENGTH, '0', STR_PAD_LEFT);
    }

    private function ensureSequenceRowExists(): void
    {
        try {
            Configuration::query()->firstOrCreate(
                ['variable_name' => self::CONFIG_KEY],
                [
                    'variable_value' => (string) $this->currentMaxSerial(),
                    'description' => 'Last issued serial for unified non-legacy enrollee IDs.',
                ]
            );
        } catch (QueryException $exception) {
            if (!$this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }
        }
    }

    private function currentMaxSerial(): int
    {
        $max = DB::table('enrollees')
            ->where('enrollee_id', 'like', self::PREFIX . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(enrollee_id, ? ) AS SIGNED)) as max_serial', [strlen(self::PREFIX) + 1])
            ->value('max_serial');

        return max(0, (int) $max);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');

        return $sqlState === '23000';
    }
}
