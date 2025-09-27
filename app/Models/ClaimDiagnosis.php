<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimDiagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'type',
        'icd_10_code',
        'icd_10_description',
        'illness_description',
        'doctor_validated',
        'doctor_validated_at',
        'doctor_validated_by',
        'doctor_validation_comments',
    ];

    protected $casts = [
        'doctor_validated' => 'boolean',
        'doctor_validated_at' => 'datetime',
    ];

    // Relationships
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function doctorValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_validated_by');
    }

    // Scopes
    public function scopePrimary($query)
    {
        return $query->where('type', 'primary');
    }

    public function scopeSecondary($query)
    {
        return $query->where('type', 'secondary');
    }

    public function scopeValidated($query)
    {
        return $query->where('doctor_validated', true);
    }

    public function scopePendingValidation($query)
    {
        return $query->where('doctor_validated', false);
    }

    // Methods
    public function validate(User $doctor, string $comments = null): void
    {
        $this->update([
            'doctor_validated' => true,
            'doctor_validated_at' => now(),
            'doctor_validated_by' => $doctor->id,
            'doctor_validation_comments' => $comments,
        ]);

        // Log the validation
        ClaimAuditLog::create([
            'claim_id' => $this->claim_id,
            'action' => 'diagnosis_validated',
            'field_changed' => 'diagnosis_validation',
            'new_value' => 'validated',
            'reason' => 'Doctor validation',
            'comments' => $comments,
            'user_id' => $doctor->id,
            'user_role' => 'doctor',
            'user_name' => $doctor->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }
}
