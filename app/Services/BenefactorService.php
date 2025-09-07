<?php

namespace App\Services;

use App\Models\Benefactor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service class for handling benefactor operations.
 */
class BenefactorService
{
    public function all(): Collection
    {
        return Benefactor::all();
    }

    /**
     * Get paginated benefactors with filters
     */
    public function paginate(array $filters = [], int $perPage = 15, string $sortBy = 'created_at', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $query = Benefactor::with('enrollees');

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['email'])) {
            $query->where('email', 'like', "%{$filters['email']}%");
        }

        if (!empty($filters['phone'])) {
            $query->where('phone', 'like', "%{$filters['phone']}%");
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function create(array $data): Benefactor
    {
        return Benefactor::create($data);
    }

    public function update(Benefactor $benefactor, array $data): Benefactor
    {
        $benefactor->update($data);
        return $benefactor;
    }

    public function delete(Benefactor $benefactor): void
    {
        $benefactor->delete();
    }
}
