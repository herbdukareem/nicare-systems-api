<?php
// app/Models/Claim.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_REVIEWING = 'REVIEWING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    /**
     * Statuses from which the claim is immutable (facility cannot edit).
     */
    public const IMMUTABLE_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_REVIEWING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * Returns true when the claim can no longer be edited by the submitting facility.
     */
    public function isImmutable(): bool
    {
        return in_array($this->status, self::IMMUTABLE_STATUSES, true);
    }

    protected $guarded = ['id'];

    protected $casts = [
        'service_date' => 'datetime',
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

    public function claimLineItems(): HasMany
    {
        return $this->hasMany(ClaimLine::class);
    }

    public function claimBundleComponents(): HasMany
    {
        return $this->hasMany(BundleComponent::class);
    }

    public function serviceBundleComponents()
    {
        if (!$this->referral || !$this->referral->serviceBundle) {
            return \App\Models\BundleComponent::query()->whereRaw('1 = 0');
        }

        return $this->referral->serviceBundle->components();
    }

    public function claimPACodes(): HasMany
    {
        return $this->hasMany(PACode::class);
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

    /**
     * User who reviewed the claim.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Claim belongs to a payment batch.
     */
    public function paymentBatch(): BelongsTo
    {
        return $this->belongsTo(ClaimPaymentBatch::class, 'payment_batch_id');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
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
