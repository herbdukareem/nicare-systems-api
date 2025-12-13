<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add admission_id to pa_codes table to link FU-PA codes to the active admission episode.
     * This is critical for episode tracking: Referral → Admission → FU-PA Code → Claim
     */
    public function up(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('pa_codes', 'admission_id')) {
                $table->foreignId('admission_id')
                      ->nullable()
                      ->constrained('admissions')
                      ->onDelete('restrict')
                      ->comment('Links FU-PA Code to the active admission episode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pa_codes', function (Blueprint $table) {
            $table->dropForeign(['admission_id']);
            $table->dropColumn('admission_id');
        });
    }
};

