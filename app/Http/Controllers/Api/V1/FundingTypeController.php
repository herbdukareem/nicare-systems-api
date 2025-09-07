<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFundingTypeRequest;
use App\Http\Requests\UpdateFundingTypeRequest;
use App\Http\Resources\FundingTypeResource;
use App\Models\FundingType;
use App\Services\FundingTypeService;

/**
 * Handles CRUD operations for funding types.
 */
class FundingTypeController extends BaseController
{
    protected FundingTypeService $service;

    public function __construct(FundingTypeService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $fundingTypes = $this->service->all();
        return $this->sendResponse(FundingTypeResource::collection($fundingTypes), 'Funding types retrieved successfully');
    }

    public function store(StoreFundingTypeReques $request)
    {
        $fundingType = $this->service->create($request->validated());
        return $this->sendResponse(new FundingTypeResource($fundingType), 'Funding type created successfully', 201);
    }

    public function show(FundingType $fundingType)
    {
        return $this->sendResponse(new FundingTypeResource($fundingType), 'Funding type retrieved successfully');
    }

    public function update(UpdateFundingTypeRequest $request, FundingType $fundingType)
    {
        $fundingType = $this->service->update($fundingType, $request->validated());
        return $this->sendResponse(new FundingTypeResource($fundingType), 'Funding type updated successfully');
    }

    public function destroy(FundingType $fundingType)
    {
        $this->service->delete($fundingType);
        return $this->sendResponse([], 'Funding type deleted successfully');
    }
}
