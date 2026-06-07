<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumPurchase extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'payer_details' => 'array',
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(PremiumPlan::class, 'premium_plan_id');
    }

    public function benefactor()
    {
        return $this->belongsTo(Benefactor::class);
    }

    public function fundingType()
    {
        return $this->belongsTo(FundingType::class);
    }

    public function group()
    {
        return $this->belongsTo(EnrollmentGroup::class, 'group_id');
    }

    public function pins()
    {
        return $this->hasMany(PremiumPin::class);
    }
}
