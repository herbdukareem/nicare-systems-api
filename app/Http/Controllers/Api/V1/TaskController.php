<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Task::with([
                'project:id,name,code',
                'category:id,name,color',
                'assignee:id,name,email',
                'reporter:id,name,email',
                'creator:id,name',
                'parentTask:id,title,task_number'
            ]);

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->filled('project_id')) {
                $query->where('project_id', $request->project_id);
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('assignee_id')) {
                $query->where('assignee_id', $request->assignee_id);
            }

            if ($request->filled('reporter_id')) {
                $query->where('reporter_id', $request->reporter_id);
            }

            if ($request->filled('due_date_from')) {
                $query->where('due_date', '>=', $request->due_date_from);
            }

            if ($request->filled('due_date_to')) {
                $query->where('due_date', '<=', $request->due_date_to);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('task_number', 'like', "%{$search}%");
                });
            }

            if ($request->filled('overdue') && $request->overdue) {
                $query->overdue();
            }

            if ($request->filled('due_today') && $request->due_today) {
                $query->dueToday();
            }

            if ($request->filled('due_this_week') && $request->due_this_week) {
                $query->dueThisWeek();
            }

            if ($request->filled('my_tasks') && $request->my_tasks) {
                $query->where('assignee_id', Auth::id());
            }

            if ($request->filled('parent_only') && $request->parent_only) {
                $query->parentTasks();
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $tasks = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Tasks retrieved successfully',
                'data' => $tasks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created task
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:todo,in_progress,review,testing,done,cancelled',
                'priority' => 'nullable|in:low,medium,high,critical',
                'type' => 'nullable|in:task,bug,feature,improvement,research',
                'story_points' => 'nullable|integer|min:1|max:100',
                'estimated_hours' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:start_date',
                'project_id' => 'nullable|exists:projects,id',
                'category_id' => 'nullable|exists:task_categories,id',
                'parent_task_id' => 'nullable|exists:tasks,id',
                'assignee_id' => 'nullable|exists:users,id',
                'reporter_id' => 'nullable|exists:users,id',
                'labels' => 'nullable|array',
                'acceptance_criteria' => 'nullable|string',
                'notes' => 'nullable|string',
                'is_recurring' => 'nullable|boolean',
                'recurrence_pattern' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $taskData = $validator->validated();
            $taskData['created_by'] = Auth::id();
            $taskData['reporter_id'] = $taskData['reporter_id'] ?? Auth::id();

            $task = Task::create($taskData);

            // Create assignment if assignee is specified
            if (!empty($taskData['assignee_id'])) {
                TaskAssignment::create([
                    'task_id' => $task->id,
                    'user_id' => $taskData['assignee_id'],
                    'role' => 'assignee',
                    'assigned_by' => Auth::id()
                ]);
            }

            // Add system comment for task creation
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'comment' => 'Task created',
                'type' => 'system',
                'metadata' => [
                    'action' => 'created',
                    'task_data' => $taskData
                ]
            ]);

            DB::commit();

            $task->load([
                'project:id,name,code',
                'category:id,name,color',
                'assignee:id,name,email',
                'reporter:id,name,email',
                'creator:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified task
     */
    public function show(Task $task): JsonResponse
    {
        try {
            $task->load([
                'project:id,name,code,status',
                'category:id,name,color,icon',
                'assignee:id,name,email',
                'reporter:id,name,email',
                'creator:id,name,email',
                'updater:id,name,email',
                'parentTask:id,title,task_number',
                'subtasks:id,title,task_number,status,priority,assignee_id',
                'subtasks.assignee:id,name',
                'assignments.user:id,name,email',
                'comments.user:id,name,email',
                'comments.replies.user:id,name,email',
                'attachments.uploader:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task retrieved successfully',
                'data' => $task
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified task
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|in:todo,in_progress,review,testing,done,cancelled',
                'priority' => 'nullable|in:low,medium,high,critical',
                'type' => 'nullable|in:task,bug,feature,improvement,research',
                'story_points' => 'nullable|integer|min:1|max:100',
                'progress_percentage' => 'nullable|integer|min:0|max:100',
                'estimated_hours' => 'nullable|numeric|min:0',
                'actual_hours' => 'nullable|numeric|min:0',
                'start_date' => 'nullable|date',
                'due_date' => 'nullable|date|after_or_equal:start_date',
                'project_id' => 'nullable|exists:projects,id',
                'category_id' => 'nullable|exists:task_categories,id',
                'parent_task_id' => 'nullable|exists:tasks,id',
                'assignee_id' => 'nullable|exists:users,id',
                'labels' => 'nullable|array',
                'acceptance_criteria' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $taskData = $validator->validated();
            $taskData['updated_by'] = Auth::id();

            // Track status changes
            if (isset($taskData['status']) && $taskData['status'] !== $task->status) {
                TaskComment::create([
                    'task_id' => $task->id,
                    'user_id' => Auth::id(),
                    'comment' => "Status changed from {$task->status} to {$taskData['status']}",
                    'type' => 'status_change',
                    'metadata' => [
                        'old_status' => $task->status,
                        'new_status' => $taskData['status']
                    ]
                ]);
            }

            // Track assignee changes
            if (isset($taskData['assignee_id']) && $taskData['assignee_id'] !== $task->assignee_id) {
                $oldAssignee = $task->assignee ? $task->assignee->name : 'Unassigned';
                $newAssignee = User::find($taskData['assignee_id'])?->name ?? 'Unassigned';

                TaskComment::create([
                    'task_id' => $task->id,
                    'user_id' => Auth::id(),
                    'comment' => "Assignee changed from {$oldAssignee} to {$newAssignee}",
                    'type' => 'assignment_change',
                    'metadata' => [
                        'old_assignee_id' => $task->assignee_id,
                        'new_assignee_id' => $taskData['assignee_id']
                    ]
                ]);

                // Update or create assignment
                if ($taskData['assignee_id']) {
                    TaskAssignment::updateOrCreate(
                        [
                            'task_id' => $task->id,
                            'user_id' => $taskData['assignee_id'],
                            'role' => 'assignee'
                        ],
                        [
                            'is_active' => true,
                            'assigned_by' => Auth::id()
                        ]
                    );
                }
            }

            $task->update($taskData);

            DB::commit();

            $task->load([
                'project:id,name,code',
                'category:id,name,color',
                'assignee:id,name,email',
                'reporter:id,name,email',
                'creator:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update task',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified task
     */
    public function destroy(Task $task): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Add system comment for task deletion
            TaskComment::create([
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'comment' => 'Task deleted',
                'type' => 'system',
                'metadata' => [
                    'action' => 'deleted',
                    'deleted_by' => Auth::id()
                ]
            ]);

            $task->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
