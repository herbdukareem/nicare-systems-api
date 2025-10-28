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
                case 'search':
                    $query->where(function($q) use ($value) {
                        $q->where('first_name', 'like', "%{$value}%")
                          ->orWhere('last_name', 'like', "%{$value}%")
                          ->orWhere('middle_name', 'like', "%{$value}%")
                          ->orWhere('enrollee_id', 'like', "%{$value}%")
                          ->orWhere('nin', 'like', "%{$value}%")
                          ->orWhere('phone', 'like', "%{$value}%")
                          ->orWhere('email', 'like', "%{$value}%");
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
                case 'enrollee_type_id':
                    if (is_array($value)) {
                        $query->whereIn('enrollee_type_id', $value);
                    } else {
                        $query->where('enrollee_type_id', $value);
                    }
                    break;
                case 'date_from':
                    $query->whereDate('created_at', '>=', $value);
                    break;
                case 'date_to':
                    $query->whereDate('created_at', '<=', $value);
                    break;
                case 'approval_date_from':
                    $query->whereDate('approval_date', '>=', $value);
                    break;
                case 'approval_date_to':
                    $query->whereDate('approval_date', '<=', $value);
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
}