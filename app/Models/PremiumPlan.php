<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class PremiumPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'insurance_programme_id',
        'benefit_package_id',
        'funding_type_id',
        'name',
        'code',
        'amount',
        'consultant_fee',
        'payment_required',
        'self_enrollment_enabled',
        'payment_gateway',
        'merchant_id',
        'merchant_service_type_id',
        'duration_days',
        'has_no_expiry',
        'waiting_period_days',
        'is_family_plan',
        'maximum_dependants',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'consultant_fee' => 'decimal:2',
        'payment_required' => 'boolean',
        'self_enrollment_enabled' => 'boolean',
        'has_no_expiry' => 'boolean',
        'duration_days' => 'integer',
        'waiting_period_days' => 'integer',
        'is_family_plan' => 'boolean',
        'maximum_dependants' => 'integer',
    ];

    public function programme()
    {
        return $this->belongsTo(InsuranceProgramme::class, 'insurance_programme_id');
    }

    public function benefitPackage()
    {
        return $this->belongsTo(BenefitPackage::class);
    }

    public function fundingType()
    {
        return $this->belongsTo(FundingType::class);
    }

    public function premiumPins()
    {
        return $this->hasMany(PremiumPin::class, 'premium_plan_id');
    }

    public function purchases()
    {
        return $this->hasMany(PremiumPurchase::class, 'premium_plan_id');
    }

    public function enrollees()
    {
        return $this->hasMany(Enrollee::class, 'premium_plan_id');
    }

    public function hasNoExpiry(): bool
    {
        return (bool) $this->has_no_expiry;
    }

    public function requiresPayment(): bool
    {
        return (bool) $this->payment_required;
    }

    public function isSelfEnrollmentEnabled(): bool
    {
        return (bool) $this->self_enrollment_enabled && $this->status === 'active';
    }

    public function isFamilyPlan(): bool
    {
        return (bool) $this->is_family_plan;
    }

    public function getEffectiveMaximumDependants(): int
    {
        return $this->isFamilyPlan() ? max(0, (int) ($this->maximum_dependants ?? 0)) : 0;
    }

    public function calculateCoverageStartDate(?Carbon $approvalDate = null): Carbon
    {
        $approvalDate ??= now();

        return $approvalDate->copy()->addDays(max(0, (int) ($this->waiting_period_days ?? 0)));
    }

    public function calculateCoverageEndDate(?Carbon $coverageStartDate = null): ?Carbon
    {
        if ($this->hasNoExpiry()) {
            return null;
        }

        $coverageStartDate ??= $this->calculateCoverageStartDate();
        $durationDays = max(1, (int) $this->duration_days);

        return $coverageStartDate->copy()->addDays($durationDays - 1)->endOfDay();
    }
}
