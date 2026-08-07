<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentIntent extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
    protected $casts = ['payer' => 'array', 'metadata' => 'array', 'amount_due' => 'decimal:2', 'amount_received' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function payable(): MorphTo { return $this->morphTo(); }
    public function accounts() { return $this->hasMany(PaymentCollectionAccount::class); }
    public function events() { return $this->hasMany(PaymentCollectionEvent::class); }
}
