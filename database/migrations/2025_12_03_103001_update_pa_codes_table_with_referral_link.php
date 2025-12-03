<?php
// database/migrations/2025_12_03_103001_update_pa_codes_table_with_referral_link.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            // New Foreign Key to enforce the policy: PA Code must follow a Referral
            $table->foreignId('referral_id')
                  ->nullable() // Keep nullable for legacy or edge cases, but required by policy logic
                  ->after('enrollee_id')
                  ->constrained('referrals')
                  ->onDelete('restrict')
                  ->comment('Links the Follow-up PA Code to the preceding Referral PA');
        });
    }

    public function down(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
            $table->dropColumn('referral_id');
        });
    }
};