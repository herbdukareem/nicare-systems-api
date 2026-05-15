<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumPin extends Model
{
    use HasFactory;

    public const STATUS_GENERATED = 'generated';
    public const STATUS_SOLD = 'sold';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'sold_at' => 'datetime',
        'used_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan()
    {
        return $this->belongsTo(PremiumPlan::class, 'premium_plan_id');
    }

    public function insuranceProgramme()
    {
        return $this->belongsTo(InsuranceProgramme::class, 'insurance_programme_id');
    }

    public function benefitPackage()
    {
        return $this->belongsTo(BenefitPackage::class, 'benefit_package_id');
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class, 'lga_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function userable()
    {
        return $this->morphTo();
    }

    public function purchase()
    {
        return $this->belongsTo(PremiumPurchase::class, 'premium_purchase_id');
    }

    public function usedByEnrollee()
    {
        return $this->belongsTo(Enrollee::class, 'used_by_enrollee_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }
}
