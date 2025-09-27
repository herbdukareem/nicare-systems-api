<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimTreatment extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'service_date',
        'service_type',
        'service_code',
        'service_description',
        'quantity',
        'unit_price',
        'total_amount',
        'approved_benefit_fee',
        'doctor_validated',
        'doctor_validated_at',
        'doctor_validated_by',
        'doctor_validation_comments',
        'pharmacist_validated',
        'pharmacist_validated_at',
        'pharmacist_validated_by',
        'pharmacist_validation_comments',
        'tariff_validated',
        'tariff_amount',
        'tariff_validation_notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'approved_benefit_fee' => 'decimal:2',
        'tariff_amount' => 'decimal:2',
        'doctor_validated' => 'boolean',
        'doctor_validated_at' => 'datetime',
        'pharmacist_validated' => 'boolean',
        'pharmacist_validated_at' => 'datetime',
        'tariff_validated' => 'boolean',
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

    public function pharmacistValidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pharmacist_validated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClaimAttachment::class, 'treatment_id');
    }

    // Scopes
    public function scopeByServiceType($query, $type)
    {
        return $query->where('service_type', $type);
    }

    public function scopeMedications($query)
    {
        return $query->where('service_type', 'medication');
    }

    public function scopeDoctorValidated($query)
    {
        return $query->where('doctor_validated', true);
    }

    public function scopePharmacistValidated($query)
    {
        return $query->where('pharmacist_validated', true);
    }

    public function scopeTariffValidated($query)
    {
        return $query->where('tariff_validated', true);
    }

    // Methods
    public function validateByDoctor(User $doctor, string $comments = null): void
    {
        $this->update([
            'doctor_validated' => true,
            'doctor_validated_at' => now(),
            'doctor_validated_by' => $doctor->id,
            'doctor_validation_comments' => $comments,
        ]);

        $this->logValidation('doctor_treatment_validated', $doctor, $comments);
    }

    public function validateByPharmacist(User $pharmacist, string $comments = null): void
    {
        if ($this->service_type !== 'medication') {
            throw new \Exception('Only medications can be validated by pharmacists');
        }

        $this->update([
            'pharmacist_validated' => true,
            'pharmacist_validated_at' => now(),
            'pharmacist_validated_by' => $pharmacist->id,
            'pharmacist_validation_comments' => $comments,
        ]);

        $this->logValidation('pharmacist_treatment_validated', $pharmacist, $comments);
    }

    public function validateTariff(float $tariffAmount, string $notes = null): void
    {
        $this->update([
            'tariff_validated' => true,
            'tariff_amount' => $tariffAmount,
            'tariff_validation_notes' => $notes,
            'approved_benefit_fee' => min($this->total_amount, $tariffAmount),
        ]);

        // Update claim totals
        $this->claim->calculateTotalAmounts();
    }

    public function requiresPharmacistValidation(): bool
    {
        return $this->service_type === 'medication';
    }

    private function logValidation(string $action, User $user, string $comments = null): void
    {
        ClaimAuditLog::create([
            'claim_id' => $this->claim_id,
            'action' => $action,
            'field_changed' => 'treatment_validation',
            'new_value' => 'validated',
            'reason' => ucfirst(str_replace('_', ' ', $action)),
            'comments' => $comments,
            'user_id' => $user->id,
            'user_role' => $user->roles->first()->name ?? 'unknown',
            'user_name' => $user->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($treatment) {
            // Auto-calculate total amount
            $treatment->total_amount = $treatment->quantity * $treatment->unit_price;
        });
    }
}
