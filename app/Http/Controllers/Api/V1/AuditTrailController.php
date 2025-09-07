<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreAuditTrailRequest;
use App\Http\Requests\UpdateAuditTrailRequest;
use App\Http\Resources\AuditTrailResource;
use App\Models\AuditTrail;
use App\Services\AuditTrailService;

/**
 * Handles CRUD operations for audit trails.
 */
class AuditTrailController extends BaseController
{
    protected AuditTrailService $service;

    public function __construct(AuditTrailService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $auditTrails = $this->service->all();
        return $this->sendResponse(AuditTrailResource::collection($auditTrails), 'Audit trails retrieved successfully');
    }

    public function store(StoreAuditTrailRequest $request)
    {
        $auditTrail = $this->service->create($request->validated());
        return $this->sendResponse(new AuditTrailResource($auditTrail), 'Audit trail created successfully', 201);
    }

    public function show(AuditTrail $auditTrail)
    {
        $auditTrail->load(['enrollee', 'user']);
        return $this->sendResponse(new AuditTrailResource($auditTrail), 'Audit trail retrieved successfully');
    }

    public function update(UpdateAuditTrailRequest $request, AuditTrail $auditTrail)
    {
        $auditTrail = $this->service->update($auditTrail, $request->validated());
        return $this->sendResponse(new AuditTrailResource($auditTrail), 'Audit trail updated successfully');
    }

    public function destroy(AuditTrail $auditTrail)
    {
        $this->service->delete($auditTrail);
        return $this->sendResponse([], 'Audit trail deleted successfully');
    }
}
