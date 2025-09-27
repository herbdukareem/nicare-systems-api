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
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number')->unique();
            
            // Patient/Enrollee Details
            $table->string('nicare_number');
            $table->string('enrollee_name');
            $table->enum('gender', ['Male', 'Female']);
            $table->string('plan')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('phone_main')->nullable();
            $table->string('phone_during_care')->nullable();
            $table->string('email_main')->nullable();
            $table->string('email_during_care')->nullable();
            $table->date('referral_date')->nullable();
            
            // Facility Information
            $table->unsignedBigInteger('facility_id');
            $table->string('facility_name');
            $table->string('facility_nicare_code');
            
            // Pre-Auth Details
            $table->unsignedBigInteger('pa_code_id')->nullable();
            $table->string('pa_code')->nullable();
            $table->enum('pa_request_type', ['Initial', 'Follow-up', 'Amendment', 'Renewal']);
            $table->enum('priority', ['Routine', 'Urgent', 'Emergency']);
            $table->date('pa_validity_start')->nullable();
            $table->date('pa_validity_end')->nullable();
            
            // Attending Physician
            $table->string('attending_physician_name');
            $table->string('attending_physician_license')->nullable();
            $table->string('attending_physician_specialization')->nullable();
            
            // Claim Status and Workflow
            $table->enum('status', [
                'draft',
                'submitted',
                'doctor_review',
                'doctor_approved',
                'doctor_rejected',
                'pharmacist_review',
                'pharmacist_approved',
                'pharmacist_rejected',
                'claim_review',
                'claim_confirmed',
                'claim_approved',
                'claim_rejected',
                'paid',
                'closed'
            ])->default('draft');
            
            // Financial Information
            $table->decimal('total_amount_claimed', 15, 2)->default(0);
            $table->decimal('total_amount_approved', 15, 2)->default(0);
            $table->decimal('total_amount_paid', 15, 2)->default(0);
            
            // Submission Details
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            
            // Review Stages
            $table->timestamp('doctor_reviewed_at')->nullable();
            $table->unsignedBigInteger('doctor_reviewed_by')->nullable();
            $table->text('doctor_comments')->nullable();
            
            $table->timestamp('pharmacist_reviewed_at')->nullable();
            $table->unsignedBigInteger('pharmacist_reviewed_by')->nullable();
            $table->text('pharmacist_comments')->nullable();
            
            $table->timestamp('claim_reviewed_at')->nullable();
            $table->unsignedBigInteger('claim_reviewed_by')->nullable();
            $table->text('claim_reviewer_comments')->nullable();
            
            $table->timestamp('claim_confirmed_at')->nullable();
            $table->unsignedBigInteger('claim_confirmed_by')->nullable();
            $table->text('claim_confirmer_comments')->nullable();
            
            $table->timestamp('claim_approved_at')->nullable();
            $table->unsignedBigInteger('claim_approved_by')->nullable();
            $table->text('claim_approver_comments')->nullable();
            
            // Audit Fields
            $table->json('audit_trail')->nullable();
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('facility_id')->references('id')->on('facilities');
            $table->foreign('pa_code_id')->references('id')->on('p_a_codes')->nullOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('doctor_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('pharmacist_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('claim_reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('claim_confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('claim_approved_by')->references('id')->on('users')->nullOnDelete();
            
            // Indexes
            $table->index(['nicare_number']);
            $table->index(['pa_code']);
            $table->index(['status']);
            $table->index(['submitted_at']);
            $table->index(['facility_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
