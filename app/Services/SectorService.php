<?php

namespace App\Services;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SectorService
{
    /**
     * Get all sectors with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Sector::query();

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
     * Get all sectors without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return Sector::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find sector by ID
     */
    public function findById(int $id): ?Sector
    {
        return Sector::find($id);
    }

    /**
     * Find sector by name
     */
    public function findByName(string $name): ?Sector
    {
        return Sector::where('name', $name)->first();
    }

    /**
     * Find sector by code
     */
    public function findByCode(string $code): ?Sector
    {
        return Sector::where('code', $code)->first();
    }

    /**
     * Create new sector
     */
    public function create(array $data): Sector
    {
        return Sector::create($data);
    }

    /**
     * Update sector
     */
    public function update(int $id, array $data): bool
    {
        $sector = $this->findById($id);
        if (!$sector) {
            return false;
        }

        return $sector->update($data);
    }

    /**
     * Delete sector
     */
    public function delete(int $id): bool
    {
        $sector = $this->findById($id);
        if (!$sector) {
            return false;
        }

        return $sector->delete();
    }

    /**
     * Get active sectors for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return Sector::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle sector status
     */
    public function toggleStatus(int $id): bool
    {
        $sector = $this->findById($id);
        if (!$sector) {
            return false;
        }

        $sector->status = $sector->status == 1 ? 0 : 1;
        return $sector->save();
    }

    /**
     * Get sector statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => Sector::count(),
            'active' => Sector::where('status', 1)->count(),
            'inactive' => Sector::where('status', 0)->count(),
        ];
    }

    /**
     * Search sectors by name
     */
    public function searchByName(string $name): Collection
    {
        return Sector::where('name', 'like', "%{$name}%")
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get sector with enrollees count
     */
    public function getWithEnrolleesCount(int $id): ?Sector
    {
        return Sector::withCount('enrollees')->find($id);
    }

    /**
     * Get sectors with enrollees count for all
     */
    public function getAllWithEnrolleesCount(): Collection
    {
        return Sector::withCount('enrollees')
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Bulk create sectors
     */
    public function bulkCreate(array $sectors): bool
    {
        try {
            foreach ($sectors as $sectorData) {
                Sector::updateOrCreate(
                    ['name' => $sectorData['name']],
                    $sectorData
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get sectors by status
     */
    public function getByStatus(int $status, int $perPage = 15): LengthAwarePaginator
    {
        return Sector::where('status', $status)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Get sector enrollees distribution
     */
    public function getEnrolleesDistribution(): Collection
    {
        return Sector::withCount('enrollees')
            ->where('status', 1)
            ->orderBy('enrollees_count', 'desc')
            ->get();
    }
}
