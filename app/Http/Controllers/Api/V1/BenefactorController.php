<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBenefactorRequest;
use App\Http\Requests\UpdateBenefactorRequest;
use App\Http\Resources\BenefactorResource;
use App\Models\Benefactor;
use App\Services\BenefactorService;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for benefactors.
 */
class BenefactorController extends BaseController
{
    protected BenefactorService $service;

    public function __construct(BenefactorService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['name', 'email', 'phone', 'status', 'search']);
        $perPage = (int) $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $benefactors = $this->service->paginate($filters, $perPage, $sortBy, $sortDirection);

        $response = BenefactorResource::collection($benefactors);
        $response->additional([
            'meta' => [
                'total' => $benefactors->total(),
                'per_page' => $benefactors->perPage(),
                'current_page' => $benefactors->currentPage(),
                'last_page' => $benefactors->lastPage(),
                'from' => $benefactors->firstItem(),
                'to' => $benefactors->lastItem(),
            ],
        ]);

        return $this->sendResponse($response, 'Benefactors retrieved successfully');
    }

    public function store(StoreBenefactorRequest $request)
    {
        $benefactor = $this->service->create($request->validated());
        return $this->sendResponse(new BenefactorResource($benefactor), 'Benefactor created successfully', 201);
    }

    public function show(Benefactor $benefactor)
    {
        $benefactor->load('enrollees');
        return $this->sendResponse(new BenefactorResource($benefactor), 'Benefactor retrieved successfully');
    }

    public function update(UpdateBenefactorRequest $request, Benefactor $benefactor)
    {
        $benefactor = $this->service->update($benefactor, $request->validated());
        return $this->sendResponse(new BenefactorResource($benefactor), 'Benefactor updated successfully');
    }

    public function destroy(Benefactor $benefactor)
    {
        $this->service->delete($benefactor);
        return $this->sendResponse([], 'Benefactor deleted successfully');
    }
}
