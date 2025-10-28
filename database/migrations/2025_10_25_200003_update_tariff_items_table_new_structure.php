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
        // Drop indexes and foreign keys using raw SQL to avoid errors if they don't exist
        $database = DB::getDatabaseName();

        // Get all foreign keys on tariff_items table
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'tariff_items'
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$database]);

        // Drop all foreign keys
        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE tariff_items DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
        }

        // Get all indexes on tariff_items table (excluding PRIMARY)
        $indexes = DB::select("
            SELECT DISTINCT INDEX_NAME
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = 'tariff_items'
            AND INDEX_NAME != 'PRIMARY'
        ", [$database]);

        // Drop all indexes
        foreach ($indexes as $index) {
            DB::statement("ALTER TABLE tariff_items DROP INDEX {$index->INDEX_NAME}");
        }

        Schema::table('tariff_items', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn([
                'service_category_id',
                'service_code',
                'description',
                'unit_cost',
                'default_qty',
                'position'
            ]);
        });

        Schema::table('tariff_items', function (Blueprint $table) {
            // Add new columns
            $table->foreignId('service_type_id')->after('case_id')->constrained('service_types')->onDelete('cascade');
            $table->string('tariff_item')->after('service_type_id');
            $table->decimal('price', 10, 2)->after('tariff_item');
            $table->foreignId('case_type_id')->after('price')->constrained('case_types')->onDelete('cascade');

            // Re-add the case_id foreign key
            $table->foreign('case_id')->references('id')->on('case_categories')->onDelete('cascade');

            // Add indexes
            $table->index(['case_id', 'service_type_id', 'case_type_id']);
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
            // Drop new foreign keys and indexes
            $table->dropForeign(['service_type_id']);
            $table->dropForeign(['case_type_id']);
            $table->dropIndex('tariff_items_case_id_service_type_id_case_type_id_index');
            
            // Drop new columns
            $table->dropColumn([
                'service_type_id',
                'tariff_item',
                'price',
                'case_type_id'
            ]);
        });

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::table('tariff_items', function (Blueprint $table) {
            // Restore old columns
            $table->foreignId('service_category_id')->after('case_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('service_code')->after('service_category_id')->unique();
            $table->text('description')->after('service_code');
            $table->decimal('unit_cost', 10, 2)->after('description');
            $table->integer('default_qty')->default(1)->after('unit_cost');
            $table->integer('position')->default(0)->after('default_qty');
            
            // Restore indexes
            $table->index(['case_id', 'service_category_id']);
            $table->index('position');
            
            // Restore foreign key
            $table->foreign('case_id')->references('id')->on('case_categories')->onDelete('cascade');
        });
    }
};

