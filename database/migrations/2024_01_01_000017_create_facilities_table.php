<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('hcp_code')->unique();
            $table->string('name');
            $table->enum('ownership', ['Public', 'Private'])->default('Public');
            $table->enum('type', ['Primary', 'Secondary', 'Tertiary'])->default('Primary');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('lga_id')->constrained();
            $table->foreignId('ward_id')->constrained();
            $table->integer('capacity')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->foreignId('account_detail_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};