<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MdaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MdaController extends BaseController
{
    protected MdaService $mdaService;

    public function __construct(MdaService $mdaService)
    {
        $this->mdaService = $mdaService;
    }

    /**
     * Display a listing of MDAs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $mdas = $this->mdaService->getAll($filters);

            return $this->successResponse($mdas, 'MDAs retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MDAs', 500);
        }
    }

    /**
     * Get all MDAs without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $mdas = $this->mdaService->getAllWithoutPagination();
            return $this->successResponse($mdas, 'MDAs retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MDAs', 500);
        }
    }

    /**
     * Store a newly created MDA
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:mdas,name',
                'code' => 'nullable|string|max:50|unique:mdas,code',
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $mda = $this->mdaService->create($validatedData);

            return $this->successResponse($mda, 'MDA created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create MDA', 500);
        }
    }

    /**
     * Display the specified MDA
     */
    public function show(int $id): JsonResponse
    {
        try {
            $mda = $this->mdaService->getWithEnrolleesCount($id);

            if (!$mda) {
                return $this->errorResponse('MDA not found', 404);
            }

            return $this->successResponse($mda, 'MDA retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MDA', 500);
        }
    }

    /**
     * Update the specified MDA
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:mdas,name,' . $id,
                'code' => 'nullable|string|max:50|unique:mdas,code,' . $id,
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $updated = $this->mdaService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('MDA not found', 404);
            }

            $mda = $this->mdaService->findById($id);
            return $this->successResponse($mda, 'MDA updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update MDA', 500);
        }
    }

    /**
     * Remove the specified MDA
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->mdaService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('MDA not found', 404);
            }

            return $this->successResponse(null, 'MDA deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete MDA', 500);
        }
    }

    /**
     * Toggle MDA status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->mdaService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('MDA not found', 404);
            }

            $mda = $this->mdaService->findById($id);
            return $this->successResponse($mda, 'MDA status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update MDA status', 500);
        }
    }

    /**
     * Get MDAs for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $mdas = $this->mdaService->getActiveForDropdown();
            return $this->successResponse($mdas, 'MDAs for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MDAs for dropdown', 500);
        }
    }

    /**
     * Get MDA statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->mdaService->getStatistics();
            return $this->successResponse($statistics, 'MDA statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MDA statistics', 500);
        }
    }

    /**
     * Search MDAs by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $mdas = $this->mdaService->searchByName($request->name);
            return $this->successResponse($mdas, 'MDAs search results retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search MDAs', 500);
        }
    }

    /**
     * Bulk create MDAs
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mdas' => 'required|array|min:1',
                'mdas.*.name' => 'required|string|max:255',
                'mdas.*.code' => 'nullable|string|max:50',
                'mdas.*.description' => 'nullable|string',
                'mdas.*.status' => 'boolean'
            ]);

            $success = $this->mdaService->bulkCreate($request->mdas);

            if (!$success) {
                return $this->errorResponse('Failed to create MDAs', 500);
            }

            return $this->successResponse(null, 'MDAs created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk create MDAs', 500);
        }
    }
}
