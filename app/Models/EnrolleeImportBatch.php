<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrolleeImportBatch extends Model
{
    protected $table = 'enrollee_import_batches';

    protected $fillable = [
        'uploaded_by',
        'file_path',
        'status',
        'total_rows',
        'imported_count',
        'duplicate_count',
        'failed_count',
        'errors',
    ];

    protected $casts = [
        'errors' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
