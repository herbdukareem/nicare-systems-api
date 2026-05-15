<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrolleeDuplicateFlag extends Model
{
    protected $table = 'enrollee_duplicate_flags';

    protected $fillable = [
        'enrollee_id',
        'matched_enrollee_id',
        'match_type',
        'flagged_by',
        'resolved',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved'    => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------------

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class, 'enrollee_id');
    }

    public function matchedEnrollee()
    {
        return $this->belongsTo(Enrollee::class, 'matched_enrollee_id');
    }

    public function flaggedBy()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
