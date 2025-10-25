<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeskOfficer extends Model
{
    use HasFactory;

    protected $table = 'desk_officers';

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

    /**
     * Get the user that owns this desk officer record.
     */
    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

    /**
     * Get the department that this desk officer belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation of this desk officer.
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Get the account details for this desk officer.
     */
    public function accountDetails()
    {
        return $this->morphMany(AccountDetail::class, 'accountable');
    }

    /**
     * Get the employment details for this desk officer.
     */
    public function employmentDetails()
    {
        return $this->morphMany(EmploymentDetail::class, 'employable');
    }
}
