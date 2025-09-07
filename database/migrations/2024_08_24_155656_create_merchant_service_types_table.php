<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchant_service_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type_name', 25);
            $table->text('code');
            $table->string('account_number', 25);
            $table->string('type_id', 25);
            $table->foreignId('merchant_id')->nullable()->constrained();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_service_types');
    }
};
