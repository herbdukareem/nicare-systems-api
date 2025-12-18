<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FeedbackRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'feedback_code',
        'enrollee_id',
        'referral_id',
        'pa_code_id',
        'feedback_officer_id',
        'feedback_type',
        'event_type',
        'is_system_generated',
        'referral_status_before',
        'referral_status_after',
        'status',
        'feedback_comments',
        'officer_observations',
        'claims_guidance',
        'enrollee_verification_data',
        'medical_history_summary',
        'additional_information',
        'priority',
        'feedback_date',
        'completed_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'enrollee_verification_data' => 'array',
        'medical_history_summary' => 'array',
        'additional_information' => 'array',
        'is_system_generated' => 'boolean',
        'feedback_date' => 'datetime',
        'completed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->feedback_code)) {
                $model->feedback_code = 'FB' . date('Ymd') . strtoupper(Str::random(6));
            }
        });
    }

    // Relationships
    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function paCode(): BelongsTo
    {
        return $this->belongsTo(PACode::class, 'pa_code_id');
    }

    public function feedbackOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('feedback_type', $type);
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'orange',
            'in_progress' => 'blue',
            'completed' => 'green',
            'escalated' => 'red',
            default => 'grey'
        };
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'orange',
            'high' => 'red',
            'urgent' => 'purple',
            default => 'grey'
        };
    }
}
