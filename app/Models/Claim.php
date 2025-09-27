<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Claim extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_number',
        'nicare_number',
        'enrollee_name',
        'gender',
        'plan',
        'marital_status',
        'phone_main',
        'phone_during_care',
        'email_main',
        'email_during_care',
        'referral_date',
        'facility_id',
        'facility_name',
        'facility_nicare_code',
        'pa_code_id',
        'pa_code',
        'pa_request_type',
        'priority',
        'pa_validity_start',
        'pa_validity_end',
        'attending_physician_name',
        'attending_physician_license',
        'attending_physician_specialization',
        'status',
        'total_amount_claimed',
        'total_amount_approved',
        'total_amount_paid',
        'submitted_at',
        'submitted_by',
        'doctor_reviewed_at',
        'doctor_reviewed_by',
        'doctor_comments',
        'pharmacist_reviewed_at',
        'pharmacist_reviewed_by',
        'pharmacist_comments',
        'claim_reviewed_at',
        'claim_reviewed_by',
        'claim_reviewer_comments',
        'claim_confirmed_at',
        'claim_confirmed_by',
        'claim_confirmer_comments',
        'claim_approved_at',
        'claim_approved_by',
        'claim_approver_comments',
        'audit_trail',
    ];

    protected $casts = [
        'referral_date' => 'date',
        'pa_validity_start' => 'date',
        'pa_validity_end' => 'date',
        'total_amount_claimed' => 'decimal:2',
        'total_amount_approved' => 'decimal:2',
        'total_amount_paid' => 'decimal:2',
        'submitted_at' => 'datetime',
        'doctor_reviewed_at' => 'datetime',
        'pharmacist_reviewed_at' => 'datetime',
        'claim_reviewed_at' => 'datetime',
        'claim_confirmed_at' => 'datetime',
        'claim_approved_at' => 'datetime',
        'audit_trail' => 'array',
    ];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function paCode(): BelongsTo
    {
        return $this->belongsTo(PACode::class, 'pa_code_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function doctorReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_reviewed_by');
    }

    public function pharmacistReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_reviewed_by');
    }

    public function claimReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_reviewed_by');
    }

    public function claimConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_confirmed_by');
    }

    public function claimApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claim_approved_by');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ClaimDiagnosis::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(ClaimTreatment::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClaimAttachment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ClaimAuditLog::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopePendingDoctorReview($query)
    {
        return $query->where('status', 'doctor_review');
    }

    public function scopePendingPharmacistReview($query)
    {
        return $query->where('status', 'pharmacist_review');
    }

    public function scopePendingClaimReview($query)
    {
        return $query->where('status', 'claim_review');
    }

    // Methods
    public static function generateClaimNumber(): string
    {
        $prefix = 'CLM';
        $year = date('Y');
        $month = date('m');
        
        // Get the last claim number for this month
        $lastClaim = static::where('claim_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderBy('claim_number', 'desc')
            ->first();

        if ($lastClaim) {
            $lastNumber = (int) substr($lastClaim->claim_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s%s-%06d', $prefix, $year, $month, $newNumber);
    }

    public function canBeEditedBy(User $user): bool
    {
        // Only allow editing in draft status by the creator or desk officers
        return $this->status === 'draft' && 
               ($this->submitted_by === $user->id || $user->hasRole('desk_officer'));
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === 'draft' && 
               $this->diagnoses()->count() > 0 && 
               $this->treatments()->count() > 0;
    }

    public function calculateTotalAmounts(): void
    {
        $this->total_amount_claimed = $this->treatments()->sum('total_amount');
        $this->total_amount_approved = $this->treatments()->sum('approved_benefit_fee');
        $this->save();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($claim) {
            if (empty($claim->claim_number)) {
                $claim->claim_number = static::generateClaimNumber();
            }
        });
    }
}
