<?php

namespace App\Services;

use App\Models\Enrollee;

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
     * Check whether the given payload represents a duplicate enrollee.
     *
     * Returns an array with:
     *   - is_duplicate        (bool)
     *   - matched_enrollee_id (?int)
     *   - match_type          (?string)  'nin_match' | 'name_dob_match'
     */
    public function check(array $payload): array
    {
        // Check 1: NIN match
        if (!empty($payload['nin'])) {
            $existing = Enrollee::query()
                ->where('nin', $payload['nin'])
                ->whereIn('status', $this->duplicateStatuses)
                ->first();
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
}
