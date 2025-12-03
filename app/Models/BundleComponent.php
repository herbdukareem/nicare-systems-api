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
}