<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollBatchEnrollee extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'raw_payload' => 'array',
        'employee_contribution' => 'decimal:2',
        'employer_contribution' => 'decimal:2',
    ];
}
