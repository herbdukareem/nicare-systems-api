<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentOfficer extends Model
{
    use HasFactory;

    protected $table = 'enrollment_officers';

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'email',
        'phone',
        'department_id',
        'designation_id',
        'address',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'status' => 'boolean',
    ];

    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

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
}
