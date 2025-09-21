<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrolleeRelation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'enrollee_id',
        'relation_type',
        'full_name',
        'phone_number',
        'address',
        'email',
        'gender',
        'date_of_birth',
        'is_primary_contact',
        'is_emergency_contact',
        'is_next_of_kin',
        'notes',
        'status',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'is_primary_contact' => 'boolean',
        'is_emergency_contact' => 'boolean',
        'is_next_of_kin' => 'boolean',
        'status' => 'boolean'
    ];

    // Relationships
    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class);
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
        return $query->where('status', true);
    }

    public function scopePrimaryContact($query)
    {
        return $query->where('is_primary_contact', true);
    }

    public function scopeNextOfKin($query)
    {
        return $query->where('is_next_of_kin', true);
    }

    public function scopeEmergencyContact($query)
    {
        return $query->where('is_emergency_contact', true);
    }

    public function scopeByRelationType($query, $type)
    {
        return $query->where('relation_type', $type);
    }

    // Accessors
    public function getAgeAttribute()
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    public function getFormattedPhoneAttribute()
    {
        return $this->phone_number ? '+234' . ltrim($this->phone_number, '0+234') : null;
    }
}
