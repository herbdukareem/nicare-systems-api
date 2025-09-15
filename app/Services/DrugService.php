<?php

namespace App\Services;

use App\Models\Drug;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DrugService
{
    /**
     * Get all drugs with filters and pagination
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Drug::with(['creator:id,name', 'updater:id,name']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $allowedSortFields = ['id', 'nicare_code', 'drug_name', 'drug_unit_price', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($filters['per_page'] ?? 15, 100); // Max 100 per page
        
        return $query->paginate($perPage);
    }

    /**
     * Create a new drug
     */
    public function create(array $data): Drug
    {
        return DB::transaction(function () use ($data) {
            return Drug::create($data);
        });
    }

    /**
     * Update an existing drug
     */
    public function update(Drug $drug, array $data): Drug
    {
        return DB::transaction(function () use ($drug, $data) {
            $drug->update($data);
            return $drug->fresh();
        });
    }

    /**
     * Delete a drug
     */
    public function delete(Drug $drug): bool
    {
        return DB::transaction(function () use ($drug) {
            return $drug->delete();
        });
    }

    /**
     * Get drug statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_drugs' => Drug::count(),
            'active_drugs' => Drug::where('status', true)->count(),
            'inactive_drugs' => Drug::where('status', false)->count(),
            'recent_additions' => Drug::where('created_at', '>=', now()->subDays(30))->count(),
            'by_dosage_form' => Drug::select('drug_dosage_form', DB::raw('count(*) as count'))
                ->groupBy('drug_dosage_form')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'price_ranges' => [
                'under_100' => Drug::where('drug_unit_price', '<', 100)->count(),
                '100_to_500' => Drug::whereBetween('drug_unit_price', [100, 500])->count(),
                '500_to_1000' => Drug::whereBetween('drug_unit_price', [500, 1000])->count(),
                'over_1000' => Drug::where('drug_unit_price', '>', 1000)->count(),
            ]
        ];
    }

    /**
     * Bulk import drugs
     */
    public function bulkImport(array $drugsData): array
    {
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($drugsData, &$imported, &$errors) {
            foreach ($drugsData as $index => $drugData) {
                try {
                    // Validate required fields
                    if (empty($drugData['nicare_code']) || empty($drugData['drug_name'])) {
                        $errors[] = "Row " . ($index + 1) . ": Missing required fields";
                        continue;
                    }

                    // Check for duplicates
                    if (Drug::where('nicare_code', $drugData['nicare_code'])->exists()) {
                        $errors[] = "Row " . ($index + 1) . ": Drug with code {$drugData['nicare_code']} already exists";
                        continue;
                    }

                    Drug::create($drugData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
        });

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($drugsData)
        ];
    }

    /**
     * Search drugs by name or code
     */
    public function search(string $query, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Drug::search($query)
            ->active()
            ->limit($limit)
            ->get(['id', 'nicare_code', 'drug_name', 'drug_dosage_form', 'drug_strength', 'drug_unit_price']);
    }

    /**
     * Get drugs for dropdown/select options
     */
    public function getForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Drug::active()
            ->orderBy('drug_name')
            ->get(['id', 'nicare_code', 'drug_name', 'drug_dosage_form', 'drug_strength', 'drug_unit_price']);
    }
}
