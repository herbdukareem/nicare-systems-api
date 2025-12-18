<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Expand the feedback_type ENUM to include more comprehensive feedback types
     * for better categorization of feedback records in the NiCare system.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the ENUM column
        DB::statement("ALTER TABLE feedback_records MODIFY COLUMN feedback_type ENUM(
            'referral',
            'pa_code',
            'general',
            'enrollee_verification',
            'service_delivery',
            'claims_guidance',
            'medical_history',
            'complaint',
            'utn_validation',
            'facility_coordination',
            'document_verification',
            'treatment_progress'
        ) NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original ENUM values
        // Note: This will fail if any records use the new types
        DB::statement("ALTER TABLE feedback_records MODIFY COLUMN feedback_type ENUM(
            'referral',
            'pa_code',
            'general'
        ) NOT NULL");
    }
};

