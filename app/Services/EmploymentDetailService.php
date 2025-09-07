<?php

namespace App\Services;

use App\Models\EmploymentDetail;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling employment detail operations.
 */
class EmploymentDetailService
{
    public function all(): Collection
    {
        return EmploymentDetail::with('enrollee')->get();
    }

    public function create(array $data): EmploymentDetail
    {
        return EmploymentDetail::create($data);
    }

    public function update(EmploymentDetail $employmentDetail, array $data): EmploymentDetail
    {
        $employmentDetail->update($data);
        return $employmentDetail;
    }

    public function delete(EmploymentDetail $employmentDetail): void
    {
        $employmentDetail->delete();
    }
}
