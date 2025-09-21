<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PrimaryEncounter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'encounter_code',
        'enrollee_id',
        'facility_id',
        'provider_id',
        'encounter_date',
        'encounter_time',
        'chief_complaint',
        'diagnosis',
        'treatment_given',
        'services_provided',
        'medications_prescribed',
        'total_cost',
        'encounter_type',
        'status',
        'notes',
        'follow_up_instructions',
        'next_appointment_date',
        'vital_signs',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'encounter_date' => 'date',
        'encounter_time' => 'datetime:H:i',
        'next_appointment_date' => 'date',
        'services_provided' => 'array',
        'medications_prescribed' => 'array',
        'vital_signs' => 'array',
        'total_cost' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->encounter_code)) {
                $model->encounter_code = 'ENC' . date('Ymd') . strtoupper(Str::random(6));
            }
        });
    }

    // Relationships
    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
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
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('encounter_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('encounter_date', [$startDate, $endDate]);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('encounter_date', '>=', now()->subDays($days));
    }

    // Accessors
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'grey'
        };
    }

    public function getEncounterTypeColorAttribute()
    {
        return match($this->encounter_type) {
            'emergency' => 'red',
            'consultation' => 'blue',
            'follow_up' => 'orange',
            'routine_check' => 'green',
            'vaccination' => 'purple',
            default => 'grey'
        };
    }

    public function getFormattedCostAttribute()
    {
        return '₦' . number_format($this->total_cost, 2);
    }
}
