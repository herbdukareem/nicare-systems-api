<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentCategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaymentCategoryController extends BaseController
{
    protected PaymentCategoryService $paymentCategoryService;

    public function __construct(PaymentCategoryService $paymentCategoryService)
    {
        $this->paymentCategoryService = $paymentCategoryService;
    }

    /**
     * Display a listing of payment categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'search', 'status', 'sort_by', 'sort_direction', 'per_page', 'page'
            ]);

            $paymentCategories = $this->paymentCategoryService->getAll($filters);

            return $this->successResponse($paymentCategories, 'Payment categories retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment categories', 500);
        }
    }

    /**
     * Get all payment categories without pagination
     */
    public function all(): JsonResponse
    {
        try {
            $paymentCategories = $this->paymentCategoryService->getAllWithoutPagination();
            return $this->successResponse($paymentCategories, 'Payment categories retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment categories', 500);
        }
    }

    /**
     * Store a newly created payment category
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:payment_categories,code',
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $paymentCategory = $this->paymentCategoryService->create($validatedData);

            return $this->successResponse($paymentCategory, 'Payment category created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create payment category', 500);
        }
    }

    /**
     * Display the specified payment category
     */
    public function show(int $id): JsonResponse
    {
        try {
            $paymentCategory = $this->paymentCategoryService->getWithInvoicesCount($id);

            if (!$paymentCategory) {
                return $this->errorResponse('Payment category not found', 404);
            }

            return $this->successResponse($paymentCategory, 'Payment category retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment category', 500);
        }
    }

    /**
     * Update the specified payment category
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:payment_categories,code,' . $id,
                'description' => 'nullable|string',
                'status' => 'boolean'
            ]);

            $updated = $this->paymentCategoryService->update($id, $validatedData);

            if (!$updated) {
                return $this->errorResponse('Payment category not found', 404);
            }

            $paymentCategory = $this->paymentCategoryService->findById($id);
            return $this->successResponse($paymentCategory, 'Payment category updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update payment category', 500);
        }
    }

    /**
     * Remove the specified payment category
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->paymentCategoryService->delete($id);

            if (!$deleted) {
                return $this->errorResponse('Payment category not found', 404);
            }

            return $this->successResponse(null, 'Payment category deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete payment category', 500);
        }
    }

    /**
     * Toggle payment category status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $toggled = $this->paymentCategoryService->toggleStatus($id);

            if (!$toggled) {
                return $this->errorResponse('Payment category not found', 404);
            }

            $paymentCategory = $this->paymentCategoryService->findById($id);
            return $this->successResponse($paymentCategory, 'Payment category status updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update payment category status', 500);
        }
    }

    /**
     * Get payment categories for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $paymentCategories = $this->paymentCategoryService->getActiveForDropdown();
            return $this->successResponse($paymentCategories, 'Payment categories for dropdown retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment categories for dropdown', 500);
        }
    }

    /**
     * Get payment category statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $statistics = $this->paymentCategoryService->getStatistics();
            return $this->successResponse($statistics, 'Payment category statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve payment category statistics', 500);
        }
    }

    /**
     * Search payment categories by name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|min:2'
            ]);

            $paymentCategories = $this->paymentCategoryService->searchByName($request->name);
            return $this->successResponse($paymentCategories, 'Payment categories search results retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to search payment categories', 500);
        }
    }

    /**
     * Bulk create payment categories
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'categories' => 'required|array|min:1',
                'categories.*.name' => 'required|string|max:255',
                'categories.*.code' => 'required|string|max:50',
                'categories.*.description' => 'nullable|string',
                'categories.*.status' => 'boolean'
            ]);

            $success = $this->paymentCategoryService->bulkCreate($request->categories);

            if (!$success) {
                return $this->errorResponse('Failed to create payment categories', 500);
            }

            return $this->successResponse(null, 'Payment categories created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to bulk create payment categories', 500);
        }
    }
}
