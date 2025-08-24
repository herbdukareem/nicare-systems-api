<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AccountDetail
 *
 * Polymorphic model that allows various entities to have a bank account.
 * Uses the morphs helper in the migration to create accountable_id and accountable_type columns.
 */
class AccountDetail extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_details';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_name',
        'account_number',
        'bank_id',
        'account_type',
        'status',
    ];

    /**
     * Get the owning model of this account detail.
     */
    public function accountable()
    {
        return $this->morphTo();
    }

    /**
     * Bank associated with this account.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
