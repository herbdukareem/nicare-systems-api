<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClaimAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'treatment_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'document_type',
        'facility_stamped',
        'validated',
        'validated_at',
        'validated_by',
        'validation_comments',
        'uploaded_by',
    ];

    protected $casts = [
        'facility_stamped' => 'boolean',
        'validated' => 'boolean',
        'validated_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // Relationships
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(ClaimTreatment::class, 'treatment_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // Scopes
    public function scopeByDocumentType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeFacilityStamped($query)
    {
        return $query->where('facility_stamped', true);
    }

    public function scopeValidated($query)
    {
        return $query->where('validated', true);
    }

    public function scopePendingValidation($query)
    {
        return $query->where('validated', false);
    }

    // Methods
    public function getFileUrl(): string
    {
        return Storage::url($this->file_path);
    }

    public function getFileSizeFormatted(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function validate(User $user, string $comments = null): void
    {
        $this->update([
            'validated' => true,
            'validated_at' => now(),
            'validated_by' => $user->id,
            'validation_comments' => $comments,
        ]);

        // Log the validation
        ClaimAuditLog::create([
            'claim_id' => $this->claim_id,
            'action' => 'attachment_validated',
            'field_changed' => 'attachment_validation',
            'new_value' => 'validated',
            'reason' => 'Document validation',
            'comments' => $comments,
            'user_id' => $user->id,
            'user_role' => $user->roles->first()->name ?? 'unknown',
            'user_name' => $user->name,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'performed_at' => now(),
        ]);
    }

    public function delete()
    {
        // Delete the physical file
        if (Storage::exists($this->file_path)) {
            Storage::delete($this->file_path);
        }

        return parent::delete();
    }
}
