<?php

namespace App\Services;

use App\Models\AccountDetail;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Service class for handling facility operations.
 */
class FacilityService
{
    public function all(): Collection
    {
        return Facility::with(['lga', 'ward', 'accountDetail.bank'])->get();
    }

    /**
     * Get paginated facilities with filters
     */
    public function paginate(array $filters = [], int $perPage = 500, string $sortBy = 'created_at', string $sortDirection = 'desc'): LengthAwarePaginator
    {
        $query = Facility::with(['lga', 'ward', 'accountDetail.bank']);

        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('hcp_code', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['ownership'])) {
            $query->where('ownership', $filters['ownership']);
        }

        if (!empty($filters['category'])) {
            $query->where('ownership', $filters['category']);
        }

        if (!empty($filters['lga_id'])) {
            $query->where('lga_id', $filters['lga_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['ward_id'])) {
            $query->where('ward_id', $filters['ward_id']);
        }

        if (!empty($filters['accreditation_status'])) {
            $query->where('accreditation_status', $filters['accreditation_status']);
        }

        if (!empty($filters['level_of_care'])) {
            if (is_array($filters['level_of_care'])) {
                $query->whereIn('level_of_care', $filters['level_of_care']);
            } else {
                $query->where('level_of_care', $filters['level_of_care']);
            }
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    public function create(array $data): Facility
    {
        return DB::transaction(function () use ($data): Facility {
            $hasAccountDetails = array_key_exists('account_detail', $data);
            $accountDetails = $data['account_detail'] ?? null;
            unset($data['account_detail']);

            $facility = Facility::create($data);
            if ($hasAccountDetails) {
                $this->saveAccountDetails($facility, $accountDetails);
            }

            return $facility->load(['lga', 'ward', 'accountDetail.bank']);
        });
    }

    public function update(Facility $facility, array $data): Facility
    {
        return DB::transaction(function () use ($facility, $data): Facility {
            $hasAccountDetails = array_key_exists('account_detail', $data);
            $accountDetails = $data['account_detail'] ?? null;
            unset($data['account_detail']);

            $facility->update($data);
            if ($hasAccountDetails) {
                $this->saveAccountDetails($facility, $accountDetails);
            }

            return $facility->load(['lga', 'ward', 'accountDetail.bank']);
        });
    }

    private function saveAccountDetails(Facility $facility, ?array $data): void
    {
        if ($data === null) {
            // Keep the old account record for historical references.
            $facility->accountDetail()->dissociate();
            $facility->save();
            return;
        }

        $account = $facility->accountDetail()->first();
        $belongsToFacility = $account
            && $account->accountable_type === $facility->getMorphClass()
            && (int) $account->accountable_id === (int) $facility->id;
        $isShared = $account && Facility::query()
            ->where('account_detail_id', $account->id)
            ->where('id', '!=', $facility->id)
            ->exists();

        if (!$belongsToFacility || $isShared) {
            // A legacy or shared link must not let an edit change another owner.
            $account = new AccountDetail;
            $account->accountable()->associate($facility);
        }

        $account->fill([
            'bank_id' => $data['bank_id'],
            'account_name' => $data['account_name'],
            'account_number' => $data['account_number'],
            'account_type' => $data['account_type'] ?? $account->account_type ?? 'savings',
        ]);
        if ($account->isDirty(['bank_id', 'account_name', 'account_number'])) {
            $account->is_verified = false;
            $account->verified_at = null;
            $account->verification_method = null;
        }
        $account->save();
        $facility->accountDetail()->associate($account);
        $facility->save();
    }

    public function delete(Facility $facility): void
    {
        $facility->delete();
    }
}
