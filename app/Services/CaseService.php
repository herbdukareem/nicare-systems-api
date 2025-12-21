<?php

namespace App\Services;

use App\Models\CaseRecord;
use Illuminate\Pagination\LengthAwarePaginator;

class CaseService
{
    /**
     * Get all case records with filters and pagination
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = CaseRecord::with(['creator:id,name', 'updater:id,name', 'detail']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nicare_code', 'like', "%{$search}%")
                  ->orWhere('service_description', 'like', "%{$search}%")
                  ->orWhere('group', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (bool) $filters['status']);
        }

        // Apply level of care filter
        if (!empty($filters['level_of_care'])) {
            $query->where('level_of_care', $filters['level_of_care']);
        }

        // Apply group filter (include specific groups)
        if (!empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

       
        // apply detail_type filter
        if (!empty($filters['detail_type'])) {
            $query->where('detail_type', $this->detailTypeToMorphType($filters['detail_type']));
        }

        // Apply exclude_groups filter (exclude specific groups)
        if (!empty($filters['exclude_groups'])) {
            $excludeGroups = is_array($filters['exclude_groups']) 
                ? $filters['exclude_groups'] 
                : explode(',', $filters['exclude_groups']);
            $query->whereNotIn('group', $excludeGroups);
        }

        // Apply PA required filter
        if (isset($filters['pa_required']) && $filters['pa_required'] !== '') {
            $query->where('pa_required', (bool) $filters['pa_required']);
        }

        // Apply referable filter
        if (isset($filters['referable']) && $filters['referable'] !== '') {
            $query->where('referable', (bool) $filters['referable']);
        }

        // Apply is_bundle filter
        if (isset($filters['is_bundle'])) {
            $is_bundle = filter_var($filters['is_bundle'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            
            $query->where('is_bundle', $is_bundle);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $allowedSortFields = ['id', 'nicare_code', 'service_description', 'level_of_care', 'price', 'group', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Apply pagination
        $perPage = $filters['per_page'] ?? 15;

 
        
        return $query->paginate($perPage);
    }

    private function detailTypeToMorphType(string $detailType): string
    {
        $detailTypes = [
            'drug' => 'App\Models\DrugDetail',
            'laboratory' => 'App\Models\LaboratoryDetail',
            'professional_service' => 'App\Models\ProfessionalServiceDetail',
            'radiology' => 'App\Models\RadiologyDetail',
            'consultation' => 'App\Models\ConsultationDetail',
            'consumable' => 'App\Models\ConsumableDetail'
        ];

        return $detailTypes[$detailType] ?? '';
    }

    /**
     * Get case statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = CaseRecord::query();

       

        $total = $query->count();
        $active = (clone $query)->where('status', true)->count();
        $paRequired = (clone $query)->where('pa_required', true)->count();
        $specialties = (clone $query)->distinct('detail_type')->count('detail_type');

        return [
            'total' => $total,
            'active' => $active,
            'pa_required' => $paRequired,
            'specialties' => $specialties
        ];
    }

    /**
     * Find case record by ID
     */
    public function findById(int $id): ?CaseRecord
    {
        return CaseRecord::with(['creator', 'updater'])->find($id);
    }

    /**
     * Create a new case record
     */
    public function create(array $data): CaseRecord
    {
        return CaseRecord::create($data);
    }

    /**
     * Update a case record
     */
    public function update(CaseRecord $caseRecord, array $data): bool
    {
        return $caseRecord->update($data);
    }

    /**
     * Delete a case record (soft delete)
     */
    public function delete(CaseRecord $caseRecord): bool
    {
        return $caseRecord->delete();
    }

    /**
     * Restore a soft-deleted case record
     */
    public function restore(int $id): bool
    {
        $caseRecord = CaseRecord::withTrashed()->find($id);
        return $caseRecord ? $caseRecord->restore() : false;
    }
}

