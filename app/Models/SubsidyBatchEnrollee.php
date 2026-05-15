<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubsidyBatchEnrollee extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'vulnerability_verified' => 'boolean',
        'raw_payload' => 'array',
    ];
}
