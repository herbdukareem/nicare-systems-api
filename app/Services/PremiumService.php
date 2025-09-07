<?php

namespace App\Services;

use App\Models\Premium;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling premium operations.
 */
class PremiumService
{
    public function all(): Collection
    {
        return Premium::all();
    }

    public function create(array $data): Premium
    {
        return Premium::create($data);
    }

    public function update(Premium $premium, array $data): Premium
    {
        $premium->update($data);
        return $premium;
    }

    public function delete(Premium $premium): void
    {
        $premium->delete();
    }
}
