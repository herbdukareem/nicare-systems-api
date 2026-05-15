<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileSyncRecord extends Model
{
    use SoftDeletes;

    protected $table = 'mobile_sync_records';

    protected $fillable = [
        'sync_batch_id',
        'device_id',
        'officer_user_id',
        'payload',
        'status',
        'enrollee_id',
        'duplicate_of_enrollee_id',
        'failure_reason',
        'synced_at',
        'ip_address',
    ];

    protected $casts = [
        'payload'    => 'array',
        'synced_at'  => 'datetime',
    ];

    // ---- Relationships -------------------------------------------------------

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class, 'enrollee_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Enrollee::class, 'duplicate_of_enrollee_id');
    }
}
