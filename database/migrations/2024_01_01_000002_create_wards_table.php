<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lga_id')->constrained()->onDelete('cascade');
            $table->integer('enrollment_cap')->default(0);
            $table->integer('total_enrolled')->default(0);
            $table->enum('settlement_type', ['Urban', 'Rural'])->default('Rural');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wards');
    }
};