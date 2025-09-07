<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountDetail extends Model
{
    protected $table = 'account_details';

    protected $fillable = [
        'account_name',
        'account_number',
        'bank_id',
        'account_type',
        'status',
        'accountable_id',
        'accountable_type',
    ];

    public function accountable()
    {
        return $this->morphTo();
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
