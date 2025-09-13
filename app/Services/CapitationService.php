<?php

namespace App\Services;

use App\Models\Capitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CapitationService
{
    /**
     * Get all capitations with pagination and filtering
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Capitation::with('user');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply year filter
        if (!empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }

        // Apply month filter
        if (!empty($filters['month'])) {
            $query->where('capitation_month', $filters['month']);
        }

        // Apply user filter
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
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
     * Get all capitations without pagination
     */
    public function getAllWithoutPagination(): Collection
    {
        return Capitation::with('user')
            ->where('status', 1)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    /**
     * Find capitation by ID
     */
    public function findById(int $id): ?Capitation
    {
        return Capitation::with('user')->find($id);
    }

    /**
     * Create new capitation
     */
    public function create(array $data): Capitation
    {
        return Capitation::create($data);
    }

    /**
     * Update capitation
     */
    public function update(int $id, array $data): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        return $capitation->update($data);
    }

    /**
     * Delete capitation
     */
    public function delete(int $id): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        return $capitation->delete();
    }

    /**
     * Get capitations by year
     */
    public function getByYear(int $year): Collection
    {
        return Capitation::with('user')
            ->where('year', $year)
            ->orderBy('capitation_month')
            ->get();
    }

    /**
     * Get capitations by user
     */
    public function getByUser(int $userId): Collection
    {
        return Capitation::with('user')
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('capitation_month', 'desc')
            ->get();
    }

    /**
     * Get capitation for specific month and year
     */
    public function getByMonthYear(int $month, int $year): Collection
    {
        return Capitation::with('user')
            ->where('capitation_month', $month)
            ->where('year', $year)
            ->get();
    }

    /**
     * Toggle capitation status
     */
    public function toggleStatus(int $id): bool
    {
        $capitation = Capitation::find($id);
        if (!$capitation) {
            return false;
        }

        $capitation->status = $capitation->status == 1 ? 0 : 1;
        return $capitation->save();
    }

    /**
     * Get capitation statistics
     */
    public function getStatistics(): array
    {
        $currentYear = date('Y');
        $currentMonth = date('n');

        return [
            'total' => Capitation::count(),
            'active' => Capitation::where('status', 1)->count(),
            'current_year' => Capitation::where('year', $currentYear)->count(),
            'current_month' => Capitation::where('year', $currentYear)
                ->where('capitation_month', $currentMonth)
                ->count(),
        ];
    }

    /**
     * Get available years
     */
    public function getAvailableYears(): Collection
    {
        return Capitation::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
    }

    /**
     * Check if capitation exists for month/year
     */
    public function existsForMonthYear(int $month, int $year, int $userId): bool
    {
        return Capitation::where('capitation_month', $month)
            ->where('year', $year)
            ->where('user_id', $userId)
            ->exists();
    }
}
