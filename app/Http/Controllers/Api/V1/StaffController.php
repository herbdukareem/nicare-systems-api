<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\StaffService;

class StaffController extends BaseController
{
    protected StaffService $service;

    public function __construct(StaffService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $staff = $this->service->all();
        return $this->sendResponse(StaffResource::collection($staff), 'Staff retrieved successfully');
    }

    public function store(StoreStaffRequest $request)
    {
        $staff = $this->service->create($request->validated());
        return $this->sendResponse(new StaffResource($staff), 'Staff created successfully', 201);
    }

    public function show(Staff $staff)
    {
        $staff->load(['department','designation','accountDetails','employmentDetails']);
        return $this->sendResponse(new StaffResource($staff), 'Staff retrieved successfully');
    }

    public function update(UpdateStaffRequest $request, Staff $staff)
    {
        $staff = $this->service->update($staff, $request->validated());
        return $this->sendResponse(new StaffResource($staff), 'Staff updated successfully');
    }

    public function destroy(Staff $staff)
    {
        $this->service->delete($staff);
        return $this->sendResponse([], 'Staff deleted successfully');
    }
}
