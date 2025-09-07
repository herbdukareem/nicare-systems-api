<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePremiumRequest;
use App\Http\Requests\UpdatePremiumRequest;
use App\Http\Resources\PremiumResource;
use App\Models\Premium;
use App\Services\PremiumService;

/**
 * Handles CRUD operations for premiums.
 */
class PremiumController extends BaseController
{
    protected PremiumService $service;

    public function __construct(PremiumService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $premiums = $this->service->all();
        return $this->sendResponse(PremiumResource::collection($premiums), 'Premiums retrieved successfully');
    }

    public function store(StorePremiumRequest $request)
    {
        $premium = $this->service->create($request->validated());
        return $this->sendResponse(new PremiumResource($premium), 'Premium created successfully', 201);
    }

    public function show(Premium $premium)
    {
        return $this->sendResponse(new PremiumResource($premium), 'Premium retrieved successfully');
    }

    public function update(UpdatePremiumRequest $request, Premium $premium)
    {
        $premium = $this->service->update($premium, $request->validated());
        return $this->sendResponse(new PremiumResource($premium), 'Premium updated successfully');
    }

    public function destroy(Premium $premium)
    {
        $this->service->delete($premium);
        return $this->sendResponse([], 'Premium deleted successfully');
    }
}
