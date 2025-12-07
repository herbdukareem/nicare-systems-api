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
        Schema::create('professional_service_details', function (Blueprint $table) {
            $table->id();
            $table->string('service_name')->comment('Name of the professional service');
            $table->string('service_code')->nullable()->unique()->comment('Service code');
            $table->string('specialty')->nullable()->comment('Medical specialty (e.g., Cardiology, Orthopedics)');
            $table->integer('duration_minutes')->nullable()->comment('Expected duration in minutes');
            $table->string('provider_type')->nullable()->comment('e.g., Consultant, Specialist, General Practitioner');
            $table->text('equipment_needed')->nullable()->comment('Special equipment or facilities required');
            $table->text('procedure_description')->nullable()->comment('Detailed description of the procedure');
            $table->text('indications')->nullable()->comment('When this service is indicated');
            $table->text('contraindications')->nullable()->comment('When this service should not be performed');
            $table->text('complications')->nullable()->comment('Possible complications');
            $table->text('pre_procedure_requirements')->nullable()->comment('Requirements before the procedure');
            $table->text('post_procedure_care')->nullable()->comment('Care needed after the procedure');
            $table->boolean('anesthesia_required')->default(false)->comment('Whether anesthesia is required');
            $table->string('anesthesia_type')->nullable()->comment('Type of anesthesia (e.g., Local, General, Sedation)');
            $table->boolean('admission_required')->default(false)->comment('Whether hospital admission is required');
            $table->integer('recovery_time_hours')->nullable()->comment('Expected recovery time in hours');
            $table->boolean('follow_up_required')->default(false)->comment('Whether follow-up is required');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('service_name');
            $table->index('service_code');
            $table->index('specialty');
            $table->index('provider_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_service_details');
    }
};

