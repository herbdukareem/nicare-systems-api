<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficerEnrollmentAssignment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'enabled' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    public function officer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function schema()
    {
        return $this->belongsTo(EnrollmentFormSchema::class, 'enrollment_form_schema_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
