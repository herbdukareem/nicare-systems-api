<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20);
            $table->foreignId('benefactor_id')->constrained();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();

            $table->index(['benefactor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_phases');
    }
};