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
        Schema::table('services', function (Blueprint $table) {
            // Add service category column (1 = Main Service, 2 = Sub Service)
            $table->tinyInteger('service_category')->default(1)->after('service_group_id')
                  ->comment('1 = Main Service, 2 = Sub Service');

            // Add foreign key to service_categories table
            $table->foreignId('service_category_id')->nullable()->after('service_category')
                  ->constrained('service_categories')->onDelete('set null');

            // Add indexes for better performance
            $table->index('service_category');
            $table->index('service_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['service_category_id']);
            $table->dropIndex(['service_category']);
            $table->dropIndex(['service_category_id']);
            $table->dropColumn(['service_category', 'service_category_id']);
        });
    }
};
