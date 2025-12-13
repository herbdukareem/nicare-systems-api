<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add UTN field to claims table for faster lookups.
     * UTN is copied from admission->referral->utn when claim is created.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            if (!Schema::hasColumn('claims', 'utn')) {
                $table->string('utn', 50)
                      ->nullable()
                      ->after('claim_number')
                      ->comment('UTN from referral - denormalized for faster lookups');
                
                $table->index('utn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropIndex(['utn']);
            $table->dropColumn('utn');
        });
    }
};

