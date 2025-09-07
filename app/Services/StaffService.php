<?php

namespace App\Services;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;

class StaffService
{
    public function all(): Collection
    {
        return Staff::with(['department','designation','accountDetails','employmentDetails'])->get();
    }

    public function create(array $data): Staff
    {
        return Staff::create($data);
    }

    public function update(Staff $staff, array $data): Staff
    {
        $staff->update($data);
        return $staff;
    }

    public function delete(Staff $staff): void
    {
        $staff->delete();
    }
}
