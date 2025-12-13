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

    protected $table = 'service_bundles';

    protected $fillable = [
        'bundle_code',
        'bundle_name',
        'name',
        'description',
        'case_category_id',
        'case_record_id',
        'icd10_code',           // ICD-10 code that triggers this bundle
        'diagnosis_icd10',      // Alternative column name
        'bundle_tariff',        // Fixed price for the bundle
        'fixed_price',          // Alternative column name
        'level_of_care',
        'status',
        'is_active',            // Alternative column name
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected $casts = [
        'bundle_tariff' => 'decimal:2',
        'fixed_price' => 'decimal:2',
        'status' => 'boolean',
        'is_active' => 'boolean',
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
        $active = $this->is_active ?? $this->status ?? false;
        if (!$active) {
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
        $diagnosisCode = $this->diagnosis_icd10 ?? $this->icd10_code;
        if (!$diagnosisCode) {
            return false;
        }
        return str_starts_with($icdCode, $diagnosisCode) ||
               $diagnosisCode === $icdCode;
    }

    /**
     * Find a bundle by ICD-10 code and optionally level of care
     */
    public static function findByDiagnosis(string $icdCode, ?string $levelOfCare = null): ?self
    {
        $query = self::where('is_active', true)
            ->where(function ($q) use ($icdCode) {
                $q->where('diagnosis_icd10', $icdCode)
                  ->orWhereRaw("? LIKE CONCAT(diagnosis_icd10, '%')", [$icdCode]);
            });

        if ($levelOfCare) {
            $query->where('level_of_care', $levelOfCare);
        }

        // Check effective dates (only if columns exist in the table)
        // Note: service_bundles table doesn't have effective_from/effective_to columns
        // So we skip this check for now
        // $query->where(function ($q) {
        //     $q->whereNull('effective_from')
        //       ->orWhere('effective_from', '<=', now());
        // })->where(function ($q) {
        //     $q->whereNull('effective_to')
        //       ->orWhere('effective_to', '>=', now());
        // });

        return $query->first();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
        // Note: service_bundles table doesn't have effective_from/effective_to columns
        // If you add them later, uncomment the following:
        // ->where(function ($q) {
        //     $q->whereNull('effective_from')
        //       ->orWhere('effective_from', '<=', now());
        // })
        // ->where(function ($q) {
        //     $q->whereNull('effective_to')
        //       ->orWhere('effective_to', '>=', now());
        // });
    }
}

