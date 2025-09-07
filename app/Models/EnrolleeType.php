<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class EnrolleeType
 *
 * Represents a type or category of enrollee (e.g., informal, formal, principal).
 */
class EnrolleeType extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'enrollee_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'premium_duration_months' => 'integer',
        'premium_amount' => 'decimal:2',
    ];

    /**
     * Get the enrollees for this type.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
