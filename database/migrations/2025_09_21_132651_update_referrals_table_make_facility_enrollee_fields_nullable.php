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
            // Make facility fields nullable (since facilities.address and facilities.phone are nullable)
            $table->text('referring_address')->nullable()->change();
            $table->string('referring_phone')->nullable()->change();
            $table->text('receiving_address')->nullable()->change();
            $table->string('receiving_phone')->nullable()->change();

            // Make enrollee fields nullable (since enrollees.phone is nullable and computed fields might be null)
            $table->string('nicare_number')->nullable()->change();
            $table->string('enrollee_full_name')->nullable()->change();
            $table->enum('gender', ['Male', 'Female'])->nullable()->change();
            $table->string('enrollee_phone_main')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Revert fields back to NOT NULL
            $table->text('referring_address')->nullable(false)->change();
            $table->string('referring_phone')->nullable(false)->change();
            $table->text('receiving_address')->nullable(false)->change();
            $table->string('receiving_phone')->nullable(false)->change();
            $table->string('nicare_number')->nullable(false)->change();
            $table->string('enrollee_full_name')->nullable(false)->change();
            $table->enum('gender', ['Male', 'Female'])->nullable(false)->change();
            $table->string('enrollee_phone_main')->nullable(false)->change();
        });
    }
};
