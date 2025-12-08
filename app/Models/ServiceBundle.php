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
}