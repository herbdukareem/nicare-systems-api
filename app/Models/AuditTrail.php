<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class AuditTrail
 *
 * Records actions taken on enrollee records for accountability.
 */
class AuditTrail extends Model
{
    protected $table = 'audit_trails';

    protected $fillable = [
        'enrollee_id',
        'action',
        'description',
        'user_id',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * The enrollee that this audit trail belongs to.
     */
    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    /**
     * The user who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
