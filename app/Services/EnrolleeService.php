<?php

namespace App\Services;

use App\Filters\EnrolleeFilter;
use App\Models\Enrollee;
use App\Models\Facility;
use App\Models\Lga;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

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
     * @param  string  $sortBy
     * @param  string  $sortDirection
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters = [], int $perPage = 15, string $sortBy = 'created_at', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $query = $this->query($filters);

        $perPage = max(1, min($perPage, 250));

        return $this->applySorting($query, $sortBy, $sortDirection)
            ->with([
                'enrolleeType', 'insuranceProgramme', 'enrolleeCategory', 'premiumPlan',
                'benefitPackage', 'facility', 'lga', 'ward', 'benefactor', 'fundingType',
                'enrollmentPhase',
            ])
            ->withCount('dependants')
            ->paginate($perPage);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function query(array $filters = []): Builder
    {
        return EnrolleeFilter::apply(Enrollee::query(), $filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function summary(array $filters = []): array
    {
        $base = $this->query($filters);
        $today = now()->toDateString();

        return [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)->where('status', Enrollee::STATUS_ACTIVE)->count(),
            'pending' => (clone $base)->where('status', Enrollee::STATUS_PENDING)->count(),
            'active_coverage' => (clone $base)
                ->where('status', Enrollee::STATUS_ACTIVE)
                ->whereNotNull('coverage_start_date')
                ->whereDate('coverage_start_date', '<=', $today)
                ->where(function (Builder $query) use ($today): void {
                    $query->whereNull('coverage_end_date')
                        ->orWhereDate('coverage_end_date', '>=', $today);
                })
                ->count(),
        ];
    }

    private function applySorting(Builder $query, string $sortBy, string $sortDirection): Builder
    {
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        return match ($sortBy) {
            'name', 'full_name' => $query->orderBy('last_name', $direction)->orderBy('first_name', $direction),
            'enrollee_id' => $query->orderBy('enrollee_id', $direction),
            'lga', 'lga_name' => $query->orderBy(
                Lga::select('name')->whereColumn('lgas.id', 'enrollees.lga_id'),
                $direction
            ),
            'facility', 'facility_name' => $query->orderBy(
                Facility::select('name')->whereColumn('facilities.id', 'enrollees.facility_id'),
                $direction
            ),
            'created_at', 'created_date' => $query->orderBy('created_at', $direction),
            default => $query->orderBy('created_at', 'desc'),
        };
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
