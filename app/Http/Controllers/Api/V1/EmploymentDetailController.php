<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmploymentDetailRequest;
use App\Http\Requests\UpdateEmploymentDetailRequest;
use App\Http\Resources\EmploymentDetailResource;
use App\Models\EmploymentDetail;
use App\Services\EmploymentDetailService;

/**
 * Handles CRUD operations for employment details.
 */
class EmploymentDetailController extends BaseController
{
    protected EmploymentDetailService $service;

    public function __construct(EmploymentDetailService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $details = $this->service->all();
        return $this->sendResponse(EmploymentDetailResource::collection($details), 'Employment details retrieved successfully');
    }

    public function store(StoreEmploymentDetailRequest $request)
    {
        $detail = $this->service->create($request->validated());
        return $this->sendResponse(new EmploymentDetailResource($detail), 'Employment detail created successfully', 201);
    }

    public function show(EmploymentDetail $employmentDetail)
    {
        $employmentDetail->load('enrollee');
        return $this->sendResponse(new EmploymentDetailResource($employmentDetail), 'Employment detail retrieved successfully');
    }

    public function update(UpdateEmploymentDetailRequest $request, EmploymentDetail $employmentDetail)
    {
        $detail = $this->service->update($employmentDetail, $request->validated());
        return $this->sendResponse(new EmploymentDetailResource($detail), 'Employment detail updated successfully');
    }

    public function destroy(EmploymentDetail $employmentDetail)
    {
        $this->service->delete($employmentDetail);
        return $this->sendResponse([], 'Employment detail deleted successfully');
    }
}
