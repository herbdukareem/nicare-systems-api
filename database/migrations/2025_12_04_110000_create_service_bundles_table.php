<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Defines the Bundle itself (e.g., Severe Malaria Management)
        Schema::create('service_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 50)->unique();
            $table->decimal('fixed_price', 10, 2);
            $table->string('diagnosis_icd10')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Defines the standard services included in the Bundle (The components)
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_bundle_id')->constrained('service_bundles')->onDelete('cascade');
            // Link to the CaseRecord table for the specific service, drug, or lab item
            $table->foreignId('case_record_id')->constrained('cases')->onDelete('restrict'); 
            $table->integer('max_quantity')->default(1)->comment('Maximum quantity covered by the fixed price');
            $table->string('item_type')->comment('LAB, DRUG, CONSULTATION, etc.');
            $table->timestamps();
            
            $table->unique(['service_bundle_id', 'case_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
        Schema::dropIfExists('service_bundles');
    }
};