<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFundingTypeRequest;
use App\Http\Requests\UpdateFundingTypeRequest;
use App\Http\Resources\FundingTypeResource;
use App\Models\FundingType;
use App\Services\FundingTypeService;
use Illuminate\Http\Request;

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

    public function index(Request $request)
    {
        $query = FundingType::query()->withCount('enrollees');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->integer('status'));
        }

        $fundingTypes = $query->orderBy('name')->paginate((int) $request->get('per_page', 25));
        $response = FundingTypeResource::collection($fundingTypes);
        $response->additional(['meta' => [
            'total' => $fundingTypes->total(),
            'per_page' => $fundingTypes->perPage(),
            'current_page' => $fundingTypes->currentPage(),
            'last_page' => $fundingTypes->lastPage(),
        ]]);

        return $this->sendResponse($response, 'Funding types retrieved successfully');
    }

    public function store(StoreFundingTypeRequest $request)
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
        if ($fundingType->enrollees()->exists() || (method_exists($fundingType, 'payrollBatches') && $fundingType->payrollBatches()->exists())) {
            $fundingType->update(['status' => 0]);
            return $this->sendResponse(new FundingTypeResource($fundingType), 'Funding type is in use and was deactivated instead.');
        }

        $this->service->delete($fundingType);
        return $this->sendResponse([], 'Funding type deleted successfully');
    }
}
