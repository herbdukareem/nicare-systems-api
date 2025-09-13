<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEnrolleeRequest;
use App\Http\Requests\UpdateEnrolleeRequest;
use App\Http\Resources\EnrolleeResource;
use App\Models\Enrollee;
use App\Services\EnrolleeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Enums\Status;

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

    /**
     * Upload passport photo for enrollee.
     *
     * @param  Request  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadPassport(Request $request, Enrollee $enrollee)
    {
        $validator = Validator::make($request->all(), [
            'passport' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $file = $request->file('passport');

            // Delete old image if exists
            if ($enrollee->image_url) {
                $oldPath = str_replace('/storage/', '', $enrollee->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Store new image
            $path = $file->store('enrollees/passports', 'public');
            $imageUrl = '/storage/' . $path;

            // Update enrollee record
            $enrollee->update(['image_url' => $imageUrl]);

            return $this->sendResponse([
                'image_url' => $imageUrl
            ], 'Passport photo uploaded successfully');

        } catch (\Exception $e) {
            return $this->sendError('Upload failed', [$e->getMessage()], 500);
        }
    }

    /**
     * Update enrollee status with comment.
     *
     * @param  Request  $request
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, Enrollee $enrollee)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:' . implode(',', Status::toValues()),
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors()->toArray(), 422);
        }

        try {
            $oldStatus = $enrollee->status;
            $newStatus = Status::coerce($request->status);

            // Update enrollee status
            $enrollee->update([
                'status' => $newStatus->value,
                'updated_at' => now(),
            ]);

            // Create audit trail for status change
            \App\Models\AuditTrail::create([
                'enrollee_id' => $enrollee->id,
                'action' => 'status_changed',
                'description' => "Status changed from {$oldStatus->label} to {$newStatus->label}",
                'user_id' => auth()->id(),
                'old_values' => json_encode(['status' => $oldStatus->value]),
                'new_values' => json_encode([
                    'status' => $newStatus->value,
                    'comment' => $request->comment
                ]),
            ]);

            return $this->sendResponse(
                new EnrolleeResource($enrollee->fresh()),
                'Enrollee status updated successfully'
            );

        } catch (\Exception $e) {
            return $this->sendError('Status update failed', [$e->getMessage()], 500);
        }
    }

    /**
     * Get enrollee statistics.
     *
     * @param  Enrollee  $enrollee
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics(Enrollee $enrollee)
    {
        try {
            // TODO: Implement real statistics queries
            $statistics = [
                'total_claims' => 0, // Count from claims table
                'total_benefits' => 0, // Sum from claims table
                'facilities_visited' => 0, // Count distinct facilities from claims
                'last_visit_days' => null, // Days since last claim
            ];

            return $this->sendResponse($statistics, 'Statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->sendError('Failed to get statistics', [$e->getMessage()], 500);
        }
    }
}