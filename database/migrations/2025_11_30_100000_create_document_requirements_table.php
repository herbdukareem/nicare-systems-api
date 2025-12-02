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
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            
            // Request type: referral or pa_code
            $table->enum('request_type', ['referral', 'pa_code']);
            
            // Document type identifier (e.g., 'medical_report', 'lab_results')
            $table->string('document_type', 50);
            
            // Display name for the document
            $table->string('name');
            
            // Description/instructions for uploading
            $table->text('description')->nullable();
            
            // Is this document required or optional?
            $table->boolean('is_required')->default(false);
            
            // Allowed file types (comma-separated: pdf,jpg,png)
            $table->string('allowed_file_types')->default('pdf,jpg,jpeg,png');
            
            // Maximum file size in MB
            $table->integer('max_file_size_mb')->default(5);
            
            // Display order for UI
            $table->integer('display_order')->default(0);
            
            // Active/inactive status
            $table->boolean('status')->default(true);
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['request_type', 'status']);
            $table->index(['request_type', 'is_required']);
            $table->unique(['request_type', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_requirements');
    }
};

