<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}
