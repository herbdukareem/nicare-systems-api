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
        Schema::create('tariff_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('case_categories')->onDelete('cascade');
            $table->foreignId('service_category_id')->constrained('service_categories')->onDelete('cascade');
            $table->string('service_code')->unique();
            $table->text('description');
            $table->decimal('unit_cost', 10, 2);
            $table->integer('default_qty')->default(1);
            $table->integer('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'service_category_id']);
            $table->index('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tariff_items');
    }
};
