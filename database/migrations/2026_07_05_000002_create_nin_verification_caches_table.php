<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nin_verification_caches', function (Blueprint $table) {
            $table->id();
            $table->string('nin', 20)->unique();
            $table->string('provider_name')->nullable();
            $table->json('provider_data');
            $table->json('raw_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nin_verification_caches');
    }
};
