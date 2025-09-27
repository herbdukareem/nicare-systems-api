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
        Schema::create('claim_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id');
            $table->unsignedBigInteger('treatment_id')->nullable(); // Link to specific treatment if applicable
            
            // File Information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->integer('file_size');
            $table->string('mime_type');
            
            // Document Classification
            $table->enum('document_type', [
                'lab_result',
                'prescription',
                'discharge_note',
                'medical_report',
                'invoice',
                'receipt',
                'referral_letter',
                'other'
            ]);
            
            // Validation
            $table->boolean('facility_stamped')->default(false);
            $table->boolean('validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->text('validation_comments')->nullable();
            
            // Audit
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('claim_id')->references('id')->on('claims')->onDelete('cascade');
            $table->foreign('treatment_id')->references('id')->on('claim_treatments')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('validated_by')->references('id')->on('users')->nullOnDelete();
            
            // Indexes
            $table->index(['claim_id']);
            $table->index(['treatment_id']);
            $table->index(['document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_attachments');
    }
};
