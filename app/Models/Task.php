<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'task_number',
        'status',
        'priority',
        'type',
        'story_points',
        'progress_percentage',
        'estimated_hours',
        'actual_hours',
        'start_date',
        'due_date',
        'completed_at',
        'project_id',
        'category_id',
        'parent_task_id',
        'assignee_id',
        'reporter_id',
        'labels',
        'acceptance_criteria',
        'notes',
        'is_recurring',
        'recurrence_pattern',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'story_points' => 'integer',
        'progress_percentage' => 'integer',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'labels' => 'array',
        'is_recurring' => 'boolean',
        'recurrence_pattern' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'todo',
        'priority' => 'medium',
        'type' => 'task',
        'progress_percentage' => 0,
        'actual_hours' => 0,
        'is_recurring' => false
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (empty($task->task_number)) {
                $task->task_number = static::generateTaskNumber();
            }
        });

        static::updating(function ($task) {
            if ($task->isDirty('status') && $task->status === 'done' && !$task->completed_at) {
                $task->completed_at = now();
                $task->progress_percentage = 100;
            }
        });
    }

    /**
     * Generate unique task number
     */
    public static function generateTaskNumber(): string
    {
        do {
            $number = 'TSK-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (static::where('task_number', $number)->exists());

        return $number;
    }

    /**
     * Get the project this task belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the category this task belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    /**
     * Get the parent task (for subtasks)
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /**
     * Get the subtasks
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /**
     * Get the assignee
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the reporter
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the user who created this task
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this task
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all assignments for this task
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get active assignments for this task
     */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->where('is_active', true);
    }

    /**
     * Get all comments for this task
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /**
     * Get all attachments for this task
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Get users assigned to this task
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignments')
            ->withPivot(['role', 'assigned_at', 'is_active'])
            ->wherePivot('is_active', true);
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['done', 'cancelled']);
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'done';
    }

    /**
     * Check if task is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, ['in_progress', 'review', 'testing']);
    }

    /**
     * Get time remaining until due date
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->due_date || $this->isCompleted()) {
            return null;
        }

        $now = now();
        if ($this->due_date->isPast()) {
            return 'Overdue by ' . $now->diffForHumans($this->due_date, true);
        }

        return 'Due in ' . $now->diffForHumans($this->due_date, true);
    }

    /**
     * Get estimated vs actual hours variance
     */
    public function getHoursVarianceAttribute(): ?float
    {
        if (!$this->estimated_hours) {
            return null;
        }

        return $this->actual_hours - $this->estimated_hours;
    }

    /**
     * Scope for tasks by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for tasks by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for tasks assigned to user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    /**
     * Scope for overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Scope for tasks due today
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today())
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Scope for tasks due this week
     */
    public function scopeDueThisWeek($query)
    {
        return $query->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Scope for active tasks
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Scope for completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'done');
    }

    /**
     * Scope for tasks in project
     */
    public function scopeInProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope for parent tasks (not subtasks)
     */
    public function scopeParentTasks($query)
    {
        return $query->whereNull('parent_task_id');
    }

    /**
     * Scope for subtasks
     */
    public function scopeSubtasks($query)
    {
        return $query->whereNotNull('parent_task_id');
    }
}
