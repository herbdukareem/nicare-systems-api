<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Create pa_code_documents table to store uploaded documents for PA code requests.
     */
    public function up(): void
    {
        Schema::create('pa_code_documents', function (Blueprint $table) {
            $table->id();
            
            // Link to PA code
            $table->foreignId('pa_code_id')
                  ->constrained('pa_codes')
                  ->onDelete('cascade')
                  ->comment('The PA code this document belongs to');
            
            // Link to document requirement (what type of document this is)
            $table->foreignId('document_requirement_id')
                  ->nullable()
                  ->constrained('document_requirements')
                  ->onDelete('set null')
                  ->comment('The document requirement this fulfills');
            
            // Document type identifier (e.g., 'pa_request_form', 'clinical_notes')
            $table->string('document_type', 50);
            
            // File information
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->comment('File extension: pdf, jpg, png, etc.');
            $table->unsignedBigInteger('file_size')->comment('File size in bytes');
            $table->string('mime_type', 100);
            
            // Original filename from upload
            $table->string('original_filename');
            
            // Upload tracking
            $table->foreignId('uploaded_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null')
                  ->comment('User who uploaded the document');
            
            // Validation tracking
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->text('validation_comments')->nullable();
            
            // Additional metadata
            $table->text('description')->nullable()->comment('Optional description/notes about the document');
            $table->boolean('is_required')->default(false)->comment('Was this a required document?');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('pa_code_id');
            $table->index('document_type');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pa_code_documents');
    }
};

