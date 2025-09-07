<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class EnrolleeFilter
 *
 * Applies filtering logic to the Enrollee query based on provided filters.
 */
class EnrolleeFilter
{
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
                case 'first_name':
                    $query->where('first_name', 'like', "%{$value}%");
                    break;
                case 'last_name':
                    $query->where('last_name', 'like', "%{$value}%");
                    break;
                case 'status':
                    // Accept either enum value or integer code
                    $query->where('status', $value);
                    break;
                case 'gender':
                    $query->where('gender', $value);
                    break;
                case 'facility_id':
                    $query->where('facility_id', $value);
                    break;
                case 'lga_id':
                    $query->where('lga_id', $value);
                    break;
                case 'ward_id':
                    $query->where('ward_id', $value);
                    break;
                case 'nin':
                    $query->where('nin', $value);
                    break;
                case 'enrollee_id':
                    $query->where('enrollee_id', $value);
                    break;
                case 'date_of_birth_from':
                    $query->whereDate('date_of_birth', '>=', $value);
                    break;
                case 'date_of_birth_to':
                    $query->whereDate('date_of_birth', '<=', $value);
                    break;
            }
        }
        return $query;
    }
}