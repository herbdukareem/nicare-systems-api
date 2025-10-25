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
        Schema::table('referrals', function (Blueprint $table) {
            // Add missing enrollee_id foreign key column
            $table->foreignId('enrollee_id')->nullable()->after('receiving_facility_id')->constrained('enrollees');

            // Add indexes for better performance (only for new column)
            $table->index(['enrollee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Drop foreign key constraint and column for enrollee_id only
            $table->dropForeign(['enrollee_id']);
            $table->dropColumn(['enrollee_id']);
        });
    }
};
