<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Bank
 *
 * Stores information about financial institutions that can be used by facilities or enrollees.
 */
class Bank extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'banks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
   protected $guarded = ['id'];

    /**
     * A bank may have many account details associated with it.
     */
    public function accountDetails()
    {
        return $this->hasMany(AccountDetail::class);
    }
}
