<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OfficerDevice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mobileEnrollmentRecords()
    {
        return $this->hasMany(MobileEnrollmentRecord::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
