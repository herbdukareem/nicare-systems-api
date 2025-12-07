<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ClaimAlert Model
 * 
 * Stores validation alerts generated during claim processing
 */
class ClaimAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'alert_type',
        'alert_code',
        'message',
        'action',
        'severity',
        'resolved',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    // Constants for alert types
    const TYPE_CRITICAL = 'CRITICAL';
    const TYPE_WARNING = 'WARNING';
    const TYPE_INFO = 'INFO';

    // Constants for alert codes
    const CODE_DOUBLE_BUNDLE = 'DOUBLE_BUNDLE';
    const CODE_UNAUTHORIZED_FFS_TOP_UP = 'UNAUTHORIZED_FFS_TOP_UP';
    const CODE_MISSING_COMPLICATION_PA = 'MISSING_COMPLICATION_PA';
    const CODE_COMPLICATION_FFS_REVIEW = 'COMPLICATION_FFS_REVIEW';

    // Constants for actions
    const ACTION_REJECT_CLAIM = 'REJECT_CLAIM';
    const ACTION_REJECT_FFS_LINES = 'REJECT_FFS_LINES';
    const ACTION_RESOLVE_ALERT = 'RESOLVE_ALERT';

    /**
     * Claim that this alert belongs to
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    /**
     * User who resolved this alert
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeUnresolved($query)
    {
        return $query->where('resolved', false);
    }

    public function scopeResolved($query)
    {
        return $query->where('resolved', true);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', self::TYPE_CRITICAL);
    }

    public function scopeWarning($query)
    {
        return $query->where('severity', self::TYPE_WARNING);
    }

    public function scopeInfo($query)
    {
        return $query->where('severity', self::TYPE_INFO);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('alert_code', $code);
    }
}

