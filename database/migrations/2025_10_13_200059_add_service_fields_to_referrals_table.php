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
            // Add service-related fields
            $table->foreignId('service_id')->nullable()->constrained('services')->after('preliminary_diagnosis');
            $table->string('service_description')->nullable()->after('service_id');

            // Add facility relationship fields
            $table->foreignId('receiving_facility_id')->nullable()->constrained('facilities')->after('receiving_email');

            // Add modification tracking
            $table->json('modification_history')->nullable()->after('comments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropColumn('service_id');
            $table->dropColumn('service_description');
            $table->dropForeign(['receiving_facility_id']);
            $table->dropColumn('receiving_facility_id');
            $table->dropColumn('modification_history');
        });
    }
};
