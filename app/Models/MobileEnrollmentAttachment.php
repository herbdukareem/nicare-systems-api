<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileEnrollmentAttachment extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function record()
    {
        return $this->belongsTo(MobileEnrollmentRecord::class, 'mobile_enrollment_record_id');
    }

    public function enrollee()
    {
        return $this->belongsTo(Enrollee::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
