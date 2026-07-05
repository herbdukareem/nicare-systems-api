<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NinVerificationCache extends Model
{
    protected $fillable = [
        'nin',
        'provider_name',
        'provider_data',
        'raw_response',
        'verified_at',
        'last_used_at',
        'hit_count',
    ];

    protected $casts = [
        'provider_data' => 'array',
        'raw_response' => 'array',
        'verified_at' => 'datetime',
        'last_used_at' => 'datetime',
        'hit_count' => 'integer',
    ];
}
