<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BundleComponent
 * Defines the individual items included within a Bundle (CaseRecord with is_bundle=true).
 * Links a bundle to its component services/items.
 */
class BundleComponent extends Model
{
    protected $guarded = ['id'];
    protected $table = 'bundle_components';

    /**
     * The component belongs to a Bundle (CaseRecord where is_bundle = true).
     * service_bundle_id references case_records.id
     */
    public function serviceBundle()
    {
        return $this->belongsTo(CaseRecord::class, 'service_bundle_id')
                    ->where('is_bundle', true);
    }

    /**
     * The component references a specific CaseRecord (service/tariff item).
     * case_record_id references case_records.id where is_bundle = false
     */
    public function caseRecord()
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }

    protected $appends = ['item_name'];

    public function getItemNameAttribute()
    {
        // Try to load the CaseRecord and return its name.
        // Assumes the CaseRecord model has a 'case_name' or 'service_description' attribute.
        // Use a default value if the relationship is not loaded or doesn't exist.
        return $this->caseRecord->case_name ?? $this->caseRecord->service_description ?? 'Unknown Item';
    }
}