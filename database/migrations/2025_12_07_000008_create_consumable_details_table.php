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
        Schema::create('consumable_details', function (Blueprint $table) {
            $table->id();
            $table->string('item_name')->comment('Name of the consumable item');
            $table->string('item_code')->nullable()->unique()->comment('Item code/SKU');
            $table->string('category')->nullable()->comment('e.g., Surgical Supplies, Dressings, Syringes, IV Fluids');
            $table->string('subcategory')->nullable()->comment('More specific categorization');
            $table->string('unit_of_measure')->nullable()->comment('e.g., Piece, Box, Pack, Bottle, Liter');
            $table->integer('units_per_pack')->nullable()->comment('Number of units in a pack');
            $table->string('manufacturer')->nullable()->comment('Manufacturer name');
            $table->string('material_composition')->nullable()->comment('What the item is made of');
            $table->boolean('sterile')->default(false)->comment('Whether the item is sterile');
            $table->string('sterilization_method')->nullable()->comment('e.g., Gamma Radiation, ETO, Autoclave');
            $table->boolean('single_use')->default(true)->comment('Whether item is single-use/disposable');
            $table->boolean('latex_free')->default(false)->comment('Whether item is latex-free');
            $table->text('specifications')->nullable()->comment('Technical specifications (e.g., size, gauge, capacity)');
            $table->text('usage_instructions')->nullable()->comment('How to use the item');
            $table->text('storage_conditions')->nullable()->comment('Storage requirements');
            $table->date('expiry_date')->nullable()->comment('Expiry date if applicable');
            $table->string('regulatory_approval')->nullable()->comment('e.g., NAFDAC, FDA, CE Mark');
            $table->boolean('requires_cold_chain')->default(false)->comment('Whether cold chain storage is required');
            $table->text('disposal_instructions')->nullable()->comment('How to properly dispose of the item');
            $table->boolean('hazardous')->default(false)->comment('Whether item is hazardous/biohazard');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('item_name');
            $table->index('item_code');
            $table->index('category');
            $table->index('subcategory');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumable_details');
    }
};

