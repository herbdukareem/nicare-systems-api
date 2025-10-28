<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'referral_date' => 'date',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
        'utn_validated' => 'boolean',
        'utn_validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'modification_history' => 'array'
    ];

    // Relationships
    public function paCodes(): HasMany
    {
        return $this->hasMany(PACode::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deniedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'denied_by');
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function referringFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'referring_facility_id');
    }

    public function receivingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'receiving_facility_id');
    }

    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class, 'enrollee_id');
    }

    public function utnValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utn_validated_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeEmergency($query)
    {
        return $query->where('severity_level', 'emergency');
    }

    public function scopeUrgent($query)
    {
        return $query->where('severity_level', 'urgent');
    }

    public function scopeUtnValidated($query)
    {
        return $query->where('utn_validated', true);
    }

    public function scopePendingUtnValidation($query)
    {
        return $query->whereNotNull('utn')->where('utn_validated', false);
    }

    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('receiving_facility_id', $facilityId);
    }

    public function scopeFromFacility($query, $facilityId)
    {
        return $query->where('referring_facility_id', $facilityId);
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        if ($this->status === 'pending') {
            $hours = match($this->severity_level) {
                'emergency' => 0.5, // 30 minutes
                'urgent' => 3,      // 3 hours
                'routine' => 72,    // 72 hours
                default => 72
            };

            return $this->created_at->addHours($hours)->isPast();
        }

        return false;
    }

    // Methods
    public function generateReferralCode(): string
    {
        $prefix = 'NGSCHA';
        $facilityCode = substr($this->referring_nicare_code, -4);
        $serial = str_pad($this->id, 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$facilityCode}-{$serial}";
    }

    public function approve(User $user, ?string $comments = null): bool
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $user->id,
            'comments' => $comments
        ]);

        return true;
    }

    public function deny(User $user, string $comments): bool
    {
        $this->update([
            'status' => 'denied',
            'denied_at' => now(),
            'denied_by' => $user->id,
            'comments' => $comments
        ]);

        return true;
    }
}
