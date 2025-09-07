<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreAccountDetailRequest;
use App\Http\Requests\UpdateAccountDetailRequest;
use App\Http\Resources\AccountDetailResource;
use App\Models\AccountDetail;
use App\Services\AccountDetailService;

/**
 * Handles CRUD operations for account details.
 */
class AccountDetailController extends BaseController
{
    protected AccountDetailService $service;

    public function __construct(AccountDetailService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $accountDetails = $this->service->all();
        return $this->sendResponse(AccountDetailResource::collection($accountDetails), 'Account details retrieved successfully');
    }

    public function store(StoreAccountDetailRequest $request)
    {
        $accountDetail = $this->service->create($request->validated());
        return $this->sendResponse(new AccountDetailResource($accountDetail), 'Account detail created successfully', 201);
    }

    public function show(AccountDetail $accountDetail)
    {
        return $this->sendResponse(new AccountDetailResource($accountDetail), 'Account detail retrieved successfully');
    }

    public function update(UpdateAccountDetailRequest $request, AccountDetail $accountDetail)
    {
        $accountDetail = $this->service->update($accountDetail, $request->validated());
        return $this->sendResponse(new AccountDetailResource($accountDetail), 'Account detail updated successfully');
    }

    public function destroy(AccountDetail $accountDetail)
    {
        $this->service->delete($accountDetail);
        return $this->sendResponse([], 'Account detail deleted successfully');
    }
}
