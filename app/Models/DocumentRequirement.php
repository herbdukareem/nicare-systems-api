<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentRequirement extends Model
{
    use HasFactory, SoftDeletes;

    // Request types
    public const REQUEST_TYPE_REFERRAL = 'referral';
    public const REQUEST_TYPE_PA_CODE = 'pa_code';

    protected $fillable = [
        'request_type',
        'document_type',
        'name',
        'description',
        'is_required',
        'allowed_file_types',
        'max_file_size_mb',
        'display_order',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'status' => 'boolean',
        'max_file_size_mb' => 'integer',
        'display_order' => 'integer',
    ];

    protected $attributes = [
        'status' => true,
        'is_required' => false,
        'max_file_size_mb' => 5,
        'allowed_file_types' => 'pdf,jpg,jpeg,png',
    ];

    // ==================== Relationships ====================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ==================== Scopes ====================

    /**
     * Scope to filter by request type
     */
    public function scopeForRequestType(Builder $query, string $requestType): Builder
    {
        return $query->where('request_type', $requestType);
    }

    /**
     * Scope for referral documents
     */
    public function scopeForReferral(Builder $query): Builder
    {
        return $query->forRequestType(self::REQUEST_TYPE_REFERRAL);
    }

    /**
     * Scope for PA code documents
     */
    public function scopeForPACode(Builder $query): Builder
    {
        return $query->forRequestType(self::REQUEST_TYPE_PA_CODE);
    }

    /**
     * Scope for active requirements only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /**
     * Scope for required documents only
     */
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope for optional documents only
     */
    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_required', false);
    }

    /**
     * Scope ordered by display_order
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('name');
    }

    // ==================== Static Query Methods ====================

    /**
     * Get all document requirements for referral requests
     */
    public static function getReferralRequirements(): \Illuminate\Database\Eloquent\Collection
    {
        return self::forReferral()->active()->ordered()->get();
    }

    /**
     * Get all document requirements for PA code requests
     */
    public static function getPACodeRequirements(): \Illuminate\Database\Eloquent\Collection
    {
        return self::forPACode()->active()->ordered()->get();
    }

    /**
     * Get required documents for a request type
     */
    public static function getRequiredDocuments(string $requestType): \Illuminate\Database\Eloquent\Collection
    {
        return self::forRequestType($requestType)->active()->required()->ordered()->get();
    }

    /**
     * Get optional documents for a request type
     */
    public static function getOptionalDocuments(string $requestType): \Illuminate\Database\Eloquent\Collection
    {
        return self::forRequestType($requestType)->active()->optional()->ordered()->get();
    }

    // ==================== Accessors ====================

    /**
     * Get allowed file types as array
     */
    public function getAllowedFileTypesArrayAttribute(): array
    {
        return array_map('trim', explode(',', $this->allowed_file_types));
    }

    /**
     * Get max file size in bytes
     */
    public function getMaxFileSizeBytesAttribute(): int
    {
        return $this->max_file_size_mb * 1024 * 1024;
    }
}

