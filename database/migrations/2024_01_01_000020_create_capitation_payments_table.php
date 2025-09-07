<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capitation_id')->constrained();
            $table->foreignId('funding_type_id')->constrained();
            $table->integer('amount');
            $table->string('invoice_number', 12)->nullable(); // order id to be generated
            $table->string('description')->nullable();
            $table->date('payment_date')->nullable();
            $table->unsignedTinyInteger('status')->default(1); // paid, and pending
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_payments');
    }
};