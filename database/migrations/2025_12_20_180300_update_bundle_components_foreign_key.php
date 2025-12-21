<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates the bundle_components table to reference case_records instead of service_bundles.
     * This aligns with the new architecture where bundles are case records with is_bundle = true.
     */
    public function up(): void
    {
        Schema::table('bundle_components', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['service_bundle_id']);
            
            // Add new foreign key constraint pointing to case_records
            $table->foreign('service_bundle_id')
                  ->references('id')
                  ->on('case_records')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bundle_components', function (Blueprint $table) {
            // Drop the case_records foreign key
            $table->dropForeign(['service_bundle_id']);
            
            // Restore the old service_bundles foreign key
            $table->foreign('service_bundle_id')
                  ->references('id')
                  ->on('service_bundles')
                  ->onDelete('cascade');
        });
    }
};

