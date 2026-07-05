<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnrollmentFormSchema extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_REVOKED = 'revoked';

    protected $guarded = ['id'];

    protected $casts = [
        'benefactor_ids' => 'array',
        'nin_verification_policy' => 'array',
        'fields' => 'array',
        'rules' => 'array',
        'ui_schema' => 'array',
        'migration_hints' => 'array',
        'requires_nin_verification' => 'boolean',
        'allow_offline_capture' => 'boolean',
        'published_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function programme()
    {
        return $this->belongsTo(InsuranceProgramme::class, 'insurance_programme_id');
    }

    public function category()
    {
        return $this->belongsTo(EnrolleeCategory::class, 'enrollee_category_id');
    }

    public function plan()
    {
        return $this->belongsTo(PremiumPlan::class, 'premium_plan_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }
}
