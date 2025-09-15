<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SecurityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'details',
        'severity',
        'user_id',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'details' => 'array',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user associated with this security log
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who resolved this security log
     */
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Scope for unresolved security logs
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * Scope for resolved security logs
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * Scope for high severity logs
     */
    public function scopeHighSeverity($query)
    {
        return $query->where('severity', 'high');
    }

    /**
     * Scope for medium severity logs
     */
    public function scopeMediumSeverity($query)
    {
        return $query->where('severity', 'medium');
    }

    /**
     * Scope for low severity logs
     */
    public function scopeLowSeverity($query)
    {
        return $query->where('severity', 'low');
    }

    /**
     * Scope for specific event types
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for logs from specific IP
     */
    public function scopeFromIp($query, $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Mark this security log as resolved
     */
    public function markAsResolved($userId = null)
    {
        $this->update([
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
    }

    /**
     * Check if this log is resolved
     */
    public function isResolved()
    {
        return !is_null($this->resolved_at);
    }

    /**
     * Get severity color for UI
     */
    public function getSeverityColorAttribute()
    {
        switch ($this->severity) {
            case 'high':
                return 'error';
            case 'medium':
                return 'warning';
            case 'low':
                return 'info';
            default:
                return 'grey';
        }
    }

    /**
     * Get formatted severity label
     */
    public function getSeverityLabelAttribute()
    {
        return ucfirst($this->severity);
    }

    /**
     * Get formatted type label
     */
    public function getTypeLabelAttribute()
    {
        return str_replace('_', ' ', ucwords($this->type, '_'));
    }
}
