<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClaimStatusHistory Model
 * 
 * Tracks all status changes for a claim
 */
class ClaimStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'claim_status_history';

    protected $fillable = [
        'claim_id',
        'old_status',
        'new_status',
        'changed_by',
        'changed_at',
        'reason',
        'notes',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    // Constants for statuses
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_REVIEWING = 'REVIEWING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Claim that this history belongs to
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * User who made the status change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // Scopes
    public function scopeForClaim($query, $claimId)
    {
        return $query->where('claim_id', $claimId)->orderBy('changed_at', 'desc');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('new_status', $status);
    }

    public function scopeApprovals($query)
    {
        return $query->where('new_status', self::STATUS_APPROVED);
    }

    public function scopeRejections($query)
    {
        return $query->where('new_status', self::STATUS_REJECTED);
    }
}

