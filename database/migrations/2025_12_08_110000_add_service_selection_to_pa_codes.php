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
            $table->enum('service_selection_type', ['bundle', 'direct'])
                  ->nullable()
                  ->after('type')
                  ->comment('Type of service selection: bundle (ServiceBundle), direct (CaseRecord), or null (FFS items only)');
            
            $table->foreignId('service_bundle_id')
                  ->nullable()
                  ->after('service_selection_type')
                  ->constrained('service_bundles')
                  ->onDelete('restrict')
                  ->comment('Selected service bundle for bundle-based FU-PA codes');
            
            $table->foreignId('case_record_id')
                  ->nullable()
                  ->after('service_bundle_id')
                  ->constrained('case_records')
                  ->onDelete('restrict')
                  ->comment('Selected direct service for single-service FU-PA codes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            $table->dropForeign(['service_bundle_id']);
            $table->dropForeign(['case_record_id']);
            $table->dropColumn(['service_selection_type', 'service_bundle_id', 'case_record_id']);
        });
    }
};

