<?php
// database/migrations/2025_12_02_100000_create_pa_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pa_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->onDelete('set null'); // Facility requesting the PA
            
            $table->string('code', 20)->unique()->comment('The generated PA code, e.g., PA-MAL-123');
            $table->string('type', 20)->comment('BUNDLE or FFS_TOP_UP');
            $table->string('status', 20)->default('PENDING')->comment('PENDING, APPROVED, REJECTED');
            $table->text('justification')->nullable()->comment('Required for complication PAs');
            $table->json('requested_services'); // Stores the list of requested CaseRecord IDs/details
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_codes');
    }
};