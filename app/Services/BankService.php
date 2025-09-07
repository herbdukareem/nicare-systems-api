<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for handling bank operations.
 */
class BankService
{
    public function all(): Collection
    {
        return Bank::all();
    }

    public function create(array $data): Bank
    {
        return Bank::create($data);
    }

    public function update(Bank $bank, array $data): Bank
    {
        $bank->update($data);
        return $bank;
    }

    public function delete(Bank $bank): void
    {
        $bank->delete();
    }
}
