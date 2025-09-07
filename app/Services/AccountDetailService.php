<?php

namespace App\Services;

use App\Models\AccountDetail;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling account detail operations.
 */
class AccountDetailService
{
    public function all(): Collection
    {
        return AccountDetail::all();
    }

    public function create(array $data): AccountDetail
    {
        return AccountDetail::create($data);
    }

    public function update(AccountDetail $accountDetail, array $data): AccountDetail
    {
        $accountDetail->update($data);
        return $accountDetail;
    }

    public function delete(AccountDetail $accountDetail): void
    {
        $accountDetail->delete();
    }
}
