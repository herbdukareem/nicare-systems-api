<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('referrals', function (Blueprint $table) {
            // Drop the old foreign key by name
            $table->dropForeign('referrals_service_id_foreign');

            // Rename the column
            $table->renameColumn('service_id', 'case_id');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Add the new foreign key
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('referrals', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign('referrals_case_id_foreign');

            // Rename the column back
            $table->renameColumn('case_id', 'service_id');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Add the old foreign key back
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });
    }
};

