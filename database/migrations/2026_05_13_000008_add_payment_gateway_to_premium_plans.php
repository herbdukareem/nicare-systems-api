<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('premium_plans', 'payment_required')) {
                $table->boolean('payment_required')->default(true)->after('amount');
            }

            if (!Schema::hasColumn('premium_plans', 'payment_gateway')) {
                $table->string('payment_gateway')->nullable()->after('payment_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('premium_plans', function (Blueprint $table) {
            if (Schema::hasColumn('premium_plans', 'payment_gateway')) {
                $table->dropColumn('payment_gateway');
            }

            if (Schema::hasColumn('premium_plans', 'payment_required')) {
                $table->dropColumn('payment_required');
            }
        });
    }
};
