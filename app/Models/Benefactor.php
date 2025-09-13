<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Benefactor
 *
 * Placeholder model representing a person or organisation that pays for an enrollee.
 */
class Benefactor extends Model
{
   protected $guarded = ['id'];
    protected $table = 'benefactors';

    /**
     * Benefactor has many enrollees.
     */
    public function enrollees()
    {
        return $this->hasMany(Enrollee::class);
    }
}
