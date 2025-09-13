<?php

namespace App\Services;

use App\Models\PremiumType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PremiumTypeService
{
    /**
     * Get all premium types with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PremiumType::query();

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
     * Get all premium types without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return PremiumType::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find premium type by ID
     */
    public function findById(int $id): ?PremiumType
    {
        return PremiumType::find($id);
    }

    /**
     * Find premium type by code
     */
    public function findByCode(string $code): ?PremiumType
    {
        return PremiumType::where('code', $code)->first();
    }

    /**
     * Create new premium type
     */
    public function create(array $data): PremiumType
    {
        return PremiumType::create($data);
    }

    /**
     * Update premium type
     */
    public function update(int $id, array $data): bool
    {
        $premiumType = $this->findById($id);
        if (!$premiumType) {
            return false;
        }

        return $premiumType->update($data);
    }

    /**
     * Delete premium type
     */
    public function delete(int $id): bool
    {
        $premiumType = $this->findById($id);
        if (!$premiumType) {
            return false;
        }

        return $premiumType->delete();
    }

    /**
     * Get active premium types for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return PremiumType::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle premium type status
     */
    public function toggleStatus(int $id): bool
    {
        $premiumType = $this->findById($id);
        if (!$premiumType) {
            return false;
        }

        $premiumType->status = $premiumType->status == 1 ? 0 : 1;
        return $premiumType->save();
    }

    /**
     * Get premium type statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => PremiumType::count(),
            'active' => PremiumType::where('status', 1)->count(),
            'inactive' => PremiumType::where('status', 0)->count(),
        ];
    }

    /**
     * Get premium type with premiums count
     */
    public function getWithPremiumsCount(int $id): ?PremiumType
    {
        return PremiumType::withCount('premiums')->find($id);
    }

    /**
     * Get premium types with premiums count for all
     */
    public function getAllWithPremiumsCount(): Collection
    {
        return PremiumType::withCount('premiums')
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Search premium types by name
     */
    public function searchByName(string $name): Collection
    {
        return PremiumType::where('name', 'like', "%{$name}%")
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get premium types by status
     */
    public function getByStatus(int $status, int $perPage = 15): LengthAwarePaginator
    {
        return PremiumType::where('status', $status)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Bulk create premium types
     */
    public function bulkCreate(array $premiumTypes): bool
    {
        try {
            foreach ($premiumTypes as $typeData) {
                PremiumType::updateOrCreate(
                    ['code' => $typeData['code']],
                    $typeData
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get premium types distribution
     */
    public function getPremiumsDistribution(): Collection
    {
        return PremiumType::withCount('premiums')
            ->where('status', 1)
            ->orderBy('premiums_count', 'desc')
            ->get();
    }
}
