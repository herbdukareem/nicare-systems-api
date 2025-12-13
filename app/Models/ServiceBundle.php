<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ServiceBundle
 * Defines the composition and fixed price of a standard bundled service.
 * Each bundle is based on a CaseRecord and has a fixed price with optional ICD-10 code.
 */
class ServiceBundle extends Model
{
    protected $guarded = ['id'];
    protected $table = 'service_bundles';

    protected $casts = [
        'fixed_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * A Bundle belongs to a CaseRecord (the base service/item).
     */
    public function caseRecord()
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }

    /**
     * A Bundle has many components (services, drugs, labs).
     */
    public function components()
    {
        return $this->hasMany(BundleComponent::class);
    }

    /**
     * Get the bundle name from the case record if not set.
     */
    public function getNameAttribute($value)
    {
        return $value ?? $this->caseRecord?->case_name;
    }

    /**
     * Get the bundle code from the case record if not set.
     */
    public function getCodeAttribute($value)
    {
        return $value ?? $this->caseRecord?->nicare_code;
    }

    /**
     * Find a service bundle by ICD-10 diagnosis code
     *
     * @param string $icdCode The ICD-10 diagnosis code
     * @param string|null $levelOfCare Optional level of care filter
     * @return ServiceBundle|null
     */
    public static function findByDiagnosis(string $icdCode, ?string $levelOfCare = null): ?self
    {
        $query = self::where('is_active', true)
            ->where(function ($q) use ($icdCode) {
                $q->where('diagnosis_icd10', $icdCode)
                  ->orWhereRaw("? LIKE CONCAT(diagnosis_icd10, '%')", [$icdCode]);
            });

        // Note: service_bundles table doesn't have level_of_care column
        // If you need to filter by level of care, you'll need to add that column
        // For now, we'll ignore the $levelOfCare parameter

        return $query->first();
    }

    /**
     * Scope to get only active service bundles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}