<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained()->onDelete('cascade');
            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_phone')->nullable();
            $table->string('job_title')->nullable();
            $table->string('employment_type')->nullable(); // full-time, part-time, contract, self-employed
            $table->string('employment_status')->default('employed'); // employed, unemployed, retired, student
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
            $table->string('industry')->nullable();
            $table->text('job_description')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['enrollee_id']);
            $table->index(['employment_status']);
            $table->index(['employment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_details');
    }
};