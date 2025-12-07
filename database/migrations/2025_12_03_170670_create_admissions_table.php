<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Simplified Admission table - enforces UTN requirement at database level
     */
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_code')->unique();

            // REQUIRED: Referral must have validated UTN (enforced in application)
            $table->foreignId('referral_id')->constrained('referrals');

            // Patient/Enrollee
            $table->foreignId('enrollee_id')->constrained('enrollees');
            $table->string('nicare_number');

            // Facility (receiving facility from referral)
            $table->foreignId('facility_id')->constrained('facilities');

            // Bundle Assignment (matched by principal diagnosis ICD-10)
            $table->foreignId('service_bundle_id')->constrained('service_bundles')->onDelete('cascade');


            // Principal Diagnosis
            $table->string('principal_diagnosis_icd10')->nullable();
            $table->string('principal_diagnosis_description')->nullable();

            // Admission Details
            $table->timestamp('admission_date');
            $table->timestamp('discharge_date')->nullable();
            $table->enum('status', ['active', 'discharged'])->default('active');

            // Basic Ward Info
            $table->string('ward_type')->nullable();
            $table->integer('ward_days')->nullable();

            // Discharge Summary
            $table->text('discharge_summary')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users');

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index(['enrollee_id', 'status']);
            $table->index(['facility_id', 'status']);
            $table->index(['referral_id']);
            $table->index(['principal_diagnosis_icd10']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};

