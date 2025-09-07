<?php

namespace App\Services;

use App\Models\Ward;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling ward operations.
 */
class WardService
{
    public function all(): Collection
    {
        return Ward::with('lga')->get();
    }

    public function create(array $data): Ward
    {
        return Ward::create($data);
    }

    public function update(Ward $ward, array $data): Ward
    {
        $ward->update($data);
        return $ward;
    }

    public function delete(Ward $ward): void
    {
        $ward->delete();
    }
}
