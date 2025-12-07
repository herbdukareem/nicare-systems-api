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
        Schema::create('consultation_details', function (Blueprint $table) {
            $table->id();
            $table->string('consultation_type')->comment('Type of consultation (e.g., Initial, Follow-up, Emergency)');
            $table->string('specialty')->nullable()->comment('Medical specialty (e.g., Cardiology, Neurology, Pediatrics)');
            $table->string('provider_level')->nullable()->comment('e.g., Consultant, Specialist, Registrar, Medical Officer');
            $table->integer('duration_minutes')->nullable()->comment('Expected consultation duration in minutes');
            $table->string('consultation_mode')->nullable()->comment('e.g., In-person, Telemedicine, Home Visit');
            $table->text('scope_of_service')->nullable()->comment('What is included in the consultation');
            $table->boolean('diagnostic_tests_included')->default(false)->comment('Whether basic diagnostic tests are included');
            $table->text('included_services')->nullable()->comment('List of services included in consultation fee');
            $table->boolean('prescription_included')->default(true)->comment('Whether prescription is included');
            $table->boolean('medical_report_included')->default(false)->comment('Whether medical report is included');
            $table->boolean('referral_letter_included')->default(false)->comment('Whether referral letter can be issued');
            $table->boolean('follow_up_required')->default(false)->comment('Whether follow-up is typically required');
            $table->integer('follow_up_interval_days')->nullable()->comment('Recommended follow-up interval in days');
            $table->boolean('emergency_available')->default(false)->comment('Whether available for emergency consultations');
            $table->text('booking_requirements')->nullable()->comment('Requirements for booking (e.g., referral needed)');
            $table->boolean('insurance_accepted')->default(true)->comment('Whether insurance is accepted');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('consultation_type');
            $table->index('specialty');
            $table->index('provider_level');
            $table->index('consultation_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultation_details');
    }
};

