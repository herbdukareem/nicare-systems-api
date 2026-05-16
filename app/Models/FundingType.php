<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class FundingType
 *
 * Placeholder model representing how an enrollee is funded (e.g., self, employer, government).
 */
class FundingType extends Model
{
    protected $table = 'funding_types';

    protected $fillable = [
        'name',
        'description',
        'capitation_rate',
        'status',
    ];

    protected $casts = [
        'capitation_rate' => 'decimal:2',
        'status' => 'integer',
    ];

    /**
     * Funding type has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }

    public function payrollBatches()
    {
        return $this->hasMany(PayrollBatch::class);
    }

    public function capitations()
    {
        return $this->hasMany(Capitation::class);
    }

    public function capitationDetails()
    {
        return $this->hasMany(CapitationDetail::class);
    }
}
