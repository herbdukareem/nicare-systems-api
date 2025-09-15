<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    /**
     * Get all services with filters and pagination
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Service::with(['creator:id,name', 'updater:id,name']);

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
        
        $allowedSortFields = ['id', 'nicare_code', 'service_description', 'level_of_care', 'price', 'group', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($filters['per_page'] ?? 15, 100); // Max 100 per page
        
        return $query->paginate($perPage);
    }

    /**
     * Create a new service
     */
    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            return Service::create($data);
        });
    }

    /**
     * Update an existing service
     */
    public function update(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $service->update($data);
            return $service->fresh();
        });
    }

    /**
     * Delete a service
     */
    public function delete(Service $service): bool
    {
        return DB::transaction(function () use ($service) {
            return $service->delete();
        });
    }

    /**
     * Get service statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_services' => Service::count(),
            'active_services' => Service::where('status', true)->count(),
            'inactive_services' => Service::where('status', false)->count(),
            'recent_additions' => Service::where('created_at', '>=', now()->subDays(30))->count(),
            'by_level_of_care' => Service::select('level_of_care', DB::raw('count(*) as count'))
                ->groupBy('level_of_care')
                ->orderBy('count', 'desc')
                ->get(),
            'by_group' => Service::select('group', DB::raw('count(*) as count'))
                ->groupBy('group')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get(),
            'pa_required_count' => Service::where('pa_required', true)->count(),
            'referable_count' => Service::where('referable', true)->count(),
            'price_ranges' => [
                'under_1000' => Service::where('price', '<', 1000)->count(),
                '1000_to_5000' => Service::whereBetween('price', [1000, 5000])->count(),
                '5000_to_10000' => Service::whereBetween('price', [5000, 10000])->count(),
                'over_10000' => Service::where('price', '>', 10000)->count(),
            ]
        ];
    }

    /**
     * Bulk import services
     */
    public function bulkImport(array $servicesData): array
    {
        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($servicesData, &$imported, &$errors) {
            foreach ($servicesData as $index => $serviceData) {
                try {
                    // Validate required fields
                    if (empty($serviceData['nicare_code']) || empty($serviceData['service_description'])) {
                        $errors[] = "Row " . ($index + 1) . ": Missing required fields";
                        continue;
                    }

                    // Check for duplicates
                    if (Service::where('nicare_code', $serviceData['nicare_code'])->exists()) {
                        $errors[] = "Row " . ($index + 1) . ": Service with code {$serviceData['nicare_code']} already exists";
                        continue;
                    }

                    Service::create($serviceData);
                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
                }
            }
        });

        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($servicesData)
        ];
    }

    /**
     * Search services by description or code
     */
    public function search(string $query, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return Service::search($query)
            ->active()
            ->limit($limit)
            ->get(['id', 'nicare_code', 'service_description', 'level_of_care', 'price', 'group']);
    }

    /**
     * Get services for dropdown/select options
     */
    public function getForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Service::active()
            ->orderBy('service_description')
            ->get(['id', 'nicare_code', 'service_description', 'level_of_care', 'price', 'group']);
    }

    /**
     * Get services by level of care
     */
    public function getByLevelOfCare(string $level): \Illuminate\Database\Eloquent\Collection
    {
        return Service::active()
            ->byLevelOfCare($level)
            ->orderBy('service_description')
            ->get();
    }

    /**
     * Get services by group
     */
    public function getByGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return Service::active()
            ->byGroup($group)
            ->orderBy('service_description')
            ->get();
    }

    /**
     * Get services that require PA
     */
    public function getServicesRequiringPA(): \Illuminate\Database\Eloquent\Collection
    {
        return Service::active()
            ->requiresPA(true)
            ->orderBy('service_description')
            ->get();
    }
}
