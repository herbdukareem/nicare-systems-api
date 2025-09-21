<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Project::with([
                'projectManager:id,name,email',
                'department:id,name',
                'creator:id,name',
                'tasks' => function ($q) {
                    $q->select('id', 'project_id', 'status')->get();
                }
            ]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('project_manager_id')) {
                $query->where('project_manager_id', $request->project_manager_id);
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('start_date_from')) {
                $query->where('start_date', '>=', $request->start_date_from);
            }

            if ($request->filled('start_date_to')) {
                $query->where('start_date', '<=', $request->start_date_to);
            }

            if ($request->filled('end_date_from')) {
                $query->where('end_date', '>=', $request->end_date_from);
            }

            if ($request->filled('end_date_to')) {
                $query->where('end_date', '<=', $request->end_date_to);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            if ($request->filled('overdue') && $request->overdue) {
                $query->overdue();
            }

            if ($request->filled('active') && $request->active) {
                $query->active();
            }

            if ($request->filled('my_projects') && $request->my_projects) {
                $query->where('project_manager_id', Auth::id());
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $projects = $query->paginate($perPage);

            // Add computed fields
            $projects->getCollection()->transform(function ($project) {
                $project->total_tasks = $project->tasks->count();
                $project->completed_tasks = $project->tasks->where('status', 'done')->count();
                $project->active_tasks = $project->tasks->whereNotIn('status', ['done', 'cancelled'])->count();
                $project->calculated_progress = $project->calculateProgress();
                unset($project->tasks); // Remove tasks from response to keep it clean
                return $project;
            });

            return response()->json([
                'success' => true,
                'message' => 'Projects retrieved successfully',
                'data' => $projects
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created project
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
                'priority' => 'nullable|in:low,medium,high,critical',
                'budget' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'project_manager_id' => 'nullable|exists:users,id',
                'department_id' => 'nullable|exists:departments,id',
                'tags' => 'nullable|array',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $projectData = $validator->validated();
            $projectData['created_by'] = Auth::id();

            $project = Project::create($projectData);

            $project->load([
                'projectManager:id,name,email',
                'department:id,name',
                'creator:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => $project
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified project
     */
    public function show(Project $project): JsonResponse
    {
        try {
            $project->load([
                'projectManager:id,name,email',
                'department:id,name',
                'creator:id,name,email',
                'updater:id,name,email',
                'tasks:id,title,task_number,status,priority,assignee_id,due_date',
                'tasks.assignee:id,name',
                'activeTasks:id,title,status,priority',
                'completedTasks:id,title,completed_at',
                'overdueTasks:id,title,due_date,status'
            ]);

            // Add computed fields
            $project->total_tasks = $project->tasks->count();
            $project->completed_tasks = $project->completedTasks->count();
            $project->active_tasks = $project->activeTasks->count();
            $project->overdue_tasks = $project->overdueTasks->count();
            $project->calculated_progress = $project->calculateProgress();
            $project->is_overdue = $project->isOverdue();
            $project->remaining_budget = $project->remaining_budget;
            $project->budget_utilization = $project->budget_utilization;

            return response()->json([
                'success' => true,
                'message' => 'Project retrieved successfully',
                'data' => $project
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified project
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
                'priority' => 'nullable|in:low,medium,high,critical',
                'budget' => 'nullable|numeric|min:0',
                'spent_amount' => 'nullable|numeric|min:0',
                'progress_percentage' => 'nullable|integer|min:0|max:100',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'actual_start_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date',
                'project_manager_id' => 'nullable|exists:users,id',
                'department_id' => 'nullable|exists:departments,id',
                'tags' => 'nullable|array',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $projectData = $validator->validated();
            $projectData['updated_by'] = Auth::id();

            $project->update($projectData);

            $project->load([
                'projectManager:id,name,email',
                'department:id,name',
                'creator:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project updated successfully',
                'data' => $project
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(Project $project): JsonResponse
    {
        try {
            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_projects' => Project::count(),
                'active_projects' => Project::active()->count(),
                'completed_projects' => Project::completed()->count(),
                'overdue_projects' => Project::overdue()->count(),
                'projects_by_status' => Project::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'projects_by_priority' => Project::selectRaw('priority, COUNT(*) as count')
                    ->groupBy('priority')
                    ->pluck('count', 'priority'),
                'total_budget' => Project::sum('budget'),
                'total_spent' => Project::sum('spent_amount'),
                'average_progress' => Project::avg('progress_percentage')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Project statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
