<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_details', function (Blueprint $table) {
            $table->id();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
             $table->foreignId('bank_id')->constrained();
            $table->string('account_type')->default('savings');
            $table->morphs('accountable'); // For polymorphic relationship
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();


            // $table->index(['enrollee_id']);
            $table->index(['account_number']);
            $table->index(['bvn']);
            $table->index(['nin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_details');
    }
};