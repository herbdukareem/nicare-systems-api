<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'code',
        'status',
        'priority',
        'budget',
        'spent_amount',
        'progress_percentage',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'project_manager_id',
        'department_id',
        'tags',
        'notes',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'spent_amount' => 'decimal:2',
        'progress_percentage' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'planning',
        'priority' => 'medium',
        'spent_amount' => 0,
        'progress_percentage' => 0
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->code)) {
                $project->code = static::generateProjectCode();
            }
        });
    }

    /**
     * Generate unique project code
     */
    public static function generateProjectCode(): string
    {
        do {
            $code = 'PROJ-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Get the project manager
     */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who created this project
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this project
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all tasks in this project
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get active tasks in this project
     */
    public function activeTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Get completed tasks in this project
     */
    public function completedTasks(): HasMany
    {
        return $this->hasMany(Task::class)->where('status', 'done');
    }

    /**
     * Get overdue tasks in this project
     */
    public function overdueTasks(): HasMany
    {
        return $this->hasMany(Task::class)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['done', 'cancelled']);
    }

    /**
     * Calculate project progress based on tasks
     */
    public function calculateProgress(): int
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->completedTasks()->count();
        return round(($completedTasks / $totalTasks) * 100);
    }

    /**
     * Update project progress
     */
    public function updateProgress(): void
    {
        $this->update(['progress_percentage' => $this->calculateProgress()]);
    }

    /**
     * Check if project is overdue
     */
    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudgetAttribute(): float
    {
        return $this->budget ? $this->budget - $this->spent_amount : 0;
    }

    /**
     * Get budget utilization percentage
     */
    public function getBudgetUtilizationAttribute(): float
    {
        return $this->budget ? ($this->spent_amount / $this->budget) * 100 : 0;
    }

    /**
     * Scope for active projects
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planning', 'active']);
    }

    /**
     * Scope for completed projects
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for overdue projects
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now())
            ->where('status', '!=', 'completed');
    }

    /**
     * Scope for projects by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for projects by manager
     */
    public function scopeByManager($query, $managerId)
    {
        return $query->where('project_manager_id', $managerId);
    }

    /**
     * Scope for projects by department
     */
    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }
}
