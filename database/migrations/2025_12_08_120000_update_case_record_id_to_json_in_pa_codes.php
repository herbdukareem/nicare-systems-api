<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['case_record_id']);
            
            // Drop the column
            $table->dropColumn('case_record_id');
        });

        Schema::table('pa_codes', function (Blueprint $table) {
            // Add JSON column for multiple case record IDs
            $table->json('case_record_ids')
                  ->nullable()
                  ->after('service_bundle_id')
                  ->comment('Array of selected direct service IDs for multi-service FU-PA codes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            $table->dropColumn('case_record_ids');
        });

        Schema::table('pa_codes', function (Blueprint $table) {
            $table->foreignId('case_record_id')
                  ->nullable()
                  ->after('service_bundle_id')
                  ->constrained('case_records')
                  ->onDelete('restrict')
                  ->comment('Selected direct service for single-service FU-PA codes');
        });
    }
};

