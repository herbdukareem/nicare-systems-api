<?php

namespace App\Services;

use App\Models\BenefitPackage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class BenefitPackageService
{
    /**
     * Get all benefit packages with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = BenefitPackage::query();

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
     * Get all benefit packages without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return BenefitPackage::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find benefit package by ID
     */
    public function findById(int $id): ?BenefitPackage
    {
        return BenefitPackage::find($id);
    }

    /**
     * Find benefit package by code
     */
    public function findByCode(string $code): ?BenefitPackage
    {
        return BenefitPackage::where('code', $code)->first();
    }

    /**
     * Create new benefit package
     */
    public function create(array $data): BenefitPackage
    {
        return BenefitPackage::create($data);
    }

    /**
     * Update benefit package
     */
    public function update(int $id, array $data): bool
    {
        $benefitPackage = $this->findById($id);
        if (!$benefitPackage) {
            return false;
        }

        return $benefitPackage->update($data);
    }

    /**
     * Delete benefit package
     */
    public function delete(int $id): bool
    {
        $benefitPackage = $this->findById($id);
        if (!$benefitPackage) {
            return false;
        }

        return $benefitPackage->delete();
    }

    /**
     * Get benefit package with enrollees count
     */
    public function getWithEnrolleesCount(int $id): ?BenefitPackage
    {
        return BenefitPackage::withCount('enrollees')->find($id);
    }

    /**
     * Get active benefit packages for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return BenefitPackage::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle benefit package status
     */
    public function toggleStatus(int $id): bool
    {
        $benefitPackage = $this->findById($id);
        if (!$benefitPackage) {
            return false;
        }

        $benefitPackage->status = $benefitPackage->status == 1 ? 0 : 1;
        return $benefitPackage->save();
    }

    /**
     * Get benefit packages statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => BenefitPackage::count(),
            'active' => BenefitPackage::where('status', 1)->count(),
            'inactive' => BenefitPackage::where('status', 0)->count(),
        ];
    }
}
