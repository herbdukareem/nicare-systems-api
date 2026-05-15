<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentGroup extends Model
{
    use HasFactory;

    protected $table = 'groups';
    protected $guarded = ['id'];

    public function benefactor()
    {
        return $this->belongsTo(Benefactor::class);
    }

    public function members()
    {
        return $this->belongsToMany(Enrollee::class, 'group_members', 'group_id', 'enrollee_id')
            ->withPivot(['member_number', 'role', 'status'])
            ->withTimestamps();
    }
}
