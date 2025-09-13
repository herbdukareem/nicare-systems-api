<?php

namespace App\Services;

use App\Models\Mda;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class MdaService
{
    /**
     * Get all MDAs with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Mda::query();

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;
        $page = $filters['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get all MDAs without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return Mda::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find MDA by ID
     */
    public function findById(int $id): ?Mda
    {
        return Mda::find($id);
    }

    /**
     * Find MDA by name
     */
    public function findByName(string $name): ?Mda
    {
        return Mda::where('name', $name)->first();
    }

    /**
     * Create new MDA
     */
    public function create(array $data): Mda
    {
        return Mda::create($data);
    }

    /**
     * Update MDA
     */
    public function update(int $id, array $data): bool
    {
        $mda = $this->findById($id);
        if (!$mda) {
            return false;
        }

        return $mda->update($data);
    }

    /**
     * Delete MDA
     */
    public function delete(int $id): bool
    {
        $mda = $this->findById($id);
        if (!$mda) {
            return false;
        }

        return $mda->delete();
    }

    /**
     * Get active MDAs for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return Mda::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle MDA status
     */
    public function toggleStatus(int $id): bool
    {
        $mda = $this->findById($id);
        if (!$mda) {
            return false;
        }

        $mda->status = $mda->status == 1 ? 0 : 1;
        return $mda->save();
    }

    /**
     * Get MDA statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => Mda::count(),
            'active' => Mda::where('status', 1)->count(),
            'inactive' => Mda::where('status', 0)->count(),
        ];
    }

    /**
     * Search MDAs by name
     */
    public function searchByName(string $name): Collection
    {
        return Mda::where('name', 'like', "%{$name}%")
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get MDA with enrollees count
     */
    public function getWithEnrolleesCount(int $id): ?Mda
    {
        return Mda::withCount('enrollees')->find($id);
    }

    /**
     * Bulk create MDAs
     */
    public function bulkCreate(array $mdas): bool
    {
        try {
            foreach ($mdas as $mdaData) {
                Mda::updateOrCreate(
                    ['name' => $mdaData['name']],
                    $mdaData
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get MDAs with pagination for specific status
     */
    public function getByStatus(int $status, int $perPage = 15): LengthAwarePaginator
    {
        return Mda::where('status', $status)
            ->orderBy('name')
            ->paginate($perPage);
    }
}
