<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BundleComponent
 * Defines the individual items included within a ServiceBundle.
 */
class BundleComponent extends Model
{
    protected $guarded = ['id'];
    protected $table = 'bundle_components';

    /**
     * The component belongs to a Bundle.
     */
    public function serviceBundle()
    {
        return $this->belongsTo(ServiceBundle::class);
    }
    
    /**
     * The component references a specific CaseRecord (service/tariff item).
     */
    public function caseRecord()
    {
        return $this->belongsTo(CaseRecord::class, 'case_record_id');
    }

    protected $appends = ['item_name'];

    public function getItemNameAttribute()
    {
        // Try to load the CaseRecord and return its name.
        // Assumes the CaseRecord model has a 'name' or 'description' attribute.
        // Use a default value if the relationship is not loaded or doesn't exist.
        return $this->caseRecord->case_name ?? $this->caseRecord->service_description ?? 'Unknown Item';
    }
}