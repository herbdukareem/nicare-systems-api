<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Simplified Admission Model
 *
 * Core Rule: An admission REQUIRES a validated UTN (via referral_id)
 * The referral must be approved and UTN validated before admission can be created.
 */
class Admission extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_code',
        'referral_id',          // REQUIRED - links to validated UTN
        'enrollee_id',
        'nicare_number',
        'facility_id',
        'bundle_id',            // Auto-matched from principal diagnosis
        'principal_diagnosis_icd10',
        'principal_diagnosis_description',
        'admission_date',
        'discharge_date',
        'status',
        'ward_type',
        'ward_days',
        'discharge_summary',
        'discharged_by',
        'created_by',
    ];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($admission) {
            if (empty($admission->admission_code)) {
                $admission->admission_code = self::generateAdmissionCode();
            }

            // Enforce: referral must be approved and UTN validated
            if ($admission->referral_id) {
                $referral = Referral::find($admission->referral_id);
                if (!$referral || $referral->status !== 'approved' || !$referral->utn_validated) {
                    throw new \InvalidArgumentException(
                        'Cannot create admission: Referral must be approved and UTN validated'
                    );
                }
            }
        });
    }

    public static function generateAdmissionCode(): string
    {
        $prefix = 'ADM';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));
        return "{$prefix}-{$date}-{$random}";
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

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function paCodes(): HasMany
    {
        return $this->hasMany(PACode::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(Claim::class);
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDischarged(): bool
    {
        return $this->status === 'discharged';
    }

    public function getStayDuration(): int
    {
        $endDate = $this->discharge_date ?? now();
        return $this->admission_date->diffInDays($endDate);
    }

    /**
     * Get the UTN from the linked referral
     */
    public function getUtn(): ?string
    {
        return $this->referral?->utn;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDischarged($query)
    {
        return $query->where('status', 'discharged');
    }
}

