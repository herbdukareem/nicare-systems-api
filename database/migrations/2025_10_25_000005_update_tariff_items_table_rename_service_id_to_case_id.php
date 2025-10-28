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

        Schema::table('tariff_items', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign('tariff_items_service_id_foreign');

            // Rename the column
            $table->renameColumn('service_id', 'case_id');

            // Update the index
            $table->dropIndex('tariff_items_service_id_service_category_id_index');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Add the new foreign key and index
        Schema::table('tariff_items', function (Blueprint $table) {
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
            $table->index(['case_id', 'service_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('tariff_items', function (Blueprint $table) {
            // Drop the new foreign key
            $table->dropForeign('tariff_items_case_id_foreign');

            // Rename the column back
            $table->renameColumn('case_id', 'service_id');

            // Update the index back
            $table->dropIndex('tariff_items_case_id_service_category_id_index');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Add the old foreign key and index back
        Schema::table('tariff_items', function (Blueprint $table) {
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->index(['service_id', 'service_category_id']);
        });
    }
};

