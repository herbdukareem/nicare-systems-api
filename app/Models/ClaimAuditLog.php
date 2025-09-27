<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'reason',
        'comments',
        'user_id',
        'user_role',
        'user_name',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    // Relationships
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('user_role', $role);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('performed_at', '>=', now()->subDays($days));
    }

    // Methods
    public static function logClaimAction(
        int $claimId,
        string $action,
        User $user,
        string $fieldChanged = null,
        string $oldValue = null,
        string $newValue = null,
        string $reason = null,
        string $comments = null
    ): self {
        return static::create([
            'claim_id' => $claimId,
            'action' => $action,
            'field_changed' => $fieldChanged,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'comments' => $comments,
            'user_id' => $user->id,
            'user_role' => $user->roles->first()->name ?? 'unknown',
            'user_name' => $user->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }

    public function getActionDisplayName(): string
    {
        $actions = [
            'created' => 'Claim Created',
            'updated' => 'Claim Updated',
            'submitted' => 'Claim Submitted',
            'doctor_approved' => 'Doctor Approved',
            'doctor_rejected' => 'Doctor Rejected',
            'pharmacist_approved' => 'Pharmacist Approved',
            'pharmacist_rejected' => 'Pharmacist Rejected',
            'claim_reviewed' => 'Claim Reviewed',
            'claim_confirmed' => 'Claim Confirmed',
            'claim_approved' => 'Claim Approved',
            'claim_rejected' => 'Claim Rejected',
            'diagnosis_validated' => 'Diagnosis Validated',
            'treatment_validated' => 'Treatment Validated',
            'attachment_validated' => 'Attachment Validated',
        ];

        return $actions[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }
}
