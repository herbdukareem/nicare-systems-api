<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Http\Resources\BankResource;
use App\Models\Bank;
use App\Services\BankService;

/**
 * Handles CRUD operations for banks.
 */
class BankController extends BaseController
{
    protected BankService $service;

    public function __construct(BankService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $banks = $this->service->all();
        return $this->sendResponse(BankResource::collection($banks), 'Banks retrieved successfully');
    }

    public function store(StoreBankRequest $request)
    {
        $bank = $this->service->create($request->validated());
        return $this->sendResponse(new BankResource($bank), 'Bank created successfully', 201);
    }

    public function show(Bank $bank)
    {
        return $this->sendResponse(new BankResource($bank), 'Bank retrieved successfully');
    }

    public function update(UpdateBankRequest $request, Bank $bank)
    {
        $bank = $this->service->update($bank, $request->validated());
        return $this->sendResponse(new BankResource($bank), 'Bank updated successfully');
    }

    public function destroy(Bank $bank)
    {
        $this->service->delete($bank);
        return $this->sendResponse([], 'Bank deleted successfully');
    }
}
