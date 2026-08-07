<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCollectionAccount extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'expires_at' => 'datetime'];
    public function intent() { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
