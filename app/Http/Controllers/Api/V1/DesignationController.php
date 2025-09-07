<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreDesignationRequest;
use App\Http\Requests\UpdateDesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Models\Designation;
use App\Services\DesignationService;

class DesignationController extends BaseController
{
    protected DesignationService $service;

    public function __construct(DesignationService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $designations = $this->service->all();
        return $this->sendResponse(DesignationResource::collection($designations), 'Designations retrieved successfully');
    }

    public function store(StoreDesignationRequest $request)
    {
        $designation = $this->service->create($request->validated());
        return $this->sendResponse(new DesignationResource($designation), 'Designation created successfully', 201);
    }

    public function show(Designation $designation)
    {
        return $this->sendResponse(new DesignationResource($designation), 'Designation retrieved successfully');
    }

    public function update(UpdateDesignationRequest $request, Designation $designation)
    {
        $designation = $this->service->update($designation, $request->validated());
        return $this->sendResponse(new DesignationResource($designation), 'Designation updated successfully');
    }

    public function destroy(Designation $designation)
    {
        $this->service->delete($designation);
        return $this->sendResponse([], 'Designation deleted successfully');
    }
}
