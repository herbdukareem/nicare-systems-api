<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Simplified Bundle Model
 *
 * Maps ICD-10 diagnosis codes to fixed bundle tariffs.
 * Used for episode-based (bundled) payments.
 */
class Bundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'bundle_code',
        'bundle_name',
        'description',
        'case_category_id',
        'icd10_code',           // ICD-10 code that triggers this bundle
        'bundle_tariff',        // Fixed price for the bundle
        'level_of_care',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'bundle_tariff' => 'decimal:2',
        'status' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    // Relationships
    public function caseCategory(): BelongsTo
    {
        return $this->belongsTo(CaseCategory::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper Methods
    public function isActive(): bool
    {
        if (!$this->status) {
            return false;
        }

        $now = now()->toDateString();

        if ($this->effective_from && $this->effective_from > $now) {
            return false;
        }

        if ($this->effective_to && $this->effective_to < $now) {
            return false;
        }

        return true;
    }

    /**
     * Check if this bundle matches a given ICD-10 code
     * Supports both exact match and prefix match (e.g., O82 matches O82.0)
     */
    public function matchesDiagnosis(string $icdCode): bool
    {
        if (!$this->icd10_code) {
            return false;
        }
        return str_starts_with($icdCode, $this->icd10_code) ||
               $this->icd10_code === $icdCode;
    }

    /**
     * Find a bundle by ICD-10 code and optionally level of care
     */
    public static function findByDiagnosis(string $icdCode, ?string $levelOfCare = null): ?self
    {
        $query = self::where('status', true)
            ->where(function ($q) use ($icdCode) {
                $q->where('icd10_code', $icdCode)
                  ->orWhereRaw("? LIKE CONCAT(icd10_code, '%')", [$icdCode]);
            });

        if ($levelOfCare) {
            $query->where('level_of_care', $levelOfCare);
        }

        // Check effective dates
        $query->where(function ($q) {
            $q->whereNull('effective_from')
              ->orWhere('effective_from', '<=', now());
        })->where(function ($q) {
            $q->whereNull('effective_to')
              ->orWhere('effective_to', '>=', now());
        });

        return $query->first();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')
                  ->orWhere('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
    }
}

