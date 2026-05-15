<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollee_facility_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollee_id')->constrained('enrollees')->onDelete('cascade');
            $table->foreignId('from_facility_id')->constrained('facilities')->onDelete('restrict');
            $table->foreignId('to_facility_id')->constrained('facilities')->onDelete('restrict');
            $table->text('transfer_reason');
            $table->foreignId('transferred_by')->constrained('users')->onDelete('restrict');
            $table->date('effective_date');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollee_facility_transfers');
    }
};
