<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'designation_id',
        'department_id',
        'address',
        'status',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    public function accountDetails()
    {
        return $this->morphMany(AccountDetail::class, 'accountable');
    }

    public function employmentDetails()
    {
        return $this->morphMany(EmploymentDetail::class, 'employable');
    }

    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }
}
