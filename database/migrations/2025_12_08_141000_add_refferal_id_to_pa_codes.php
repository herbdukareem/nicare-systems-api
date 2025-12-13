<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('pa_codes', 'referral_id')) {
                $table->string('referral_id', 50)
                      ->nullable()
                      ->comment('Link pa codes to referrals');
                
                $table->index('referral_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            $table->dropIndex(['referral_id']);
            $table->dropColumn('referral_id');
        });
    }
};

