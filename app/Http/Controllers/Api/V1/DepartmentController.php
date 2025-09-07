<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Services\DepartmentService;

class DepartmentController extends BaseController
{
    protected DepartmentService $service;

    public function __construct(DepartmentService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $departments = $this->service->all();
        return $this->sendResponse(DepartmentResource::collection($departments), 'Departments retrieved successfully');
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = $this->service->create($request->validated());
        return $this->sendResponse(new DepartmentResource($department), 'Department created successfully', 201);
    }

    public function show(Department $department)
    {
        return $this->sendResponse(new DepartmentResource($department), 'Department retrieved successfully');
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $department = $this->service->update($department, $request->validated());
        return $this->sendResponse(new DepartmentResource($department), 'Department updated successfully');
    }

    public function destroy(Department $department)
    {
        $this->service->delete($department);
        return $this->sendResponse([], 'Department deleted successfully');
    }
}
