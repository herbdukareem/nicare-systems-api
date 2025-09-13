<?php

namespace App\Services;

use App\Models\VulnerableGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class VulnerableGroupService
{
    /**
     * Get all vulnerable groups with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = VulnerableGroup::query();

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
     * Get all vulnerable groups without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return VulnerableGroup::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find vulnerable group by ID
     */
    public function findById(int $id): ?VulnerableGroup
    {
        return VulnerableGroup::find($id);
    }

    /**
     * Find vulnerable group by name
     */
    public function findByName(string $name): ?VulnerableGroup
    {
        return VulnerableGroup::where('name', $name)->first();
    }

    /**
     * Find vulnerable group by code
     */
    public function findByCode(string $code): ?VulnerableGroup
    {
        return VulnerableGroup::where('code', $code)->first();
    }

    /**
     * Create new vulnerable group
     */
    public function create(array $data): VulnerableGroup
    {
        return VulnerableGroup::create($data);
    }

    /**
     * Update vulnerable group
     */
    public function update(int $id, array $data): bool
    {
        $vulnerableGroup = $this->findById($id);
        if (!$vulnerableGroup) {
            return false;
        }

        return $vulnerableGroup->update($data);
    }

    /**
     * Delete vulnerable group
     */
    public function delete(int $id): bool
    {
        $vulnerableGroup = $this->findById($id);
        if (!$vulnerableGroup) {
            return false;
        }

        return $vulnerableGroup->delete();
    }

    /**
     * Get active vulnerable groups for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return VulnerableGroup::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle vulnerable group status
     */
    public function toggleStatus(int $id): bool
    {
        $vulnerableGroup = $this->findById($id);
        if (!$vulnerableGroup) {
            return false;
        }

        $vulnerableGroup->status = $vulnerableGroup->status == 1 ? 0 : 1;
        return $vulnerableGroup->save();
    }

    /**
     * Get vulnerable group statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => VulnerableGroup::count(),
            'active' => VulnerableGroup::where('status', 1)->count(),
            'inactive' => VulnerableGroup::where('status', 0)->count(),
        ];
    }

    /**
     * Search vulnerable groups by name
     */
    public function searchByName(string $name): Collection
    {
        return VulnerableGroup::where('name', 'like', "%{$name}%")
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get vulnerable group with enrollees count
     */
    public function getWithEnrolleesCount(int $id): ?VulnerableGroup
    {
        return VulnerableGroup::withCount('enrollees')->find($id);
    }

    /**
     * Get vulnerable groups with enrollees count for all
     */
    public function getAllWithEnrolleesCount(): Collection
    {
        return VulnerableGroup::withCount('enrollees')
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Bulk create vulnerable groups
     */
    public function bulkCreate(array $vulnerableGroups): bool
    {
        try {
            foreach ($vulnerableGroups as $groupData) {
                VulnerableGroup::updateOrCreate(
                    ['name' => $groupData['name']],
                    $groupData
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get vulnerable groups by status
     */
    public function getByStatus(int $status, int $perPage = 15): LengthAwarePaginator
    {
        return VulnerableGroup::where('status', $status)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Get vulnerable group enrollees distribution
     */
    public function getEnrolleesDistribution(): Collection
    {
        return VulnerableGroup::withCount('enrollees')
            ->where('status', 1)
            ->orderBy('enrollees_count', 'desc')
            ->get();
    }

    /**
     * Get vulnerable groups with age range filter
     */
    public function getByAgeRange(int $minAge = null, int $maxAge = null): Collection
    {
        $query = VulnerableGroup::where('status', 1);

        if ($minAge !== null) {
            $query->where('min_age', '>=', $minAge);
        }

        if ($maxAge !== null) {
            $query->where('max_age', '<=', $maxAge);
        }

        return $query->orderBy('name')->get();
    }
}
