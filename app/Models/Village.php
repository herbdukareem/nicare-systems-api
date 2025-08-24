<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Village
 *
 * Represents a village linked to a ward.
 */
class Village extends Model
{
    protected $table = 'villages';

    protected $fillable = [
        'name',
        'ward_id',
        'status',
    ];

    /**
     * Village belongs to a ward.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }
}
