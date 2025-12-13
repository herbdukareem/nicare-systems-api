<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add referral_id to claims table to support claims without admissions.
     * This allows FFS-only claims to be submitted directly against a referral.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'referral_id')) {
                $table->foreignId('referral_id')
                      ->nullable()
                      ->after('admission_id')
                      ->constrained('referrals')
                      ->onDelete('restrict')
                      ->comment('Link to referral - required for all claims (with or without admission)');
                
                $table->index('referral_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign(['referral_id']);
            $table->dropIndex(['referral_id']);
            $table->dropColumn('referral_id');
        });
    }
};

