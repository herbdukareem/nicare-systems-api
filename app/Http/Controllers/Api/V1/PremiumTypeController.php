<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PremiumTypeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PremiumTypeController extends BaseController
{
    protected PremiumTypeService $premiumTypeService;

    public function __construct(PremiumTypeService $premiumTypeService)
    {
        $this->premiumTypeService = $premiumTypeService;
    }

    /**
     * Display a listing of premium types
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $premiumTypes = $this->premiumTypeService->getAll($filters);

            return $this->successResponse($premiumTypes, 'Premium types retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium types', 500);
        }
    }

    /**
     * Get all premium types without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $premiumTypes = $this->premiumTypeService->getAllWithoutPagination();
            return $this->successResponse($premiumTypes, 'Premium types retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium types', 500);
        }
    }

    /**
     * Store a newly created premium type
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:premium_types,code',
                'description' => 'nullable|string',
                'amount' => 'nullable|numeric|min:0',
                'duration_months' => 'nullable|integer|min:1|max:60',
                'status' => 'boolean'
            ]);

            $premiumType = $this->premiumTypeService->create($validatedData);

            return $this->successResponse($premiumType, 'Premium type created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create premium type', 500);
        }
    }

    /**
     * Display the specified premium type
     */
    public function show(int $id): JsonResponse
    {
        try {
            $premiumType = $this->premiumTypeService->getWithPremiumsCount($id);

            if (!$premiumType) {
                return $this->errorResponse('Premium type not found', 404);
            }

            return $this->successResponse($premiumType, 'Premium type retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium type', 500);
        }
    }

    /**
     * Update the specified premium type
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:premium_types,code,' . $id,
                'description' => 'nullable|string',
                'amount' => 'nullable|numeric|min:0',
                'duration_months' => 'nullable|integer|min:1|max:60',
                'status' => 'boolean'
            ]);

            $updated = $this->premiumTypeService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Premium type not found', 404);
            }

            $premiumType = $this->premiumTypeService->findById($id);
            return $this->successResponse($premiumType, 'Premium type updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update premium type', 500);
        }
    }

    /**
     * Remove the specified premium type
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->premiumTypeService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Premium type not found', 404);
            }

            return $this->successResponse(null, 'Premium type deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete premium type', 500);
        }
    }

    /**
     * Toggle premium type status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->premiumTypeService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Premium type not found', 404);
            }

            $premiumType = $this->premiumTypeService->findById($id);
            return $this->successResponse($premiumType, 'Premium type status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update premium type status', 500);
        }
    }

    /**
     * Get premium types for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $premiumTypes = $this->premiumTypeService->getActiveForDropdown();
            return $this->successResponse($premiumTypes, 'Premium types for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium types for dropdown', 500);
        }
    }

    /**
     * Get premium type statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->premiumTypeService->getStatistics();
            return $this->successResponse($statistics, 'Premium type statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium type statistics', 500);
        }
    }

    /**
     * Search premium types by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $premiumTypes = $this->premiumTypeService->searchByName($request->name);
            return $this->successResponse($premiumTypes, 'Premium types search results retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search premium types', 500);
        }
    }

    /**
     * Get premium types with premiums count
     */
    public function withPremiumsCount(): JsonResponse
    {
        try {
            $premiumTypes = $this->premiumTypeService->getAllWithPremiumsCount();
            return $this->successResponse($premiumTypes, 'Premium types with premiums count retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premium types with premiums count', 500);
        }
    }

    /**
     * Get premiums distribution by premium type
     */
    public function premiumsDistribution(): JsonResponse
    {
        try {
            $distribution = $this->premiumTypeService->getPremiumsDistribution();
            return $this->successResponse($distribution, 'Premiums distribution by type retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve premiums distribution', 500);
        }
    }

    /**
     * Bulk create premium types
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'premium_types' => 'required|array|min:1',
                'premium_types.*.name' => 'required|string|max:255',
                'premium_types.*.code' => 'required|string|max:50',
                'premium_types.*.description' => 'nullable|string',
                'premium_types.*.amount' => 'nullable|numeric|min:0',
                'premium_types.*.duration_months' => 'nullable|integer|min:1|max:60',
                'premium_types.*.status' => 'boolean'
            ]);

            $success = $this->premiumTypeService->bulkCreate($request->premium_types);

            if (!$success) {
                return $this->errorResponse('Failed to create premium types', 500);
            }

            return $this->successResponse(null, 'Premium types created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk create premium types', 500);
        }
    }
}
