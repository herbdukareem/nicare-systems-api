<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PACode extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'claim_submitted_at' => 'datetime',
        'approved_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    public function claims(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Claim::class, 'pa_code_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->orWhere('status', 'expired');
    }

    public function scopeUsed($query)
    {
        return $query->where('status', 'used');
    }

    // Accessors
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast() || $this->status === 'expired';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active' && !$this->is_expired;
    }

    public function getCanBeUsedAttribute(): bool
    {
        return $this->is_active && $this->usage_count < $this->max_usage;
    }

    // Methods
    public static function generatePACode(): string
    {
        $prefix = 'PA';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(6));

        return "{$prefix}{$timestamp}{$random}";
    }

    public static function generateUTN(): string
    {
        $prefix = 'UTN';
        $timestamp = now()->format('ymdHis');
        $random = mt_rand(1000, 9999);

        return "{$prefix}{$timestamp}{$random}";
    }

    public function markAsUsed(?string $claimReference = null): bool
    {
        $this->update([
            'status' => 'used',
            'used_at' => now(),
            'usage_count' => $this->usage_count + 1,
            'claim_reference' => $claimReference
        ]);

        return true;
    }

    public function expire(): bool
    {
        $this->update([
            'status' => 'expired'
        ]);

        return true;
    }

    public function cancel(): bool
    {
        $this->update([
            'status' => 'cancelled'
        ]);

        return true;
    }
}
