<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreWardRequest;
use App\Http\Requests\UpdateWardRequest;
use App\Http\Resources\WardResource;
use App\Models\Ward;
use App\Services\WardService;

/**
 * Handles CRUD operations for wards.
 */
class WardController extends BaseController
{
    protected WardService $service;

    public function __construct(WardService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $wards = $this->service->all();
        return $this->sendResponse(WardResource::collection($wards), 'Wards retrieved successfully');
    }

    public function store(StoreWardRequest $request)
    {
        $ward = $this->service->create($request->validated());
        return $this->sendResponse(new WardResource($ward), 'Ward created successfully', 201);
    }

    public function show(Ward $ward)
    {
        $ward->load('lga');
        return $this->sendResponse(new WardResource($ward), 'Ward retrieved successfully');
    }

    public function update(UpdateWardRequest $request, Ward $ward)
    {
        $ward = $this->service->update($ward, $request->validated());
        return $this->sendResponse(new WardResource($ward), 'Ward updated successfully');
    }

    public function destroy(Ward $ward)
    {
        $this->service->delete($ward);
        return $this->sendResponse([], 'Ward deleted successfully');
    }
}
