<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_lines', function (Blueprint $table) {
            // Add service_type to differentiate between drugs, services, and bundle components
            $table->enum('service_type', ['drug', 'service', 'bundle_component'])
                  ->default('service')
                  ->after('claim_id')
                  ->comment('Type of service: drug, service (from case_records), or bundle_component');
            
            // Add drug_id for drug line items
            $table->foreignId('drug_id')
                  ->nullable()
                  ->after('service_type')
                  ->constrained('drugs')
                  ->onDelete('restrict')
                  ->comment('Link to drugs table when service_type is drug');
            
            // Add bundle_id to link to service bundles
            $table->foreignId('bundle_id')
                  ->nullable()
                  ->after('pa_code_id')
                  ->constrained('service_bundles')
                  ->onDelete('restrict')
                  ->comment('Link to service bundle for bundle claims');
            
            // Make case_record_id nullable since we now have drug_id as alternative
            $table->foreignId('case_record_id')
                  ->nullable()
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('claim_lines', function (Blueprint $table) {
            $table->dropForeign(['drug_id']);
            $table->dropForeign(['bundle_id']);
            $table->dropColumn(['service_type', 'drug_id', 'bundle_id']);
            
            // Restore case_record_id to not nullable
            $table->foreignId('case_record_id')
                  ->nullable(false)
                  ->change();
        });
    }
};

