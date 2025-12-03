<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('referring_facility_id')->constrained('facilities')->onDelete('restrict');
            $table->foreignId('receiving_facility_id')->constrained('facilities')->onDelete('restrict');

            $table->string('referral_code', 50)->unique()->comment('e.g. NGSCHA/Facility Code/Serial Number');
            $table->string('utn', 50)->unique()->comment('Unique Transaction Number - key for claim access');
            $table->string('status', 20)->default('PENDING')->comment('PENDING, APPROVED, DENIED');

            // Clinical Justification (RR Template fields)
            $table->text('presenting_complains');
            $table->text('reasons_for_referral');
            $table->text('treatments_given');
            $table->text('investigations_done');
            $table->text('examination_findings');
            $table->string('preliminary_diagnosis');
            $table->text('medical_history')->nullable();
            $table->text('medication_history')->nullable();
            
            // Severity and Personnel (RR Template fields)
            $table->enum('severity_level', ['Routine', 'Urgent/Expidited', 'Emergency']);
            $table->string('referring_person_name');
            $table->string('referring_person_specialisation');
            $table->string('referring_person_cadre');
            
            // Contact & Dates
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->string('contact_person_email')->nullable();
            
            $table->timestamp('request_date');
            $table->timestamp('approval_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};