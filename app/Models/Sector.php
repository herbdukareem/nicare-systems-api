<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    protected $table = 'sectors';
    protected $primaryKey = 'id';
    protected $guarded  = [];


    // enrollees
    public function enrollees(){
        return $this->hasMany(Enrollee::class, 'sector_id', 'id');
    }
    
}
