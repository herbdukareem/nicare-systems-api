<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration converts the `status` column on the `enrollees` table from
     * a string enum to an unsigned tiny integer and sets a default of 0.
     */
    public function up(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            // Change the status column to unsigned tiny integer with default 0.
            // Note: requires the doctrine/dbal package if using change().
            $table->unsignedTinyInteger('status')->default(0)->comment('0=pending,1=active,3=expired,4=suspended')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollees', function (Blueprint $table) {
            // Revert the status column back to the original enum definition.
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->change();
        });
    }
};
