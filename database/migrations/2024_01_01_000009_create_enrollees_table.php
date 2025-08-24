<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollees', function (Blueprint $table) {
            $table->id();
            $table->string('enrollee_id')->unique();
            $table->string('nin')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth');
            $table->enum('gender', ['Male', 'Female']);
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->default('Single');
            $table->text('address')->nullable();
            $table->foreignId('enrollee_type_id')->constrained();
            $table->string('enrollee_category')->nullable();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->string('village')->nullable();
            $table->foreignId('premium_id')->nullable()->constrained('premiums');
            $table->foreignId('employment_detail_id')->nullable()->constrained();
            $table->foreignId('funding_type_id')->nullable()->constrained();
            $table->foreignId('benefactor_id')->nullable()->constrained();
            $table->date('capitation_start_date')->nullable();
            $table->timestamp('approval_date')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollees');
    }
};