<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CapitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CapitationController extends BaseController
{
    protected CapitationService $capitationService;

    public function __construct(CapitationService $capitationService)
    {
        $this->capitationService = $capitationService;
    }

    /**
     * Display a listing of capitations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'year', 'month', 'user_id', 
                'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $capitations = $this->capitationService->getAll($filters);

            return $this->successResponse($capitations, 'Capitations retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitations', 500);
        }
    }

    /**
     * Get all capitations without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $capitations = $this->capitationService->getAllWithoutPagination();
            return $this->successResponse($capitations, 'Capitations retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitations', 500);
        }
    }

    /**
     * Store a newly created capitation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'capitated_month' => 'required|integer|min:1|max:12',
                'capitation_month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:' . (date('Y') + 5),
                'user_id' => 'required|exists:users,id',
                'status' => 'boolean'
            ]);

            // Check if capitation already exists for this month/year/user
            if ($this->capitationService->existsForMonthYear(
                $validatedData['capitation_month'], 
                $validatedData['year'], 
                $validatedData['user_id']
            )) {
                return $this->errorResponse('Capitation already exists for this month, year and user', 422);
            }

            $capitation = $this->capitationService->create($validatedData);

            return $this->successResponse($capitation, 'Capitation created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create capitation', 500);
        }
    }

    /**
     * Display the specified capitation
     */
    public function show(int $id): JsonResponse
    {
        try {
            $capitation = $this->capitationService->findById($id);

            if (!$capitation) {
                return $this->errorResponse('Capitation not found', 404);
            }

            return $this->successResponse($capitation, 'Capitation retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitation', 500);
        }
    }

    /**
     * Update the specified capitation
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'capitated_month' => 'sometimes|required|integer|min:1|max:12',
                'capitation_month' => 'sometimes|required|integer|min:1|max:12',
                'year' => 'sometimes|required|integer|min:2020|max:' . (date('Y') + 5),
                'user_id' => 'sometimes|required|exists:users,id',
                'status' => 'boolean'
            ]);

            $updated = $this->capitationService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Capitation not found', 404);
            }

            $capitation = $this->capitationService->findById($id);
            return $this->successResponse($capitation, 'Capitation updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update capitation', 500);
        }
    }

    /**
     * Remove the specified capitation
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->capitationService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Capitation not found', 404);
            }

            return $this->successResponse(null, 'Capitation deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete capitation', 500);
        }
    }

    /**
     * Get capitations by year
     */
    public function getByYear(int $year): JsonResponse
    {
        try {
            $capitations = $this->capitationService->getByYear($year);
            return $this->successResponse($capitations, 'Capitations for year retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitations for year', 500);
        }
    }

    /**
     * Get capitations by user
     */
    public function getByUser(int $userId): JsonResponse
    {
        try {
            $capitations = $this->capitationService->getByUser($userId);
            return $this->successResponse($capitations, 'User capitations retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user capitations', 500);
        }
    }

    /**
     * Get capitations by month and year
     */
    public function getByMonthYear(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:' . (date('Y') + 5)
            ]);

            $capitations = $this->capitationService->getByMonthYear(
                $request->month, 
                $request->year
            );

            return $this->successResponse($capitations, 'Capitations for month/year retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitations for month/year', 500);
        }
    }

    /**
     * Toggle capitation status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->capitationService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Capitation not found', 404);
            }

            $capitation = $this->capitationService->findById($id);
            return $this->successResponse($capitation, 'Capitation status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update capitation status', 500);
        }
    }

    /**
     * Get capitation statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->capitationService->getStatistics();
            return $this->successResponse($statistics, 'Capitation statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve capitation statistics', 500);
        }
    }

    /**
     * Get available years
     */
    public function availableYears(): JsonResponse
    {
        try {
            $years = $this->capitationService->getAvailableYears();
            return $this->successResponse($years, 'Available years retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve available years', 500);
        }
    }
}
