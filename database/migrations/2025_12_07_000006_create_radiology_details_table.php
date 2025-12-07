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
        Schema::create('radiology_details', function (Blueprint $table) {
            $table->id();
            $table->string('examination_name')->comment('Name of the radiological examination');
            $table->string('examination_code')->nullable()->unique()->comment('Radiology examination code');
            $table->string('modality')->nullable()->comment('e.g., X-Ray, CT Scan, MRI, Ultrasound, Mammography');
            $table->string('body_part')->nullable()->comment('Body part being examined');
            $table->string('view_projection')->nullable()->comment('e.g., AP, Lateral, Oblique for X-rays');
            $table->boolean('contrast_required')->default(false)->comment('Whether contrast agent is required');
            $table->string('contrast_type')->nullable()->comment('Type of contrast (e.g., Iodinated, Gadolinium, Barium)');
            $table->text('preparation_instructions')->nullable()->comment('Patient preparation instructions');
            $table->integer('duration_minutes')->nullable()->comment('Expected examination duration in minutes');
            $table->text('indications')->nullable()->comment('Clinical indications for the examination');
            $table->text('contraindications')->nullable()->comment('Contraindications for the examination');
            $table->boolean('pregnancy_safe')->default(true)->comment('Whether safe during pregnancy');
            $table->string('radiation_dose')->nullable()->comment('Approximate radiation dose (e.g., Low, Medium, High)');
            $table->integer('turnaround_time')->nullable()->comment('Report turnaround time in hours');
            $table->boolean('urgent_available')->default(false)->comment('Whether urgent/STAT examination is available');
            $table->decimal('urgent_surcharge', 10, 2)->nullable()->comment('Additional cost for urgent examination');
            $table->text('special_equipment')->nullable()->comment('Special equipment or facilities required');
            $table->boolean('sedation_required')->default(false)->comment('Whether sedation may be required');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('examination_name');
            $table->index('examination_code');
            $table->index('modality');
            $table->index('body_part');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radiology_details');
    }
};

