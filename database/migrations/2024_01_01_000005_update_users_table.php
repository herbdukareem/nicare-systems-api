<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('agent_reg_number')->nullable()->unique()->after('phone');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending')->after('agent_reg_number');
            $table->foreignId('lga_id')->nullable()->constrained()->after('status');
            $table->foreignId('ward_id')->nullable()->constrained()->after('lga_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['lga_id']);
            $table->dropForeign(['ward_id']);
            $table->dropColumn(['phone', 'agent_reg_number', 'status', 'lga_id', 'ward_id']);
        });
    }
};