<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Simplify claims tables:
     * - Add admission_id to claims (required link to UTN)
     * - Add item_type and pa_code_id to claim_treatments
     * - FFS items REQUIRE pa_code_id (enforced in application layer)
     */
    public function up(): void
    {
        // Simplify claims table
        Schema::table('claims', function (Blueprint $table) {
            // Link claim to admission (which links to UTN via referral)
            $table->foreignId('admission_id')->nullable()->after('pa_code_id')->constrained('admissions');
            
            // Track bundle vs FFS amounts
            $table->decimal('bundle_amount', 15, 2)->default(0)->after('total_amount_paid');
            $table->decimal('ffs_amount', 15, 2)->default(0)->after('bundle_amount');
            
            // Add unique constraint: one claim per admission
            $table->unique(['admission_id']);
            
            // Index for admission lookup
            $table->index(['admission_id', 'status']);
        });

        // Simplify claim_treatments table
        Schema::table('claim_treatments', function (Blueprint $table) {
            // Item type: bundle (covered by bundle) or ffs (requires PA)
            $table->enum('item_type', ['bundle', 'ffs'])->default('bundle')->after('service_type');
            
            // Link to PA code (REQUIRED for FFS items)
            $table->foreignId('pa_code_id')->nullable()->after('item_type')->constrained('p_a_codes');
            
            // Link to tariff item for price lookup
            $table->foreignId('tariff_item_id')->nullable()->after('pa_code_id')->constrained('tariff_items');
            
            // Index for querying
            $table->index(['claim_id', 'item_type']);
            $table->index(['pa_code_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claim_treatments', function (Blueprint $table) {
            $table->dropForeign(['pa_code_id']);
            $table->dropForeign(['tariff_item_id']);
            $table->dropIndex(['claim_id', 'item_type']);
            $table->dropIndex(['pa_code_id']);
            $table->dropColumn(['item_type', 'pa_code_id', 'tariff_item_id']);
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['admission_id']);
            $table->dropUnique(['admission_id']);
            $table->dropIndex(['admission_id', 'status']);
            $table->dropColumn(['admission_id', 'bundle_amount', 'ffs_amount']);
        });
    }
};

