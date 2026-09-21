<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateNinVerificationItem extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected $casts = [
        'provider_data' => 'array',
        'comparison_summary' => 'array',
        'verified_at' => 'datetime',
        'applied_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(DuplicateNinVerificationBatch::class, 'batch_id');
    }

    public function candidates()
    {
        return $this->hasMany(DuplicateNinVerificationCandidate::class, 'item_id');
    }

    public function matchedEnrollee()
    {
        return $this->belongsTo(Enrollee::class, 'matched_enrollee_id');
    }
}
