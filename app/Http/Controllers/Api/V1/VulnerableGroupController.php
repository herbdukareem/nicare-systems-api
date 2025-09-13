<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\VulnerableGroupService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VulnerableGroupController extends BaseController
{
    protected VulnerableGroupService $vulnerableGroupService;

    public function __construct(VulnerableGroupService $vulnerableGroupService)
    {
        $this->vulnerableGroupService = $vulnerableGroupService;
    }

    /**
     * Display a listing of vulnerable groups
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $vulnerableGroups = $this->vulnerableGroupService->getAll($filters);

            return $this->successResponse($vulnerableGroups, 'Vulnerable groups retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable groups', 500);
        }
    }

    /**
     * Get all vulnerable groups without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $vulnerableGroups = $this->vulnerableGroupService->getAllWithoutPagination();
            return $this->successResponse($vulnerableGroups, 'Vulnerable groups retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable groups', 500);
        }
    }

    /**
     * Store a newly created vulnerable group
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:vulnerable_groups,name',
                'code' => 'nullable|string|max:50|unique:vulnerable_groups,code',
                'description' => 'nullable|string',
                'min_age' => 'nullable|integer|min:0|max:120',
                'max_age' => 'nullable|integer|min:0|max:120|gte:min_age',
                'status' => 'boolean'
            ]);

            $vulnerableGroup = $this->vulnerableGroupService->create($validatedData);

            return $this->successResponse($vulnerableGroup, 'Vulnerable group created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create vulnerable group', 500);
        }
    }

    /**
     * Display the specified vulnerable group
     */
    public function show(int $id): JsonResponse
    {
        try {
            $vulnerableGroup = $this->vulnerableGroupService->getWithEnrolleesCount($id);

            if (!$vulnerableGroup) {
                return $this->errorResponse('Vulnerable group not found', 404);
            }

            return $this->successResponse($vulnerableGroup, 'Vulnerable group retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable group', 500);
        }
    }

    /**
     * Update the specified vulnerable group
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:vulnerable_groups,name,' . $id,
                'code' => 'nullable|string|max:50|unique:vulnerable_groups,code,' . $id,
                'description' => 'nullable|string',
                'min_age' => 'nullable|integer|min:0|max:120',
                'max_age' => 'nullable|integer|min:0|max:120|gte:min_age',
                'status' => 'boolean'
            ]);

            $updated = $this->vulnerableGroupService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Vulnerable group not found', 404);
            }

            $vulnerableGroup = $this->vulnerableGroupService->findById($id);
            return $this->successResponse($vulnerableGroup, 'Vulnerable group updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update vulnerable group', 500);
        }
    }

    /**
     * Remove the specified vulnerable group
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->vulnerableGroupService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Vulnerable group not found', 404);
            }

            return $this->successResponse(null, 'Vulnerable group deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete vulnerable group', 500);
        }
    }

    /**
     * Toggle vulnerable group status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->vulnerableGroupService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Vulnerable group not found', 404);
            }

            $vulnerableGroup = $this->vulnerableGroupService->findById($id);
            return $this->successResponse($vulnerableGroup, 'Vulnerable group status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update vulnerable group status', 500);
        }
    }

    /**
     * Get vulnerable groups for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $vulnerableGroups = $this->vulnerableGroupService->getActiveForDropdown();
            return $this->successResponse($vulnerableGroups, 'Vulnerable groups for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable groups for dropdown', 500);
        }
    }

    /**
     * Get vulnerable group statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->vulnerableGroupService->getStatistics();
            return $this->successResponse($statistics, 'Vulnerable group statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable group statistics', 500);
        }
    }

    /**
     * Search vulnerable groups by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $vulnerableGroups = $this->vulnerableGroupService->searchByName($request->name);
            return $this->successResponse($vulnerableGroups, 'Vulnerable groups search results retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search vulnerable groups', 500);
        }
    }

    /**
     * Get vulnerable groups with enrollees count
     */
    public function withEnrolleesCount(): JsonResponse
    {
        try {
            $vulnerableGroups = $this->vulnerableGroupService->getAllWithEnrolleesCount();
            return $this->successResponse($vulnerableGroups, 'Vulnerable groups with enrollees count retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable groups with enrollees count', 500);
        }
    }

    /**
     * Get enrollees distribution by vulnerable group
     */
    public function enrolleesDistribution(): JsonResponse
    {
        try {
            $distribution = $this->vulnerableGroupService->getEnrolleesDistribution();
            return $this->successResponse($distribution, 'Enrollees distribution by vulnerable group retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve enrollees distribution', 500);
        }
    }

    /**
     * Get vulnerable groups by age range
     */
    public function getByAgeRange(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'min_age' => 'nullable|integer|min:0|max:120',
                'max_age' => 'nullable|integer|min:0|max:120|gte:min_age'
            ]);

            $vulnerableGroups = $this->vulnerableGroupService->getByAgeRange(
                $request->min_age,
                $request->max_age
            );

            return $this->successResponse($vulnerableGroups, 'Vulnerable groups by age range retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve vulnerable groups by age range', 500);
        }
    }

    /**
     * Bulk create vulnerable groups
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'vulnerable_groups' => 'required|array|min:1',
                'vulnerable_groups.*.name' => 'required|string|max:255',
                'vulnerable_groups.*.code' => 'nullable|string|max:50',
                'vulnerable_groups.*.description' => 'nullable|string',
                'vulnerable_groups.*.min_age' => 'nullable|integer|min:0|max:120',
                'vulnerable_groups.*.max_age' => 'nullable|integer|min:0|max:120',
                'vulnerable_groups.*.status' => 'boolean'
            ]);

            $success = $this->vulnerableGroupService->bulkCreate($request->vulnerable_groups);

            if (!$success) {
                return $this->errorResponse('Failed to create vulnerable groups', 500);
            }

            return $this->successResponse(null, 'Vulnerable groups created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk create vulnerable groups', 500);
        }
    }
}
