<?php

namespace App\Services;

use App\Models\Enrollee;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class EnrolleeDuplicateDetectionService
{
    /**
     * @var array<int, int>
     */
    private array $duplicateStatuses = [
        Enrollee::STATUS_PENDING,
        Enrollee::STATUS_ACTIVE,
        Enrollee::STATUS_REJECTED,
    ];

    /**
     * @template TReturn
     * @param  callable():TReturn  $callback
     * @return TReturn
     */
    public function withinSubmissionLock(array $payload, callable $callback, int $seconds = 15)
    {
        $lockKey = $this->submissionLockKey($payload);
        if ($lockKey === null) {
            return $callback();
        }

        try {
            /** @var Lock $lock */
            $lock = Cache::lock($lockKey, $seconds);
        } catch (\Throwable) {
            return $callback();
        }

        if (!$lock->get()) {
            throw new RuntimeException('Another enrollment submission for this person is already being processed. Please wait a moment and try again.');
        }

        try {
            return $callback();
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // Ignore lock release failures.
            }
        }
    }

    public function findRecentPendingMatch(array $payload, int $minutes = 5, ?int $ignoreEnrolleeId = null): ?Enrollee
    {
        return null;
    }

    /**
     * Find an existing enrollee by normalized NIN.
     */
    public function findExistingByNin(?string $nin, ?int $ignoreEnrolleeId = null): ?Enrollee
    {
        $normalizedNin = Enrollee::normalizeNin($nin);
        if ($normalizedNin === null) {
            return null;
        }

        $baseQuery = Enrollee::query()
            ->whereIn('status', $this->duplicateStatuses)
            ->when($ignoreEnrolleeId, fn ($query) => $query->whereKeyNot($ignoreEnrolleeId));

        return $baseQuery
            ->where('nin', $normalizedNin)
            ->first();
    }

    /**
     * Check whether the given payload represents a duplicate enrollee.
     *
     * Returns an array with:
     *   - is_duplicate        (bool)
     *   - matched_enrollee_id (?int)
     *   - match_type          (?string)  'nin_match' | 'name_dob_match'
     */
    public function check(array $payload, ?int $ignoreEnrolleeId = null): array
    {
        // Check 1: NIN match
        if (!empty($payload['nin'])) {
            $existing = $this->findExistingByNin((string) $payload['nin'], $ignoreEnrolleeId);
            if ($existing) {
                return [
                    'is_duplicate'        => true,
                    'matched_enrollee_id' => $existing->id,
                    'match_type'          => 'nin_match',
                ];
            }
        }

        // Check 2: Fuzzy name + exact DOB + gender + facility
        if (!empty($payload['date_of_birth']) && !empty($payload['gender']) && !empty($payload['facility_id'])) {
            $candidates = Enrollee::query()
                ->whereDate('date_of_birth', $payload['date_of_birth'])
                ->where('sex', $payload['gender'])
                ->where('facility_id', $payload['facility_id'])
                ->whereIn('status', $this->duplicateStatuses)
                ->when($ignoreEnrolleeId, fn ($query) => $query->whereKeyNot($ignoreEnrolleeId))
                ->get(['id', 'first_name', 'last_name']);

            $incomingName = strtolower(trim(($payload['first_name'] ?? '') . ' ' . ($payload['last_name'] ?? '')));

            foreach ($candidates as $candidate) {
                $existingName = strtolower(trim($candidate->first_name . ' ' . $candidate->last_name));
                $distance     = levenshtein($incomingName, $existingName);

                if ($distance <= 2) {
                    return [
                        'is_duplicate'        => true,
                        'matched_enrollee_id' => $candidate->id,
                        'match_type'          => 'name_dob_match',
                    ];
                }
            }
        }

        return [
            'is_duplicate'        => false,
            'matched_enrollee_id' => null,
            'match_type'          => null,
        ];
    }

    private function submissionLockKey(array $payload): ?string
    {
        $normalizedNin = Enrollee::normalizeNin($payload['nin'] ?? null);
        return $normalizedNin !== null
            ? 'enrollee_submission:nin:' . sha1($normalizedNin)
            : null;
    }

}
