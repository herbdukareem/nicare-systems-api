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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->unique();

            // Referring Provider Details
            $table->string('referring_facility_name');
            $table->string('referring_nicare_code');
            $table->text('referring_address');
            $table->string('referring_phone');
            $table->string('referring_email')->nullable();
            $table->string('tpa_name')->nullable();

            // Contact Person Details
            $table->string('contact_full_name');
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();

            // Receiving Provider Details
            $table->string('receiving_facility_name');
            $table->string('receiving_nicare_code');
            $table->text('receiving_address');
            $table->string('receiving_phone');
            $table->string('receiving_email')->nullable();

            // Patient/Enrollee Details
            $table->string('nicare_number');
            $table->string('enrollee_full_name');
            $table->enum('gender', ['Male', 'Female']);
            $table->integer('age');
            $table->string('marital_status')->nullable();
            $table->string('enrollee_category')->nullable();
            $table->string('enrollee_phone_main');
            $table->string('enrollee_phone_encounter')->nullable();
            $table->string('enrollee_phone_relation')->nullable();
            $table->string('enrollee_email')->nullable();
            $table->string('programme')->nullable();
            $table->string('organization')->nullable();
            $table->string('benefit_plan')->nullable();
            $table->date('referral_date');

            // Clinical Justification
            $table->text('presenting_complaints');
            $table->text('reasons_for_referral');
            $table->text('treatments_given')->nullable();
            $table->text('investigations_done')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('preliminary_diagnosis');

            // Basic History
            $table->text('medical_history')->nullable();
            $table->text('medication_history')->nullable();

            // Severity Level
            $table->enum('severity_level', ['emergency', 'urgent', 'routine']);

            // Referring Personnel Details
            $table->string('personnel_full_name');
            $table->string('personnel_specialization')->nullable();
            $table->string('personnel_cadre')->nullable();
            $table->string('personnel_phone');
            $table->string('personnel_email')->nullable();

            // Supporting Documents
            $table->string('enrollee_id_card_path')->nullable();
            $table->string('referral_letter_path')->nullable();

            // Status and Processing
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->text('comments')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('denied_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['referral_code']);
            $table->index(['nicare_number']);
            $table->index(['status']);
            $table->index(['severity_level']);
            $table->index(['referral_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
