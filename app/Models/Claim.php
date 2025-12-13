<?php
// app/Models/Claim.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Claim extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'claim_date' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'payment_processed_at' => 'datetime',
        'bundle_amount' => 'decimal:2',
        'ffs_amount' => 'decimal:2',
        'total_amount_claimed' => 'decimal:2',
    ];

    /**
     * Claim belongs to an Enrollee.
     */
    public function enrollee(): BelongsTo
    {
        return $this->belongsTo(Enrollee::class);
    }

    /**
     * Claim belongs to the submitting Facility.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Claim belongs to an Admission (OPTIONAL - can be null for FFS-only claims).
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Claim belongs to a Referral (REQUIRED - all claims must be linked to a referral with validated UTN).
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /**
     * Claim has many line items.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(ClaimLine::class);
    }

    /**
     * Claim has many alerts.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(ClaimAlert::class);
    }

    /**
     * Claim has many status history records.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ClaimStatusHistory::class);
    }

    /**
     * User who approved the claim.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who rejected the claim.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * User who submitted the claim.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'REJECTED');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'SUBMITTED');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }

    /**
     * Generate a unique claim number
     */
    public static function generateClaimNumber(): string
    {
        $prefix = 'CLM';
        $timestamp = now()->format('YmdHis');
        $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        return "{$prefix}-{$timestamp}-{$random}";
    }
}