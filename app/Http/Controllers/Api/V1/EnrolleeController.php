<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEnrolleeRequest;
use App\Http\Requests\UpdateEnrolleeRequest;
use App\Http\Resources\EnrolleeResource;
use App\Models\Enrollee;
use App\Services\EnrolleeService;
use Illuminate\Http\Request;

/**
 * Class EnrolleeController
 *
 * Handles CRUD operations for enrollees via API.
 */
class EnrolleeController extends BaseController
{
    /**
     * @var EnrolleeService
     */
    protected EnrolleeService $enrolleeService;

    public function __construct(EnrolleeService $enrolleeService)
    {
        $this->enrolleeService = $enrolleeService;
    }

    /**
     * Display a listing of the enrollees.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'first_name', 'last_name', 'status', 'gender', 'facility_id',
            'lga_id', 'ward_id', 'nin', 'enrollee_id', 'date_of_birth_from',
            'date_of_birth_to', 'enrollee_type_id', 'search', 'date_from',
            'date_to', 'approval_date_from', 'approval_date_to', 'age_from', 'age_to'
        ]);

        // Handle array parameters
        $arrayFilters = ['status', 'lga_id', 'ward_id', 'facility_id', 'enrollee_type_id', 'gender'];
        foreach ($arrayFilters as $filter) {
            if ($request->has($filter) && is_string($request->$filter)) {
                $filters[$filter] = explode(',', $request->$filter);
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $enrollees = $this->enrolleeService->paginate($filters, $perPage, $sortBy, $sortDirection);

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

        return $this->sendResponse($response, 'Enrollees retrieved successfully');
    }

    /**
     * Store a newly created enrollee in storage.
     *
     * @param  StoreEnrolleeRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreEnrolleeRequest $request)
    {
        $data = $request->validated();
        $enrollee = $this->enrolleeService->create($data);
        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee created successfully', 201);
    }

    /**
     * Display the specified enrollee.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Enrollee $enrollee)
    {
        // load related models
        $enrollee->load(['enrolleeType', 'facility', 'lga', 'ward']);
        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee retrieved successfully');
    }

    /**
     * Update the specified enrollee in storage.
     *
     * @param  UpdateEnrolleeRequest  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateEnrolleeRequest $request, Enrollee $enrollee)
    {
        $data = $request->validated();
        $enrollee = $this->enrolleeService->update($enrollee, $data);
        return $this->sendResponse(new EnrolleeResource($enrollee), 'Enrollee updated successfully');
    }

    /**
     * Remove the specified enrollee from storage.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Enrollee $enrollee)
    {
        $this->enrolleeService->delete($enrollee);
        return $this->sendResponse([], 'Enrollee deleted successfully');
    }
}