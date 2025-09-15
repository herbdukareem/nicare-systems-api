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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('nicare_code')->unique()->comment('NiCare service code e.g., NGSCHS/GCons/P/0001');
            $table->text('service_description')->comment('Description of the service');
            $table->enum('level_of_care', ['Primary', 'Secondary', 'Tertiary'])->comment('Level of care required');
            $table->decimal('price', 10, 2)->comment('Price of the service');
            $table->string('group')->comment('Service group/category');
            $table->boolean('pa_required')->default(false)->comment('Whether PA is required');
            $table->boolean('referable')->default(true)->comment('Whether service is referable');
            $table->boolean('status')->default(true)->comment('Active/Inactive status');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['status', 'level_of_care']);
            $table->index(['group', 'status']);
            $table->index('nicare_code');
            $table->index('pa_required');
            $table->index('referable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
