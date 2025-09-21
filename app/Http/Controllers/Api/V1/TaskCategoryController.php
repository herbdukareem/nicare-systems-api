<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class TaskCategoryController extends Controller
{
    /**
     * Display a listing of task categories
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TaskCategory::with(['creator:id,name', 'tasks' => function ($q) {
                $q->select('id', 'category_id', 'status')->get();
            }]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($request->filled('active') && $request->active) {
                $query->active();
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');

            if ($sortBy === 'sort_order') {
                $query->ordered();
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            // Pagination
            $perPage = $request->get('per_page', 50);
            $categories = $query->paginate($perPage);

            // Add computed fields
            $categories->getCollection()->transform(function ($category) {
                $category->total_tasks = $category->tasks->count();
                $category->active_tasks = $category->tasks->whereNotIn('status', ['done', 'cancelled'])->count();
                unset($category->tasks); // Remove tasks from response to keep it clean
                return $category;
            });

            return response()->json([
                'success' => true,
                'message' => 'Task categories retrieved successfully',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created task category
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:task_categories,name',
                'description' => 'nullable|string',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'nullable|string|max:50',
                'status' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $categoryData = $validator->validated();
            $categoryData['created_by'] = Auth::id();

            // Set sort order if not provided
            if (!isset($categoryData['sort_order'])) {
                $categoryData['sort_order'] = TaskCategory::max('sort_order') + 1;
            }

            $category = TaskCategory::create($categoryData);

            $category->load('creator:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Task category created successfully',
                'data' => $category
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified task category
     */
    public function show(TaskCategory $taskCategory): JsonResponse
    {
        try {
            $taskCategory->load([
                'creator:id,name,email',
                'updater:id,name,email',
                'tasks:id,title,task_number,status,priority',
                'activeTasks:id,title,status,priority'
            ]);

            // Add computed fields
            $taskCategory->total_tasks = $taskCategory->tasks->count();
            $taskCategory->active_tasks = $taskCategory->activeTasks->count();

            return response()->json([
                'success' => true,
                'message' => 'Task category retrieved successfully',
                'data' => $taskCategory
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified task category
     */
    public function update(Request $request, TaskCategory $taskCategory): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255|unique:task_categories,name,' . $taskCategory->id,
                'description' => 'nullable|string',
                'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'nullable|string|max:50',
                'status' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $categoryData = $validator->validated();
            $categoryData['updated_by'] = Auth::id();

            $taskCategory->update($categoryData);

            $taskCategory->load('creator:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Task category updated successfully',
                'data' => $taskCategory
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified task category
     */
    public function destroy(TaskCategory $taskCategory): JsonResponse
    {
        try {
            // Check if category has tasks
            if ($taskCategory->tasks()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category that has tasks assigned to it'
                ], 422);
            }

            $taskCategory->delete();

            return response()->json([
                'success' => true,
                'message' => 'Task category deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sort order of categories
     */
    public function updateSortOrder(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array',
                'categories.*.id' => 'required|exists:task_categories,id',
                'categories.*.sort_order' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->categories as $categoryData) {
                TaskCategory::where('id', $categoryData['id'])
                    ->update([
                        'sort_order' => $categoryData['sort_order'],
                        'updated_by' => Auth::id()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Category sort order updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sort order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active categories for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $categories = TaskCategory::active()
                ->ordered()
                ->select('id', 'name', 'color', 'icon')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
