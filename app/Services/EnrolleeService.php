<?php

namespace App\Services;

use App\Filters\EnrolleeFilter;
use App\Models\Enrollee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class EnrolleeService
 *
 * Handles business logic related to enrollees.
 */
class EnrolleeService
{
    /**
     * Get a paginated list of enrollees with optional filters.
     *
     * @param  array<string, mixed>  $filters
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Enrollee::query();
        $query = EnrolleeFilter::apply($query, $filters);
        return $query->with(['enrolleeType', 'facility', 'lga', 'ward', 'benefactor', 'fundingType'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Retrieve a single enrollee by ID.
     *
     * @param  int  $id
     * @return Enrollee
     */
    public function find(int $id): Enrollee
    {
        return Enrollee::with(['enrolleeType', 'facility', 'lga', 'ward'])
            ->findOrFail($id);
    }

    /**
     * Create a new enrollee.
     *
     * @param  array<string, mixed>  $data
     * @return Enrollee
     */
    public function create(array $data): Enrollee
    {
        return Enrollee::create($data);
    }

    /**
     * Update an existing enrollee.
     *
     * @param  Enrollee  $enrollee
     * @param  array<string, mixed>  $data
     * @return Enrollee
     */
    public function update(Enrollee $enrollee, array $data): Enrollee
    {
        $enrollee->update($data);
        return $enrollee;
    }

    /**
     * Delete an enrollee (soft delete).
     *
     * @param  Enrollee  $enrollee
     * @return bool|null
     */
    public function delete(Enrollee $enrollee): ?bool
    {
        return $enrollee->delete();
    }
}