<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreFacilityRequest;
use App\Http\Requests\UpdateFacilityRequest;
use App\Http\Resources\FacilityResource;
use App\Http\Resources\EnrolleeResource;
use App\Models\Facility;
use App\Services\FacilityService;
use Illuminate\Http\Request;

/**
 * Handles CRUD operations for facilities.
 */
class FacilityController extends BaseController
{
    protected FacilityService $service;

    public function __construct(FacilityService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filters = $request->only(['name', 'type', 'category', 'lga_id', 'status', 'search', 'level_of_care']);
      
        $perPage = (int) $request->get('per_page', 500);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $facilities = $this->service->paginate($filters, $perPage, $sortBy, $sortDirection);

        $response = FacilityResource::collection($facilities);
        $response->additional([
            'meta' => [
                'total' => $facilities->total(),
                'per_page' => $facilities->perPage(),
                'current_page' => $facilities->currentPage(),
                'last_page' => $facilities->lastPage(),
                'from' => $facilities->firstItem(),
                'to' => $facilities->lastItem(),
            ],
        ]);

        return $this->sendResponse($response, 'Facilities retrieved successfully');
    }

    public function store(StoreFacilityRequest $request)
    {
        $facility = $this->service->create($request->validated());
        return $this->sendResponse(new FacilityResource($facility), 'Facility created successfully', 201);
    }

    public function show(Facility $facility)
    {
        $facility->load(['lga', 'ward', 'accountDetail']);
        return $this->sendResponse(new FacilityResource($facility), 'Facility retrieved successfully');
    }

    public function update(UpdateFacilityRequest $request, Facility $facility)
    {
        $facility = $this->service->update($facility, $request->validated());
        return $this->sendResponse(new FacilityResource($facility), 'Facility updated successfully');
    }

    public function destroy(Facility $facility)
    {
        $this->service->delete($facility);
        return $this->sendResponse([], 'Facility deleted successfully');
    }

    /**
     * Get enrollees for a specific facility
     */
    public function enrollees(Request $request, Facility $facility)
    {
        $perPage = (int) $request->get('per_page', 15);
        $search = $request->get('search');

        $enrolleesQuery = $facility->enrollees()
            ->with(['enrolleeType', 'lga', 'ward']);

        if ($search) {
            $enrolleesQuery->where(function($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('enrollee_id', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollees = $enrolleesQuery->paginate($perPage);

        $response = EnrolleeResource::collection($enrollees);
        $response->additional([
            'meta' => [
                'total' => $enrollees->total(),
                'per_page' => $enrollees->perPage(),
                'current_page' => $enrollees->currentPage(),
                'last_page' => $enrollees->lastPage(),
                'from' => $enrollees->firstItem(),
                'to' => $enrollees->lastItem(),
            ],
        ]);

        return $this->sendResponse($response, 'Facility enrollees retrieved successfully');
    }
}
