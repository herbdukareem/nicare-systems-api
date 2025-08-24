<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Lga
 *
 * Represents a Local Government Area (LGA).
 */
class Lga extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lgas';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'zone',
        'baseline',
        'total_enrolled',
        'status',
    ];

    protected $casts = [
        'baseline' => 'integer',
        'total_enrolled' => 'integer',
    ];

    /**
     * A lga has many wards.
     */
    public function wards()
    {
        return $this->hasMany(Ward::class);
    }

    /**
     * A lga has many facilities.
     */
    public function facilities()
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * A lga has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
