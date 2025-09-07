<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreVillageRequest;
use App\Http\Requests\UpdateVillageRequest;
use App\Http\Resources\VillageResource;
use App\Models\Village;
use App\Services\VillageService;

/**
 * Handles CRUD operations for villages.
 */
class VillageController extends BaseController
{
    protected VillageService $service;

    public function __construct(VillageService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $villages = $this->service->all();
        return $this->sendResponse(VillageResource::collection($villages), 'Villages retrieved successfully');
    }

    public function store(StoreVillageRequest $request)
    {
        $village = $this->service->create($request->validated());
        return $this->sendResponse(new VillageResource($village), 'Village created successfully', 201);
    }

    public function show(Village $village)
    {
        $village->load('ward');
        return $this->sendResponse(new VillageResource($village), 'Village retrieved successfully');
    }

    public function update(UpdateVillageRequest $request, Village $village)
    {
        $village = $this->service->update($village, $request->validated());
        return $this->sendResponse(new VillageResource($village), 'Village updated successfully');
    }

    public function destroy(Village $village)
    {
        $this->service->delete($village);
        return $this->sendResponse([], 'Village deleted successfully');
    }
}
