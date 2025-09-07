<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('capitated_month');
            $table->unsignedTinyInteger('capitation_month');
            $table->unsignedTinyInteger('year');
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitations');
    }
};