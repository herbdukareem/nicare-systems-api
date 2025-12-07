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
        Schema::create('laboratory_details', function (Blueprint $table) {
            $table->id();
            $table->string('test_name')->comment('Name of the laboratory test');
            $table->string('test_code')->nullable()->unique()->comment('Laboratory test code');
            $table->string('specimen_type')->nullable()->comment('e.g., Blood, Urine, Stool, Sputum');
            $table->string('specimen_volume')->nullable()->comment('Required specimen volume');
            $table->string('collection_method')->nullable()->comment('How to collect the specimen');
            $table->string('test_method')->nullable()->comment('Testing methodology (e.g., ELISA, PCR, Microscopy)');
            $table->string('test_category')->nullable()->comment('e.g., Hematology, Chemistry, Microbiology');
            $table->integer('turnaround_time')->nullable()->comment('Expected turnaround time in hours');
            $table->text('preparation_instructions')->nullable()->comment('Patient preparation (e.g., fasting required)');
            $table->text('reference_range')->nullable()->comment('Normal reference ranges');
            $table->string('reporting_unit')->nullable()->comment('Unit of measurement (e.g., mg/dL, mmol/L)');
            $table->boolean('fasting_required')->default(false)->comment('Whether fasting is required');
            $table->boolean('urgent_available')->default(false)->comment('Whether urgent/STAT testing is available');
            $table->decimal('urgent_surcharge', 10, 2)->nullable()->comment('Additional cost for urgent testing');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('test_name');
            $table->index('test_code');
            $table->index('test_category');
            $table->index('specimen_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laboratory_details');
    }
};

