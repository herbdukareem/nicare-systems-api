<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCollectionEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime'];
    public function intent() { return $this->belongsTo(PaymentIntent::class, 'payment_intent_id'); }
}
