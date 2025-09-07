<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedTinyInteger('premium_type')->default(2);// 1=>percentage 2=value
            $table->decimal('premium_type_value')->default(3600);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdas');
    }
};