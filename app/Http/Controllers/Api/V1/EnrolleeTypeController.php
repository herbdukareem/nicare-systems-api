<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEnrolleeTypeRequest;
use App\Http\Requests\UpdateEnrolleeTypeRequest;
use App\Http\Resources\EnrolleeTypeResource;
use App\Models\EnrolleeType;
use App\Services\EnrolleeTypeService;

/**
 * Handles CRUD operations for enrollee types.
 */
class EnrolleeTypeController extends BaseController
{
    protected EnrolleeTypeService $service;

    public function __construct(EnrolleeTypeService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $types = $this->service->all();
        return $this->sendResponse(EnrolleeTypeResource::collection($types), 'Enrollee types retrieved successfully');
    }

    public function store(StoreEnrolleeTypeRequest $request)
    {
        $type = $this->service->create($request->validated());
        return $this->sendResponse(new EnrolleeTypeResource($type), 'Enrollee type created successfully', 201);
    }

    public function show(EnrolleeType $enrolleeType)
    {
        return $this->sendResponse(new EnrolleeTypeResource($enrolleeType), 'Enrollee type retrieved successfully');
    }

    public function update(UpdateEnrolleeTypeRequest $request, EnrolleeType $enrolleeType)
    {
        $type = $this->service->update($enrolleeType, $request->validated());
        return $this->sendResponse(new EnrolleeTypeResource($type), 'Enrollee type updated successfully');
    }

    public function destroy(EnrolleeType $enrolleeType)
    {
        $this->service->delete($enrolleeType);
        return $this->sendResponse([], 'Enrollee type deleted successfully');
    }
}
