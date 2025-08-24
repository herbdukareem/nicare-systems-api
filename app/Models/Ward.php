<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Ward
 *
 * Represents a ward within an LGA.
 */
class Ward extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'wards';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'lga_id',
        'enrollment_cap',
        'total_enrolled',
        'settlement_type',
        'status',
    ];

    /**
     * A ward belongs to an LGA.
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * A ward has many facilities.
     */
    public function facilities()
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * A ward has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
