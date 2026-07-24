<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Class EnrolleeFilter
 *
 * Applies filtering logic to the Enrollee query based on provided filters.
 */
class EnrolleeFilter
{
    private const TABLE = 'enrollees';

    /**
     * Apply the given filters to the query.
     *
     * @param  Builder  $query
     * @param  array<string, mixed>  $filters
     * @return Builder
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            switch ($key) {
                case 'search':
                    $query->where(function($q) use ($value) {
                        $q->where(self::TABLE . '.first_name', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.last_name', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.middle_name', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.enrollee_id', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.legacy_id', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.legacy_enrollee_id', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.nin', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.phone', 'like', "%{$value}%")
                          ->orWhere(self::TABLE . '.email', 'like', "%{$value}%");
                    });
                    break;
                case 'first_name':
                    $query->where('first_name', 'like', "%{$value}%");
                    break;
                case 'last_name':
                    $query->where('last_name', 'like', "%{$value}%");
                    break;
                case 'status':
                    // Accept either enum value or integer code, or array
                    if (is_array($value)) {
                        $query->whereIn('status', $value);
                    } else {
                        $query->where('status', $value);
                    }
                    break;
                case 'gender':
                    if (is_array($value)) {
                        $query->whereIn('gender', $value);
                    } else {
                        $query->where('gender', $value);
                    }
                    break;
                case 'facility_id':
                    if (is_array($value)) {
                        $query->whereIn('facility_id', $value);
                    } else {
                        $query->where('facility_id', $value);
                    }
                    break;
                case 'insurance_programme_id':
                case 'enrollee_category_id':
                case 'premium_plan_id':
                case 'funding_type_id':
                case 'benefactor_id':
                case 'enrollment_phase_id':
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                    break;
                case 'lga_id':
                    if (is_array($value)) {
                        $query->whereIn('lga_id', $value);
                    } else {
                        $query->where('lga_id', $value);
                    }
                    break;
                case 'ward_id':
                    if (is_array($value)) {
                        $query->whereIn('ward_id', $value);
                    } else {
                        $query->where('ward_id', $value);
                    }
                    break;
                case 'nin':
                    $query->where(self::TABLE . '.nin', 'like', "%{$value}%");
                    break;
                case 'enrollee_id':
                    $query->where(self::TABLE . '.enrollee_id', 'like', "%{$value}%");
                    break;
                case 'legacy_id':
                    $query->where('legacy_id', $value);
                    break;
                case 'nin_state':
                    if ($value === 'with_nin') {
                        $query->whereNotNull(self::TABLE . '.nin')->where(self::TABLE . '.nin', '!=', '');
                    } elseif ($value === 'without_nin') {
                        $query->where(function (Builder $ninQuery): void {
                            $ninQuery->whereNull(self::TABLE . '.nin')->orWhere(self::TABLE . '.nin', '');
                        });
                    }
                    break;
                case 'duplicate_nin_only':
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        // A `whereIn('nin', $closure)` with GROUP BY/HAVING inside is
                        // optimized by MySQL as a dependent subquery — re-executed for
                        // every outer row (catastrophic at 100k+ rows: hours, not seconds).
                        // Computing the duplicate-NIN set once and caching it briefly turns
                        // the filter into a plain indexed `WHERE nin IN (...)` lookup, and
                        // avoids redoing the expensive grouping for both the count and the
                        // page queries that `paginate()` issues.
                        $duplicateNins = Cache::remember(
                            'enrollees:duplicate-nin-values',
                            now()->addMinutes(5),
                            fn () => self::duplicateNinValuesSubquery()->pluck('matched_nin')->all()
                        );

                        if (empty($duplicateNins)) {
                            $query->whereRaw('1 = 0');
                        } else {
                            $query->whereIn(self::TABLE . '.nin', $duplicateNins);
                        }
                    }
                    break;
                case 'duplicate_flag_only':
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        $query->where('is_possible_duplicate', true);
                    }
                    break;
                case 'date_of_birth_from':
                    $query->whereDate('date_of_birth', '>=', $value);
                    break;
                case 'date_of_birth_to':
                    $query->whereDate('date_of_birth', '<=', $value);
                    break;
                case 'enrollee_type_id':
                    if (is_array($value)) {
                        $query->whereIn('enrollee_type_id', $value);
                    } else {
                        $query->where('enrollee_type_id', $value);
                    }
                    break;
                case 'date_from':
                    $query->whereDate(self::dateField($filters), '>=', $value);
                    break;
                case 'date_to':
                    $query->whereDate(self::dateField($filters), '<=', $value);
                    break;
                case 'approval_date_from':
                    $query->whereDate('approval_date', '>=', $value);
                    break;
                case 'approval_date_to':
                    $query->whereDate('approval_date', '<=', $value);
                    break;
                case 'coverage_status':
                    $today = now()->toDateString();
                    if ($value === 'active') {
                        $query->where('status', 1)
                            ->whereDate('coverage_start_date', '<=', $today)
                            ->where(function ($q) use ($today) {
                                $q->whereNull('coverage_end_date')
                                    ->orWhereDate('coverage_end_date', '>=', $today);
                            });
                    } elseif ($value === 'expired') {
                        $query->whereNotNull('coverage_end_date')
                            ->whereDate('coverage_end_date', '<', $today);
                    } elseif ($value === 'no_expiry') {
                        $query->whereNull('coverage_end_date')
                            ->whereNotNull('coverage_start_date');
                    } elseif ($value === 'future') {
                        $query->whereDate('coverage_start_date', '>', $today);
                    }
                    break;
                case 'age_from':
                    $query->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= ?', [$value]);
                    break;
                case 'age_to':
                    $query->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) <= ?', [$value]);
                    break;
            }
        }
        return $query;
    }

    private static function duplicateNinValuesSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('enrollees')
            ->select('nin as matched_nin')
            ->whereNotNull('nin')
            ->where('nin', '!=', '')
            ->groupBy('nin')
            ->havingRaw('COUNT(*) > 1');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function dateField(array $filters): string
    {
        return in_array($filters['date_field'] ?? null, ['created_at', 'enrollment_date'], true)
            ? $filters['date_field']
            : 'created_at';
    }
}
