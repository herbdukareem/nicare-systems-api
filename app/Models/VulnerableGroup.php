<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VulnerableGroup extends Model
{
    protected $table = 'vulnerable_groups';
    protected $primaryKey = 'id';
    protected $guarded  = [];

    // enrollees
    public function enrollees(){
        return $this->hasMany(Enrollee::class, 'vulnerable_group_id', 'id');
    }
}
