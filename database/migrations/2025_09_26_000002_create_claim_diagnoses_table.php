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
        Schema::create('claim_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            
            // Diagnosis Information
            $table->enum('type', ['primary', 'secondary']);
            $table->string('icd_10_code');
            $table->string('icd_10_description');
            $table->text('illness_description')->nullable();
            
            // Validation Status
            $table->boolean('doctor_validated')->default(false);
            $table->timestamp('doctor_validated_at')->nullable();
            $table->unsignedBigInteger('doctor_validated_by')->nullable();
            $table->text('doctor_validation_comments')->nullable();
            
            // Audit
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('claim_id')->references('id')->on('claims')->onDelete('cascade');
            $table->foreign('doctor_validated_by')->references('id')->on('users')->nullOnDelete();
            
            // Indexes
            $table->index(['claim_id', 'type']);
            $table->index(['icd_10_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_diagnoses');
    }
};
