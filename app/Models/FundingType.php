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
        'status',
    ];

    /**
     * Funding type has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
