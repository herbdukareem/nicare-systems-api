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
            // Make clinical fields nullable to match validation rules
            $table->text('presenting_complaints')->nullable()->change();
            $table->text('preliminary_diagnosis')->nullable()->change();

            // Make personnel contact fields nullable
            $table->string('personnel_full_name')->nullable()->change();
            $table->string('personnel_phone')->nullable()->change();

            // Make contact person fields nullable
            $table->string('contact_full_name')->nullable()->change();
            $table->string('contact_phone')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Revert fields back to NOT NULL
            $table->text('presenting_complaints')->nullable(false)->change();
            $table->text('preliminary_diagnosis')->nullable(false)->change();
            $table->string('personnel_full_name')->nullable(false)->change();
            $table->string('personnel_phone')->nullable(false)->change();
            $table->string('contact_full_name')->nullable(false)->change();
            $table->string('contact_phone')->nullable(false)->change();
        });
    }
};
