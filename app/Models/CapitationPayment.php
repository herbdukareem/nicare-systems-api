<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitationPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'status' => 'integer',
    ];

    public function capitation()
    {
        return $this->belongsTo(Capitation::class);
    }

    public function fundingType()
    {
        return $this->belongsTo(FundingType::class);
    }
}
