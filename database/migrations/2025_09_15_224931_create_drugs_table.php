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
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('nicare_code')->unique()->comment('NiCare drug code e.g., NGSCHA/DRUG/001');
            $table->string('drug_name')->comment('Name of the drug');
            $table->string('drug_dosage_form')->comment('Form of the drug e.g., Tablet, Injection');
            $table->string('drug_strength')->nullable()->comment('Strength of the drug e.g., 200mg, 5mg');
            $table->string('drug_presentation')->comment('Presentation unit e.g., Tab, Card, Ampoule');
            $table->decimal('drug_unit_price', 10, 2)->comment('Unit price of the drug');
            $table->boolean('status')->default(true)->comment('Active/Inactive status');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for better performance
            $table->index(['status', 'drug_name']);
            $table->index('nicare_code');
            $table->index('drug_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
