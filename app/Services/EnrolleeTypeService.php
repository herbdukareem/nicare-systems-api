<?php

namespace App\Services;

use App\Models\EnrolleeType;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling enrollee type operations.
 */
class EnrolleeTypeService
{
    public function all(): Collection
    {
        return EnrolleeType::all();
    }

    public function create(array $data): EnrolleeType
    {
        return EnrolleeType::create($data);
    }

    public function update(EnrolleeType $enrolleeType, array $data): EnrolleeType
    {
        $enrolleeType->update($data);
        return $enrolleeType;
    }

    public function delete(EnrolleeType $enrolleeType): void
    {
        $enrolleeType->delete();
    }
}
