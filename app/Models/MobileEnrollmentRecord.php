<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MobileEnrollmentRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PENDING_NIN = 'pending_nin';
    public const STATUS_NIN_FAILED = 'nin_failed';
    public const STATUS_DUPLICATE_SUSPECTED = 'duplicate_suspected';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_REQUIRES_REVIEW = 'requires_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SYNC_FAILED = 'sync_failed';

    public const LOCAL_STATUSES = [
        'draft_local',
        'queued_for_sync',
        'syncing',
        'sync_failed',
        self::STATUS_RECEIVED,
        self::STATUS_PENDING_NIN,
        self::STATUS_NIN_FAILED,
        self::STATUS_DUPLICATE_SUSPECTED,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_REQUIRES_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'core_data' => 'array',
        'extra_fields' => 'array',
        'migration_hints' => 'array',
        'nin_verification_policy' => 'array',
        'nin_verified_data' => 'array',
        'nin_autofill_changes' => 'array',
        'nin_conflicts' => 'array',
        'verified_field_edit_reasons' => 'array',
        'location_capture_policy' => 'array',
        'location_payload' => 'array',
        'captured_at' => 'datetime',
        'received_at' => 'datetime',
        'nin_verified_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function officer()
    {
        return $this->belongsTo(User::class, 'officer_user_id');
    }

    public function device()
    {
        return $this->belongsTo(OfficerDevice::class, 'officer_device_id');
    }

    public function schema()
    {
        return $this->belongsTo(EnrollmentFormSchema::class, 'enrollment_form_schema_id');
    }

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function duplicateOf()
    {
        return $this->belongsTo(Enrollee::class, 'duplicate_of_enrollee_id');
    }

    public function attachments()
    {
        return $this->hasMany(MobileEnrollmentAttachment::class);
    }
}
