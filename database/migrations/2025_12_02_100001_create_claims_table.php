<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('restrict');
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('restrict');
            
            $table->string('claim_number', 50)->unique();
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT, SUBMITTED, REVIEWING, APPROVED, REJECTED');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->timestamp('service_date');
            $table->timestamp('submission_date')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};