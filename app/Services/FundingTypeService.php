<?php

namespace App\Services;

use App\Models\FundingType;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling funding type operations.
 */
class FundingTypeService
{
    public function all(): Collection
    {
        return FundingType::all();
    }

    public function create(array $data): FundingType
    {
        return FundingType::create($data);
    }

    public function update(FundingType $fundingType, array $data): FundingType
    {
        $fundingType->update($data);
        return $fundingType;
    }

    public function delete(FundingType $fundingType): void
    {
        $fundingType->delete();
    }
}
