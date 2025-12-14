<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimPaymentBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'total_bundle_amount' => 'decimal:2',
        'total_ffs_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'processed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'PENDING';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_PAID = 'PAID';
    const STATUS_FAILED = 'FAILED';

    /**
     * Batch belongs to a facility (null for all facilities)
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Batch has many claims
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'payment_batch_id');
    }

    /**
     * User who created the batch
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who processed the batch
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * User who paid the batch
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Generate a unique batch number
     */
    public static function generateBatchNumber(): string
    {
        $prefix = 'BATCH';
        $timestamp = now()->format('YmdHis');
        $random = str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
        return "{$prefix}-{$timestamp}-{$random}";
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', self::STATUS_PROCESSING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForMonth($query, string $month)
    {
        return $query->where('batch_month', $month);
    }

    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }
}

