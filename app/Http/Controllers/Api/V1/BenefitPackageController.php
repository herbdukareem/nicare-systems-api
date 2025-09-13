<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BenefitPackageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BenefitPackageController extends BaseController
{
    protected BenefitPackageService $benefitPackageService;

    public function __construct(BenefitPackageService $benefitPackageService)
    {
        $this->benefitPackageService = $benefitPackageService;
    }

    /**
     * Display a listing of benefit packages
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $benefitPackages = $this->benefitPackageService->getAll($filters);

            return $this->successResponse($benefitPackages, 'Benefit packages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve benefit packages', 500);
        }
    }

    /**
     * Get all benefit packages without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $benefitPackages = $this->benefitPackageService->getAllWithoutPagination();
            return $this->successResponse($benefitPackages, 'Benefit packages retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve benefit packages', 500);
        }
    }

    /**
     * Store a newly created benefit package
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:benefit_packages,code',
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $benefitPackage = $this->benefitPackageService->create($validatedData);

            return $this->successResponse($benefitPackage, 'Benefit package created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create benefit package', 500);
        }
    }

    /**
     * Display the specified benefit package
     */
    public function show(int $id): JsonResponse
    {
        try {
            $benefitPackage = $this->benefitPackageService->getWithEnrolleesCount($id);

            if (!$benefitPackage) {
                return $this->errorResponse('Benefit package not found', 404);
            }

            return $this->successResponse($benefitPackage, 'Benefit package retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve benefit package', 500);
        }
    }

    /**
     * Update the specified benefit package
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:benefit_packages,code,' . $id,
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $updated = $this->benefitPackageService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Benefit package not found', 404);
            }

            $benefitPackage = $this->benefitPackageService->findById($id);
            return $this->successResponse($benefitPackage, 'Benefit package updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update benefit package', 500);
        }
    }

    /**
     * Remove the specified benefit package
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->benefitPackageService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Benefit package not found', 404);
            }

            return $this->successResponse(null, 'Benefit package deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete benefit package', 500);
        }
    }

    /**
     * Toggle benefit package status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->benefitPackageService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Benefit package not found', 404);
            }

            $benefitPackage = $this->benefitPackageService->findById($id);
            return $this->successResponse($benefitPackage, 'Benefit package status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update benefit package status', 500);
        }
    }

    /**
     * Get benefit packages for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $benefitPackages = $this->benefitPackageService->getActiveForDropdown();
            return $this->successResponse($benefitPackages, 'Benefit packages for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve benefit packages for dropdown', 500);
        }
    }

    /**
     * Get benefit packages statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->benefitPackageService->getStatistics();
            return $this->successResponse($statistics, 'Benefit packages statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve benefit packages statistics', 500);
        }
    }
}
