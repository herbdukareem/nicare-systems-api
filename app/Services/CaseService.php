<?php

namespace App\Services;

use App\Models\CaseRecord;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CaseService
{
    /**
     * Get all cases with filters and pagination
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = CaseRecord::with(['creator:id,name', 'updater:id,name']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        // Apply level of care filter
        if (!empty($filters['level_of_care'])) {
            $query->byLevelOfCare($filters['level_of_care']);
        }

        // Apply group filter
        if (!empty($filters['group'])) {
            $query->byGroup($filters['group']);
        }

        // Apply PA required filter
        if (isset($filters['pa_required']) && $filters['pa_required'] !== '') {
            $query->requiresPA((bool) $filters['pa_required']);
        }

        // Apply referable filter
        if (isset($filters['referable']) && $filters['referable'] !== '') {
            $query->referable((bool) $filters['referable']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $allowedSortFields = ['id', 'nicare_code', 'case_description', 'level_of_care', 'price', 'group', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($filters['per_page'] ?? 15, 100); // Max 100 per page
        
        return $query->paginate($perPage);
    }

    /**
     * Create a new case
     */
    public function create(array $data): CaseRecord
    {
        return DB::transaction(function () use ($data) {
            return CaseRecord::create($data);
        });
    }

    /**
     * Update an existing case
     */
    public function update(CaseRecord $caseRecord, array $data): CaseRecord
    {
        return DB::transaction(function () use ($caseRecord, $data) {
            $caseRecord->update($data);
            return $caseRecord->fresh();
        });
    }

    /**
     * Delete a case
     */
    public function delete(CaseRecord $caseRecord): bool
    {
        return DB::transaction(function () use ($caseRecord) {
            return $caseRecord->delete();
        });
    }

    /**
     * Get case statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_cases' => CaseRecord::count(),
            'active_cases' => CaseRecord::where('status', true)->count(),
            'inactive_cases' => CaseRecord::where('status', false)->count(),
            'recent_additions' => CaseRecord::where('created_at', '>=', now()->subDays(30))->count(),
            'by_level_of_care' => CaseRecord::select('level_of_care', DB::raw('count(*) as count'))
                ->groupBy('level_of_care')
                ->orderBy('count', 'desc')
                ->get(),
            'by_group' => CaseRecord::select('group', DB::raw('count(*) as count'))
                ->groupBy('group')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'pa_required_count' => CaseRecord::where('pa_required', true)->count(),
            'referable_count' => CaseRecord::where('referable', true)->count(),
            'price_ranges' => [
                'under_1000' => CaseRecord::where('price', '<', 1000)->count(),
                '1000_to_5000' => CaseRecord::whereBetween('price', [1000, 5000])->count(),
                '5000_to_10000' => CaseRecord::whereBetween('price', [5000, 10000])->count(),
                'over_10000' => CaseRecord::where('price', '>', 10000)->count(),
            ]
        ];
    }

    /**
     * Bulk import cases
     */
    public function bulkImport(array $casesData): array
    {
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($casesData, &$imported, &$errors) {
            foreach ($casesData as $index => $caseData) {
                try {
                    // Validate required fields
                    if (empty($caseData['nicare_code']) || empty($caseData['case_description'])) {
                        $errors[] = "Row " . ($index + 1) . ": Missing required fields";
                        continue;
                    }

                    // Check for duplicates
                    if (CaseRecord::where('nicare_code', $caseData['nicare_code'])->exists()) {
                        $errors[] = "Row " . ($index + 1) . ": Case with code {$caseData['nicare_code']} already exists";
                        continue;
                    }

                    CaseRecord::create($caseData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
        });

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($casesData)
        ];
    }

    /**
     * Search cases by description or code
     */
    public function search(string $query, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return CaseRecord::search($query)
            ->active()
            ->limit($limit)
            ->get(['id', 'nicare_code', 'case_description', 'level_of_care', 'price', 'group']);
    }

    /**
     * Get cases for dropdown/select options
     */
    public function getForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return CaseRecord::active()
            ->orderBy('case_description')
            ->get(['id', 'nicare_code', 'case_description', 'level_of_care', 'price', 'group']);
    }

    /**
     * Get cases by level of care
     */
    public function getByLevelOfCare(string $level): \Illuminate\Database\Eloquent\Collection
    {
        return CaseRecord::active()
            ->byLevelOfCare($level)
            ->orderBy('case_description')
            ->get();
    }

    /**
     * Get cases by group
     */
    public function getByGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return CaseRecord::active()
            ->byGroup($group)
            ->orderBy('case_description')
            ->get();
    }

    /**
     * Get cases that require PA
     */
    public function getCasesRequiringPA(): \Illuminate\Database\Eloquent\Collection
    {
        return CaseRecord::active()
            ->requiresPA(true)
            ->orderBy('case_description')
            ->get();
    }
}

