<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Simplified Bundle table - just maps ICD-10 diagnosis to bundle tariff
     */
    public function up(): void
    {
        
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('bundle_code')->unique();
            $table->string('bundle_name');
            $table->text('description')->nullable();

            // Classification
            $table->foreignId('case_category_id')->nullable()->constrained('case_categories');
            $table->string('icd10_code'); // ICD-10 code that triggers this bundle

            // Pricing - single fixed tariff
            $table->decimal('bundle_tariff', 15, 2);

            // Level of Care
            $table->enum('level_of_care', ['Primary', 'Secondary', 'Tertiary']);

            // Validity
            $table->boolean('status')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index(['icd10_code']);
            $table->index(['level_of_care', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};

