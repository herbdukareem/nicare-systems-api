<?php

namespace App\Services;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Collection;

class DesignationService
{
    public function all(): Collection
    {
        return Designation::all();
    }

    public function create(array $data): Designation
    {
        return Designation::create($data);
    }

    public function update(Designation $designation, array $data): Designation
    {
        $designation->update($data);
        return $designation;
    }

    public function delete(Designation $designation): void
    {
        $designation->delete();
    }
}
