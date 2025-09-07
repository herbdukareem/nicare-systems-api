<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreLgaRequest;
use App\Http\Requests\UpdateLgaRequest;
use App\Http\Resources\LgaResource;
use App\Models\Lga;
use App\Services\LgaService;

/**
 * Handles CRUD operations for LGAs.
 */
class LgaController extends BaseController
{
    protected LgaService $service;

    public function __construct(LgaService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $lgas = $this->service->all();
        return $this->sendResponse(LgaResource::collection($lgas), 'LGAs retrieved successfully');
    }

    public function store(StoreLgaRequest $request)
    {
        $lga = $this->service->create($request->validated());
        return $this->sendResponse(new LgaResource($lga), 'LGA created successfully', 201);
    }

    public function show(Lga $lga)
    {
        return $this->sendResponse(new LgaResource($lga), 'LGA retrieved successfully');
    }

    public function update(UpdateLgaRequest $request, Lga $lga)
    {
        $lga = $this->service->update($lga, $request->validated());
        return $this->sendResponse(new LgaResource($lga), 'LGA updated successfully');
    }

    public function destroy(Lga $lga)
    {
        $this->service->delete($lga);
        return $this->sendResponse([], 'LGA deleted successfully');
    }
}
