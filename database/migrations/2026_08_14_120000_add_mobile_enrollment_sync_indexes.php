<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table): void {
            // Covers NIN duplicate checks while retaining the status filter.
            $table->index(['nin', 'status'], 'enrollees_nin_status_index');

            // Covers the name/DOB duplicate candidate lookup.
            $table->index(['facility_id', 'date_of_birth', 'sex', 'status'], 'enrollees_duplicate_candidate_index');
        });

        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            // Used by per-device batch and status refreshes.
            $table->index(['officer_device_id', 'sync_batch_id'], 'mobile_enrollment_device_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_enrollment_records', function (Blueprint $table): void {
            $table->dropIndex('mobile_enrollment_device_batch_index');
        });

        Schema::table('enrollees', function (Blueprint $table): void {
            $table->dropIndex('enrollees_duplicate_candidate_index');
            $table->dropIndex('enrollees_nin_status_index');
        });
    }
};
