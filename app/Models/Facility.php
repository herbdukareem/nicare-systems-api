<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Facility
 *
 * Represents a healthcare facility/provider.
 */
class Facility extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'facilities';

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'hcp_code',
        'name',
        'category',
        'type',
        'level',
        'address',
        'phone',
        'email',
        'lga_id',
        'ward_id',
        'capacity',
        'status',
        'account_detail_id',
    ];

    /**
     * Facility belongs to an LGA.
     */
    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    /**
     * Facility belongs to a Ward.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Facility may have an account detail.
     */
    public function accountDetail()
    {
        return $this->belongsTo(AccountDetail::class);
    }

    /**
     * Facility has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
