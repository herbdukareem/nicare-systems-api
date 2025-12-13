<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ReferralDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'referral_id',
        'document_requirement_id',
        'document_type',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'original_filename',
        'uploaded_by',
        'is_validated',
        'validated_at',
        'validated_by',
        'validation_comments',
        'description',
        'is_required',
    ];

    protected $casts = [
        'is_validated' => 'boolean',
        'is_required' => 'boolean',
        'validated_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // ==================== Relationships ====================

    /**
     * Document belongs to a Referral
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class);
    }

    /**
     * Document belongs to a DocumentRequirement
     */
    public function documentRequirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class);
    }

    /**
     * Document was uploaded by a User
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Document was validated by a User
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // ==================== Accessors ====================

    /**
     * Get the full URL to the document
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get human-readable file size
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // ==================== Methods ====================

    /**
     * Mark document as validated
     */
    public function markAsValidated(User $validator, ?string $comments = null): void
    {
        $this->update([
            'is_validated' => true,
            'validated_at' => now(),
            'validated_by' => $validator->id,
            'validation_comments' => $comments,
        ]);
    }

    /**
     * Delete the document file from storage
     */
    public function deleteFile(): bool
    {
        if (Storage::disk('public')->exists($this->file_path)) {
            return Storage::disk('public')->delete($this->file_path);
        }

        return false;
    }

    /**
     * Override delete to also remove file from storage
     */
    public function delete()
    {
        $this->deleteFile();
        return parent::delete();
    }
}

