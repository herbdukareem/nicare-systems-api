<?php

namespace App\Services;

use App\Models\PaymentCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentCategoryService
{
    /**
     * Get all payment categories with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = PaymentCategory::query();

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
     * Get all payment categories without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return PaymentCategory::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Find payment category by ID
     */
    public function findById(int $id): ?PaymentCategory
    {
        return PaymentCategory::find($id);
    }

    /**
     * Find payment category by code
     */
    public function findByCode(string $code): ?PaymentCategory
    {
        return PaymentCategory::where('code', $code)->first();
    }

    /**
     * Create new payment category
     */
    public function create(array $data): PaymentCategory
    {
        return PaymentCategory::create($data);
    }

    /**
     * Update payment category
     */
    public function update(int $id, array $data): bool
    {
        $paymentCategory = $this->findById($id);
        if (!$paymentCategory) {
            return false;
        }

        return $paymentCategory->update($data);
    }

    /**
     * Delete payment category
     */
    public function delete(int $id): bool
    {
        $paymentCategory = $this->findById($id);
        if (!$paymentCategory) {
            return false;
        }

        return $paymentCategory->delete();
    }

    /**
     * Get active payment categories for dropdown
     */
    public function getActiveForDropdown(): Collection
    {
        return PaymentCategory::where('status', 1)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }

    /**
     * Toggle payment category status
     */
    public function toggleStatus(int $id): bool
    {
        $paymentCategory = $this->findById($id);
        if (!$paymentCategory) {
            return false;
        }

        $paymentCategory->status = $paymentCategory->status == 1 ? 0 : 1;
        return $paymentCategory->save();
    }

    /**
     * Get payment category statistics
     */
    public function getStatistics(): array
    {
        return [
            'total' => PaymentCategory::count(),
            'active' => PaymentCategory::where('status', 1)->count(),
            'inactive' => PaymentCategory::where('status', 0)->count(),
        ];
    }

    /**
     * Search payment categories by name
     */
    public function searchByName(string $name): Collection
    {
        return PaymentCategory::where('name', 'like', "%{$name}%")
            ->where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get payment category with invoices count
     */
    public function getWithInvoicesCount(int $id): ?PaymentCategory
    {
        return PaymentCategory::withCount('invoices')->find($id);
    }

    /**
     * Bulk create payment categories
     */
    public function bulkCreate(array $categories): bool
    {
        try {
            foreach ($categories as $categoryData) {
                PaymentCategory::updateOrCreate(
                    ['code' => $categoryData['code']],
                    $categoryData
                );
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
