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

        Schema::table('cases', function (Blueprint $table) {
            // Rename columns
            $table->renameColumn('service_group_id', 'case_group_id');
            $table->renameColumn('service_category_id', 'case_category_id');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Schema::table('cases', function (Blueprint $table) {
            // Rename back to original names
            $table->renameColumn('case_group_id', 'service_group_id');
            $table->renameColumn('case_category_id', 'service_category_id');
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};

