<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Service selection type: 'bundle', 'direct', or null (none)
            $table->enum('service_selection_type', ['bundle', 'direct'])
                  ->nullable()
                  ->after('preliminary_diagnosis')
                  ->comment('Type of service selection: bundle (ServiceBundle), direct (FFS Service - CaseRecord), or null (none)');

            // Link to service bundle if bundle type is selected
            $table->foreignId('service_bundle_id')
                  ->nullable()
                  ->after('service_selection_type')
                  ->constrained('service_bundles')
                  ->onDelete('restrict')
                  ->comment('Selected service bundle for bundle-based referrals');

            // Link to case record if FFS service is selected
            $table->foreignId('case_record_id')
                  ->nullable()
                  ->after('service_bundle_id')
                  ->constrained('case_records')
                  ->onDelete('restrict')
                  ->comment('Selected FFS service for single-service referrals');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['service_bundle_id']);
            $table->dropForeign(['case_record_id']);
            $table->dropColumn(['service_selection_type', 'service_bundle_id', 'case_record_id']);
        });
    }
};

