<?php

namespace App\Services;

use App\Models\Lga;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling LGA operations.
 */
class LgaService
{
    public function all(): Collection
    {
        return Lga::all();
    }

    public function create(array $data): Lga
    {
        return Lga::create($data);
    }

    public function update(Lga $lga, array $data): Lga
    {
        $lga->update($data);
        return $lga;
    }

    public function delete(Lga $lga): void
    {
        $lga->delete();
    }
}
