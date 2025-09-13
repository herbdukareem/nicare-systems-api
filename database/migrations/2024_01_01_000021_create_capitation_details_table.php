<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capitation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capitation_id')->constrained();
            $table->foreignId('facility_id')->constrained();
            $table->foreignId('funding_type_id')->constrained();
            $table->integer('total_enrolled');
            $table->decimal('rate');
            $table->decimal('amount', 10, 2);
            $table->foreignId('reviewed_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->foreignId('paid_by')->nullable();
            $table->date('reviewed_at')->nullable();
            $table->date('approved_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->foreignId('capitation_payment_id')->nullable()->constrained();// this will allow payment in batch
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capitation_details');
    }
};