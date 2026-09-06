<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NinVerificationAttempt extends Model
{
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CACHE_HIT = 'cache_hit';

    protected $fillable = [
        'enrollee_id',
        'user_id',
        'nin_hash',
        'provider_name',
        'channel',
        'status',
        'is_provider_request',
        'is_reconstructed',
        'provider_reference',
        'http_status',
        'failure_message',
        'source_record_key',
        'metadata',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'is_provider_request' => 'boolean',
        'is_reconstructed' => 'boolean',
        'metadata' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
