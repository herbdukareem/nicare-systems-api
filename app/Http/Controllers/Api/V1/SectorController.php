<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SectorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class SectorController extends BaseController
{
    protected SectorService $sectorService;

    public function __construct(SectorService $sectorService)
    {
        $this->sectorService = $sectorService;
    }

    /**
     * Display a listing of sectors
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $sectors = $this->sectorService->getAll($filters);

            return $this->successResponse($sectors, 'Sectors retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sectors', 500);
        }
    }

    /**
     * Get all sectors without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $sectors = $this->sectorService->getAllWithoutPagination();
            return $this->successResponse($sectors, 'Sectors retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sectors', 500);
        }
    }

    /**
     * Store a newly created sector
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:sectors,name',
                'code' => 'nullable|string|max:50|unique:sectors,code',
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $sector = $this->sectorService->create($validatedData);

            return $this->successResponse($sector, 'Sector created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create sector', 500);
        }
    }

    /**
     * Display the specified sector
     */
    public function show(int $id): JsonResponse
    {
        try {
            $sector = $this->sectorService->getWithEnrolleesCount($id);

            if (!$sector) {
                return $this->errorResponse('Sector not found', 404);
            }

            return $this->successResponse($sector, 'Sector retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sector', 500);
        }
    }

    /**
     * Update the specified sector
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:sectors,name,' . $id,
                'code' => 'nullable|string|max:50|unique:sectors,code,' . $id,
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $updated = $this->sectorService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Sector not found', 404);
            }

            $sector = $this->sectorService->findById($id);
            return $this->successResponse($sector, 'Sector updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update sector', 500);
        }
    }

    /**
     * Remove the specified sector
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->sectorService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Sector not found', 404);
            }

            return $this->successResponse(null, 'Sector deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete sector', 500);
        }
    }

    /**
     * Toggle sector status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->sectorService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Sector not found', 404);
            }

            $sector = $this->sectorService->findById($id);
            return $this->successResponse($sector, 'Sector status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update sector status', 500);
        }
    }

    /**
     * Get sectors for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $sectors = $this->sectorService->getActiveForDropdown();
            return $this->successResponse($sectors, 'Sectors for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sectors for dropdown', 500);
        }
    }

    /**
     * Get sector statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->sectorService->getStatistics();
            return $this->successResponse($statistics, 'Sector statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sector statistics', 500);
        }
    }

    /**
     * Search sectors by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $sectors = $this->sectorService->searchByName($request->name);
            return $this->successResponse($sectors, 'Sectors search results retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search sectors', 500);
        }
    }

    /**
     * Get sectors with enrollees count
     */
    public function withEnrolleesCount(): JsonResponse
    {
        try {
            $sectors = $this->sectorService->getAllWithEnrolleesCount();
            return $this->successResponse($sectors, 'Sectors with enrollees count retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sectors with enrollees count', 500);
        }
    }

    /**
     * Get enrollees distribution by sector
     */
    public function enrolleesDistribution(): JsonResponse
    {
        try {
            $distribution = $this->sectorService->getEnrolleesDistribution();
            return $this->successResponse($distribution, 'Enrollees distribution by sector retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve enrollees distribution', 500);
        }
    }

    /**
     * Bulk create sectors
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sectors' => 'required|array|min:1',
                'sectors.*.name' => 'required|string|max:255',
                'sectors.*.code' => 'nullable|string|max:50',
                'sectors.*.description' => 'nullable|string',
                'sectors.*.status' => 'boolean'
            ]);

            $success = $this->sectorService->bulkCreate($request->sectors);

            if (!$success) {
                return $this->errorResponse('Failed to create sectors', 500);
            }

            return $this->successResponse(null, 'Sectors created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk create sectors', 500);
        }
    }
}
