<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service class for handling facility operations.
 */
class FacilityService
{
    public function all(): Collection
    {
        return Facility::with(['lga', 'ward', 'accountDetail'])->get();
    }

    /**
     * Get paginated facilities with filters
     */
    public function paginate(array $filters = [], int $perPage = 500, string $sortBy = 'created_at', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $query = Facility::with(['lga', 'ward', 'accountDetail']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['lga_id'])) {
            $query->where('lga_id', $filters['lga_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['level_of_care'])) {
            if (is_array($filters['level_of_care'])) {
                $query->whereIn('level_of_care', $filters['level_of_care']);
            } else {
                $query->where('level_of_care', $filters['level_of_care']);
            }
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function create(array $data): Facility
    {
        return Facility::create($data);
    }

    public function update(Facility $facility, array $data): Facility
    {
        $facility->update($data);
        return $facility;
    }

    public function delete(Facility $facility): void
    {
        $facility->delete();
    }
}
