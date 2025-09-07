<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PremiumType extends Model
{
    protected $table = 'premium_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
    ];

    public function premiums()
    {
        return $this->hasMany(Premium::class, 'premium_type_id');
    }
}
