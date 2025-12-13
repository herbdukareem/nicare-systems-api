<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add bundle_component_id to claim_lines table to track actual amounts
     * for each bundle component in a claim.
     */
    public function up(): void
    {
        Schema::table('claim_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('claim_lines', 'bundle_component_id')) {
                $table->foreignId('bundle_component_id')
                      ->nullable()
                      ->after('bundle_id')
                      ->constrained('bundle_components')
                      ->onDelete('restrict')
                      ->comment('Link to specific bundle component for bundle claims');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_lines', function (Blueprint $table) {
            if (Schema::hasColumn('claim_lines', 'bundle_component_id')) {
                $table->dropForeign(['bundle_component_id']);
                $table->dropColumn('bundle_component_id');
            }
        });
    }
};
