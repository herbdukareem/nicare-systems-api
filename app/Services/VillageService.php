<?php

namespace App\Services;

use App\Models\Village;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling village operations.
 */
class VillageService
{
    public function all(): Collection
    {
        return Village::with('ward')->get();
    }

    public function create(array $data): Village
    {
        return Village::create($data);
    }

    public function update(Village $village, array $data): Village
    {
        $village->update($data);
        return $village;
    }

    public function delete(Village $village): void
    {
        $village->delete();
    }
}
