<?php

use App\Models\PremiumType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premiums', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_id')->nullable();
            $table->string('pin', 20); 
            $table->string('serial_no', 20); // Serial number
            $table->foreignId('premium_type_id')->constrained(); // Premium type
            $table->foreignId('sector_id')->nullable()->constrained(); // Sector
            $table->foreignId('benefit_package_id')->nullable()->constrained(); // Benefit package
            $table->decimal('amount', 10, 2);
            $table->timestamp('date_used')->nullable();
            $table->timestamp('date_expired')->nullable();
            $table->unsignedSmallInteger('status')->default(1);
            $table->foreignId('lga_id')->nullable()->constrained(); // Set when used
            $table->foreignId('ward_id')->nullable()->constrained(); // Set when used
            $table->string('reference')->nullable(); // For bulk payment tracking
            $table->string('invoice_id')->nullable(); // For generation request tracking
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
           
            $table->morphs('userable');
            $table->index(['premium_type_id']);

        });

        
    }

    public function down(): void
    {
        Schema::dropIfExists('premiums');
    }
};