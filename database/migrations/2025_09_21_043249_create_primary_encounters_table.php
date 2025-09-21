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
        Schema::create('primary_encounters', function (Blueprint $table) {
            $table->id();
            $table->string('encounter_code')->unique();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('cascade');
            $table->foreignId('provider_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('encounter_date');
            $table->time('encounter_time')->nullable();
            $table->string('chief_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment_given')->nullable();
            $table->json('services_provided')->nullable(); // Array of services
            $table->json('medications_prescribed')->nullable(); // Array of medications
            $table->decimal('total_cost', 10, 2)->default(0);
            $table->enum('encounter_type', ['consultation', 'follow_up', 'emergency', 'routine_check', 'vaccination', 'other']);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->date('next_appointment_date')->nullable();
            $table->json('vital_signs')->nullable(); // Store vital signs data
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['enrollee_id', 'encounter_date']);
            $table->index(['facility_id', 'encounter_date']);
            $table->index(['encounter_date', 'status']);
            $table->index('encounter_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('primary_encounters');
    }
};
