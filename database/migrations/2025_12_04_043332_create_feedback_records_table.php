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
        Schema::create('feedback_records', function (Blueprint $table) {
            $table->id();
            $table->string('feedback_code')->unique();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('referral_id')->nullable()->constrained('referrals')->onDelete('set null');
            $table->foreignId('pa_code_id')->nullable()->constrained('pa_codes')->onDelete('set null');
            $table->foreignId('feedback_officer_id')->constrained('users')->onDelete('cascade');
            $table->enum('feedback_type', ['referral', 'pa_code', 'general']);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'escalated'])->default('pending');
            $table->text('feedback_comments')->nullable();
            $table->text('officer_observations')->nullable();
            $table->text('claims_guidance')->nullable();
            $table->json('enrollee_verification_data')->nullable(); // Store verification details
            $table->json('medical_history_summary')->nullable(); // Store medical history
            $table->json('additional_information')->nullable(); // Store any additional data
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->timestamp('feedback_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['enrollee_id', 'feedback_type']);
            $table->index(['status', 'priority']);
            $table->index(['feedback_officer_id', 'status']);
            $table->index('feedback_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_records');
    }
};
