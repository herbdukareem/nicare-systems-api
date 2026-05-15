<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Benefactor
 *
 * Placeholder model representing a person or organisation that pays for an enrollee.
 */
class Benefactor extends Model
{
   protected $guarded = ['id'];
    protected $table = 'benefactors';

    /**
     * Benefactor has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }

    public function fundedEnrollees()
    {
        return $this->belongsToMany(Enrollee::class, 'benefactor_enrollees')
            ->withPivot(['premium_purchase_id', 'relationship', 'status'])
            ->withTimestamps();
    }

    public function purchases()
    {
        return $this->hasMany(PremiumPurchase::class);
    }

    public function enrollmentPhases()
    {
        return $this->hasMany(EnrollmentPhase::class);
    }

    public function payrollBatches()
    {
        return $this->hasMany(PayrollBatch::class);
    }

    public function groups()
    {
        return $this->hasMany(EnrollmentGroup::class);
    }
}
